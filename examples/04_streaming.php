<?php

/**
 * 示例 4：流式读取 — cursor()
 *
 * 演示：处理大文件时保持内存恒定
 * 运行：php examples/04_streaming.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Royfee\Import\Import;

$dataDir = __DIR__ . '/data/';

// ─── 1. cursor() — Generator 逐行流式读取 ───────────────────

echo "=== cursor() 流式读取 ===" . PHP_EOL;

$import = new Import($dataDir . 'orders.csv');
$count  = 0;

foreach ($import->cursor() as $row) {
    $count++;
    echo "  第{$count}行：{$row['A']} - {$row['B']}" . PHP_EOL;
}
echo "共处理 {$count} 行" . PHP_EOL;
echo PHP_EOL;

// ─── 2. cursor() + 手动聚合 ─────────────────────────────────

echo "=== cursor() 手动聚合 ===" . PHP_EOL;

$import    = new Import($dataDir . 'orders.csv');
$total     = 0;
$sumAmount = 0;

foreach ($import->cursor() as $row) {
    $total++;
    $sumAmount += (float) $row['E'];
}

echo "总订单数：{$total}" . PHP_EOL;
echo "总金额：¥" . number_format($sumAmount, 2) . PHP_EOL;
echo PHP_EOL;

// ─── 3. cursor() + 手动分批 ─────────────────────────────────

echo "=== cursor() 手动分批（每批 2 条） ===" . PHP_EOL;

$import   = new Import($dataDir . 'orders.csv');
$batch    = [];
$batchIdx = 0;

foreach ($import->cursor() as $row) {
    $batch[] = $row;

    if (count($batch) >= 2) {
        echo "  批次 {$batchIdx}：" . count($batch) . " 条" . PHP_EOL;
        foreach ($batch as $r) {
            echo "    - {$r['A']} {$r['B']}" . PHP_EOL;
        }
        $batch = [];
        $batchIdx++;
    }
}

if (!empty($batch)) {
    echo "  批次 {$batchIdx}：" . count($batch) . " 条" . PHP_EOL;
    foreach ($batch as $r) {
        echo "    - {$r['A']} {$r['B']}" . PHP_EOL;
    }
    $batchIdx++;
}

echo "共 {$batchIdx} 个批次" . PHP_EOL;
echo PHP_EOL;

// ─── 4. 内存对比示意 ────────────────────────────────────────

echo "=== 内存使用 ===" . PHP_EOL;
echo "getData() 内存：" . number_format(memory_get_usage(true) / 1024) . " KB" . PHP_EOL;
echo "cursor() 峰值：" . number_format(memory_get_peak_usage(true) / 1024) . " KB" . PHP_EOL;
echo "（流式读取不会随文件增大而增加内存）" . PHP_EOL;
