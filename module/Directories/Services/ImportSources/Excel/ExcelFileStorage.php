<?php

declare(strict_types=1);

namespace Module\Directories\Services\ImportSources\Excel;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use RuntimeException;

final class ExcelFileStorage
{
    public function store(UploadedFile $file): string
    {
        $path = $file->store('directory-imports/'.Carbon::now()->format('Y/m/d'));

        if ($path === false) {
            throw new RuntimeException('Failed to store import file.');
        }

        return $path;
    }
}
