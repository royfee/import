<?php

namespace Royfee\Import\Tests\Unit\FileReader;

use PHPUnit\Framework\TestCase;
use Royfee\Import\FileReader\CsvReader;
use Royfee\Import\Exceptions\FileNotFoundException;

class CsvReaderTest extends TestCase
{
    protected string $dataDir;

    protected function setUp(): void
    {
        $this->dataDir = dirname(__DIR__, 3) . '/examples/data/';
    }

    // ─── __construct() ─────────────────────────────────────────

    public function testConstructWithDefaultOptions(): void
    {
        $reader = new CsvReader();
        $this->assertInstanceOf(CsvReader::class, $reader);
    }

    public function testConstructWithCustomOptions(): void
    {
        $reader = new CsvReader([
            'delimiter' => ',',
            'enclosure' => '"',
            'skip_empty' => false,
        ]);
        $this->assertInstanceOf(CsvReader::class, $reader);
    }

    // ─── supports() ────────────────────────────────────────────

    public function testSupportsCsv(): void
    {
        $reader = new CsvReader();
        $this->assertTrue($reader->supports('csv'));
        $this->assertTrue($reader->supports('CSV'));
    }

    public function testSupportsTxt(): void
    {
        $reader = new CsvReader();
        $this->assertTrue($reader->supports('txt'));
        $this->assertTrue($reader->supports('TXT'));
    }

    public function testDoesNotSupportXls(): void
    {
        $reader = new CsvReader();
        $this->assertFalse($reader->supports('xls'));
    }

    // ─── setOptions() ──────────────────────────────────────────

    public function testSetOptionsReturnsSelf(): void
    {
        $reader = new CsvReader();
        $result = $reader->setOptions(['delimiter' => ';']);
        $this->assertSame($reader, $result);
    }

    // ─── read() ────────────────────────────────────────────────

    public function testReadReturnsArray(): void
    {
        $reader = new CsvReader();
        $data = $reader->read($this->dataDir . 'orders.csv');
        
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
    }

    public function testReadReturnsRowsWithColumnKeys(): void
    {
        $reader = new CsvReader();
        $data = $reader->read($this->dataDir . 'orders.csv');
        
        $this->assertArrayHasKey('A', $data[0]);
        $this->assertArrayHasKey('B', $data[0]);
    }

    public function testReadWithNonExistentFile(): void
    {
        $reader = new CsvReader();
        
        $this->expectException(FileNotFoundException::class);
        $reader->read('/path/to/nonexistent.csv');
    }

    public function testReadWithCustomDelimiter(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.txt';
        file_put_contents($tempFile, "A\tB\tC\n1\t2\t3\n");
        
        try {
            $reader = new CsvReader(['delimiter' => "\t", 'auto_delimiter' => false]);
            $data = $reader->read($tempFile);
            
            $this->assertCount(1, $data);
            $this->assertEquals('1', $data[0]['A']);
            $this->assertEquals('2', $data[0]['B']);
            $this->assertEquals('3', $data[0]['C']);
        } finally {
            unlink($tempFile);
        }
    }

    // ─── cursor() ──────────────────────────────────────────────

    public function testCursorReturnsGenerator(): void
    {
        $reader = new CsvReader();
        $cursor = $reader->cursor($this->dataDir . 'orders.csv');
        
        $this->assertInstanceOf(\Generator::class, $cursor);
    }

    public function testCursorYieldsRowsWithColumnKeys(): void
    {
        $reader = new CsvReader();
        $cursor = $reader->cursor($this->dataDir . 'orders.csv');
        
        $firstRow = null;
        foreach ($cursor as $row) {
            $firstRow = $row;
            break;
        }
        
        $this->assertNotNull($firstRow);
        $this->assertArrayHasKey('A', $firstRow);
    }

    // ─── getSheetCount() ───────────────────────────────────────

    public function testGetSheetCountReturnsOne(): void
    {
        $reader = new CsvReader();
        $count = $reader->getSheetCount($this->dataDir . 'orders.csv');
        
        $this->assertEquals(1, $count);
    }

    // ─── readMultiSheet() ──────────────────────────────────────

    public function testReadMultiSheetReturnsSingleSheet(): void
    {
        $reader = new CsvReader();
        $data = $reader->readMultiSheet($this->dataDir . 'orders.csv', 2);
        
        $this->assertIsArray($data);
        $this->assertArrayHasKey(0, $data);
        $this->assertCount(1, $data);
    }

    // ─── getHeaders() ──────────────────────────────────────────

    public function testGetHeadersReturnsAssociativeArray(): void
    {
        $reader = new CsvReader();
        $headers = $reader->getHeaders($this->dataDir . 'orders.csv');
        
        $this->assertIsArray($headers);
        $this->assertArrayHasKey('A', $headers);
        $this->assertArrayHasKey('B', $headers);
        $this->assertEquals('订单号', $headers['A']);
        $this->assertEquals('收件人', $headers['B']);
    }

    public function testGetHeadersWithNonExistentFile(): void
    {
        $reader = new CsvReader();
        
        $this->expectException(FileNotFoundException::class);
        $reader->getHeaders('/path/to/nonexistent.csv');
    }

    // ─── getHeadersMultiSheet() ────────────────────────────────

    public function testGetHeadersMultiSheetReturnsSingleSheet(): void
    {
        $reader = new CsvReader();
        $headers = $reader->getHeadersMultiSheet($this->dataDir . 'orders.csv', 2);
        
        $this->assertIsArray($headers);
        $this->assertArrayHasKey(0, $headers);
        $this->assertCount(1, $headers);
    }

    // ─── BOM handling ──────────────────────────────────────────

    public function testReadWithBom(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.csv';
        file_put_contents($tempFile, "\xEF\xBB\xBFA,B,C\n1,2,3\n");
        
        try {
            $reader = new CsvReader(['skip_bom' => true]);
            $data = $reader->read($tempFile);
            
            $this->assertCount(1, $data);
            $this->assertEquals('1', $data[0]['A']);
        } finally {
            unlink($tempFile);
        }
    }

    // ─── Empty row handling ────────────────────────────────────

    public function testReadSkipsEmptyRows(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.csv';
        file_put_contents($tempFile, "A,B,C\n1,2,3\n\n\n4,5,6\n");
        
        try {
            $reader = new CsvReader(['skip_empty' => true]);
            $data = $reader->read($tempFile);
            
            $this->assertCount(2, $data);
        } finally {
            unlink($tempFile);
        }
    }

    public function testReadIncludesEmptyRowsWhenDisabled(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.csv';
        file_put_contents($tempFile, "A,B,C\n1,2,3\n\n4,5,6\n");
        
        try {
            $reader = new CsvReader(['skip_empty' => false]);
            $data = $reader->read($tempFile);
            
            $this->assertCount(3, $data);
        } finally {
            unlink($tempFile);
        }
    }
}
