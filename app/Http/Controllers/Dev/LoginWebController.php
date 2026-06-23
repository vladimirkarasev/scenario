<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class LoginWebController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Auth/Login');
    }
}
