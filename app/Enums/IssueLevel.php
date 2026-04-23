<?php

namespace App\Enums;

enum IssueLevel: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';
    case Debug = 'debug';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
