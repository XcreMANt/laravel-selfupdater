<?php

namespace Codedge\Updater\Jobs;

use Codedge\Updater\UpdaterManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $updater = new UpdaterManager(app());

        $updater->source()->preUpdateArtisanCommands();

        if($updater->source()->isNewVersionAvailable()) {

            $versionAvailable = $updater->source()->getVersionAvailable();

            $release = $updater->source()->fetch($versionAvailable);

            $updater->source()->update($release);

            $updater->source()->setNewVersion($versionAvailable);

        } else {
            Log::info('No new version available.');
        }

        $updater->source()->postUpdateArtisanCommands();
    }
}
