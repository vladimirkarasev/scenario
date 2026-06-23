# Actions Queue Drivers

Actions use Laravel queues through `dispatch()`, `Bus::chain()` and `Bus::batch()`.
The Actions module does not depend on a concrete queue backend, so the same API
works with `database`, `redis` or `sqs`.

## Yandex Message Queue

Yandex Message Queue is SQS-compatible, so use Laravel's `sqs` connection:

```dotenv
QUEUE_CONNECTION=sqs
QUEUE_NAMES=default

AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=ru-central1

SQS_ENDPOINT=https://message-queue.api.cloud.yandex.net
SQS_PREFIX=https://message-queue.api.cloud.yandex.net/<cloud-id>/<queue-folder-or-account>
SQS_QUEUE=default
SQS_SUFFIX=
```

Run worker:

```bash
php artisan queue:work sqs --queue=default --tries=3 --timeout=90
```

Notes:
- `action_runs` stores business execution status, error reason and attempts count.
- Laravel's failed job storage still uses `failed_jobs`.
- `Bus::batch()` also requires Laravel's `job_batches` table.
- If the SQS client package is not installed in the target environment, install
  `aws/aws-sdk-php` before enabling `QUEUE_CONNECTION=sqs`.
