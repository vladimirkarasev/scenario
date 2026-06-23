<?php

declare(strict_types=1);

namespace Module\Actions\Jobs;

use denis660\Centrifugo\Centrifugo;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Throwable;

final class DispatchActionBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, string>  $actionIds
     * @param  array<string, mixed>  $input
     * @param  array<int, int>  $backoff
     * @param  array<int, string>  $afterActionIds
     * @param  array<int, string>  $failedActionIds
     */
    public function __construct(
        private readonly array $actionIds,
        private readonly array $input,
        private readonly int $tries = 1,
        private readonly array $backoff = [60],
        private readonly array $afterActionIds = [],
        private readonly array $failedActionIds = [],
        private readonly ?string $scenarioRunId = null,
    ) {
    }

    public function handle(): void
    {
        $batch = Bus::batch($this->jobs($this->actionIds));
        $afterActionIds = $this->afterActionIds;
        $failedActionIds = $this->failedActionIds;
        $input = $this->input;
        $tries = $this->tries;
        $backoff = $this->backoff;
        $scenarioRunId = $this->scenarioRunId;

        $batch->then(
            static function (Batch $batch) use ($afterActionIds, $input, $tries, $backoff, $scenarioRunId): void {
                if ($scenarioRunId !== null) {
                    app(Centrifugo::class)->publish("scenario-run:{$scenarioRunId}", [
                        'type' => 'batch_completed',
                        'batch_id' => $batch->id,
                    ]);
                }

                self::dispatchJobs($afterActionIds, $input, $tries, $backoff);
            },
        );

        $batch->catch(
            static function (Batch $batch, Throwable $exception) use (
                $failedActionIds,
                $input,
                $tries,
                $backoff,
                $scenarioRunId,
            ): void {
                if ($scenarioRunId !== null) {
                    app(Centrifugo::class)->publish("scenario-run:{$scenarioRunId}", [
                        'type' => 'batch_failed',
                        'batch_id' => $batch->id,
                        'error' => $exception->getMessage(),
                    ]);
                }

                self::dispatchJobs(
                    $failedActionIds,
                    $input + [
                        'failed_batch_id' => $batch->id,
                        'failed_error' => $exception->getMessage(),
                    ],
                    $tries,
                    $backoff,
                );
            },
        );

        $batch->dispatch();
    }

    /**
     * @param  array<int, string>  $actionIds
     * @return array<int, ExecuteActionJob>
     */
    private function jobs(array $actionIds): array
    {
        $jobs = [];

        foreach ($actionIds as $id) {
            $jobs[] = new ExecuteActionJob($id, $this->input, $this->tries, $this->backoff);
        }

        return $jobs;
    }

    /**
     * @param  array<int, string>  $actionIds
     * @param  array<string, mixed>  $input
     * @param  array<int, int>  $backoff
     */
    private static function dispatchJobs(array $actionIds, array $input, int $tries, array $backoff): void
    {
        if ($actionIds === []) {
            return;
        }

        $jobs = [];

        foreach ($actionIds as $id) {
            $jobs[] = new ExecuteActionJob($id, $input, $tries, $backoff);
        }

        Bus::chain($jobs)->dispatch();
    }
}
