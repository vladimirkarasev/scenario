<?php

declare(strict_types=1);

namespace Module\Actions\Services\Handlers;

use Module\Actions\Contracts\ActionHandlerInterface;
use Module\Actions\DTO\ActionResult;
use Module\Actions\Models\Action;
use Module\Actions\Services\ActionDataResolver;
use Module\Actions\Services\Handlers\Concerns\HasNoConfigFields;

final class XmlActionHandler implements ActionHandlerInterface
{
    use HasNoConfigFields;

    public function __construct(
        private readonly ActionDataResolver $dataResolver,
    ) {
    }

    /** @param  array<string, mixed>  $input */
    public function handle(Action $action, array $input = []): ActionResult
    {
        $config = $this->dataResolver->resolve(
            $action->config ?? [],
            $this->dataResolver->contextForAction($action, $input)
        );
        $config = is_array($config) ? $config : [];
        $rootName = is_string($config['root'] ?? null) ? (string)$config['root'] : 'document';
        $payload = $config['body'] ?? $input;

        $xml = new \SimpleXMLElement(sprintf('<?xml version="1.0" encoding="UTF-8"?><%s/>', $rootName));

        $this->append($xml, is_array($payload) ? $payload : ['value' => $payload]);

        return ActionResult::success([
            'xml' => $xml->asXML(),
        ]);
    }

    /** @param  array<int|string, mixed>  $data */
    private function append(\SimpleXMLElement $element, array $data): void
    {
        foreach ($data as $key => $value) {
            $nodeName = is_string($key) ? $key : 'item';

            if (is_array($value)) {
                $child = $element->addChild($nodeName);
                $this->append($child, $value);

                continue;
            }

            $element->addChild($nodeName, htmlspecialchars(is_scalar($value) || $value === null ? (string)$value : ''));
        }
    }
}
