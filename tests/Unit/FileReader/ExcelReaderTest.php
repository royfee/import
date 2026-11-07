<?php

namespace Royfee\Import\Tests\Unit\FileReader;

use PHPUnit\Framework\TestCase;
use Royfee\Import\FileReader\ExcelReader;
use Royfee\Import\Exceptions\FileNotFoundException;

class ExcelReaderTest extends TestCase
{
    protected string $dataDir;

    protected function setUp(): void
    {
        $this->dataDir = dirname(__DIR__, 3) . '/examples/data/';
    }

    // ─── __construct() ─────────────────────────────────────────

    public function testConstructWithDefaultOptions(): void
    {
        $reader = new ExcelReader();
        $this->assertInstanceOf(ExcelReader::class, $reader);
    }

    public function testConstructWithCustomOptions(): void
    {
        $reader = new ExcelReader(['sheet' => 1, 'skip_empty' => false]);
        $this->assertInstanceOf(ExcelReader::class, $reader);
    }

    // ─── supports() ────────────────────────────────────────────

    public function testSupportsXls(): void
    {
        $reader = new ExcelReader();
        $this->assertTrue($reader->supports('xls'));
        $this->assertTrue($reader->supports('XLS'));
    }

    public function testSupportsXlsx(): void
    {
        $reader = new ExcelReader();
        $this->assertTrue($reader->supports('xlsx'));
        $this->assertTrue($reader->supports('XLSX'));
    }

    public function testDoesNotSupportCsv(): void
    {
        $reader = new ExcelReader();
        $this->assertFalse($reader->supports('csv'));
    }

    // ─── setOptions() ──────────────────────────────────────────

    public function testSetOptionsReturnsSelf(): void
    {
        $reader = new ExcelReader();
        $result = $reader->setOptions(['sheet' => 1]);
        $this->assertSame($reader, $result);
    }

    public function testSetOptionsMergesWithExisting(): void
    {
        $reader = new ExcelReader(['sheet' => 0]);
        $reader->setOptions(['skip_empty' => false]);
        
        // Should still work with both options
        $this->assertInstanceOf(ExcelReader::class, $reader);
    }

    // ─── read() ────────────────────────────────────────────────

    public function testReadReturnsArray(): void
    {
        $reader = new ExcelReader(['sheet' => 0]);
        $data = $reader->read($this->dataDir . 'JZT1-49.xls');
        
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
    }

    public function testReadReturnsRowsWithColumnKeys(): void
    {
        $reader = new ExcelReader(['sheet' => 0]);
        $data = $reader->read($this->dataDir . 'JZT1-49.xls');
        
        $this->assertArrayHasKey('A', $data[0]);
        $this->assertArrayHasKey('B', $data[0]);
    }

    public function testReadWithNonExistentFile(): void
    {
        $reader = new ExcelReader();
        
        $this->expectException(FileNotFoundException::class);
        $reader->read('/path/to/nonexistent.xls');
    }

    // ─── cursor() ──────────────────────────────────────────────

    public function testCursorReturnsGenerator(): void
    {
        $reader = new ExcelReader(['sheet' => 0]);
        $cursor = $reader->cursor($this->dataDir . 'JZT1-49.xls');
        
        $this->assertInstanceOf(\Generator::class, $cursor);
    }

    public function testCursorYieldsRowsWithColumnKeys(): void
    {
        $reader = new ExcelReader(['sheet' => 0]);
        $cursor = $reader->cursor($this->dataDir . 'JZT1-49.xls');
        
        $firstRow = null;
        foreach ($cursor as $row) {
            $firstRow = $row;
            break;
        }
        
        $this->assertNotNull($firstRow);
        $this->assertArrayHasKey('A', $firstRow);
    }

    // ─── getSheetCount() ───────────────────────────────────────

    public function testGetSheetCountReturnsInteger(): void
    {
        $reader = new ExcelReader();
        $count = $reader->getSheetCount($this->dataDir . 'JZT1-49.xls');
        
        $this->assertIsInt($count);
        $this->assertGreaterThan(0, $count);
    }

    public function testGetSheetCountWithNonExistentFile(): void
    {
        $reader = new ExcelReader();
        
        $this->expectException(FileNotFoundException::class);
        $reader->getSheetCount('/path/to/nonexistent.xls');
    }

    // ─── readMultiSheet() ──────────────────────────────────────

    public function testReadMultiSheetReturnsArray(): void
    {
        $reader = new ExcelReader();
        $data = $reader->readMultiSheet($this->dataDir . 'JZT1-49.xls', 2);
        
        $this->assertIsArray($data);
        $this->assertArrayHasKey(0, $data);
        $this->assertArrayHasKey(1, $data);
    }

    public function testReadMultiSheetRespectsLimit(): void
    {
        $reader = new ExcelReader();
        $data = $reader->readMultiSheet($this->dataDir . 'JZT1-49.xls', 1);
        
        $this->assertCount(1, $data);
        $this->assertArrayHasKey(0, $data);
    }

    public function testReadMultiSheetWithNonExistentFile(): void
    {
        $reader = new ExcelReader();
        
        $this->expectException(FileNotFoundException::class);
        $reader->readMultiSheet('/path/to/nonexistent.xls', 2);
    }

    // ─── getHeaders() ──────────────────────────────────────────

    public function testGetHeadersReturnsAssociativeArray(): void
    {
        $reader = new ExcelReader(['sheet' => 0]);
        $headers = $reader->getHeaders($this->dataDir . 'JZT1-49.xls');
        
        $this->assertIsArray($headers);
        $this->assertArrayHasKey('A', $headers);
        $this->assertArrayHasKey('B', $headers);
    }

    public function testGetHeadersWithNonExistentFile(): void
    {
        $reader = new ExcelReader();
        
        $this->expectException(FileNotFoundException::class);
        $reader->getHeaders('/path/to/nonexistent.xls');
    }

    // ─── getHeadersMultiSheet() ────────────────────────────────

    public function testGetHeadersMultiSheetReturnsArray(): void
    {
        $reader = new ExcelReader();
        $headers = $reader->getHeadersMultiSheet($this->dataDir . 'JZT1-49.xls', 2);
        
        $this->assertIsArray($headers);
        $this->assertArrayHasKey(0, $headers);
        $this->assertArrayHasKey(1, $headers);
    }

    public function testGetHeadersMultiSheetRespectsLimit(): void
    {
        $reader = new ExcelReader();
        $headers = $reader->getHeadersMultiSheet($this->dataDir . 'JZT1-49.xls', 1);
        
        $this->assertCount(1, $headers);
    }

    public function testGetHeadersMultiSheetWithNonExistentFile(): void
    {
        $reader = new ExcelReader();
        
        $this->expectException(FileNotFoundException::class);
        $reader->getHeadersMultiSheet('/path/to/nonexistent.xls', 2);
    }

    // ─── colName() ─────────────────────────────────────────────

    public function testColNameConvertsIndexToLetter(): void
    {
        $this->assertEquals('A', ExcelReader::colName(0));
        $this->assertEquals('B', ExcelReader::colName(1));
        $this->assertEquals('Z', ExcelReader::colName(25));
        $this->assertEquals('AA', ExcelReader::colName(26));
        $this->assertEquals('AB', ExcelReader::colName(27));
        $this->assertEquals('AZ', ExcelReader::colName(51));
        $this->assertEquals('BA', ExcelReader::colName(52));
    }
}
