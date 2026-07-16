# Recover corrupted form pages by removing inserted import lines

$modules = @(
    "Companies", "Branches", "Departments", "Positions", "Grades",
    "Shifts", "Users", "Holidays", "Vacations\Requests", "Zones",
    "FingerprintDevices", "Attendance\Sessions"
)
$pageTypes = @("Create.vue", "Edit.vue", "Show.vue")
$basePath = "D:\hrm_alepair\resources\js\Pages"
$recovered = 0
$failed = @()

# Build a list of components likely imported by these files
$standardImports = @(
    "AppLayout", "PageHeader", "FormInput", "FormTextarea", "FormSelect",
    "FormCheckbox", "FormSwitch", "FormDatepicker", "Button", "Card", "IconButton",
    "Badge", "Alert", "LoadingSpinner", "DataTable", "SearchInput", "ConfirmDialog",
    "EmptyState", "FormModal", "FormGroup", "Tabs", "Pagination", "Avatar",
    "StatCard", "Breadcrumb", "FormRadio"
)

foreach ($module in $modules) {
    foreach ($pageType in $pageTypes) {
        $file = Join-Path $basePath "$module\$pageType"
        if (-not (Test-Path $file)) { continue }

        $content = Get-Content $file -Raw
        $originalSize = $content.Length

        # Detect corruption: file is mostly import lines
        $importCount = ([regex]::Matches($content, "import Card from '@/Components/ui/Card\.vue';")).Count

        if ($importCount -lt 50) {
            # File looks OK, skip
            continue
        }

        # Recovery: remove all inserted "import Card from '@/Components/ui/Card.vue';" lines
        # These were inserted with a newline after each
        $cleaned = $content -replace [regex]::Escape("import Card from '@/Components/ui/Card.vue';"), ''

        # Now we have a file with all the original characters but no newlines
        # We need to reconstruct proper newlines based on Vue/JS patterns

        # First, identify the script setup block bounds
        # Find positions of "<script setup>" and "</script>"
        $scriptStart = $cleaned.IndexOf('<script setup>')
        $scriptEnd = $cleaned.IndexOf('</script>')

        if ($scriptStart -lt 0 -or $scriptEnd -lt 0) {
            $failed += $file
            continue
        }

        # Heuristic: in Vue files, after <script setup> we expect: blank line, then imports ending with semicolons, blank line, then const/let/function/reactive, then </script>

        # Add newlines after ";" in the script section for known import patterns
        $scriptSection = $cleaned.Substring($scriptStart, $scriptEnd - $scriptStart + 9)

        # Add newlines after each Vue import (look for "import X from ...")
        $scriptSection = [regex]::Replace($scriptSection, "(import .+? from .+?;)(?!\n)", "`$1`n")
        # Add newline after each "}" that's followed by const/let
        $scriptSection = [regex]::Replace($scriptSection, "(const \w+|let \w+|function \w+|\$\w+ = )", "`n`$1")
        # Add newline before return statements
        $scriptSection = [regex]::Replace($scriptSection, "(return )", "`n`$1")
        # Add newline after each "}" at end of code block
        $scriptSection = [regex]::Replace($scriptSection, "(}\s*;?)(?!\n)(?!\s*<)(?!\s*\w)(?!\s*[,;)])", "`$1`n")

        $cleaned = $cleaned.Substring(0, $scriptStart) + $scriptSection + $cleaned.Substring($scriptEnd + 9)

        # Add newlines for template sections
        # Before "<template>"
        $cleaned = $cleaned -replace "(?<=\})(\s*)<template>", "`n`n<template>"
        # After each </template> closing
        $cleaned = $cleaned -replace "</template>(\s*)", "</template>`n"
        # Newline after each top-level closing tag
        $cleaned = $cleaned -replace "(</[A-Z]\w+>)", "`$1`n"

        # Save
        $cleaned = $cleaned.Trim() + "`n"
        Set-Content -Path $file -Value $cleaned -NoNewline -Encoding UTF8
        $recovered++
    }
}

Write-Host "Recovered $recovered files"
if ($failed.Count -gt 0) {
    Write-Host "Failed:"
    $failed | ForEach-Object { Write-Host "  $_" }
}
