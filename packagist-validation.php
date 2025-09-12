<?php

/**
 * Packagist Publication Validation Script
 * 
 * This script validates that the Laravel API Model Client package
 * is ready for publication to Packagist.org
 */

echo "🚀 LARAVEL API MODEL CLIENT - PACKAGIST PUBLICATION VALIDATION\n";
echo "===============================================================\n\n";

$errors = [];
$warnings = [];
$passed = 0;
$total = 0;

function validate($description, $condition, $errorMessage = null, $isWarning = false) {
    global $errors, $warnings, $passed, $total;
    
    $total++;
    echo "🔍 Checking: {$description}... ";
    
    if ($condition) {
        echo "✅ PASSED\n";
        $passed++;
    } else {
        if ($isWarning) {
            echo "⚠️  WARNING\n";
            $warnings[] = $errorMessage ?: $description;
        } else {
            echo "❌ FAILED\n";
            $errors[] = $errorMessage ?: $description;
        }
    }
}

// 1. Check composer.json structure
echo "📋 COMPOSER.JSON VALIDATION\n";
echo "============================\n";

$composerPath = __DIR__ . '/composer.json';
validate('composer.json exists', file_exists($composerPath));

if (file_exists($composerPath)) {
    $composer = json_decode(file_get_contents($composerPath), true);
    
    validate('composer.json is valid JSON', $composer !== null);
    validate('Package name is set', isset($composer['name']) && !empty($composer['name']));
    validate('Package name follows vendor/package format', 
        isset($composer['name']) && preg_match('/^[a-z0-9]([_.-]?[a-z0-9]+)*\/[a-z0-9]([_.-]?[a-z0-9]+)*$/', $composer['name']));
    validate('Description is present', isset($composer['description']) && !empty($composer['description']));
    validate('License is specified', isset($composer['license']) && !empty($composer['license']));
    validate('Authors are specified', isset($composer['authors']) && is_array($composer['authors']) && count($composer['authors']) > 0);
    validate('PSR-4 autoload is configured', 
        isset($composer['autoload']['psr-4']) && is_array($composer['autoload']['psr-4']));
    validate('Keywords are present', isset($composer['keywords']) && is_array($composer['keywords']) && count($composer['keywords']) > 0);
    validate('Minimum stability is set', isset($composer['minimum-stability']));
    validate('Prefer stable is enabled', isset($composer['prefer-stable']) && $composer['prefer-stable'] === true);
    
    // Check version
    if (isset($composer['version'])) {
        validate('Version follows semantic versioning', 
            preg_match('/^\d+\.\d+\.\d+(-[a-zA-Z0-9\-\.]+)?$/', $composer['version']));
        echo "   📌 Current version: {$composer['version']}\n";
    } else {
        validate('Version tag will be used from Git', true, null, true);
    }
}

echo "\n";

// 2. Check required files
echo "📁 REQUIRED FILES VALIDATION\n";
echo "=============================\n";

validate('README.md exists', file_exists(__DIR__ . '/README.md'));
validate('LICENSE.md exists', file_exists(__DIR__ . '/LICENSE.md'));
validate('CHANGELOG.md exists', file_exists(__DIR__ . '/CHANGELOG.md'));
validate('src/ directory exists', is_dir(__DIR__ . '/src'));
validate('Service provider exists', file_exists(__DIR__ . '/src/ApiModelRelationsServiceProvider.php'));

// Check if phpunit.xml exists
validate('PHPUnit configuration exists', 
    file_exists(__DIR__ . '/phpunit.xml') || file_exists(__DIR__ . '/phpunit.xml.dist'));

echo "\n";

// 3. Check Git repository
echo "🔧 GIT REPOSITORY VALIDATION\n";
echo "=============================\n";

validate('.git directory exists', is_dir(__DIR__ . '/.git'));
validate('.gitignore exists', file_exists(__DIR__ . '/.gitignore'));

// Check if we're in a git repository and get remote info
if (is_dir(__DIR__ . '/.git')) {
    $remoteOutput = shell_exec('cd ' . __DIR__ . ' && git remote -v 2>/dev/null');
    validate('Git remote is configured', !empty($remoteOutput));
    
    if (!empty($remoteOutput)) {
        echo "   🔗 Git remotes:\n";
        $lines = explode("\n", trim($remoteOutput));
        foreach ($lines as $line) {
            if (!empty($line)) {
                echo "      {$line}\n";
            }
        }
    }
    
    // Check for uncommitted changes
    $statusOutput = shell_exec('cd ' . __DIR__ . ' && git status --porcelain 2>/dev/null');
    validate('No uncommitted changes', empty(trim($statusOutput)), 
        'There are uncommitted changes. Commit them before publishing.', true);
    
    // Check for tags
    $tagsOutput = shell_exec('cd ' . __DIR__ . ' && git tag -l 2>/dev/null');
    validate('Git tags exist', !empty(trim($tagsOutput)), 
        'No git tags found. Create a version tag before publishing.', true);
}

echo "\n";

// 4. Check package structure
echo "🏗️  PACKAGE STRUCTURE VALIDATION\n";
echo "==================================\n";

$requiredDirs = ['src', 'config'];
foreach ($requiredDirs as $dir) {
    validate("{$dir}/ directory exists", is_dir(__DIR__ . '/' . $dir));
}

// Check for main classes
$mainClasses = [
    'src/Models/ApiModel.php',
    'src/Query/ApiQueryBuilder.php',
    'src/Services/ApiClient.php',
    'src/ApiModelRelationsServiceProvider.php'
];

foreach ($mainClasses as $class) {
    validate(basename($class) . ' exists', file_exists(__DIR__ . '/' . $class));
}

echo "\n";

// 5. Check namespace consistency
echo "🔍 NAMESPACE VALIDATION\n";
echo "========================\n";

$phpFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__ . '/src', RecursiveDirectoryIterator::SKIP_DOTS)
);

$namespaceConsistent = true;
$expectedNamespace = 'MTechStack\\LaravelApiModelClient';

foreach ($phpFiles as $file) {
    if ($file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = trim($matches[1]);
            if (!str_starts_with($namespace, $expectedNamespace)) {
                $namespaceConsistent = false;
                break;
            }
        }
    }
}

validate('Namespace consistency', $namespaceConsistent, 
    'Some files have inconsistent namespaces');

echo "\n";

// 6. Run basic functionality test
echo "🧪 FUNCTIONALITY VALIDATION\n";
echo "============================\n";

// Run the existing test suite
$testOutput = shell_exec('cd ' . __DIR__ . ' && php run-tests.php 2>&1');
$testPassed = strpos($testOutput, '✅ Passed: 9') !== false;

validate('Core functionality tests pass', $testPassed, 
    'Some functionality tests are failing');

echo "\n";

// 7. Final summary
echo "🎯 PUBLICATION READINESS SUMMARY\n";
echo "=================================\n";
echo "Total Checks: {$total}\n";
echo "✅ Passed: {$passed}\n";
echo "❌ Failed: " . count($errors) . "\n";
echo "⚠️  Warnings: " . count($warnings) . "\n";

$successRate = round(($passed / $total) * 100, 1);
echo "📊 Success Rate: {$successRate}%\n\n";

if (count($errors) > 0) {
    echo "❌ CRITICAL ISSUES (Must fix before publishing):\n";
    foreach ($errors as $error) {
        echo "   • {$error}\n";
    }
    echo "\n";
}

if (count($warnings) > 0) {
    echo "⚠️  WARNINGS (Recommended to fix):\n";
    foreach ($warnings as $warning) {
        echo "   • {$warning}\n";
    }
    echo "\n";
}

// Publication readiness assessment
if (count($errors) === 0) {
    echo "🎉 PACKAGE IS READY FOR PACKAGIST PUBLICATION!\n";
    echo "===============================================\n";
    echo "✅ All critical requirements met\n";
    echo "✅ Package structure is valid\n";
    echo "✅ Core functionality works\n";
    echo "✅ Git repository is properly configured\n";
    
    if (count($warnings) > 0) {
        echo "\n📝 Consider addressing the warnings above for best practices.\n";
    }
    
    echo "\n🚀 NEXT STEPS:\n";
    echo "==============\n";
    echo "1. Ensure your GitHub repository is public\n";
    echo "2. Create and push a version tag:\n";
    echo "   git tag v1.0.11\n";
    echo "   git push origin v1.0.11\n";
    echo "3. Go to https://packagist.org/packages/submit\n";
    echo "4. Enter your GitHub repository URL\n";
    echo "5. Click 'Check' and then 'Submit'\n";
    echo "6. Set up auto-updating webhook (optional but recommended)\n";
    
    exit(0);
} else {
    echo "⚠️  PACKAGE NEEDS FIXES BEFORE PUBLICATION\n";
    echo "==========================================\n";
    echo "Please address the critical issues listed above.\n";
    
    exit(1);
}
