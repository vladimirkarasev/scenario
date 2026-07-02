<?php

declare(strict_types=1);

namespace Tests\Unit\App\Exceptions;

use App\Contracts\ErrorText;
use App\Exceptions\ConflictException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use Module\Users\Enums\UserErrorCode;
use Tests\TestCase;

final class DomainExceptionTest extends TestCase
{
    private function errorText(): ErrorText
    {
        return new class implements ErrorText {
            public function code(): string
            {
                return 'SAMPLE_CODE';
            }

            public function title(): string
            {
                return 'Заголовок';
            }

            public function detail(): string
            {
                return 'Подробности.';
            }
        };
    }

    public function test_from_builds_error_payload_from_error_text(): void
    {
        $e = ForbiddenException::from($this->errorText());

        self::assertSame(403, $e->status());
        self::assertSame('SAMPLE_CODE', $e->errorCode);
        self::assertSame('Подробности.', $e->getMessage());
        self::assertSame([
            'status' => '403',
            'code' => 'SAMPLE_CODE',
            'title' => 'Заголовок',
            'detail' => 'Подробности.',
        ], $e->toError());
    }

    public function test_status_codes_per_subclass(): void
    {
        self::assertSame(403, ForbiddenException::from($this->errorText())->status());
        self::assertSame(404, NotFoundException::from($this->errorText())->status());
        self::assertSame(422, ConflictException::from($this->errorText())->status());
    }

    public function test_from_resolves_text_from_user_error_code_enum(): void
    {
        $e = NotFoundException::from(UserErrorCode::UserNotFound);

        self::assertSame('USER_NOT_FOUND', $e->errorCode);
        self::assertSame('Пользователь не найден', $e->errorTitle);
        self::assertSame('Пользователь не найден в проекте.', $e->getMessage());
    }

    public function test_make_normalizes_backed_enum_code(): void
    {
        $e = ConflictException::make('Роль «Оператор» не найдена.', UserErrorCode::RoleNotFound);

        self::assertSame('ROLE_NOT_FOUND', $e->errorCode);
        self::assertSame('Роль «Оператор» не найдена.', $e->getMessage());
    }
}
