#!/usr/bin/env pwsh
# Script to batch-fix PHPStan errors in controllers
# Fixes patterns like getUser()->getType(), getUser()->getId()

$controllerPath = "C:\xampp\htdocs\Carrieri_Webapp-main\src\Controller"

# Get all PHP files in controllers
$phpFiles = Get-ChildItem -Path $controllerPath -Filter "*.php" -Recurse

$fixedCount = 0

foreach ($file in $phpFiles) {
    if ($file.Name -eq "UserTypeCasterTrait.php") { continue }

    $content = Get-Content $file.FullName -Raw
    $originalContent = $content

    # Check if file already uses the trait
    if ($content -contains "use UserTypeCasterTrait") {
        continue
    }

    # Only fix if it extends AbstractController
    if (-not $content -contains "extends AbstractController") {
        continue
    }

    # Check if needs fixing (has getUser()->method calls)
    if ($content -match "getUser\(\)->") {
        Write-Host "Fixing: $($file.Name)"

        # Add declare(strict_types=1) if missing
        if ($content -notmatch "declare\(strict_types=1\)") {
            $content = $content -replace "^<\?php", "<?php`r`n`r`ndeclare(strict_types=1);"
        }

        # Add the trait import after use statements
        if ($content -match "use Symfony\\Bundle\\FrameworkBundle\\Controller\\AbstractController;") {
            $content = $content -replace `
                "(use Symfony\\Bundle\\FrameworkBundle\\Controller\\AbstractController;)", `
                "`$1`r`nuse App\Controller\UserTypeCasterTrait;"
        }

        # Add trait usage in class
        if ($content -match "class\s+\w+\s+extends\s+AbstractController\s*\{") {
            $content = $content -replace `
                "(class\s+\w+\s+extends\s+AbstractController\s*\{)", `
                "`$1`r`    use UserTypeCasterTrait;`r`n"
        }

        # Replace simple patterns
        $content = $content -replace `
            '\$user\s*=\s*\$this->getUser\(\);', `
            '$user = $this->getAuthenticatedUser();'

        if ($content -ne $originalContent) {
            Set-Content -Path $file.FullName -Value $content
            $fixedCount++
            Write-Host "  -> Fixed!"
        }
    }
}

Write-Host "Total files fixed: $fixedCount"

