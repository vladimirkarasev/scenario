<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DemoProjectSeeder;
use Inertia\Inertia;
use Inertia\Response;
use Module\Projects\Models\Project;

final class IframeAuthWebController extends Controller
{
    public function __invoke(): Response
    {
        /** @var Project|null $demo */
        $demo = Project::query()->where('sitekey', DemoProjectSeeder::SITEKEY)->first();

        return Inertia::render('Auth/IframeAuth', [
            'demo' => $demo ? [
                'project_uuid' => $demo->id,
                'shared_secret' => $demo->shared_secret,
                'login' => AdminUserSeeder::LOGIN,
                'name' => AdminUserSeeder::NAME,
                'email' => AdminUserSeeder::EMAIL,
                'roles' => AdminUserSeeder::ROLE,
            ] : null,
        ]);
    }
}
