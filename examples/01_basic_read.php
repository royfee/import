<?php

/**
 * 示例 1：基础读取 — getData()
 *
 * 演示：读取 CSV / TXT 文件，获取原始数据
 * 运行：php examples/01_basic_read.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Royfee\Import\Import;

$dataDir = __DIR__ . '/data/';

// ─── 1. 读取 CSV 文件 ───────────────────────────────────────

echo "=== 读取 CSV 文件 ===" . PHP_EOL;

$import = new Import($dataDir . 'orders.csv');
$data   = $import->getData();

echo "共 " . count($data) . " 条记录" . PHP_EOL;
echo "第一条：" . json_encode($data[0], JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo PHP_EOL;

// ─── 2. 读取 Tab 分隔的 TXT 文件 ────────────────────────────

echo "=== 读取 TXT 文件（Tab 分隔） ===" . PHP_EOL;

$import = new Import($dataDir . 'users.txt');
$data   = $import->getData();

echo "共 " . count($data) . " 条记录" . PHP_EOL;
echo "第一条：" . json_encode($data[0], JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo PHP_EOL;

// ─── 3. 读取商品 CSV ────────────────────────────────────────

echo "=== 读取商品 CSV ===" . PHP_EOL;

$import = new Import($dataDir . 'products.csv');
$data   = $import->getData();

echo "共 " . count($data) . " 条记录" . PHP_EOL;
foreach ($data as $row) {
    echo "  {$row['A']} - {$row['B']} ¥{$row['C']}" . PHP_EOL;
}
