# Recovery v2: extract original characters by removing the import pattern
# Pattern: "import Card from '@/Components/ui/Card.vue';" + newline = 45 chars

$modules = @(
    "Companies", "Branches", "Departments", "Positions", "Grades",
    "Shifts", "Users", "Holidays", "Vacations\Requests", "Zones",
    "FingerprintDevices", "Attendance\Sessions"
)
$pageTypes = @("Create.vue", "Edit.vue", "Show.vue")
$basePath = "D:\hrm_alepair\resources\js\Pages"
$recovered = 0
$failed = @()

# The exact corruption pattern: each char of original is followed by "import Card from '@/Components/ui/Card.vue';" + newline
# Pattern length: 45 chars per line
$corruption = "import Card from '@/Components/ui/Card.vue';" + "`n"
$corruptionLen = 45

foreach ($module in $modules) {
    foreach ($pageType in $pageTypes) {
        $file = Join-Path $basePath "$module\$pageType"
        if (-not (Test-Path $file)) { continue }

        $content = Get-Content $file -Raw
        $importCount = ([regex]::Matches($content, "import Card from '@/Components/ui/Card\.vue';")).Count

        if ($importCount -lt 10) {
            continue
        }

        # Simply remove all instances of the import pattern
        $cleaned = $content.Replace($corruption, '')

        Write-Host "File: $module\$pageType — had $importCount imports, original size: $($cleaned.Length) chars"

        # Now we have a file with NO newlines, all original characters
        # Reconstruct by adding newlines based on Vue/JS patterns

        # Step 1: Add newline after each ";" (typical statement end)
        $cleaned = [regex]::Replace($cleaned, "(;)(?=[^\n])", "`$1`n")
        # Step 2: Add newline before "const ", "let ", "function ", "return " in script sections
        $cleaned = [regex]::Replace($cleaned, "(\s)(const )", "`n`$2")
        $cleaned = [regex]::Replace($cleaned, "(\s)(let )", "`n`$2")
        $cleaned = [regex]::Replace($cleaned, "(\s)(function )", "`n`$2")
        # Step 3: Add newline after "<script setup>" and before "</script>"
        $cleaned = $cleaned -replace "(<script setup>)", "`$1`n"
        $cleaned = $cleaned -replace "(</script>)", "`n`$1`n"
        # Step 4: Add newline after "</template>"
        $cleaned = $cleaned -replace "(</template>)", "`$1`n"
        # Step 5: Add newline before "<template>"
        $cleaned = $cleaned -replace "(<template>)", "`n`$1`n"
        # Step 6: Clean up multiple consecutive newlines (max 2)
        $cleaned = [regex]::Replace($cleaned, "`n{3,}", "`n`n")
        # Step 7: Add newline after each "}" at end of object/method (preceded by )))
        $cleaned = [regex]::Replace($cleaned, "(\})\s*(?=<)", "`$1`n")
        # Step 8: Add newline after each "</...>" closing tag
        $cleaned = [regex]::Replace($cleaned, "(</[A-Za-z][A-Za-z0-9-]*>)", "`$1`n")

        $cleaned = $cleaned.Trim() + "`n"

        # Save
        Set-Content -Path $file -Value $cleaned -NoNewline -Encoding UTF8
        $recovered++
    }
}

Write-Host "`n=== Recovery complete: $recovered files ==="
