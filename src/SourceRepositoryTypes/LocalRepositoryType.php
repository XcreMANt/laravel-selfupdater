<?php

declare(strict_types=1);

namespace Codedge\Updater\SourceRepositoryTypes;

use Codedge\Updater\Contracts\SourceRepositoryTypeContract;
use Codedge\Updater\Events\UpdateAvailable;
use Codedge\Updater\Exceptions\ReleaseException;
use Codedge\Updater\Exceptions\VersionException;
use Codedge\Updater\Models\Release;
use Codedge\Updater\Models\UpdateExecutor;
use Codedge\Updater\Traits\UseVersionFile;
use Exception;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class LocalRepositoryType implements SourceRepositoryTypeContract
{
    use UseVersionFile;

    protected ClientInterface $client;
    protected array $config;
    protected Release $release;
    protected string $prepend;
    protected string $append;
    protected UpdateExecutor $updateExecutor;

    public function __construct(UpdateExecutor $updateExecutor)
    {
        $this->config = config('self-update.repository_types.local');

        // Get prepend and append strings
        $this->prepend = preg_replace('/_VERSION_.*$/', '', $this->config['pkg_filename_format']);
        $this->append = preg_replace('/^.*_VERSION_/', '', $this->config['pkg_filename_format']);

        $this->release = resolve(Release::class);
        $this->release->setStoragePath(Str::finish($this->config['download_path'], DIRECTORY_SEPARATOR))
                      ->setUpdatePath(base_path(), config('self-update.exclude_folders'));

        $this->updateExecutor = $updateExecutor;
    }

    /**
     * Check repository if a newer version than the installed one is available.
     *
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function isNewVersionAvailable(string $currentVersion = ''): bool
    {
        $version = $currentVersion ?: $this->getVersionInstalled();

        if (!$version) {
            throw VersionException::versionInstalledNotFound();
        }

        $versionAvailable = $this->getVersionAvailable();

        if (version_compare($version, $versionAvailable, '<')) {
            if (!$this->versionFileExists()) {
                $this->setVersionFile($this->getVersionAvailable());
                event(new UpdateAvailable($this->getVersionAvailable()));
            }

            return true;
        }

        return false;
    }

    /**
     * Fetches the latest version. If you do not want the latest version, specify one and pass it.
     *
     * @throws ReleaseException
     */
    public function fetch(string $version = ''): Release
    {
        $releaseCollection = $this->extractFromStorage(Storage::disk('updates')->allFiles());

        if ($releaseCollection->isEmpty()) {
            throw ReleaseException::noReleaseFound($version);
        }

        $release = $this->selectRelease($releaseCollection, $version);

        $this->release->setVersion($this->prepend.$release->name.$this->append)
                      ->setRelease($this->prepend.$release->name.$this->append.'.zip')
                      ->updateStoragePath()
                      ->setDownloadUrl($release->zipball_url);

        if (!$this->release->isSourceAlreadyFetched()) {
            $fileName = $this->release->getRelease();
            File::copy(
                $this->config['repository_url'] . DIRECTORY_SEPARATOR . $fileName,
                $this->config['download_path'] . DIRECTORY_SEPARATOR . $fileName);
            $this->release->extract();
        }

        return $this->release;
    }

    public function selectRelease(Collection $collection, string $version)
    {
        $release = $collection->first();

        if (!empty($version)) {
            if ($collection->contains('name', $version)) {
                $release = $collection->where('name', $version)->first();
            } else {
                Log::info('No release for version "'.$version.'" found. Selecting latest.');
            }
        }

        return $release;
    }

    /**
     * @throws Exception
     */
    public function update(Release $release): bool
    {
        return $this->updateExecutor->run($release);
    }

    /**
     * {@inheritdoc}
     */
    public function getVersionInstalled(): string
    {
        return (string) config('self-update.version_installed');
    }

    /**
     * Get the latest version that has been published in a certain repository.
     * Example: 2.6.5 or v2.6.5.
     *
     * @param string $prepend Prepend a string to the latest version
     * @param string $append  Append a string to the latest version
     *
     * @throws Exception|GuzzleException
     */
    public function getVersionAvailable(string $prepend = '', string $append = ''): string
    {
        if ($this->versionFileExists()) {
            $version = $this->getVersionFile();
        } else {
            $releaseCollection = $this->extractFromStorage(Storage::disk('updates')->allFiles());
            $version = $releaseCollection->first()->name;
        }

        return $version;
    }

    final public function getReleases(): Response
    {
        //
    }

    /**
     * @throws ReleaseException
     */
    protected function extractFromStorage(array $content): Collection
    {
        $format = str_replace('_VERSION_', '(\d+\.\d+\.\d+)', $this->config['pkg_filename_format']).'.zip';
        $pattern = '(.*'.$format.')';

        $files = [];
        foreach ($content as $item) {
            preg_match_all($pattern, $item, $matches);

            if (array_filter($matches)) {
                $files[0][] = $matches[0][0] ?? null;
                $files[1][] = $matches[1][0] ?? null;
            }
        }

        if (count($files) === 0) {
            throw ReleaseException::cannotExtractDownloadLink($format);
        }

        // Special handling when file version cannot be properly detected
        if (!array_key_exists(1, $files)) {
            foreach ($files[0] as $key=>$val) {
                preg_match('/[a-zA-Z\-]([.\d]*)(?=\.\w+$)/', $val, $versions);
                $files[][$key] = $versions[1];
            }
        }

        $releaseVersions = array_combine($files[1], $files[0]);

        return collect($releaseVersions)->map(function ($item, $key) {
            return (object)[
                'name'        => $key,
                'zipball_url' => $this->config['repository_url'] . '/',
            ];
        });
    }
}
