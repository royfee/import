<?php

namespace Royfee\Import\FileReader;

use Royfee\Import\Contracts\FileReaderInterface;
use Royfee\Import\Exceptions\FileNotFoundException;

class CsvReader implements FileReaderInterface
{
    protected array $options = [
        'delimiter'      => ',',
        'enclosure'      => '"',
        'encoding'       => 'UTF-8',
        'skip_empty'     => true,
        'skip_bom'       => true,
        'auto_delimiter' => true,
    ];

    protected array $extensions = ['csv', 'txt', 'tsv'];

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
        $prev = ini_set('auto_detect_line_endings', '1');

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("无法打开文件: {$filePath}");
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                return;
            }

            if ($this->options['skip_bom']) {
                $firstLine = preg_replace('/^\x{FEFF}/u', '', $firstLine);
                $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
            }

            $firstLine = $this->convertEncoding($firstLine);

            if ($this->options['auto_delimiter']) {
                $this->options['delimiter'] = $this->detectDelimiter($firstLine);
            }

            $colCount = count($this->parseLine($firstLine));

            while (($line = fgets($handle)) !== false) {
                $line = rtrim($line, "\r\n");
                if ($this->options['skip_empty'] && trim($line) === '') {
                    continue;
                }

                $row = $this->parseLine($line);
                if (count($row) < $colCount) {
                    $row = array_pad($row, $colCount, '');
                } elseif (count($row) > $colCount) {
                    $row = array_slice($row, 0, $colCount);
                }

                $item  = [];
                $empty = true;
                for ($i = 0; $i < $colCount; $i++) {
                    $val = trim($row[$i] ?? '');
                    if ($val !== '') {
                        $empty = false;
                    }
                    $item[ExcelReader::colName($i)] = $val;
                }

                if ($this->options['skip_empty'] && $empty) {
                    continue;
                }

                yield $item;
            }
        } finally {
            fclose($handle);
            ini_set('auto_detect_line_endings', $prev);
        }
    }

    public function getSheetCount(string $filePath): int
    {
        return 1;
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
        return [0 => $this->read($filePath)];
    }

    public function getHeaders(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new FileNotFoundException($filePath);
        }

        $prev = ini_set('auto_detect_line_endings', '1');
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("无法打开文件: {$filePath}");
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                return [];
            }

            if ($this->options['skip_bom']) {
                $firstLine = preg_replace('/^\x{FEFF}/u', '', $firstLine);
                $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
            }

            $firstLine = $this->convertEncoding($firstLine);

            if ($this->options['auto_delimiter']) {
                $this->options['delimiter'] = $this->detectDelimiter($firstLine);
            }

            $headers = array_map('trim', $this->parseLine($firstLine));
            
            // 返回关联数组，键为列名（A, B, C...）
            $result = [];
            foreach ($headers as $i => $header) {
                $result[ExcelReader::colName($i)] = $header;
            }
            return $result;
        } finally {
            fclose($handle);
            ini_set('auto_detect_line_endings', $prev);
        }
    }

    public function getHeadersMultiSheet(string $filePath, int $maxSheets): array
    {
        return [0 => $this->getHeaders($filePath)];
    }

    protected function parseLine(string $line): array
    {
        $d = $this->options['delimiter'];
        $e = $this->options['enclosure'];

        if (strpos($line, $e) === false) {
            return explode($d, $line);
        }

        $fields = [];
        $field  = '';
        $in     = false;
        $len    = strlen($line);

        for ($i = 0; $i < $len; $i++) {
            $c = $line[$i];
            $n = ($i + 1 < $len) ? $line[$i + 1] : '';

            if ($in) {
                if ($c === $e) {
                    if ($n === $e) { $field .= $e; $i++; }
                    else { $in = false; }
                } else {
                    $field .= $c;
                }
            } else {
                if ($c === $e)      { $in = true; }
                elseif ($c === $d)  { $fields[] = $field; $field = ''; }
                else                { $field .= $c; }
            }
        }
        $fields[] = $field;

        return $fields;
    }

    protected function detectDelimiter(string $line): string
    {
        $best = ',';
        $max  = 0;
        foreach ([',', ';', "\t", '|'] as $delim) {
            $c = substr_count($line, $delim);
            if ($c > $max) { $max = $c; $best = $delim; }
        }
        return $best;
    }

    protected function convertEncoding(string $str): string
    {
        $target = $this->options['encoding'];
        if (mb_check_encoding($str, $target)) {
            return $str;
        }
        $from = mb_detect_encoding($str, ['UTF-8', 'GBK', 'GB2312', 'GB18030', 'ISO-8859-1'], true) ?: 'ISO-8859-1';
        return ($from !== $target) ? mb_convert_encoding($str, $target, $from) : $str;
    }
}
