<?php

declare(strict_types=1);

namespace Module\Actions\DTO;

use Illuminate\Http\Request;

final readonly class RunActionsData
{
    /**
     * @param array<string, string>     $actions  map code => action_uuid (основные)
     * @param array<string, string>     $before   map code => action_uuid (до основных, последовательно)
     * @param array<string, string>     $after    map code => action_uuid (после основных, последовательно)
     * @param array<string, string>     $onError  map code => action_uuid (при сбое)
     * @param array<string, mixed>      $input    глобальные данные для всех action в этом запуске
     * @param array<string, mixed>|null $schedule если задан — создать расписание вместо немедленного запуска
     * @param array<string, string>     $scopeMap code => scope результата ('' = глобальный scope, output мержится в корень контекста)
     */
    public function __construct(
        public string $mode,
        public array $actions,
        public array $before,
        public array $after,
        public array $onError,
        public array $input,
        public ?array $schedule,
        public bool $canManageActions,
        public array $scopeMap = [],
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            mode: $request->string('mode', 'sequential')->toString(),
            actions: self::codeMap($request->input('actions')),
            before: self::codeMap($request->input('before')),
            after: self::codeMap($request->input('after')),
            onError: self::codeMap($request->input('on_error')),
            input: self::stringKeyed($request->input('input')),
            schedule: self::scheduleData($request->input('schedule')),
            canManageActions: $request->user() !== null,
        );
    }

    /** @return list<string> */
    public function actionIds(): array
    {
        return array_values($this->actions);
    }

    /** @return list<string> */
    public function beforeIds(): array
    {
        return array_values($this->before);
    }

    /** @return list<string> */
    public function afterIds(): array
    {
        return array_values($this->after);
    }

    /** @return list<string> */
    public function onErrorIds(): array
    {
        return array_values($this->onError);
    }

    /**
     * Обратный индекс actionId => code из всех map. Дубли overwrite-ятся: actions > before > after > on_error.
     *
     * @return array<string, string>
     */
    public function codesByActionId(): array
    {
        $result = [];

        foreach ($this->onError as $code => $id) {
            $result[$id] = $code;
        }

        foreach ($this->after as $code => $id) {
            $result[$id] = $code;
        }

        foreach ($this->before as $code => $id) {
            $result[$id] = $code;
        }

        foreach ($this->actions as $code => $id) {
            $result[$id] = $code;
        }

        return $result;
    }

    /** @return array<string, string> */
    private static function codeMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $code => $actionId) {
            if (! is_string($code) || $code === '' || ! is_scalar($actionId)) {
                continue;
            }

            $id = (string) $actionId;

            if ($id !== '') {
                $result[$code] = $id;
            }
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private static function stringKeyed(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $key => $item) {
            $result[(string) $key] = $item;
        }

        return $result;
    }

    /** @return array<string, mixed>|null */
    private static function scheduleData(mixed $value): ?array
    {
        if (! is_array($value) || $value === []) {
            return null;
        }

        return self::stringKeyed($value);
    }
}
