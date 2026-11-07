<?php

/**
 * 示例 7：异常处理
 *
 * 演示：各种异常情况的捕获和处理
 * 运行：php examples/07_error_handling.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Royfee\Import\Import;
use Royfee\Import\Exceptions\FileNotFoundException;
use Royfee\Import\Exceptions\UnsupportedFormatException;
use Royfee\Import\Exceptions\TemplateMatchException;

// ─── 1. 文件不存在 ──────────────────────────────────────────

echo "=== 文件不存在 ===" . PHP_EOL;

try {
    $import = new Import('/path/to/nonexistent.csv');
} catch (FileNotFoundException $e) {
    echo "捕获异常：" . $e->getMessage() . PHP_EOL;
}
echo PHP_EOL;

// ─── 2. 不支持的格式 ────────────────────────────────────────

echo "=== 不支持的格式 ===" . PHP_EOL;

$tmpFile = tempnam(sys_get_temp_dir(), 'example_unsupported_');
unlink($tmpFile);
$tmpFile .= '.xml';
file_put_contents($tmpFile, '<root><item>test</item></root>');

try {
    $import = new Import($tmpFile);
    $import->getData();
} catch (UnsupportedFormatException $e) {
    echo "捕获异常：" . $e->getMessage() . PHP_EOL;
}

unlink($tmpFile);
echo PHP_EOL;

// ─── 3. 模板匹配失败 ────────────────────────────────────────

echo "=== 模板匹配失败 ===" . PHP_EOL;

$dataDir = __DIR__ . '/data/';

try {
    $import = new Import($dataDir . 'orders.csv');
    $import->matchData([
        ['完全不相关的字段' => 'target_field'],
    ]);
} catch (TemplateMatchException $e) {
    echo "捕获异常：" . $e->getMessage() . PHP_EOL;
    echo "文件表头: " . json_encode($e->getHeaders(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
    echo "匹配分数: " . json_encode($e->getScores(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
echo PHP_EOL;

// ─── 4. 安全处理（不中断程序） ──────────────────────────────

echo "=== 安全处理示例 ===" . PHP_EOL;

function safeImport(string $filePath, array $templates): array
{
    try {
        $import = new Import($filePath);
        return $import->matchData($templates);
    } catch (FileNotFoundException $e) {
        echo "  [错误] 文件不存在" . PHP_EOL;
        return [];
    } catch (UnsupportedFormatException $e) {
        echo "  [错误] 不支持的文件格式" . PHP_EOL;
        return [];
    } catch (TemplateMatchException $e) {
        echo "  [错误] 模板匹配失败，请检查表头" . PHP_EOL;
        return [];
    }
}

$result = safeImport('/nonexistent.csv', [['订单号' => 'id']]);
echo "  返回 " . count($result) . " 条" . PHP_EOL;

$result = safeImport($dataDir . 'orders.csv', [['订单号' => 'order_no', '收件人' => 'name']]);
echo "  成功返回 " . count($result) . " 条" . PHP_EOL;
