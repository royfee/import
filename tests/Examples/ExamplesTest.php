<?php

namespace Royfee\Import\Tests\Examples;

use PHPUnit\Framework\TestCase;

/**
 * 验证 examples/ 下所有示例脚本能正确运行
 */
class ExamplesTest extends TestCase
{
    protected string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = dirname(__DIR__, 2);
    }

    protected function runExample(string $filename): string
    {
        $path   = $this->rootDir . '/examples/' . $filename;
        $cmd    = 'php ' . escapeshellarg($path) . ' 2>&1';
        $output = shell_exec($cmd);
        $this->assertNotNull($output, "Example {$filename} returned null");
        return $output;
    }

    // ─── 01_basic_read ───────────────────────────────────────

    public function testBasicRead(): void
    {
        $output = $this->runExample('01_basic_read.php');

        $this->assertStringContainsString('读取 CSV 文件', $output);
        $this->assertStringContainsString('共 5 条记录', $output);
        $this->assertStringContainsString('ORD2024001', $output);
        $this->assertStringContainsString('张三', $output);

        $this->assertStringContainsString('读取 TXT 文件', $output);
        $this->assertStringContainsString('共 4 条记录', $output);
        $this->assertStringContainsString('admin001', $output);

        $this->assertStringContainsString('读取商品 CSV', $output);
        $this->assertStringContainsString('SKU001 - iPhone 15 Pro', $output);
        $this->assertStringContainsString('SKU004 - iPad Air', $output);
    }

    // ─── 02_template_match ───────────────────────────────────

    public function testTemplateMatch(): void
    {
        $output = $this->runExample('02_template_match.php');

        // 单模板
        $this->assertStringContainsString('单模板匹配', $output);
        $this->assertStringContainsString('order_no, recipient, phone, amount', $output);
        $this->assertStringContainsString('ORD2024001 | 张三 | ¥199.00', $output);

        // 模糊匹配
        $this->assertStringContainsString('模糊匹配', $output);
        $this->assertStringContainsString('"sku":"SKU001"', $output);
        $this->assertStringContainsString('"name":"iPhone 15 Pro"', $output);

        // 多模板选最佳
        $this->assertStringContainsString('多模板选最佳', $output);
        $this->assertStringContainsString('order_no, name, amount', $output);
    }

    // ─── 03_multi_sheet ──────────────────────────────────────

    public function testMultiSheet(): void
    {
        $output = $this->runExample('03_multi_sheet.php');

        // 单 sheet
        $this->assertStringContainsString('单 Sheet 读取', $output);
        $this->assertStringContainsString('行数：2', $output);

        // 多 sheet
        $this->assertStringContainsString('多 Sheet 读取', $output);
        $this->assertStringContainsString('Sheet 数量：2', $output);
        $this->assertStringContainsString('Sheet 0 行数：2', $output);
        $this->assertStringContainsString('Sheet 1 行数：2', $output);

        // 多 sheet 多模板
        $this->assertStringContainsString('多 Sheet 多模板匹配', $output);
        $this->assertStringContainsString('"order_no":"ORD001"', $output);
        $this->assertStringContainsString('"sku":"SKU001"', $output);

        // 多 sheet 单模板
        $this->assertStringContainsString('多 Sheet 单模板', $output);
        $this->assertStringContainsString('"A":"SKU001"', $output);
    }

    // ─── 04_streaming ────────────────────────────────────────

    public function testStreaming(): void
    {
        $output = $this->runExample('04_streaming.php');

        // cursor
        $this->assertStringContainsString('cursor() 流式读取', $output);
        $this->assertStringContainsString('第1行：ORD2024001 - 张三', $output);
        $this->assertStringContainsString('第5行：ORD2024005 - 孙七', $output);
        $this->assertStringContainsString('共处理 5 行', $output);

        // 手动聚合
        $this->assertStringContainsString('手动聚合', $output);
        $this->assertStringContainsString('总订单数：5', $output);
        $this->assertStringContainsString('总金额：¥2,632.30', $output);

        // 手动分批
        $this->assertStringContainsString('手动分批', $output);
        $this->assertStringContainsString('批次 0：2 条', $output);
        $this->assertStringContainsString('批次 2：1 条', $output);
        $this->assertStringContainsString('共 3 个批次', $output);
    }

    // ─── 05_chunk_data ───────────────────────────────────────

    public function testChunkData(): void
    {
        $output = $this->runExample('05_chunk_data.php');

        // matchData + 手动分批
        $this->assertStringContainsString('手动分批', $output);
        $this->assertStringContainsString('批次 0（2 条）', $output);
        $this->assertStringContainsString('ORD2024001 | 张三 | ¥199.00 | pending', $output);
        $this->assertStringContainsString('批次 2（1 条）', $output);
        $this->assertStringContainsString('ORD2024005 | 孙七 | ¥456.80 | shipped', $output);
        $this->assertStringContainsString('共 3 个批次', $output);

        // 模拟入库
        $this->assertStringContainsString('模拟批量入库', $output);
        $this->assertStringContainsString('批次 0：插入 3 条（累计 3）', $output);
        $this->assertStringContainsString('批次 1：插入 2 条（累计 5）', $output);
        $this->assertStringContainsString('入库完成，共 5 条', $output);
    }

    // ─── 06_custom_reader ────────────────────────────────────

    public function testCustomReader(): void
    {
        $output = $this->runExample('06_custom_reader.php');

        $this->assertStringContainsString('自定义 JSON Reader', $output);
        $this->assertStringContainsString('JSON 记录数：3', $output);
        $this->assertStringContainsString('Alice - 30岁', $output);
        $this->assertStringContainsString('Bob - 25岁', $output);
        $this->assertStringContainsString('Charlie - 35岁', $output);

        $this->assertStringContainsString('自定义 Reader + 模板匹配', $output);
        $this->assertStringContainsString('"user_name":"Alice"', $output);
        $this->assertStringContainsString('"user_age":"30"', $output);
    }

    // ─── 07_error_handling ───────────────────────────────────

    public function testErrorHandling(): void
    {
        $output = $this->runExample('07_error_handling.php');

        // 文件不存在
        $this->assertStringContainsString('文件不存在', $output);
        $this->assertStringContainsString('捕获异常：文件不存在: /path/to/nonexistent.csv', $output);

        // 不支持的格式
        $this->assertStringContainsString('不支持的格式', $output);
        $this->assertStringContainsString('不支持的文件格式: .xml', $output);

        // 模板匹配失败
        $this->assertStringContainsString('模板匹配失败', $output);
        $this->assertStringContainsString('无法匹配模板', $output);
        $this->assertStringContainsString('订单号', $output);

        // 安全处理
        $this->assertStringContainsString('安全处理示例', $output);
        $this->assertStringContainsString('[错误] 文件不存在', $output);
        $this->assertStringContainsString('成功返回 5 条', $output);
    }
}
