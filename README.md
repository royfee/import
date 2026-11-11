# Royfee Import

轻量级 PHP 文件导入工具，支持 Excel/CSV/TXT 格式，提供自动模板匹配、字段映射和流式读取功能。

## 特性

- 🚀 **多格式支持**：Excel（.xls/.xlsx）、CSV、TXT
- 🎯 **智能模板匹配**：自动识别表头并映射到目标字段
- 💾 **内存优化**：流式读取大文件，内存占用恒定
- 🔄 **多 Sheet 支持**：一次读取 Excel 的多个工作表
- 🔗 **关联合并**：多 sheet 匹配时单次遍历将子记录嵌套到父记录
- 🛡️ **容错处理**：匹配失败时返回原始数据，不中断程序
- 📦 **轻量级**：最小化依赖，易于集成

## 安装

```bash
composer require royfee/import
```

## 快速开始

### 1. 基础读取

```php
use Royfee\Import\Import;

$import = new Import('data.xlsx');
$data = $import->getData();

// 返回格式：[['A' => '值1', 'B' => '值2', ...], ...]
```

### 2. 模板匹配

```php
$import = new Import('orders.csv');
$data = $import->matchData([
    ['订单号' => 'order_no', '收件人' => 'recipient', '金额' => 'amount']
]);

// 返回格式：[['order_no' => 'ORD001', 'recipient' => '张三', 'amount' => '199.00'], ...]
```

### 3. 多 Sheet 读取

```php
$import = new Import('data.xls', ['sheet' => 2]);
$data = $import->getData();

// 返回格式：[0 => [...], 1 => [...]]
```

### 4. 多 Sheet 模板匹配

```php
$import = new Import('data.xls', ['sheet' => 2]);
$data = $import->matchData([
    0 => [['关联单号' => 'order_no', '运单号' => 'tracking_no']],
    1 => [['关联单号' => 'order_no', '物品名称' => 'name', '行邮税号' => 'hscode']]
]);

// 返回格式：[0 => [订单数组], 1 => [内件数组]]（独立）
```

### 5. 多 Sheet 关联合并（Merge）

当 Sheet1 是订单、Sheet2 是订单内件时，可通过 `merge` 参数在匹配的同时将内件嵌套到对应订单中，**单次遍历完成，无额外开销**：

```php
$import = new Import('data.xls', ['sheet' => 2]);
$data = $import->matchData([
    0 => [['关联单号' => 'order_no', '运单号' => 'tracking_no', '重量' => 'weight']],
    1 => [['关联单号' => 'order_no', '物品名称' => 'name', '行邮税号' => 'hscode']]
], [
    'key'    => 'order_no',   // 关联字段（两个 sheet 共 的目标字段名）
    'target' => 'items',      // 内件嵌套到订单里的字段名
    // 'parent' => 0,         // 父 sheet 索引（默认 0）
    // 'child'  => 1,         // 子 sheet 索引（默认 1）
]);

// 返回格式（直接已嵌套内件）：
// [
//     ['order_no' => 'ORD001', 'tracking_no' => 'T001', 'weight' => '1.5', 'items' => [
//         ['order_no' => 'ORD001', 'name' => '物品A', 'hscode' => '12345678'],
//         ['order_no' => 'ORD001', 'name' => '物品B', 'hscode' => '87654321'],
//     ]],
//     ['order_no' => 'ORD002', 'tracking_no' => 'T002', 'weight' => '2.0', 'items' => [
//         ['order_no' => 'ORD002', 'name' => '物品C', 'hscode' => '11111111'],
//     ]],
// ]
```

**merge 参数说明**：

| 参数 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `key` | string | `'order_no'` | 关联字段名（映射后的目标字段名） |
| `target` | string | `'items'` | 子记录嵌套到父记录的字段名 |
| `parent` | int | `0` | 父 sheet 索引 |
| `child` | int | `1` | 子 sheet 索引 |

**性能**：merge 在匹配过程中完成完成，不产生二次遍历，适合大数据量场景。

### 6. 流式读取（大文件）

```php
$import = new Import('large_file.xlsx');

foreach ($import->cursor() as $row) {
    // 逐行处理，内存占用恒定
    echo $row['A'] . PHP_EOL;
}
```

### 7. 分批处理

```php
$import = new Import('data.csv');
$data = $import->matchData([['订单号' => 'order_no']]);

$chunks = array_chunk($data, 100);
foreach ($chunks as $chunk) {
    // 每批 100 条入库
    db_insert($chunk);
}
```

## 高级用法

### 自定义 Reader

```php
use Royfee\Import\Contracts\FileReaderInterface;
use Royfee\Import\Import;

class JsonReader implements FileReaderInterface
{
    public function read(string $filePath): array
    {
        $content = file_get_contents($filePath);
        return json_decode($content, true) ?: [];
    }
    
    public function cursor(string $filePath): \Generator
    {
        foreach ($this->read($filePath) as $row) {
            yield $row;
        }
    }
    
    public function getHeaders(string $filePath): array
    {
        // 返回 ['A' => '字段1', 'B' => '字段2', ...]
    }
    
    // ... 其他接口方法
}

$import = new Import('data.json');
$import->addReader(new JsonReader());
$data = $import->getData();
```

### 配置选项

```php
$import = new Import('data.xls', [
    'sheet' => 2,              // 读取的 sheet数量
    'skip_empty' => true,      // 跳过空行
    'match_threshold' => 0.3,  // 模板匹配阈值（0-1）
]);
```

### 上传文件（扩展名不可用）

上传的临时文件通常没有真实扩展名（如 PHP 的 `upload_tmp_dir/phpXXXX.tmp`），此时用 `extension` 显式指定格式，包本身不做文件内容探测：

```php
// ThinkPHP 6/8
$file = $request->file('excel');

$import = new Import($file->getPathname(), [
    'extension' => $file->getOriginalExtension(), // 'xlsx'
]);
```

不传或传空字符串时，仍按文件路径的扩展名判断。

## API 参考

### Import 类

#### `__construct(string $filePath, array $options = [])`
创建导入实例。`options` 中的 `extension` 可覆盖按路径推断出的扩展名（用于上传的 `.tmp` 文件）。

#### `getData(): array`
获取原始数据，列名使用 A/B/C...

#### `matchData(array $templates, ?array $merge = null): array`
根据模板匹配并映射数据。`$merge` 参数可选，用于多 sheet 场景下将子 sheet 数据 关联嵌套到父 sheet 记录中（详见"多 Sheet 关联合并"章节）。

#### `cursor(): \Generator`
流式读取，返回 Generator

#### `addReader(FileReaderInterface $reader): self`
添加自定义 Reader

### FileReaderInterface

所有 Reader 必须实现的接口：

- `read(string $filePath): array`
- `cursor(string $filePath): \Generator`
- `getHeaders(string $filePath): array`
- `getHeadersMultiSheet(string $filePath, int $maxSheets): array`
- `readMultiSheet(string $filePath, int $maxSheets): array`
- `getSheetCount(string $filePath): int`
- `supports(string $extension): bool`
- `setOptions(array $options): self`

## 错误处理

```php
use Royfee\Import\Exceptions\FileNotFoundException;
use Royfee\Import\Exceptions\UnsupportedFormatException;

try {
    $import = new Import('data.xlsx');
    $data = $import->matchData($templates);
} catch (FileNotFoundException $e) {
    echo '文件不存在: ' . $e->getMessage();
} catch (UnsupportedFormatException $e) {
    echo '不支持的格式: ' . $e->getMessage();
}
```

**注意**：模板匹配失败时不会抛出异常，而是返回原始数据（使用 A/B/C 列名）。

## 性能建议

### 内存优化

- 使用 `cursor()` 流式读取大文件
- 避免一次性加载整个文件到内存
- 使用 `array_chunk()` 分批处理数据

### 示例：处理 10 万行 Excel

```php
$import = new Import('large.xlsx');
$batch = [];
$batchSize = 1000;

foreach ($import->cursor() as $row) {
    $batch[] = $row;
    
    if (count($batch) >= $batchSize) {
        processBatch($batch);
        $batch = [];
    }
}

if (!empty($batch)) {
    processBatch($batch);
}
```

## 测试

```bash
composer test
```

## 要求

- PHP >= 7.4
- ext-json
- ext-mbstring

## 依赖

- `avadim/fast-excel-reader`: ^4.5 - Excel 文件解析

## 许可证

MIT License

## 贡献

欢迎提交 Issue 和 Pull Request！

## 作者

- **royfee** - [aiernet@126.com](mailto:aiernet@126.com)
