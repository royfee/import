<?php

/**
 * 示例 5：分批匹配处理
 *
 * 演示：大数据量时分批读取 + 模板匹配 + 回调处理
 * 运行：php examples/05_chunk_data.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Royfee\Import\Import;

$dataDir = __DIR__ . '/data/';

// ─── 1. matchData() + 手动分批 ──────────────────────────────

echo "=== matchData() + 手动分批 ===" . PHP_EOL;

$import  = new Import($dataDir . 'orders.csv');
$mapped  = $import->matchData([
    ['订单号' => 'order_no', '收件人' => 'name', '手机号' => 'phone', '金额' => 'amount', '状态' => 'status'],
]);

$chunkSize  = 2;
$totalBatches = 0;

foreach (array_chunk($mapped, $chunkSize) as $batchIdx => $batch) {
    echo "批次 {$batchIdx}（" . count($batch) . " 条）：" . PHP_EOL;
    foreach ($batch as $row) {
        echo "  {$row['order_no']} | {$row['name']} | ¥{$row['amount']} | {$row['status']}" . PHP_EOL;
    }
    $totalBatches++;
}

echo "共 {$totalBatches} 个批次" . PHP_EOL;
echo PHP_EOL;

// ─── 2. 模拟批量入库 ────────────────────────────────────────

echo "=== 模拟批量入库 ===" . PHP_EOL;

$import = new Import($dataDir . 'orders.csv');
$mapped = $import->matchData([
    ['订单号' => 'order_no', '收件人' => 'recipient', '金额' => 'amount'],
]);

$totalInserted = 0;

foreach (array_chunk($mapped, 3) as $batchIdx => $batch) {
    $count = count($batch);
    $totalInserted += $count;
    echo "  批次 {$batchIdx}：插入 {$count} 条（累计 {$totalInserted}）" . PHP_EOL;
}

echo "入库完成，共 {$totalInserted} 条" . PHP_EOL;
