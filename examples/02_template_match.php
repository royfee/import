<?php

/**
 * 示例 2：模板匹配 — matchData()
 *
 * 演示：将中文表头映射为英文字段名
 * 运行：php examples/02_template_match.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Royfee\Import\Import;

$dataDir = __DIR__ . '/data/';

// ─── 1. 单模板匹配 ──────────────────────────────────────────

echo "=== 单模板匹配 ===" . PHP_EOL;

$import = new Import($dataDir . 'orders.csv');
$data   = $import->matchData([
    ['订单号' => 'order_no', '收件人' => 'recipient', '手机号' => 'phone', '金额' => 'amount'],
]);

echo "匹配后字段：" . implode(', ', array_keys($data[0])) . PHP_EOL;
foreach ($data as $row) {
    echo "  {$row['order_no']} | {$row['recipient']} | ¥{$row['amount']}" . PHP_EOL;
}
echo PHP_EOL;

// ─── 2. 模糊匹配（表头有空格也能匹配） ──────────────────────

echo "=== 模糊匹配 ===" . PHP_EOL;

$import = new Import($dataDir . 'products.csv');
$data   = $import->matchData([
    ['商品 编码' => 'sku', '商品 名称' => 'name', '价格' => 'price'],
]);

echo "模糊匹配后：" . json_encode($data[0], JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo PHP_EOL;

// ─── 3. 多模板匹配（从多个模板中选最佳） ────────────────────

echo "=== 多模板选最佳 ===" . PHP_EOL;

$import = new Import($dataDir . 'orders.csv');
$data   = $import->matchData([
    // 模板 A：2 个 key 只匹配 1 个 → 得分 0.5
    ['订单号' => 'id', '不存在的字段' => 'xxx'],
    // 模板 B：3 个 key 全部匹配 → 得分 1.0
    ['订单号' => 'order_no', '收件人' => 'name', '金额' => 'amount'],
    // 模板 C：5 个 key 全部匹配 → 得分 1.0（与 B 并列，但 B 先匹配到）
    ['订单号' => 'order_no', '收件人' => 'recipient', '手机号' => 'phone', '金额' => 'money', '状态' => 'status'],
]);

echo "最佳模板匹配后字段：" . implode(', ', array_keys($data[0])) . PHP_EOL;
echo "第一条：" . json_encode($data[0], JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo "（模板 A 得分 0.5，模板 B/C 均得 1.0，B 先匹配成功）" . PHP_EOL;
