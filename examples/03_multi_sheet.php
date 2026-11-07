<?php

/**
 * 示例 3：多 Sheet Excel 读取
 *
 * 演示：读取多 sheet 的 Excel 文件，每个 sheet 可用不同模板
 * 运行：php examples/03_multi_sheet.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Royfee\Import\Import;
use avadim\FastExcelWriter\Excel as WriterExcel;

// ─── 创建测试用多 Sheet Excel 文件 ──────────────────────────

$xlsxPath = tempnam(sys_get_temp_dir(), 'example_multi_');
unlink($xlsxPath);
$xlsxPath .= '.xlsx';

$excel = WriterExcel::create(['订单']);

// Sheet 1: 订单
$sheet1 = $excel->getSheet();
$sheet1->writeRow(['订单号', '收件人', '金额']);
$sheet1->writeRow(['ORD001', '张三', '199.00']);
$sheet1->writeRow(['ORD002', '李四', '599.00']);

// Sheet 2: 商品
$sheet2 = $excel->makeSheet('商品');
$sheet2->writeRow(['商品编码', '商品名称', '价格']);
$sheet2->writeRow(['SKU001', 'iPhone 15', '7999.00']);
$sheet2->writeRow(['SKU002', 'MacBook Pro', '14999.00']);

$excel->save($xlsxPath);

// ─── 1. 读取单个 Sheet（默认） ──────────────────────────────

echo "=== 单 Sheet 读取（默认） ===" . PHP_EOL;

$import = new Import($xlsxPath);
$data   = $import->getData();

echo "行数：" . count($data) . PHP_EOL;
echo "第一条：" . json_encode($data[0], JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo PHP_EOL;

// ─── 2. 读取多个 Sheet ──────────────────────────────────────

echo "=== 多 Sheet 读取 ===" . PHP_EOL;

$import = new Import($xlsxPath, ['sheet' => 2]);
$data   = $import->getData();

echo "Sheet 数量：" . count($data) . PHP_EOL;
echo "Sheet 0 行数：" . count($data[0]) . PHP_EOL;
echo "Sheet 1 行数：" . count($data[1]) . PHP_EOL;
echo "Sheet 0 第一条：" . json_encode($data[0][0], JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo "Sheet 1 第一条：" . json_encode($data[1][0], JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo PHP_EOL;

// ─── 3. 多 Sheet + 多模板匹配 ───────────────────────────────

echo "=== 多 Sheet 多模板匹配 ===" . PHP_EOL;

$import = new Import($xlsxPath, ['sheet' => 2]);
$data   = $import->matchData([
    0 => [['订单号' => 'order_no', '收件人' => 'name', '金额' => 'amount']],
    1 => [['商品编码' => 'sku', '商品名称' => 'product_name', '价格' => 'price']],
]);

echo "Sheet 0 匹配后：" . json_encode($data[0][0], JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo "Sheet 1 匹配后：" . json_encode($data[1][0], JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo PHP_EOL;

// ─── 4. 多 Sheet + 单模板（只匹配 Sheet 0） ─────────────────

echo "=== 多 Sheet 单模板（Sheet 1 原样返回） ===" . PHP_EOL;

$import = new Import($xlsxPath, ['sheet' => 2]);
$data   = $import->matchData([
    ['订单号' => 'order_no', '收件人' => 'name', '金额' => 'amount'],
]);

echo "Sheet 0（已匹配）：" . json_encode($data[0][0], JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo "Sheet 1（原始数据）：" . json_encode($data[1][0], JSON_UNESCAPED_UNICODE) . PHP_EOL;

// 清理临时文件
unlink($xlsxPath);
