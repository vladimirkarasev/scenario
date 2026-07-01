<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Module\Projects\Models\Project;
use Module\Users\Services\SystemUserService;

final class DemoProjectSeeder extends Seeder
{
    public const string SITEKEY = 'demo-site';

    public const string HOST = 'parent.localhost';

    public const string SHARED_SECRET = 'demo-site-shared-secret-for-local-dev';

    public const string SITEKEY_2 = 'alpha-site';

    public const string HOST_2 = 'alpha.localhost';

    public const string SHARED_SECRET_2 = 'alpha-site-shared-secret-for-local-dev';

    private const string PROJECT_NAME = 'Demo Site';

    private const string PROJECT_NAME_2 = 'Alpha Site';

    public function run(): void
    {
        $systemUsers = app(SystemUserService::class);

        $demo = Project::query()->updateOrCreate(
            ['sitekey' => self::SITEKEY, 'host' => self::HOST],
            [
                'id' => '019e5d9f-86d8-7311-9f8b-fb1dfef34a71',
                'name' => self::PROJECT_NAME,
                'shared_secret' => self::SHARED_SECRET,
                'is_active' => true,
            ],
        );

        $alpha = Project::query()->updateOrCreate(
            ['sitekey' => self::SITEKEY_2, 'host' => self::HOST_2],
            [
                'id' => '019e5d9f-86d8-7311-9f8b-fb1dfef34a72',
                'name' => self::PROJECT_NAME_2,
                'shared_secret' => self::SHARED_SECRET_2,
                'is_active' => true,
            ],
        );

        $systemUsers->provision($demo);
        $systemUsers->provision($alpha);
    }
}
