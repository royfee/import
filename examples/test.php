<?php
require __DIR__ . '/../vendor/autoload.php';

use Royfee\Import\Import;

$dataDir = __DIR__ . '/data/';

// ─── 1. 读取 CSV 文件 ───────────────────────────────────────

echo "=== 读取 CSV 文件 ===" . PHP_EOL;
$import = new Import($dataDir . 'JZT1-49.xls',['sheet'=>2]);


$data   = $import->getData();
file_put_contents('11.07.txt',var_export($data,true));
echo '===>'.count($data);
exit;


/*
$data   = $import->matchData([
	0 => [
		['关联单号' => 'order_no', '运单号' => 'tracking_no', '收件人电话' => 'phone', '本包裹总毛重量(KG)' => 'weight', '发货国家简称' => 'country']
	],
	1 => [
		['关联单号' => 'order_no', '物品名称' => 'name', '规格' => 'spec', '品牌' => 'brand', '行邮税号' => 'hscode']
	]
]);

file_put_contents('11.07.txt',var_export($data,true));
echo '===>'.count($data);
*/