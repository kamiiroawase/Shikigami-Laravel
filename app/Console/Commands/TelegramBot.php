<?php

namespace App\Console\Commands;

use App\Libs\TelegramBotApi;
use App\Libs\TelegramBotTaskGen;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * @noinspection PhpUnused
 */

class TelegramBot extends Command
{
    protected $signature = 'telegram-bot';

    private array $bots;

    private Filesystem $disk;

    public function __construct()
    {
        parent::__construct();

        $this->disk = Storage::disk('local');

        $botConfigs = config('shikigami.telegram_bots');

        foreach ($botConfigs as $key => $configs) {
            $this->bots[$key] = [
                'configs' => $configs,
                'task_gen' => new TelegramBotTaskGen($configs),
                'api' => new TelegramBotApi($configs['bot_token'], $configs['proxy']),
            ];
        }
    }

    public function handle(): void
    {
        try {
            while (true) {
                foreach ($this->bots as $bot) {
                    /** @var TelegramBotApi $api */
                    $api = $bot['api'];

                    $api->onMessage(
                        $this->getTgUpdateId($bot['configs']['cache_key']),
                        function (string $update_id, array $requestData) use ($bot) {
                            /** @var TelegramBotTaskGen $task_gen */
                            $task_gen = $bot['task_gen'];
                            $task_gen->taskGen($requestData);
                            $this->disk->put($bot['configs']['cache_key'], $update_id);
                        }
                    );
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function getTgUpdateId(string $cache_key): int
    {
        try {
            return (int)$this->disk->get($cache_key);
        } catch (Throwable) {
            return 0;
        }
    }
}
