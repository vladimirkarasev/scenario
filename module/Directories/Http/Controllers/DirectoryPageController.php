<?php

declare(strict_types=1);

namespace Module\Directories\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class DirectoryPageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Directories/Index');
    }

    public function show(string $directory): RedirectResponse
    {
        return redirect()->route('directories.settings', $directory);
    }

    public function settings(string $directory): Response
    {
        return Inertia::render('Directories/Settings', [
            'directoryId' => $directory,
        ]);
    }

    public function version(string $directory, string $version): Response
    {
        return Inertia::render('Directories/Version', [
            'directoryId' => $directory,
            'versionId' => $version,
        ]);
    }
}
