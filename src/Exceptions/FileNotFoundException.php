<?php

namespace Royfee\Import\Exceptions;

/**
 * 文件不存在异常
 */
class FileNotFoundException extends ImportException
{
    public function __construct(string $filePath)
    {
        parent::__construct("文件不存在: {$filePath}");
    }
}
