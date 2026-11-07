<?php

namespace Royfee\Import\Exceptions;

/**
 * 文件读取异常
 */
class FileReadException extends ImportException
{
    public function __construct(string $filePath, string $reason = '')
    {
        $message = "无法读取文件: {$filePath}";
        if ($reason) {
            $message .= " ({$reason})";
        }
        parent::__construct($message);
    }
}
