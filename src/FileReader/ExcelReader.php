<?php

namespace Royfee\Import\FileReader;

use Royfee\Import\Contracts\FileReaderInterface;
use Royfee\Import\Exceptions\FileNotFoundException;
use Royfee\Import\Exceptions\FileReadException;
use avadim\FastExcelReader\AbstractBook;
use avadim\FastExcelReader\Excel;

class ExcelReader implements FileReaderInterface
{
    protected array $options = [
        'sheet'      => 0,
        'skip_empty' => true,
    ];

    protected array $extensions = ['xlsx', 'xls'];

    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->options, $options);
    }

    public function read(string $filePath): array
    {
        $data = [];
        foreach ($this->cursor($filePath) as $row) {
            $data[] = $row;
        }
        return $data;
    }

    public function cursor(string $filePath): \Generator
    {
        if (!file_exists($filePath)) {
            throw new FileNotFoundException($filePath);
        }

        return $this->doCursor($filePath);
    }

    protected function doCursor(string $filePath): \Generator
    {
        try {
            $excel = $this->openExcel($filePath);
            $sheet = $this->selectTargetSheet($excel);
            if ($sheet === null) {
                return;
            }

            yield from $this->iterateSheet($sheet);
        } catch (FileNotFoundException $e) {
            throw $e;
        } catch (FileReadException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new FileReadException($filePath, $e->getMessage());
        }
    }

    protected function iterateSheet(\avadim\FastExcelReader\AbstractSheet $sheet): \Generator
    {
        foreach ($sheet->nextRow() as $rowNum => $rowData) {
            // 跳过第一行（表头）
            if ($rowNum === 1) {
                continue;
            }

            $item  = [];
            $empty = true;

            // 直接使用原始的列名（A, B, C...）
            foreach ($rowData as $col => $val) {
                $valStr = trim((string)$val);
                if ($valStr !== '') {
                    $empty = false;
                }
                $item[$col] = $valStr;
            }

            if ($this->options['skip_empty'] && $empty) {
                continue;
            }

            yield $item;
        }
    }

    public function getSheetCount(string $filePath): int
    {
        if (!file_exists($filePath)) {
            throw new FileNotFoundException($filePath);
        }

        try {
            $excel = $this->openExcel($filePath);
            return $excel->countSheets();
        } catch (FileNotFoundException $e) {
            throw $e;
        } catch (FileReadException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new FileReadException($filePath, $e->getMessage());
        }
    }

    public function supports(string $extension): bool
    {
        return in_array(strtolower($extension), $this->extensions);
    }

    public function setOptions(array $options): self
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    public function readMultiSheet(string $filePath, int $maxSheets): array
    {
        if (!file_exists($filePath)) {
            throw new FileNotFoundException($filePath);
        }

        try {
            $excel      = $this->openExcel($filePath);
            $sheetNames = $excel->getSheetNames();
            $ids        = array_keys($sheetNames);
            $limit      = min($maxSheets, count($ids));

            if ($limit <= 0) {
                return [];
            }

            $result = [];
            for ($i = 0; $i < $limit; $i++) {
                $sheet    = $excel->selectSheetById($ids[$i]);
                $result[$i] = $this->readSheetRows($sheet);
            }
            return $result;
        } catch (FileNotFoundException $e) {
            throw $e;
        } catch (FileReadException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new FileReadException($filePath, $e->getMessage());
        }
    }

    protected function readSheetRows(\avadim\FastExcelReader\AbstractSheet $sheet): array
    {
        $data = [];
        foreach ($this->iterateSheet($sheet) as $row) {
            $data[] = $row;
        }
        return $data;
    }

    public function getHeaders(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new FileNotFoundException($filePath);
        }

        try {
            $excel = $this->openExcel($filePath);
            $sheet = $this->selectTargetSheet($excel);
            if ($sheet === null) {
                return [];
            }
            return $this->readFirstRow($sheet);
        } catch (FileNotFoundException $e) {
            throw $e;
        } catch (FileReadException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new FileReadException($filePath, $e->getMessage());
        }
    }

    public function getHeadersMultiSheet(string $filePath, int $maxSheets): array
    {
        if (!file_exists($filePath)) {
            throw new FileNotFoundException($filePath);
        }

        try {
            $excel      = $this->openExcel($filePath);
            $sheetNames = $excel->getSheetNames();
            $ids        = array_keys($sheetNames);
            $limit      = min($maxSheets, count($ids));

            $result = [];
            for ($i = 0; $i < $limit; $i++) {
                $sheet      = $excel->selectSheetById($ids[$i]);
                $result[$i] = $this->readFirstRow($sheet);
            }
            return $result;
        } catch (FileNotFoundException $e) {
            throw $e;
        } catch (FileReadException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new FileReadException($filePath, $e->getMessage());
        }
    }

    protected function readFirstRow(\avadim\FastExcelReader\AbstractSheet $sheet): array
    {
        foreach ($sheet->nextRow() as $rowNum => $rowData) {
            // 返回第一行，保持原始的列名（A, B, C...）作为值
            $result = [];
            foreach ($rowData as $col => $val) {
                $result[$col] = trim((string)$val);
            }
            return $result;
        }
        return [];
    }

    protected function openExcel(string $filePath): AbstractBook
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($ext === 'xls') {
            $excel = Excel::openXls($filePath);
        } elseif ($ext === 'xlsx') {
            $excel = Excel::openXlsx($filePath);
        } else {
            $excel = Excel::open($filePath);
        }

        if ($excel === null) {
            throw new FileReadException($filePath, 'Cannot open file');
        }

        return $excel;
    }

    protected function selectTargetSheet(AbstractBook $excel): ?\avadim\FastExcelReader\AbstractSheet
    {
        $targetIndex = $this->options['sheet'];
        $sheetNames  = $excel->getSheetNames();
        $ids         = array_keys($sheetNames);

        if (!isset($ids[$targetIndex])) {
            return null;
        }

        return $excel->selectSheetById($ids[$targetIndex]);
    }

    public static function colName(int $index): string
    {
        $name = '';
        $i    = $index;
        while ($i >= 0) {
            $name = chr(65 + ($i % 26)) . $name;
            $i    = intdiv($i, 26) - 1;
        }
        return $name;
    }
}
