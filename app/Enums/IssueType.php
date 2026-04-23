<?php

namespace App\Enums;

enum IssueType: string
{
    case Exception = 'exception';
    case Log = 'log';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
