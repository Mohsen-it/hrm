# Fix duplicate Card imports in form pages
$modules = @(
    "Companies", "Branches", "Departments", "Positions", "Grades",
    "Shifts", "Users", "Holidays", "Vacations\Requests", "Zones",
    "FingerprintDevices", "Attendance\Sessions"
)
$pageTypes = @("Create.vue", "Edit.vue", "Show.vue")
$basePath = "D:\hrm_alepair\resources\js\Pages"
$fixed = 0

foreach ($module in $modules) {
    foreach ($pageType in $pageTypes) {
        $file = Join-Path $basePath "$module\$pageType"
        if (-not (Test-Path $file)) { continue }
        $content = Get-Content $file -Raw
        # Remove duplicate Card imports (keep only first occurrence)
        $newContent = [regex]::Replace($content, '(import Card from .@/Components/ui/Card\.vue.;\r?\n)+', "import Card from '@/Components/ui/Card.vue';`r`n", 1)
        if ($newContent -ne $content) {
            Set-Content -Path $file -Value $newContent -NoNewline
            $fixed++
        }
    }
}

Write-Host "Fixed $files files (removed duplicate Card imports)"
