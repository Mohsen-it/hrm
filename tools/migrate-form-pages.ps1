# Migrate form pages (Create/Edit/Show) to use Button + Card components
# Replaces common .btn-* and .card patterns with shared components

param(
    [string]$BasePath = "D:\hrm_alepair\resources\js\Pages"
)

$modules = @(
    "Companies", "Branches", "Departments", "Positions", "Grades",
    "Shifts", "Users", "Holidays", "Vacations\Requests", "Zones",
    "FingerprintDevices", "Attendance\Sessions"
)

$pageTypes = @("Create.vue", "Edit.vue", "Show.vue")

$stats = @{
    FilesProcessed = 0
    FilesModified = 0
    ButtonsReplaced = 0
    CardsReplaced = 0
    CheckboxesReplaced = 0
}

foreach ($module in $modules) {
    foreach ($pageType in $pageTypes) {
        $file = Join-Path $BasePath "$module\$pageType"
        if (-not (Test-Path $file)) {
            $altFile = Join-Path $BasePath "$module\$pageType"
            if (Test-Path $altFile) {
                $file = $altFile
            } else {
                continue
            }
        }

        $stats.FilesProcessed++
        $content = Get-Content $file -Raw
        $original = $content
        $modified = $false

        # 1. Ensure Button import is present
        if ($content -match "import AppLayout" -and $content -notmatch "import Button from '@/Components/ui/Button\.vue'") {
            $content = $content -replace "(import PageHeader from '@/Components/ui/PageHeader\.vue';)", "`$1`nimport Button from '@/Components/ui/Button.vue';"
            $modified = $true
        }

        # 2. Ensure Card import is present
        if ($content -match "import AppLayout" -and $content -notmatch "import Card from '@/Components/ui/Card\.vue'") {
            $content = $content -replace "(import Button from '@/Components/ui/Button\.vue';\n)?", "`$1import Card from '@/Components/ui/Card.vue';`n"
            if (-not $modified) { $modified = $true }
        }

        # 3. Ensure FormCheckbox import is present (if checkboxes exist)
        if ($content -match '<input\s+id="[^"]+"\s+v-model="form\.[^"]+"\s+type="checkbox"' -and $content -notmatch "import FormCheckbox") {
            $content = $content -replace "(import Card from '@/Components/ui/Card\.vue';\n)?", "`$1import FormCheckbox from '@/Components/ui/FormCheckbox.vue';`n"
            if (-not $modified) { $modified = $true }
        }

        # 4. Replace `<Link :href="route('X.index')" class="btn btn-secondary">...<span>{{ t('common.back') }}</span></Link>` patterns
        $btnBackPattern = '<Link\s+:href="route\([''"]([^''"]+)\.index[''"]\)"\s+class="btn btn-secondary">\s*<i\s+class="fas fa-arrow-right rtl-flip"></i>\s*<span>\{\{\s*t\([''"]common\.back[''"]\)\s*\}\}</span>\s*</Link>'
        if ($content -match $btnBackPattern) {
            $content = [regex]::Replace($content, $btnBackPattern, '<Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route(`$1.index`)">{{ t(''common.back'') }}</Button>')
            $stats.ButtonsReplaced++
            $modified = $true
        }

        # 5. Replace `<Link ... class="btn btn-primary">` (Edit button in Show pages)
        $btnLinkPrimaryPattern = '<Link\s+:href="route\([''"]([^''"]+)\.edit[''"],\s*([^)]+)\)"\s+class="btn btn-primary">\s*<i\s+class="fas fa-edit"></i>\s*<span>\{\{\s*t\([''"]common\.edit[''"]\)\s*\}\}</span>\s*</Link>'
        if ($content -match $btnLinkPrimaryPattern) {
            $content = [regex]::Replace($content, $btnLinkPrimaryPattern, '<Button variant="primary" icon="fas fa-edit" :href="route(`$1.edit`, `$2`)">{{ t(''common.edit'') }}</Button>')
            $stats.ButtonsReplaced++
            $modified = $true
        }

        # 6. Replace `<button type="submit" class="btn btn-primary" :disabled="processing">` for save/update
        $btnSavePattern = '<button\s+type="submit"\s+class="btn btn-primary"\s+:disabled="processing">\s*<i\s+v-if="processing"\s+class="fas fa-spinner fa-spin"></i>\s*<i\s+v-else\s+class="fas fa-save"></i>\s*<span>\{\{\s*t\([''"]common\.(save|update)[''"]\)\s*\}\}</span>\s*</Button>'
        if ($content -match $btnSavePattern) {
            $content = [regex]::Replace($content, $btnSavePattern, '<Button type="submit" variant="primary" :loading="processing" icon="fas fa-save">{{ t(''common.$1'') }}</Button>')
            $stats.ButtonsReplaced++
            $modified = $true
        }

        # 7. Replace `<Link :href="route('X.index')" class="btn btn-secondary">{{ t('common.cancel') }}</Link>`
        $btnCancelPattern = '<Link\s+:href="route\([''"]([^''"]+)\.index[''"]\)"\s+class="btn btn-secondary">\s*\{\{\s*t\([''"]common\.cancel[''"]\)\s*\}\}\s*</Link>'
        if ($content -match $btnCancelPattern) {
            $content = [regex]::Replace($content, $btnCancelPattern, '<Button variant="secondary" :href="route(`$1.index`)">{{ t(''common.cancel'') }}</Button>')
            $stats.ButtonsReplaced++
            $modified = $true
        }

        # 8. Replace `<form class="card p-N">` with `<Card variant="base" padding="md" as="form" @submit.prevent="...">`
        $formCardPattern = '<form\s+class="card\s+p-(\d+)"\s+@submit\.prevent="(\w+)">'
        if ($content -match $formCardPattern) {
            $content = [regex]::Replace($content, $formCardPattern, '<Card variant="base" padding="md" as="form" @submit.prevent="$2">')
            $stats.CardsReplaced++
            $modified = $true
        }

        # 9. Replace closing `</form>` with `</Card>` only if we replaced opening
        if ($content -match '<Card\s+variant="base"\s+padding="md"\s+as="form"') {
            $count = ([regex]::Matches($content, '</form>')).Count
            $content = $content -replace '</form>', '</Card>'
            $modified = $true
        }

        # 10. Replace raw checkbox pattern `<input id="X" v-model="form.Y" type="checkbox" class="w-4 h-4" />` + label
        $checkboxPattern = '<input\s+id="([^"]+)"\s+v-model="form\.([^"]+)"\s+type="checkbox"\s+class="w-4 h-4"\s*/>\s*<label\s+for="[^"]+"\s+class="text-\[14px\]\s+text-\[var\(--color-ink\)\]">\s*\{\{\s*t\([''"]([^''"]+)[''"]\)\s*\}\}\s*</label>'
        if ($content -match $checkboxPattern) {
            $content = [regex]::Replace($content, $checkboxPattern, '<FormCheckbox v-model="form.$2" :label="t(''$3'')" />')
            $stats.CheckboxesReplaced++
            $modified = $true
        }

        # Save if modified
        if ($modified) {
            Set-Content -Path $file -Value $content -NoNewline
            $stats.FilesModified++
            Write-Host "Modified: $module\$pageType"
        }
    }
}

Write-Host "`n=== Migration Stats ==="
$stats | Format-Table -AutoSize
