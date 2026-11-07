<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// Opt-in metadata-only provisioning. Never copies records or changes source tables.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection('mysql');
if ($connection->getDriverName() !== 'mysql') {
    throw new RuntimeException('Requires a MariaDB/MySQL connection.');
}
$pdo = $connection->getPdo();
$original = $pdo->query('SELECT DATABASE()')->fetchColumn();
$milestone = in_array('--modules', $argv, true) ? 'm5' : (in_array('--payments', $argv, true) ? 'm4' : 'm3');
$name = 'bizpilot_'.$milestone.'_qa_'.date('Ymd').'_'.bin2hex(random_bytes(3));
if ($name === $original || ! preg_match('/^bizpilot_m[345]_qa_\d{8}_[a-f0-9]{6}$/D', $name)) {
    throw new RuntimeException('Unsafe QA schema name.');
}
$definitions = [];
foreach (['users', 'invoices', 'invoice_items', 'subscription_plans', 'subscriptions', 'gst_slabs'] as $table) {
    $sql = $pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
    $definitions[] = preg_replace('/ AUTO_INCREMENT=\d+/', '', $sql);
}
$pdo->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE `'.$name.'`');
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== $name) {
    throw new RuntimeException('QA schema selection failed.');
}
foreach ($definitions as $sql) {
    $pdo->exec($sql);
}
echo $name.PHP_EOL;
