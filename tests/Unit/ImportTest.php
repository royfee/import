<?php

namespace Royfee\Import\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Royfee\Import\Import;
use Royfee\Import\Exceptions\FileNotFoundException;
use Royfee\Import\Exceptions\UnsupportedFormatException;
use Royfee\Import\FileReader\CsvReader;
use Royfee\Import\FileReader\ExcelReader;

class ImportTest extends TestCase
{
    protected string $dataDir;

    protected function setUp(): void
    {
        $this->dataDir = dirname(__DIR__, 2) . '/examples/data/';
    }

    // ─── __construct() ─────────────────────────────────────────

    public function testConstructWithValidCsvFile(): void
    {
        $import = new Import($this->dataDir . 'orders.csv');
        $this->assertInstanceOf(Import::class, $import);
    }

    public function testConstructWithValidXlsFile(): void
    {
        $import = new Import($this->dataDir . 'JZT1-49.xls');
        $this->assertInstanceOf(Import::class, $import);
    }

    public function testConstructWithNonExistentFile(): void
    {
        $this->expectException(FileNotFoundException::class);
        new Import('/path/to/nonexistent.csv');
    }

    public function testConstructWithUnsupportedFormat(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.xml';
        file_put_contents($tempFile, '<root/>');
        
        try {
            $this->expectException(UnsupportedFormatException::class);
            new Import($tempFile);
        } finally {
            unlink($tempFile);
        }
    }

    public function testConstructWithOptions(): void
    {
        $import = new Import($this->dataDir . 'orders.csv', [
            'sheet' => 1,
            'skip_empty' => false,
        ]);
        $this->assertInstanceOf(Import::class, $import);
    }

    // ─── getData() ─────────────────────────────────────────────

    public function testGetDataReturnsArrayWithColumnKeys(): void
    {
        $import = new Import($this->dataDir . 'orders.csv');
        $data = $import->getData();
        
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('A', $data[0]);
        $this->assertArrayHasKey('B', $data[0]);
    }

    public function testGetDataCachesResult(): void
    {
        $import = new Import($this->dataDir . 'orders.csv');
        $data1 = $import->getData();
        $data2 = $import->getData();
        
        $this->assertSame($data1, $data2);
    }

    public function testGetDataWithMultiSheet(): void
    {
        $import = new Import($this->dataDir . 'JZT1-49.xls', ['sheet' => 2]);
        $data = $import->getData();
        
        $this->assertIsArray($data);
        $this->assertArrayHasKey(0, $data);
        $this->assertArrayHasKey(1, $data);
    }

    // ─── matchData() ───────────────────────────────────────────

    public function testMatchDataWithSingleTemplate(): void
    {
        $import = new Import($this->dataDir . 'orders.csv');
        $data = $import->matchData([
            ['订单号' => 'order_no', '收件人' => 'recipient']
        ]);
        
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('order_no', $data[0]);
        $this->assertArrayHasKey('recipient', $data[0]);
    }

    public function testMatchDataWithMultiTemplate(): void
    {
        $import = new Import($this->dataDir . 'JZT1-49.xls', ['sheet' => 2]);
        $data = $import->matchData([
            0 => [['关联单号' => 'order_no']],
            1 => [['关联单号' => 'order_no', '物品名称' => 'name']]
        ]);
        
        $this->assertIsArray($data);
        $this->assertArrayHasKey(0, $data);
        $this->assertArrayHasKey(1, $data);
    }

    public function testMatchDataReturnsRawDataWhenNoMatch(): void
    {
        $import = new Import($this->dataDir . 'orders.csv');
        $data = $import->matchData([
            ['完全不相关的字段' => 'target']
        ]);
        
        // Should return raw data with A/B/C keys instead of throwing exception
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('A', $data[0]);
    }

    public function testMatchDataWithFuzzyMatching(): void
    {
        $import = new Import($this->dataDir . 'products.csv');
        $data = $import->matchData([
            ['商品 编码' => 'sku', '商品 名称' => 'name']
        ]);
        
        $this->assertArrayHasKey('sku', $data[0]);
        $this->assertArrayHasKey('name', $data[0]);
        $this->assertNotEmpty($data[0]['sku']);
        $this->assertNotEmpty($data[0]['name']);
    }

    public function testMatchDataPreventsDuplicateMapping(): void
    {
        $import = new Import($this->dataDir . 'JZT1-49.xls', ['sheet' => 2]);
        $data = $import->matchData([
            1 => [['物品名称' => 'name']]
        ]);
        
        // Should only map C (物品名称) to name, not D (物品简称) or M (物品英文名称)
        $this->assertArrayHasKey('name', $data[1][0]);
        $this->assertNotEmpty($data[1][0]['name']);
    }

    // ─── addReader() ───────────────────────────────────────────

    public function testAddReaderReturnsSelf(): void
    {
        $import = new Import($this->dataDir . 'orders.csv');
        $reader = new CsvReader();
        
        $result = $import->addReader($reader);
        $this->assertSame($import, $result);
    }

    public function testAddReaderPrioritizesCustomReader(): void
    {
        $import = new Import($this->dataDir . 'orders.csv');
        
        $customReader = new class extends CsvReader {
            public bool $wasUsed = false;
            
            public function read(string $filePath): array
            {
                $this->wasUsed = true;
                return parent::read($filePath);
            }
        };
        
        $import->addReader($customReader);
        $import->getData();
        
        $this->assertTrue($customReader->wasUsed);
    }

    // ─── cursor() ──────────────────────────────────────────────

    public function testCursorReturnsGenerator(): void
    {
        $import = new Import($this->dataDir . 'orders.csv');
        $cursor = $import->cursor();
        
        $this->assertInstanceOf(\Generator::class, $cursor);
    }

    public function testCursorYieldsRowsWithColumnKeys(): void
    {
        $import = new Import($this->dataDir . 'orders.csv');
        $firstRow = null;
        
        foreach ($import->cursor() as $row) {
            $firstRow = $row;
            break;
        }
        
        $this->assertNotNull($firstRow);
        $this->assertArrayHasKey('A', $firstRow);
        $this->assertArrayHasKey('B', $firstRow);
    }

    public function testCursorWithMultiSheet(): void
    {
        $import = new Import($this->dataDir . 'JZT1-49.xls', ['sheet' => 2]);
        $cursor = $import->cursor();
        
        $sheets = [];
        foreach ($cursor as $item) {
            $sheetIndex = array_key_first($item);
            if (!isset($sheets[$sheetIndex])) {
                $sheets[$sheetIndex] = 0;
            }
            $sheets[$sheetIndex]++;
        }
        
        $this->assertArrayHasKey(0, $sheets);
        $this->assertArrayHasKey(1, $sheets);
    }
}
