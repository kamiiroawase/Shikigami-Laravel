<?php

namespace App\Jobs;

use App\Libs\OctaneHelper;
use App\Libs\TelegramBotApi;
use App\Libs\TelegramBotTaskGen;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TelegramBotOnMessageJob extends QueueJob
{
    private Filesystem $disk;

    private TelegramBotApi $telegram_bot_api;

    private TelegramBotTaskGen $telegram_bot_task_gen;

    public function __construct(private readonly array $configs)
    {
        $this->disk = Storage::disk('local');
        $this->telegram_bot_task_gen = new TelegramBotTaskGen($this->configs);
        $this->telegram_bot_api = new TelegramBotApi($this->configs['bot_token'], $this->configs['proxy']);
    }

    public function handle(): void
    {
        // 监听消息并获取请求任务
        $this->onMessage(function (array $requestData) {
            return $this->telegram_bot_task_gen->getTaskFn($requestData);
        });
    }

    private function onMessage(callable $callback): void
    {
        $whileTrueCallback = $this->getWhileTrueCallback($callback);

        OctaneHelper::whileTrueForProcesses($whileTrueCallback, true);
    }

    private function getWhileTrueCallback(callable $callback): callable
    {
        return function (callable $getProcessCallback) use ($callback) {
            $onMessageCallback = $this->getOnMessageCallback($callback, $getProcessCallback);

            try {
                $this->telegram_bot_api->onMessage($this->getTgUpdateId(), $onMessageCallback);
            } catch (Throwable $e) {
                report($e);
            }
        };
    }

    private function getOnMessageCallback(callable $callback, callable $getProcessCallback): callable
    {
        return function (string $update_id, array $requestData) use ($callback, $getProcessCallback) {
            $jobFn = $callback($requestData);

            if (!is_null($jobFn)) {
                $process = OctaneHelper::taskStart(fn() => $jobFn()->handle());
                $getProcessCallback($process);
            }

            $this->disk->put($this->configs['cache_key'], $update_id);
        };
    }

    private function getTgUpdateId(): int
    {
        try {
            return (int)$this->disk->get($this->configs['cache_key']);
        } catch (Throwable) {
            return 0;
        }
    }
}
