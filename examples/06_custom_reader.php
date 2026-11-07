<?php

/**
 * 示例 6：自定义 Reader
 *
 * 演示：通过 addReader() 添加自定义文件读取器
 * 运行：php examples/06_custom_reader.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Royfee\Import\Import;
use Royfee\Import\Contracts\FileReaderInterface;
use Royfee\Import\FileReader\ExcelReader;

// ─── 1. 创建 JSON Reader ────────────────────────────────────

echo "=== 自定义 JSON Reader ===" . PHP_EOL;

$jsonPath = tempnam(sys_get_temp_dir(), 'example_json_');
unlink($jsonPath);
$jsonPath .= '.json';

file_put_contents($jsonPath, json_encode([
    ['id' => 1, 'name' => 'Alice', 'age' => 30],
    ['id' => 2, 'name' => 'Bob', 'age' => 25],
    ['id' => 3, 'name' => 'Charlie', 'age' => 35],
], JSON_UNESCAPED_UNICODE));

$jsonReader = new class implements FileReaderInterface {
    private array $headers = [];
    private array $options = [];

    public function read(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $raw = json_decode($content, true) ?: [];

        if (empty($raw)) {
            return [];
        }

        $keys = array_keys($raw[0]);
        $data = [];
        foreach ($raw as $record) {
            $row = [];
            foreach ($keys as $i => $key) {
                $row[ExcelReader::colName($i)] = (string)($record[$key] ?? '');
            }
            $data[] = $row;
        }
        return $data;
    }

    public function cursor(string $filePath): \Generator
    {
        foreach ($this->read($filePath) as $row) {
            yield $row;
        }
    }

    public function getSheetCount(string $filePath): int
    {
        return 1;
    }

    public function supports(string $extension): bool
    {
        return $extension === 'json';
    }

    public function setOptions(array $options): self
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    public function readMultiSheet(string $filePath, int $maxSheets): array
    {
        return [0 => $this->read($filePath)];
    }

    public function getHeaders(string $filePath): array
    {
        if (!empty($this->headers)) {
            return $this->headers;
        }
        $content = file_get_contents($filePath);
        $raw = json_decode($content, true) ?: [];
        if (empty($raw)) return [];
        $keys = array_keys($raw[0]);
        
        // 返回关联数组，键为列名（A, B, C...）
        $this->headers = [];
        foreach ($keys as $i => $key) {
            $this->headers[ExcelReader::colName($i)] = $key;
        }
        return $this->headers;
    }

    public function getHeadersMultiSheet(string $filePath, int $maxSheets): array
    {
        return [0 => $this->getHeaders($filePath)];
    }
};

$import = new Import($jsonPath);
$import->addReader($jsonReader);

$data = $import->getData();
echo "JSON 记录数：" . count($data) . PHP_EOL;
foreach ($data as $row) {
    echo "  {$row['B']} - {$row['C']}岁" . PHP_EOL;
}
echo PHP_EOL;

// ─── 2. 自定义 Reader + 模板匹配 ────────────────────────────

echo "=== 自定义 Reader + 模板匹配 ===" . PHP_EOL;

$import = new Import($jsonPath);
$import->addReader($jsonReader);

$data = $import->matchData([
    ['name' => 'user_name', 'age' => 'user_age'],
]);

echo "匹配后：" . json_encode($data[0], JSON_UNESCAPED_UNICODE) . PHP_EOL;

unlink($jsonPath);
