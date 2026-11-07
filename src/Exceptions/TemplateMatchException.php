<?php

namespace Royfee\Import\Exceptions;

/**
 * 模板匹配失败异常
 */
class TemplateMatchException extends ImportException
{
    protected array $headers;
    protected array $scores;

    public function __construct(array $headers, array $scores = [])
    {
        $this->headers = $headers;
        $this->scores  = $scores;

        $headerStr = implode(', ', $headers);
        $message   = "无法匹配模板，文件表头: [{$headerStr}]";

        if (!empty($scores)) {
            $message .= "\n匹配度: " . json_encode($scores, JSON_UNESCAPED_UNICODE);
        }

        parent::__construct($message);
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getScores(): array
    {
        return $this->scores;
    }
}
