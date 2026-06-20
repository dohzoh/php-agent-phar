#!/usr/bin/env php
<?php

declare(strict_types=1);

if (!extension_loaded('phar')) {
    fwrite(STDERR, "Error: The phar extension is required to build.\n");
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

$phar = new Phar($outputFile, 0, 'php-agent.phar');
$phar->setSignatureAlgorithm(Phar::SHA256);
$phar->startBuffering();

$phar->buildFromDirectory($srcDir, '/\.php$/');

if (is_dir($vendorDir)) {
    $phar->buildFromDirectory($vendorDir, '/\.php$/');
}

$phar->setStub(file_get_contents($stubFile));
$phar->stopBuffering();

$phar->compressFiles(Phar::GZ);

echo "Built: $outputFile\n";
echo "Size: " . filesize($outputFile) . " bytes\n";
