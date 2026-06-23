<?php

declare(strict_types=1);

namespace Module\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class UserWebController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Users/Index');
    }

    public function roles(): Response
    {
        return Inertia::render('Users/Roles');
    }

    public function groups(): Response
    {
        return Inertia::render('Users/Groups');
    }
}
