<?php

declare(strict_types=1);

namespace Module\Projects\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class ProjectWebController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Projects/Index');
    }
}
