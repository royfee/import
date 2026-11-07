<?php

/**
 * Royfee\Import 重构测试
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Royfee\Import\Import;
use Royfee\Import\Exceptions\FileNotFoundException;
use Royfee\Import\Exceptions\UnsupportedFormatException;
use Royfee\Import\Exceptions\TemplateMatchException;
use Royfee\Import\FileReader\CsvReader;
use Royfee\Import\FileReader\ExcelReader;

$passed = 0;
$failed = 0;

function test(string $name, callable $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        echo "  ✓ {$name}\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "  ✗ {$name}: {$e->getMessage()}\n    @ {$e->getFile()}:{$e->getLine()}\n";
        $failed++;
    }
}

function assert_eq($expected, $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new \RuntimeException(
            ($msg ? "{$msg}: " : '') .
            "期望 " . var_export($expected, true) .
            ", 实际 " . var_export($actual, true)
        );
    }
}

function assert_true($val, string $msg = ''): void
{
    if ($val !== true) {
        throw new \RuntimeException($msg ?: '期望 true');
    }
}

function assert_count(int $expected, $arr, string $msg = ''): void
{
    $actual = is_array($arr) ? count($arr) : -1;
    if ($actual !== $expected) {
        throw new \RuntimeException(($msg ? "{$msg}: " : '') . "期望 {$expected} 项, 实际 {$actual} 项");
    }
}

function assert_throws(string $class, callable $fn, string $msg = ''): void
{
    try {
        $fn();
        throw new \RuntimeException($msg ?: "期望抛出 {$class} 但未抛出");
    } catch (\Throwable $e) {
        if (!($e instanceof $class)) {
            throw new \RuntimeException("期望 {$class}, 实际 " . get_class($e) . ": " . $e->getMessage());
        }
    }
}

$tmpDir = __DIR__ . '/tmp';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0755, true);
}

$templates = [
    ['订单号' => 'order_no', '收件人' => 'receiver_name', '电话' => 'receiver_phone', '地址' => 'receiver_address', '金额' => 'amount'],
    [
        '关联单号' => 'order_no', '追踪单号' => 'tracking_number', '收件人' => 'receiver_name',
        '收件人电话' => 'receiver_phone', '收件人地址' => 'receiver_address',
        '本包裹总毛重量(KG)' => 'weight', '国家' => 'receiver_country',
    ],
];

echo "=== Royfee\\Import 重构测试 ===\n\n";

// ─── CSV 读取 ─────────────────────────────────────────────
echo "--- CSV 读取 ---\n";

test('CSV getData 返回行数组', function () use ($tmpDir) {
    $file = $tmpDir . '/csv1.csv';
    file_put_contents($file, "姓名,年龄,城市\n张三,25,北京\n李四,30,上海\n");

    $data = (new Import($file))->getData();
    assert_count(2, $data);
    assert_eq('张三', $data[0]['姓名']);
    assert_eq('30', $data[1]['年龄']);
    assert_eq('上海', $data[1]['城市']);
    @unlink($file);
});

test('CSV 自动检测分隔符', function () use ($tmpDir) {
    $file = $tmpDir . '/csv_tab.txt';
    file_put_contents($file, "姓名\t年龄\n张三\t25\n");

    $data = (new Import($file))->getData();
    assert_count(1, $data);
    assert_eq('张三', $data[0]['姓名']);
    @unlink($file);
});

test('CSV 跳过空行', function () use ($tmpDir) {
    $file = $tmpDir . '/csv_empty.csv';
    file_put_contents($file, "A,B\n1,2\n\n3,4\n\n");

    $data = (new Import($file))->getData();
    assert_count(2, $data);
    @unlink($file);
});

test('CSV 包围符处理', function () use ($tmpDir) {
    $file = $tmpDir . '/csv_quote.csv';
    $content = "姓名,描述\n张三,\"他,很好\"\n李四,\"说\"\"你好\"\"\"\n";
    file_put_contents($file, $content);

    $data = (new Import($file))->getData();
    assert_eq('他,很好', $data[0]['描述']);
    assert_eq('说"你好"', $data[1]['描述']);
    @unlink($file);
});

// ─── Excel 读取 ────────────────────────────────────────────
echo "\n--- Excel 读取 ---\n";

test('Excel getData 返回行数组', function () use ($tmpDir) {
    $file = $tmpDir . '/excel1.xlsx';
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', '姓名'); $sheet->setCellValue('B1', '年龄');
    $sheet->setCellValue('A2', '张三'); $sheet->setCellValue('B2', '25');
    $sheet->setCellValue('A3', '李四'); $sheet->setCellValue('B3', '30');
    \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx')->save($file);

    $data = (new Import($file))->getData();
    assert_count(2, $data);
    assert_eq('张三', $data[0]['姓名']);
    assert_eq('30', $data[1]['年龄']);
    @unlink($file);
});

test('Excel 多 sheet 读取', function () use ($tmpDir) {
    $file = $tmpDir . '/excel_multi.xlsx';
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

    $sheet0 = $spreadsheet->getActiveSheet();
    $sheet0->setTitle('订单');
    $sheet0->setCellValue('A1', '订单号'); $sheet0->setCellValue('B1', '收件人');
    $sheet0->setCellValue('A2', 'ORD001'); $sheet0->setCellValue('B2', '张三');

    $sheet1 = $spreadsheet->createSheet();
    $sheet1->setTitle('明细');
    $sheet1->setCellValue('A1', '商品名'); $sheet1->setCellValue('B1', '数量');
    $sheet1->setCellValue('A2', '手机壳'); $sheet1->setCellValue('B2', '5');

    \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx')->save($file);

    $data = (new Import($file, ['sheet' => 2]))->getData();
    assert_true(is_array($data), '应返回数组');
    assert_true(isset($data[0]) && isset($data[1]), '应有两个 sheet');
    assert_eq('ORD001', $data[0][0]['订单号']);
    assert_eq('手机壳', $data[1][0]['商品名']);
    @unlink($file);
});

test('Excel getSheetCount', function () use ($tmpDir) {
    $file = $tmpDir . '/excel_count.xlsx';
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $spreadsheet->getActiveSheet()->setTitle('Sheet1');
    $spreadsheet->createSheet();
    $spreadsheet->createSheet();
    \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx')->save($file);

    $reader = new ExcelReader();
    assert_eq(3, $reader->getSheetCount($file));
    @unlink($file);
});

// ─── 模板匹配 ────────────────────────────────────────────
echo "\n--- 模板匹配 ---\n";

test('CSV matchData 单模板映射', function () use ($tmpDir, $templates) {
    $file = $tmpDir . '/match1.csv';
    file_put_contents($file, "订单号,收件人,电话,地址,金额\nORD001,张三,13800001111,北京市,100.5\n");

    $result = (new Import($file))->matchData($templates);
    assert_eq('ORD001', $result[0]['order_no']);
    assert_eq('张三', $result[0]['receiver_name']);
    assert_eq('100.5', $result[0]['amount']);
    @unlink($file);
});

test('CSV matchData 自动选最佳模板', function () use ($tmpDir, $templates) {
    $file = $tmpDir . '/match2.csv';
    file_put_contents($file, "关联单号,追踪单号,收件人,收件人电话,国家\nORD001,TRK001,张三,13800001111,中国\n");

    $result = (new Import($file))->matchData($templates);
    assert_eq('ORD001', $result[0]['order_no']);
    assert_eq('TRK001', $result[0]['tracking_number']);
    assert_eq('中国', $result[0]['receiver_country']);
    @unlink($file);
});

test('Excel matchData 单 sheet', function () use ($tmpDir, $templates) {
    $file = $tmpDir . '/match_excel.xlsx';
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', '订单号'); $sheet->setCellValue('B1', '收件人'); $sheet->setCellValue('C1', '电话'); $sheet->setCellValue('D1', '地址'); $sheet->setCellValue('E1', '金额');
    $sheet->setCellValue('A2', 'ORD001'); $sheet->setCellValue('B2', '张三'); $sheet->setCellValue('C2', '13800001111'); $sheet->setCellValue('D2', '北京市'); $sheet->setCellValue('E2', '100.5');
    \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx')->save($file);

    $result = (new Import($file))->matchData($templates);
    assert_eq('ORD001', $result[0]['order_no']);
    assert_eq('张三', $result[0]['receiver_name']);
    @unlink($file);
});

test('Excel 多 sheet + 不同模板', function () use ($tmpDir) {
    $file = $tmpDir . '/match_multi.xlsx';
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

    $sheet0 = $spreadsheet->getActiveSheet();
    $sheet0->setTitle('订单');
    $sheet0->setCellValue('A1', '订单号'); $sheet0->setCellValue('B1', '收件人'); $sheet0->setCellValue('C1', '电话'); $sheet0->setCellValue('D1', '地址'); $sheet0->setCellValue('E1', '金额');
    $sheet0->setCellValue('A2', 'ORD001'); $sheet0->setCellValue('B2', '张三'); $sheet0->setCellValue('C2', '138'); $sheet0->setCellValue('D2', '北京'); $sheet0->setCellValue('E2', '100');

    $sheet1 = $spreadsheet->createSheet();
    $sheet1->setTitle('明细');
    $sheet1->setCellValue('A1', '关联单号'); $sheet1->setCellValue('B1', '追踪单号'); $sheet1->setCellValue('C1', '收件人'); $sheet1->setCellValue('D1', '国家');
    $sheet1->setCellValue('A2', 'ORD001'); $sheet1->setCellValue('B2', 'TRK001'); $sheet1->setCellValue('C2', '张三'); $sheet1->setCellValue('D2', '中国');

    \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx')->save($file);

    $orderTpl = [['订单号' => 'order_no', '收件人' => 'name', '电话' => 'phone', '地址' => 'addr', '金额' => 'amt']];
    $itemTpl  = [['关联单号' => 'order_no', '追踪单号' => 'track_no', '收件人' => 'name', '国家' => 'country']];

    $result = (new Import($file, ['sheet' => 2]))->matchData([
        0 => $orderTpl,
        1 => $itemTpl,
    ]);

    assert_true(isset($result[0]) && isset($result[1]), '应返回两个 sheet 的结果');
    assert_eq('ORD001', $result[0][0]['order_no']);
    assert_eq('TRK001', $result[1][0]['track_no']);
    assert_eq('中国', $result[1][0]['country']);
    @unlink($file);
});

test('多 sheet + 模板不够 → 多余 sheet 原样返回', function () use ($tmpDir) {
    $file = $tmpDir . '/match_partial.xlsx';
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

    $sheet0 = $spreadsheet->getActiveSheet();
    $sheet0->setCellValue('A1', '订单号'); $sheet0->setCellValue('B1', '收件人');
    $sheet0->setCellValue('A2', 'ORD001'); $sheet0->setCellValue('B2', '张三');

    $sheet1 = $spreadsheet->createSheet();
    $sheet1->setCellValue('A1', '商品名'); $sheet1->setCellValue('B1', '数量');
    $sheet1->setCellValue('A2', '手机壳'); $sheet1->setCellValue('B2', '5');

    \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx')->save($file);

    $tpl = [['订单号' => 'order_no', '收件人' => 'name']];

    $result = (new Import($file, ['sheet' => 2]))->matchData($tpl);

    assert_true(isset($result[0]) && isset($result[1]), '应返回两个 sheet');
    assert_eq('ORD001', $result[0][0]['order_no']);
    assert_eq('手机壳', $result[1][0]['商品名'], 'sheet 1 应原样返回');
    @unlink($file);
});

// ─── 异常场景 ────────────────────────────────────────────
echo "\n--- 异常场景 ---\n";

test('文件不存在抛 FileNotFoundException', function () {
    assert_throws(FileNotFoundException::class, function () {
        new Import('/no/such/file.csv');
    });
});

test('不支持的格式抛 UnsupportedFormatException', function () use ($tmpDir) {
    $file = $tmpDir . '/bad.xyz';
    file_put_contents($file, 'test');
    assert_throws(UnsupportedFormatException::class, function () use ($file) {
        (new Import($file))->getData();
    });
    @unlink($file);
});

test('模板匹配失败抛 TemplateMatchException', function () use ($tmpDir, $templates) {
    $file = $tmpDir . '/no_match.csv';
    file_put_contents($file, "完全不相关,字段名\n1,2\n");
    assert_throws(TemplateMatchException::class, function () use ($file, $templates) {
        (new Import($file))->matchData($templates);
    });
    @unlink($file);
});

// ─── 边界情况 ────────────────────────────────────────────
echo "\n--- 边界情况 ---\n";

test('空 CSV 文件', function () use ($tmpDir) {
    $file = $tmpDir . '/empty.csv';
    file_put_contents($file, '');
    $data = (new Import($file))->getData();
    assert_eq([], $data);
    @unlink($file);
});

test('只有表头无数据', function () use ($tmpDir) {
    $file = $tmpDir . '/header_only.csv';
    file_put_contents($file, "A,B,C\n");
    $data = (new Import($file))->getData();
    assert_eq([], $data);
    @unlink($file);
});

test('空模板返回原始数据', function () use ($tmpDir) {
    $file = $tmpDir . '/no_tpl.csv';
    file_put_contents($file, "A,B\n1,2\n");
    $result = (new Import($file))->matchData([]);
    assert_eq('1', $result[0]['A']);
    @unlink($file);
});

test('getData 缓存', function () use ($tmpDir) {
    $file = $tmpDir . '/cache.csv';
    file_put_contents($file, "A\n1\n");
    $imp = new Import($file);
    $d1 = $imp->getData();
    $d2 = $imp->getData();
    assert_eq($d1, $d2);
    @unlink($file);
});

test('addReader 自定义读取器', function () use ($tmpDir) {
    $file = $tmpDir . '/custom.json';
    file_put_contents($file, json_encode([['name' => 'test']]));

    $reader = new class implements \Royfee\Import\Contracts\FileReaderInterface {
        public function read(string $filePath): array {
            return json_decode(file_get_contents($filePath), true);
        }
        public function cursor(string $filePath): \Generator {
            yield from json_decode(file_get_contents($filePath), true);
        }
        public function getSheetCount(string $filePath): int { return 1; }
        public function supports(string $extension): bool { return $extension === 'json'; }
        public function setOptions(array $options): self { return $this; }
    };

    $imp = new Import($file);
    $imp->addReader($reader);
    $data = $imp->getData();
    assert_eq('test', $data[0]['name']);
    @unlink($file);
});

// ─── 流式/分批读取 ─────────────────────────────────────────
echo "\n--- 流式/分批读取 ---\n";

test('CSV cursor() 流式读取', function () use ($tmpDir) {
    $file = $tmpDir . '/cursor.csv';
    file_put_contents($file, "姓名,年龄\n张三,25\n李四,30\n王五,35\n");

    $rows = [];
    foreach ((new Import($file))->cursor() as $row) {
        $rows[] = $row;
    }
    assert_count(3, $rows);
    assert_eq('张三', $rows[0]['姓名']);
    assert_eq('30', $rows[1]['年龄']);
    assert_eq('王五', $rows[2]['姓名']);
    @unlink($file);
});

test('Excel cursor() 流式读取', function () use ($tmpDir) {
    $file = $tmpDir . '/cursor.xlsx';
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', '姓名'); $sheet->setCellValue('B1', '年龄');
    $sheet->setCellValue('A2', '张三'); $sheet->setCellValue('B2', '25');
    $sheet->setCellValue('A3', '李四'); $sheet->setCellValue('B3', '30');
    \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx')->save($file);

    $rows = [];
    foreach ((new Import($file))->cursor() as $row) {
        $rows[] = $row;
    }
    assert_count(2, $rows);
    assert_eq('张三', $rows[0]['姓名']);
    assert_eq('30', $rows[1]['年龄']);
    @unlink($file);
});

test('each() 逐行处理', function () use ($tmpDir) {
    $file = $tmpDir . '/each.csv';
    file_put_contents($file, "A,B\n1,2\n3,4\n5,6\n");

    $collected = [];
    $count = (new Import($file))->each(function ($row, $idx) use (&$collected) {
        $collected[] = $row;
    });
    assert_eq(3, $count);
    assert_eq('1', $collected[0]['A']);
    assert_eq('6', $collected[2]['B']);
    @unlink($file);
});

test('chunk() 分批处理', function () use ($tmpDir) {
    $file = $tmpDir . '/chunk.csv';
    $lines = "姓名,年龄\n";
    for ($i = 1; $i <= 25; $i++) {
        $lines .= "用户{$i},{$i}\n";
    }
    file_put_contents($file, $lines);

    $batches = [];
    $batchCount = (new Import($file))->chunk(10, function ($rows, $idx) use (&$batches) {
        $batches[$idx] = count($rows);
    });

    assert_eq(3, $batchCount, '应分 3 批');
    assert_eq(10, $batches[0], '第 1 批 10 行');
    assert_eq(10, $batches[1], '第 2 批 10 行');
    assert_eq(5, $batches[2], '第 3 批 5 行');
    @unlink($file);
});

test('chunkData() 分批匹配模板', function () use ($tmpDir, $templates) {
    $file = $tmpDir . '/chunk_match.csv';
    $lines = "订单号,收件人,电话,地址,金额\n";
    for ($i = 1; $i <= 15; $i++) {
        $lines .= "ORD{$i},用户{$i},1380000{$i},地址{$i},{$i}00\n";
    }
    file_put_contents($file, $lines);

    $batches = [];
    (new Import($file))->chunkData(5, $templates, function ($mappedRows, $idx) use (&$batches) {
        $batches[$idx] = $mappedRows;
    });

    assert_eq(3, count($batches), '应分 3 批');
    assert_eq('ORD1', $batches[0][0]['order_no'], '第 1 批第 1 行');
    assert_eq('ORD6', $batches[1][0]['order_no'], '第 2 批第 1 行');
    assert_eq('ORD11', $batches[2][0]['order_no'], '第 3 批第 1 行');
    assert_eq('receiver_name', array_keys($batches[0][0])[1], '字段应已映射');
    @unlink($file);
});

test('多 sheet cursor()', function () use ($tmpDir) {
    $file = $tmpDir . '/cursor_multi.xlsx';
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

    $sheet0 = $spreadsheet->getActiveSheet();
    $sheet0->setTitle('订单');
    $sheet0->setCellValue('A1', '订单号'); $sheet0->setCellValue('B1', '收件人');
    $sheet0->setCellValue('A2', 'ORD001'); $sheet0->setCellValue('B2', '张三');

    $sheet1 = $spreadsheet->createSheet();
    $sheet1->setTitle('明细');
    $sheet1->setCellValue('A1', '商品名'); $sheet1->setCellValue('B1', '数量');
    $sheet1->setCellValue('A2', '手机壳'); $sheet1->setCellValue('B2', '5');

    \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx')->save($file);

    $allRows = [];
    foreach ((new Import($file, ['sheet' => 2]))->cursor() as $item) {
        $allRows[] = $item;
    }

    assert_true(count($allRows) >= 2, '应至少有 2 行');
    @unlink($file);
});

// ─── 清理 ────────────────────────────────────────────────────
@array_map('unlink', glob($tmpDir . '/*'));
@rmdir($tmpDir);

echo "\n==================================================\n";
echo "测试完成!  通过: {$passed}  失败: {$failed}\n";
echo "==================================================\n";

exit($failed > 0 ? 1 : 0);
