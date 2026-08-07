import {execSync} from 'node:child_process'

export default function globalSetup(): void {
    if (process.env.E2E_SKIP_SEED === '1') return

    const command = process.env.E2E_SEED_COMMAND
        ?? 'docker compose exec -T laravel php artisan db:seed --class=Database\\\\Seeders\\\\DevSeeder --force'

    execSync(command, {
        cwd: process.cwd(),
        stdio: 'inherit',
        shell: '/bin/bash',
    })
}
