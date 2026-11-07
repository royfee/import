<?php

namespace Royfee\Import\Contracts;

/**
 * 文件读取器接口 — 只负责读取，不管模板/映射
 */
interface FileReaderInterface
{
    /**
     * 读取文件，返回行数组（全量加载到内存）
     *
     * @param string $filePath 文件路径
     * @return array [['A' => 'val1', 'B' => 'val2', ...], ...]
     */
    public function read(string $filePath): array;

    /**
     * 流式读取，返回 Generator（逐行产出，内存恒定）
     *
     * @param string $filePath 文件路径
     * @return \Generator 每次 yield 一行 ['A' => 'val1', 'B' => 'val2', ...]
     */
    public function cursor(string $filePath): \Generator;

    /**
     * 获取 sheet 数量（CSV/TXT 返回 1）
     *
     * @param string $filePath 文件路径
     * @return int
     */
    public function getSheetCount(string $filePath): int;

    /**
     * 是否支持该文件类型
     *
     * @param string $extension 文件扩展名（小写）
     * @return bool
     */
    public function supports(string $extension): bool;

    /**
     * 设置选项（如 sheet 索引等）
     *
     * @param array $options
     * @return self
     */
    public function setOptions(array $options): self;

    /**
     * 一次打开文件，读取多个 sheet 的数据
     *
     * @param string $filePath  文件路径
     * @param int    $maxSheets 最多读取几个 sheet
     * @return array [sheetIndex => 行数组]，CSV/TXT 只返回 [0 => 行数组]
     */
    public function readMultiSheet(string $filePath, int $maxSheets): array;

    /**
     * 获取指定 sheet 的表头（第一行）
     *
     * @param string $filePath 文件路径
     * @return array ['A' => 'header0', 'B' => 'header1', ...]
     */
    public function getHeaders(string $filePath): array;

    /**
     * 一次打开文件，获取多个 sheet 的表头
     *
     * @param string $filePath  文件路径
     * @param int    $maxSheets 最多读取几个 sheet
     * @return array [sheetIndex => ['A' => 'header0', 'B' => 'header1', ...]]
     */
    public function getHeadersMultiSheet(string $filePath, int $maxSheets): array;
}
