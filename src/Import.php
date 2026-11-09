<?php

namespace Royfee\Import;

use Royfee\Import\Contracts\FileReaderInterface;
use Royfee\Import\Exceptions\FileNotFoundException;
use Royfee\Import\Exceptions\TemplateMatchException;
use Royfee\Import\Exceptions\UnsupportedFormatException;
use Royfee\Import\FileReader\CsvReader;
use Royfee\Import\FileReader\ExcelReader;

class Import
{
    protected string $filePath;
    protected string $extension;
    protected array $options;
    protected ?FileReaderInterface $reader = null;

    protected array $readers = [];

    protected $cachedData = null;

    public function __construct(string $filePath, array $options = [])
    {
        if (!file_exists($filePath)) {
            throw new FileNotFoundException($filePath);
        }

        $this->filePath  = $filePath;
        $this->extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $this->options   = array_merge(['sheet' => 1], $options);
    }

    protected function getReader(): FileReaderInterface
    {
        if ($this->reader === null) {
            $this->reader = $this->resolveReader();
        }
        return $this->reader;
    }

    // ─── 公开 API ────────────────────────────────────────────

    /**
     * 获取原始数据（列名用 A, B, C...）
     *
     * sheet=1（默认）: [['A' => 'val', 'B' => 'val', ...], ...]
     * sheet>1:         [0 => [...], 1 => [...]]
     */
    public function getData(): array
    {
        if ($this->cachedData !== null) {
            return $this->cachedData;
        }

        $wantSheets = $this->options['sheet'];

        if ($wantSheets <= 1) {
            $this->getReader()->setOptions(['sheet' => 0]);
            $this->cachedData = $this->getReader()->read($this->filePath);
            return $this->cachedData;
        }

        $multi = $this->getReader()->readMultiSheet($this->filePath, $wantSheets);

        if (count($multi) <= 1) {
            $this->cachedData = $multi[0] ?? [];
        } else {
            $this->cachedData = $multi;
        }

        return $this->cachedData;
    }

    /**
     * 根据模板匹配并返回映射后的数据（key 为目标字段名）
     *
     * 单模板: 匹配 sheet 0，返回扁平行数组
     * 多模板: [int => 模板数组] 按 sheet 索引分配
     *
     * merge 参数（可选）: 在多 sheet 匹配时直接将子 sheet 数据嵌套到父 sheet 中
     *   - key:    关联字段名（映射后的目标字段名，如 'order_no'）
     *   - target: 嵌套字段名（如 'items'）
     *   - parent: 父 sheet 索引（默认 0）
     *   - child:  子 sheet 索引（默认 1）
     *   启用后仅遍历，直接引用索引直接接合并
     */
    public function matchData(array $templates, ?array $merge = null): array
    {
        $wantSheets = $this->options['sheet'];
        $isMultiTpl = $this->isMultiTemplate($templates);

        if ($wantSheets > 1) {
            $allHeaders = $this->getReader()->getHeadersMultiSheet($this->filePath, $wantSheets);
            $data       = $this->getData();
            $isMulti    = $this->isMultiSheetData($data);

            if ($isMulti) {
                return $this->matchMultiSheet($data, $allHeaders, $templates, $isMultiTpl, $merge);
            }

            $headers = $allHeaders[0] ?? [];
            if ($isMultiTpl) {
                return $this->matchSingleSheet($data, $headers, $templates[0] ?? []);
            }
            return $this->matchSingleSheet($data, $headers, $templates);
        }

        $this->getReader()->setOptions(['sheet' => 0]);
        $headers = $this->getReader()->getHeaders($this->filePath);
        $data    = $this->getData();

        if ($isMultiTpl) {
            return $this->matchSingleSheet($data, $headers, $templates[0] ?? []);
        }

        return $this->matchSingleSheet($data, $headers, $templates);
    }

    public function addReader(FileReaderInterface $reader): self
    {
        array_unshift($this->readers, $reader);
        $this->reader = null;
        $this->cachedData = null;
        return $this;
    }

    // ─── 流式读取 ─────────────────────────────────────────────

    /**
     * 流式读取，返回 Generator（列名用 A, B, C...）
     */
    public function cursor(): \Generator
    {
        $wantSheets = $this->options['sheet'];

        if ($wantSheets <= 1) {
            $this->getReader()->setOptions(['sheet' => 0]);
            return $this->getReader()->cursor($this->filePath);
        }

        return $this->doCursorMultiSheetLazy($wantSheets);
    }

    protected function doCursorMultiSheetLazy(int $wantSheets): \Generator
    {
        $sheetCount = $this->getReader()->getSheetCount($this->filePath);
        $limit = min($wantSheets, $sheetCount);

        if ($limit <= 1) {
            $this->getReader()->setOptions(['sheet' => 0]);
            yield from $this->getReader()->cursor($this->filePath);
            return;
        }

        for ($i = 0; $i < $limit; $i++) {
            $this->getReader()->setOptions(['sheet' => $i]);
            foreach ($this->getReader()->cursor($this->filePath) as $row) {
                yield [$i => $row];
            }
        }
    }

    // ─── 多 sheet 匹配 ───────────────────────────────────────

    protected function matchMultiSheet(array $data, array $allHeaders, array $templates, bool $isMultiTpl, ?array $merge = null): array
    {
        $result    = [];
        $mergeUsed = false;

        // 启用 merge 时，真正的单次遍历：
        // 1. 处理父 sheet 时建立索引
        // 2. 处理子 sheet 时，每映射一行就直接 merge 到父记录
        if ($merge !== null) {
            $parentIdx = $merge['parent'] ?? 0;
            $childIdx  = $merge['child']  ?? 1;
            $key       = $merge['key']    ?? 'order_no';
            $target    = $merge['target'] ?? 'items';

            if (isset($data[$parentIdx], $data[$childIdx])) {
                $parentHeaders = $allHeaders[$parentIdx] ?? [];
                $childHeaders  = $allHeaders[$childIdx]  ?? [];

                $parentTpl = $isMultiTpl ? ($templates[$parentIdx] ?? []) : $templates;
                $childTpl  = $templates[$childIdx] ?? [];

                // 获取列映射（只算一次）
                $parentColMapping = $this->getColMapping($parentHeaders, $parentTpl);
                $childColMapping  = $this->getColMapping($childHeaders, $childTpl);

                // 第一遍：处理父 sheet，映射 + 建索引
                $parentMapped = [];
                $keyIndex = [];  // key => 父记录在数组中的索引
                foreach ($data[$parentIdx] as $row) {
                    $mapped = $this->mapRow($row, $parentColMapping);
                    $mapped[$target] = [];
                    $kv = $mapped[$key] ?? '';
                    if ($kv !== '') {
                        $keyIndex[$kv] = count($parentMapped);
                    }
                    $parentMapped[] = $mapped;
                }

                // 第二遍：处理子 sheet，映射一行 merge 一行（无中间数组）
                foreach ($data[$childIdx] as $row) {
                    $mapped = $this->mapRow($row, $childColMapping);
                    $kv = $mapped[$key] ?? '';
                    if ($kv !== '' && isset($keyIndex[$kv])) {
                        $parentMapped[$keyIndex[$kv]][$target][] = $mapped;
                    }
                }

                $result[$parentIdx] = $parentMapped;
                $mergeUsed = true;
            }
        }

        // 处理其余 sheet
        foreach ($data as $sheetIndex => $sheetData) {
            if ($mergeUsed && $sheetIndex === ($parentIdx ?? null)) {
                continue;  // 父 sheet 已处理
            }
            if ($mergeUsed && $sheetIndex === ($childIdx ?? null)) {
                continue;  // 子 sheet 已 merge，跳过
            }

            $headers = $allHeaders[$sheetIndex] ?? [];

            if ($isMultiTpl && isset($templates[$sheetIndex])) {
                $tpl = $templates[$sheetIndex];
            } elseif (!$isMultiTpl && $sheetIndex === 0) {
                $tpl = $templates;
            } else {
                $result[$sheetIndex] = $sheetData;
                continue;
            }

            $result[$sheetIndex] = $this->matchSingleSheet($sheetData, $headers, $tpl);
        }

        ksort($result);

        // merge 模式下只返回父 sheet 数据（已包含嵌套子记录）
        if ($mergeUsed) {
            return $result[$parentIdx] ?? [];
        }

        return $result;
    }

    protected function matchSingleSheet(array $data, array $headers, array $templates): array
    {
        if (empty($data) || empty($templates)) {
            return $data;
        }

        $matched = $this->matchTemplate($headers, $templates);

        if ($matched === null) {
            return $data;
        }

        $colMapping = $this->buildColMapping($headers, $matched);
        return $this->mapData($data, $colMapping);
    }

    /**
     * 获取列映射（不处理数据），用于逐行处理场景
     */
    protected function getColMapping(array $headers, array $templates): array
    {
        if (empty($headers) || empty($templates)) {
            return [];
        }

        $matched = $this->matchTemplate($headers, $templates);
        if ($matched === null) {
            return [];
        }

        return $this->buildColMapping($headers, $matched);
    }

    /**
     * 映射单行数据
     */
    protected function mapRow(array $row, array $colMapping): array
    {
        $mapped = [];
        foreach ($colMapping as $col => $target) {
            $mapped[$target] = $row[$col] ?? '';
        }
        return $mapped;
    }

    // ─── 格式判断 ────────────────────────────────────────────

    protected function isMultiSheetData(array $data): bool
    {
        if (empty($data)) {
            return false;
        }
        $firstKey = array_key_first($data);
        if (!is_int($firstKey) || !is_array($data[$firstKey])) {
            return false;
        }
        $inner = $data[$firstKey];
        if (empty($inner)) {
            return false;
        }
        return is_array($inner[array_key_first($inner)]);
    }

    protected function isMultiTemplate(array $templates): bool
    {
        if (empty($templates)) {
            return false;
        }
        $firstKey = array_key_first($templates);
        if (!is_int($firstKey) || !is_array($templates[$firstKey])) {
            return false;
        }
        $inner = $templates[$firstKey];
        if (empty($inner)) {
            return false;
        }
        return is_array($inner[array_key_first($inner)]);
    }

    // ─── 读取器管理 ──────────────────────────────────────────

    protected function resolveReader(): FileReaderInterface
    {
        if (empty($this->readers)) {
            $this->registerDefaultReaders();
        }

        foreach ($this->readers as $reader) {
            if ($reader->supports($this->extension)) {
                return $reader;
            }
        }

        throw new UnsupportedFormatException($this->extension);
    }

    protected function registerDefaultReaders(): void
    {
        $this->readers = [
            new CsvReader(),
            new ExcelReader(),
        ];
    }

    // ─── 模板匹配逻辑 ────────────────────────────────────────

    protected function matchTemplate(array $headers, array $templates): ?array
    {
        $normalized = array_map([$this, 'normalize'], $headers);
        $best       = null;
        $bestScore  = 0.0;
        $threshold  = $this->options['match_threshold'] ?? 0.3;

        foreach ($templates as $tpl) {
            $srcKeys = array_map([$this, 'normalize'], array_keys($tpl));
            $matched = 0;

            foreach ($normalized as $h) {
                foreach ($srcKeys as $sk) {
                    if ($this->isMatch($h, $sk)) {
                        $matched++;
                        break;
                    }
                }
            }

            $score = count($srcKeys) > 0 ? $matched / count($srcKeys) : 0;

            if ($score > $bestScore) {
                $bestScore = $score;
                $best      = $tpl;
            }
        }

        return ($bestScore >= $threshold) ? $best : null;
    }

    /**
     * 构建列映射: [colName => targetField]
     *
     * 例: headers=['A'=>'订单号','B'=>'收件人'], template=['订单号'=>'order_no']
     * 返回: ['A' => 'order_no']
     */
    protected function buildColMapping(array $headers, array $template): array
    {
        $mapping   = [];
        $tplLookup = [];
        $usedTargets = []; // 记录已映射的目标字段，避免重复映射

        foreach ($template as $src => $target) {
            $tplLookup[$this->normalize($src)] = $target;
        }

        // headers 现在是 ['A' => '订单号', 'B' => '收件人', ...] 格式
        foreach ($headers as $col => $header) {
            $norm = $this->normalize($header);
            foreach ($tplLookup as $tplNorm => $target) {
                // 如果目标字段已被映射，跳过
                if (isset($usedTargets[$target])) {
                    continue;
                }
                
                if ($this->isMatch($norm, $tplNorm)) {
                    $mapping[$col] = $target;
                    $usedTargets[$target] = true;
                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * 根据 [colName => target] 映射转换数据
     */
    protected function mapData(array $rawData, array $colMapping): array
    {
        $result = [];
        foreach ($rawData as $row) {
            $mapped = [];
            foreach ($colMapping as $col => $target) {
                $mapped[$target] = $row[$col] ?? '';
            }
            $result[] = $mapped;
        }
        
        // 如果启用了数据清洗，修复列偏移问题
        if ($this->options['clean_data'] ?? false) {
            $result = $this->cleanData($result, $rawData, $colMapping);
        }
        
        return $result;
    }

    /**
     * 清洗数据：修复列偏移问题
     *
     * 当映射后的字段为空时，检查相邻列是否有符合模式的值
     */
    protected function cleanData(array $mappedData, array $rawData, array $colMapping): array
    {
        // 反向映射：target => colName
        $reverseMapping = array_flip($colMapping);
        
        foreach ($mappedData as $idx => $row) {
            $rawRow = $rawData[$idx] ?? [];
            
            // 检查 hscode 字段
            if (isset($reverseMapping['hscode']) && empty($row['hscode'])) {
                $hscodeCol = $reverseMapping['hscode'];
                $hscodeColIdx = $this->colToIndex($hscodeCol);
                
                // 检查前一列（通常是 weight 列）是否有 8 位数字
                if ($hscodeColIdx > 0) {
                    $prevCol = ExcelReader::colName($hscodeColIdx - 1);
                    $prevVal = $rawRow[$prevCol] ?? '';
                    
                    // 如果是 8 位数字，可能是偏移的 hscode
                    if (preg_match('/^\d{8}$/', $prevVal)) {
                        $mappedData[$idx]['hscode'] = $prevVal;
                    }
                }
            }
        }
        
        return $mappedData;
    }

    /**
     * 将列名（A, B, AA...）转换为索引（0, 1, 26...）
     */
    protected function colToIndex(string $col): int
    {
        $index = 0;
        $len = strlen($col);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($col[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }

    // ─── 工具方法 ────────────────────────────────────────────

    protected function normalize(string $str): string
    {
        $str = mb_strtolower(trim($str));
        $str = preg_replace('/\s+/', '', $str);
        $str = preg_replace('/[^a-z0-9\x{4e00}-\x{9fa5}]/u', '', $str);
        return $str;
    }

    protected function isMatch(string $a, string $b): bool
    {
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }
        return false;
    }
}
