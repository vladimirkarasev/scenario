<?php

declare(strict_types=1);

namespace Module\Proxy\Enums;

use App\Contracts\ErrorText;

enum ProxyErrorCode: string implements ErrorText
{
    case EndpointNotFound = 'PROXY_ENDPOINT_NOT_FOUND';
    case ConnectionNotFound = 'PROXY_CONNECTION_NOT_FOUND';
    case RequestNotFound = 'PROXY_REQUEST_NOT_FOUND';
    case CategoryNotFound = 'PROXY_CATEGORY_NOT_FOUND';
    case SystemCategoryDeleteForbidden = 'PROXY_SYSTEM_CATEGORY_DELETE_FORBIDDEN';

    #[\Override]
    public function code(): string
    {
        return $this->value;
    }

    #[\Override]
    public function title(): string
    {
        return (string) trans("errors.proxy.{$this->value}.title");
    }

    #[\Override]
    public function detail(): string
    {
        return (string) trans("errors.proxy.{$this->value}.detail");
    }
}
