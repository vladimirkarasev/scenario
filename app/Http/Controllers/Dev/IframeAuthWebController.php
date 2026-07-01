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
            'demo' => $demo !== null ? [
                'project_id' => $demo->id,
                'name' => AdminUserSeeder::NAME,
                'login' => AdminUserSeeder::LOGIN,
                'email' => AdminUserSeeder::EMAIL,
                'roles' => AdminUserSeeder::ROLE,
            ] : null,
        ]);
    }
}
