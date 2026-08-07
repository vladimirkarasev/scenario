<?php

declare(strict_types=1);

namespace Module\Scenario\Enums;

use App\Contracts\ErrorText;

enum ScenarioErrorCode: string implements ErrorText
{
    case ScenarioNotFound = 'SCENARIO_NOT_FOUND';
    case ScenarioVersionNotFound = 'SCENARIO_VERSION_NOT_FOUND';
    case ScenarioRunNotFound = 'SCENARIO_RUN_NOT_FOUND';
    case DispatchNoProjectContext = 'DISPATCH_NO_PROJECT_CONTEXT';
    case DispatchTargetUserNotFound = 'DISPATCH_TARGET_USER_NOT_FOUND';
    case DispatchScenarioNotFound = 'DISPATCH_SCENARIO_NOT_FOUND';
    case ServiceAccountRequired = 'SCENARIO_SERVICE_ACCOUNT_REQUIRED';
    case SystemCategoryDeleteForbidden = 'SCENARIO_SYSTEM_CATEGORY_DELETE_FORBIDDEN';
    case FieldPresetNotFound = 'SCENARIO_FIELD_PRESET_NOT_FOUND';
    case FieldPresetNameConflict = 'SCENARIO_FIELD_PRESET_NAME_CONFLICT';

    #[\Override]
    public function code(): string
    {
        return $this->value;
    }

    #[\Override]
    public function title(): string
    {
        return (string) trans("errors.scenario.{$this->value}.title");
    }

    #[\Override]
    public function detail(): string
    {
        return (string) trans("errors.scenario.{$this->value}.detail");
    }
}
