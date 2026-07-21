<?php

declare(strict_types=1);

namespace App\Contracts;

interface ErrorText
{
    public function code(): string;

    public function title(): string;

    public function detail(): string;
}
