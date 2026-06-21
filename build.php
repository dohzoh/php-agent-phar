#!/usr/bin/env php
<?php

declare(strict_types=1);

if (!extension_loaded('phar')) {
    fwrite(STDERR, "Error: The phar extension is required to build.
");
    exit(1);
}

$srcDir = __DIR__ . '/src';
$vendorDir = __DIR__ . '/vendor';
$stubFile = __DIR__ . '/stub.php';
$outputFile = __DIR__ . '/php-agent.phar';
$buildDir = __DIR__ . '/build';

if (is_file($outputFile)) {
    unlink($outputFile);
}

if (!is_dir($vendorDir) || !is_file($vendorDir . '/autoload.php')) {
    fwrite(STDERR, "Error: run composer install before building.
");
    exit(1);
}

$phar = new Phar($outputFile, 0, 'php-agent.phar');
$phar->setSignatureAlgorithm(Phar::SHA256);
$phar->startBuffering();

// Preserve src/ directory structure for proper PSR-4 autoloading
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $file) {
    if ($file->isFile() && strtolower(pathinfo($file->getPathname(), PATHINFO_EXTENSION)) === 'php') {
        $relPath = 'src/' . substr($file->getPathname(), strlen($srcDir) + 1);
        $phar->addFile($file->getPathname(), ltrim($relPath, '/'));
    }
}

if (is_dir($vendorDir)) {
    // Preserve vendor/ directory structure for proper autoloading
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vendorDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $file) {
        if ($file->isFile() && strtolower(pathinfo($file->getPathname(), PATHINFO_EXTENSION)) === 'php') {
            // Calculate relative path from vendor root to preserve structure
            $relPath = 'vendor/' . substr($file->getPathname(), strlen($vendorDir) + 1);
            $phar->addFile($file->getPathname(), ltrim($relPath, '/'));
        }
    }
}

$phar->setStub(file_get_contents($stubFile));
$phar->stopBuffering();

$phar->compressFiles(Phar::GZ);

echo "Built: $outputFile
";
echo "Size: " . filesize($outputFile) . " bytes
";
