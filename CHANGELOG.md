# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-11-07

### Added
- 核心功能
  - `Import` 主类，支持 Excel/CSV/TXT 文件导入
  - `getData()` 方法，返回原始数据（A/B/C 列名格式）
  - `matchData()` 方法，支持模板匹配和字段映射
  - `cursor()` 方法，流式读取大文件
  - `addReader()` 方法，支持自定义 Reader 扩展

- 文件读取器
  - `ExcelReader` - Excel 文件读取（.xls/.xlsx）
  - `CsvReader` - CSV/TXT 文件读取
  - `FileReaderInterface` - 读取器接口定义

- 多 Sheet 支持
  - `readMultiSheet()` - 一次打开文件读取多个 Sheet
  - `getHeadersMultiSheet()` - 一次获取多个 Sheet 的表头
  - 内存优化：避免多次打开同一文件

- 异常处理
  - `FileNotFoundException` - 文件不存在
  - `UnsupportedFormatException` - 不支持的文件格式
  - `TemplateMatchException` - 模板匹配失败（保留但不再抛出）
  - `FileReadException` - 文件读取错误
  - `ImportException` - 基础异常类

- 文档
  - README.md - 完整使用文档
  - LICENSE - MIT 许可证
  - 代码注释 - 关键方法中文注释

### Changed
- **破坏性变更**：数据格式从表头键名改为列字母键名
  - 旧格式：`[['订单号' => 'ORD001', '收件人' => '张三'], ...]`
  - 新格式：`[['A' => 'ORD001', 'B' => '张三'], ...]`
  - 原因：节省内存，避免表头重复问题

- **破坏性变更**：模板匹配失败时返回原始数据而非抛出异常
  - 旧行为：匹配失败抛出 `TemplateMatchException`
  - 新行为：匹配失败返回原始数据（A/B/C 列名）
  - 原因：避免因编码问题导致程序中断

- 模板匹配逻辑
  - 移除模糊匹配（similar_text）
  - 只使用精确匹配（normalize 后）
  - 防止多个列映射到同一字段
  - 原因：避免"物品名称"、"物品简称"、"物品英文名称"互相匹配

- 性能优化
  - `readMultiSheet()` 单次打开文件，内存降低 12%
  - 使用 Generator 实现流式读取
  - 支持 `skip_empty` 选项跳过空行

### Fixed
- Excel 列错位问题
  - 原因：不同行的列数不一致（11-15列）
  - 修复：保留 avadim 库返回的原始列名（A/B/C...）
  
- 重复映射问题
  - 原因：多个列被映射到同一目标字段
  - 修复：添加 `$usedTargets` 跟踪已映射字段

- 内存泄漏问题
  - 原因：多次打开同一文件
  - 修复：实现 `readMultiSheet()` 单次打开

### Security
- 无已知安全漏洞
- 文件句柄正确关闭（try-finally）
- 无 eval() 或动态函数调用

## [0.1.0] - 2024-03-01

### Added
- 初始版本
- 基础的文件读取功能
- 简单的模板匹配

---

## 版本说明

### 语义化版本
- **主版本号（X.0.0）**：包含不兼容的 API 变更
- **次版本号（0.X.0）**：以向后兼容的方式新增功能
- **修订号（0.0.X）**：向后兼容的问题修正

### 升级指南

#### 从 0.x 升级到 1.0.0

**破坏性变更 1：数据格式变化**

```php
// 0.x 版本
$data = $import->getData();
echo $data[0]['订单号']; // 使用表头作为键

// 1.0.0 版本
$data = $import->getData();
echo $data[0]['A']; // 使用列字母作为键

// 或使用 matchData() 获取命名键
$data = $import->matchData([['订单号' => 'order_no']]);
echo $data[0]['order_no'];
```

**破坏性变更 2：匹配失败行为变化**

```php
// 0.x 版本
try {
    $data = $import->matchData($templates);
} catch (TemplateMatchException $e) {
    // 处理异常
}

// 1.0.0 版本
$data = $import->matchData($templates);
if (isset($data[0]['A'])) {
    // 匹配失败，返回了原始数据
} else {
    // 匹配成功
}
```

**建议**：升级后先在小范围测试，确认数据格式符合预期后再全面部署。

[1.0.0]: https://github.com/royfee/import/releases/tag/v1.0.0
[0.1.0]: https://github.com/royfee/import/releases/tag/v0.1.0
