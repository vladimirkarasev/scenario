<?php

declare(strict_types=1);

namespace Module\Directories\DTO;

use Module\Directories\Enums\DirectoryImportMode;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Models\DirectoryVersion;

final readonly class DirectoryImportOptions
{
    public function __construct(
        public bool $addNew,
        public bool $updateExisting,
        public bool $deleteUnused,
    ) {
    }

    public static function forMode(string $mode): self
    {
        return match ($mode) {
            DirectoryImportMode::Create->value => new self(addNew: true, updateExisting: false, deleteUnused: false),
            DirectoryImportMode::Update->value => new self(addNew: false, updateExisting: true, deleteUnused: false),
            DirectoryImportMode::Replace->value => new self(addNew: true, updateExisting: true, deleteUnused: true),
            default => new self(addNew: true, updateExisting: true, deleteUnused: false),
        };
    }

    /** @param  array<string, mixed>  $input */
    public static function fromInput(array $input, string $mode): self
    {
        $defaults = self::forMode($mode);

        return new self(
            addNew: array_key_exists('add_new', $input) ? (bool)$input['add_new'] : $defaults->addNew,
            updateExisting: array_key_exists(
                'update_existing',
                $input
            ) ? (bool)$input['update_existing'] : $defaults->updateExisting,
            deleteUnused: array_key_exists(
                'delete_unused',
                $input
            ) ? (bool)$input['delete_unused'] : $defaults->deleteUnused,
        );
    }

    public static function fromImport(DirectoryImport $import): self
    {
        $config = $import->source_config_json;
        $defaults = self::forMode((string)$import->mode);

        return new self(
            addNew: array_key_exists('add_new', $config) ? (bool)$config['add_new'] : $defaults->addNew,
            updateExisting: array_key_exists(
                'update_existing',
                $config
            ) ? (bool)$config['update_existing'] : $defaults->updateExisting,
            deleteUnused: array_key_exists(
                'delete_unused',
                $config
            ) ? (bool)$config['delete_unused'] : $defaults->deleteUnused,
        );
    }

    public static function fromVersion(DirectoryVersion $version): self
    {
        $options = $version->sync_options ?? [];

        return new self(
            addNew: array_key_exists('add_new', $options) ? (bool)$options['add_new'] : true,
            updateExisting: array_key_exists('update_existing', $options) ? (bool)$options['update_existing'] : true,
            deleteUnused: array_key_exists('delete_unused', $options) ? (bool)$options['delete_unused'] : false,
        );
    }

    /** @return array{add_new: bool, update_existing: bool, delete_unused: bool} */
    public function toArray(): array
    {
        return [
            'add_new' => $this->addNew,
            'update_existing' => $this->updateExisting,
            'delete_unused' => $this->deleteUnused,
        ];
    }
}
