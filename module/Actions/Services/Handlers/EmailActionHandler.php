<?php

declare(strict_types=1);

namespace Module\Actions\Services\Handlers;

use Module\Actions\Contracts\ActionHandlerInterface;
use Module\Actions\DTO\ActionConfigField;
use Module\Actions\DTO\ActionResult;
use Module\Actions\DTO\EmailMessage;
use Module\Actions\Models\Action;
use Module\Actions\Services\ActionDataResolver;
use Module\Actions\Services\Mail\EmailDispatcher;

final class EmailActionHandler implements ActionHandlerInterface
{
    public function __construct(
        private readonly ActionDataResolver $dataResolver,
        private readonly EmailDispatcher $dispatcher,
    ) {
    }

    /** @param  array<string, mixed>  $input */
    public function handle(Action $action, array $input = []): ActionResult
    {
        $config = $this->dataResolver->resolveActionConfig($action, $input);

        $to = $this->addressList($config['to'] ?? null);
        $subject = $this->stringValue($config['subject'] ?? null);
        $bodyHtml = $this->stringValue($config['body_html'] ?? null);
        $bodyText = $this->stringValue($config['body_text'] ?? null);

        if ($to === []) {
            return ActionResult::failed('Email action requires non-empty `to` recipients.');
        }

        if ($bodyHtml === '' && $bodyText === '') {
            return ActionResult::failed('Email action requires body_html or body_text in config.');
        }

        $from = $this->stringValue($config['from'] ?? null);

        $message = new EmailMessage(
            from: $from !== '' ? $from : null,
            fromName: null,
            to: $to,
            cc: $this->addressList($config['cc'] ?? null),
            bcc: $this->addressList($config['bcc'] ?? null),
            subject: $subject,
            bodyHtml: $bodyHtml,
            bodyText: $bodyText,
        );

        $result = $this->dispatcher->send($message);

        $payload = [
            'sent' => $result->sent,
            'transport' => $result->transport,
            'message_id' => $result->messageId,
            'to' => $to,
            'subject' => $subject,
        ];

        if (!$result->sent) {
            return ActionResult::failed($result->error ?? 'Email send failed.', $payload);
        }

        return ActionResult::success($payload);
    }

    public function configFields(): iterable
    {
        yield ActionConfigField::make('to')
            ->label('Кому')
            ->type('email_list')
            ->required()
            ->placeholder('{{ email }}');

        yield ActionConfigField::make('cc')
            ->label('Копия (cc)')
            ->type('email_list')
            ->placeholder('manager@example.com');

        yield ActionConfigField::make('bcc')
            ->label('Скрытая копия (bcc)')
            ->type('email_list');

        yield ActionConfigField::make('from')
            ->label('От')
            ->type('string')
            ->placeholder('noreply@example.com');

        yield ActionConfigField::make('subject')
            ->label('Тема')
            ->type('string')
            ->placeholder('{{ subject }}');

        yield ActionConfigField::make('body_html')
            ->label('HTML-тело')
            ->type('html')
            ->placeholder('{{ body_html }}');

        yield ActionConfigField::make('body_text')
            ->label('Текстовое тело')
            ->type('text')
            ->multiline()
            ->placeholder('{{ body_text }}');
    }

    /** @return list<string> */
    private function addressList(mixed $value): array
    {
        if (is_string($value)) {
            $parts = preg_split('/[\s,;]+/', $value) ?: [];

            return $this->filterEmails($parts);
        }

        if (is_array($value)) {
            return $this->filterEmails($value);
        }

        return [];
    }

    /**
     * @param  iterable<int|string, mixed>  $values
     * @return list<string>
     */
    private function filterEmails(iterable $values): array
    {
        $result = [];

        foreach ($values as $item) {
            if (!is_string($item)) {
                continue;
            }

            $trimmed = trim($item);

            if ($trimmed !== '') {
                $result[] = $trimmed;
            }
        }

        return $result;
    }

    private function stringValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return is_scalar($value) ? (string)$value : '';
    }
}
