<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Contracts\ErrorText;
use RuntimeException;

abstract class DomainException extends RuntimeException
{
    final public function __construct(
        public readonly string $errorCode,
        public readonly string $errorTitle,
        string $detail,
    ) {
        parent::__construct($detail);
    }

    abstract public function status(): int;

    public static function from(ErrorText $code): static
    {
        return new static($code->code(), $code->title(), $code->detail());
    }

    protected static function normalizeCode(string|\BackedEnum $code): string
    {
        return $code instanceof \BackedEnum ? (string) $code->value : $code;
    }

    /** @return array{status: string, code: string, title: string, detail: string} */
    public function toError(): array
    {
        return [
            'status' => (string) $this->status(),
            'code' => $this->errorCode,
            'title' => $this->errorTitle,
            'detail' => $this->getMessage(),
        ];
    }
}
