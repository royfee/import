<?php
require __DIR__ . '/../vendor/autoload.php';

use Royfee\Import\Import;

$dataDir = __DIR__ . '/data/';

// ─── 1. 读取 CSV 文件 ───────────────────────────────────────

echo "=== 读取 CSV 文件 ===" . PHP_EOL;
$import = new Import($dataDir . 'JZT1-49.xls',['sheet'=>2]);

/*
$data   = $import->getData();
file_put_contents('11.07.txt',var_export($data,true));
echo '===>'.count($data);
exit;
*/

/*
// ─── 不合并：返回 [0 => [订单数组], 1 => [内件数组]]（独立） ───
$data = $import->matchData([
	0 => [
		['关联单号' => 'order_no', '运单号' => 'tracking_no', '收件人电话' => 'phone', '本包裹总毛重量(KG)' => 'weight', '发货国家简称' => 'country']
	],
	1 => [
		['关联单号' => 'order_no', '物品名称' => 'name', '规格' => 'spec', '品牌' => 'brand', '行邮税号' => 'hscode']
	]
]);
file_put_contents('11.07.txt',var_export($data,true));
*/

// ─── 合并：内件直接嵌套到对应订单里，单次遍历完成 ───
$data = $import->matchData([
	0 => [
		['关联单号' => 'order_no', '运单号' => 'tracking_no', '收件人电话' => 'phone', '本包裹总毛重量(KG)' => 'weight', '发货国家简称' => 'country']
	],
	1 => [
		['关联单号' => 'order_no', '物品名称' => 'name', '规格' => 'spec', '品牌' => 'brand', '行邮税号' => 'hscode']
	]
], [
	'key'    => 'order_no',   // 关联字段（两个 sheet 共有的目标字段名）
	'target' => 'items',      // 内件嵌套到订单里的字段名
	// 'parent' => 0,         // 父 sheet 索引（默认 0）
	// 'child'  => 1,         // 子 sheet 索引（默认 1）
]);

// 返回结果：[['order_no' => 'X', 'weight' => '...', ..., 'items' => [['name' => 'A', ...], ...]], ...]

file_put_contents('11.07.txt', var_export($data, true));
echo '===>' . count($data);
