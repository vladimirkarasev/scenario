<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\DaData;

enum DaDataGateway: string
{
    case Suggest = 'dadata_suggest';
    case Clean = 'dadata_clean';
    case Profile = 'dadata_profile';

    public function baseUri(): string
    {
        return match ($this) {
            self::Suggest => 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/',
            self::Clean => 'https://cleaner.dadata.ru/api/v1/',
            self::Profile => 'https://dadata.ru/api/v2/',
        };
    }
}
