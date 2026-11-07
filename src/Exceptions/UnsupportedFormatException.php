<?php

namespace Royfee\Import\Exceptions;

/**
 * 不支持的文件格式异常
 */
class UnsupportedFormatException extends ImportException
{
    public function __construct(string $extension)
    {
        parent::__construct("不支持的文件格式: .{$extension}");
    }
}
