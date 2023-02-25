<?php

declare(strict_types=1);

namespace Codedge\Updater\Traits;

use Illuminate\Support\Facades\Storage;

trait UseVersionFile
{
    protected string $versionFile = 'self-updater-new-version';

    /**
     * Check if the file with the new version already exists.
     */
    public function versionFileExists(): bool
    {
        return Storage::disk('updates')->exists($this->versionFile);
    }

    /**
     * Write the version file.
     */
    public function setVersionFile(string $content): bool
    {
        return Storage::disk('updates')->put($this->versionFile, $content);
    }

    /**
     * Get the content of the version file.
     */
    public function getVersionFile(): string
    {
        return trim(Storage::disk('updates')->get($this->versionFile));
    }

    /**
     * Delete the version file.
     */
    public function deleteVersionFile(): bool
    {
        return Storage::disk('updates')->delete($this->versionFile);
    }
}
