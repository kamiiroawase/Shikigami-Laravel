<?php

namespace App\Console\Commands;

use App\Libs\TelegramBotApi;
use App\Libs\TelegramBotTaskGenerator;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * @noinspection PhpUnused
 */

class TelegramBotCommand extends Command
{
    protected $signature = 'telegram-bot:start';

    private Filesystem $storage;

    private array $bot_instances;

    private bool $should_shutdown = false;

    public function __construct()
    {
        parent::__construct();

        pcntl_signal(SIGINT, function () {
            $this->should_shutdown = true;
        });

        pcntl_signal(SIGTERM, function () {
            $this->should_shutdown = true;
        });

        $this->storage = Storage::disk('local');

        $bot_configs = config('shikigami.telegram_bots');

        foreach ($bot_configs as $key => $configs) {
            $this->bot_instances[$key] = [
                'configs' => $configs,
                'task_generator' => new TelegramBotTaskGenerator($configs),
                'telegram_bot_api' => new TelegramBotApi($configs['bot_token'], $configs['proxy']),
            ];
        }
    }

    public function handle(): void
    {
        while (true) {
            if ($this->should_shutdown) {
                break;
            }

            try {
                $this->handleAllBotInstances();
            } catch (ConnectionException) {
                //
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    /**
     * @throws Throwable
     */
    private function handleAllBotInstances(): void
    {
        foreach ($this->bot_instances as $bot_instances) {
            /** @var TelegramBotApi $telegram_bot_api */
            $telegram_bot_api = $bot_instances['telegram_bot_api'];

            $telegram_bot_api->onMessage(
                $this->getLastUpdateId($bot_instances['configs']['cache_key']),
                function (string $update_id, array $request_data) use ($bot_instances) {
                    /** @var TelegramBotTaskGenerator $task_generator */
                    $task_generator = $bot_instances['task_generator'];
                    $task_generator->taskGen($request_data);
                    $this->storage->put($bot_instances['configs']['cache_key'], $update_id);
                }
            );
        }
    }

    private function getLastUpdateId(string $cache_key): int
    {
        try {
            return (int)$this->storage->get($cache_key);
        } catch (Throwable) {
            return 0;
        }
    }
}
