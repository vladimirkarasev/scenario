<?php

declare(strict_types=1);

namespace Module\Scenario\Observers;

use Module\Scenario\Enums\ScenarioStatus;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioVersion;

final class ScenarioVersionObserver
{
    public function created(ScenarioVersion $version): void
    {
        if ($version->status !== 'active') {
            return;
        }

        $this->syncScenarioStatus($version);
    }

    public function updated(ScenarioVersion $version): void
    {
        if (!$version->wasChanged('status')) {
            return;
        }

        $this->syncScenarioStatus($version);
    }

    private function syncScenarioStatus(ScenarioVersion $version): void
    {
        $scenario = Scenario::query()->find($version->scenario_id);

        if ($scenario === null) {
            return;
        }

        if ($version->status === 'active') {
            $scenario->status = ScenarioStatus::Active;
            $scenario->active_version_id = $version->id;
            $scenario->saveQuietly();

            return;
        }

        // Статус снят с active — ищем другую активную версию
        $otherActive = ScenarioVersion::query()
            ->where('scenario_id', $version->scenario_id)
            ->where('id', '!=', $version->id)
            ->where('status', 'active')
            ->first();

        if ($otherActive !== null) {
            $scenario->active_version_id = $otherActive->id;
        } else {
            $scenario->status = ScenarioStatus::Draft;
            $scenario->active_version_id = null;
        }

        $scenario->saveQuietly();
    }
}
