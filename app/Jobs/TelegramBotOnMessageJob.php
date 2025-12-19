<?php

namespace App\Jobs;

use App\Libs\OctaneHelper;
use App\Libs\TelegramBotApi;
use App\Libs\TelegramBotTaskGen;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TelegramBotOnMessageJob extends QueueJob
{
    public function __construct(private readonly array $configs)
    {
        //
    }

    public function handle(): void
    {
        $disk = Storage::disk('local');
        $telegram_bot_api = new TelegramBotApi($this->configs['bot_token'], $this->configs['proxy']);
        $telegram_bot_task_gen = new TelegramBotTaskGen($this->configs);

        // 进入循环，在循环之前创建对象通过 use 传进去，防止循环内重复创建对象
        OctaneHelper::whileTrueForProcesses(
            function (callable $callback)
            use ($disk, $telegram_bot_api, $telegram_bot_task_gen) {
                try {
                    $tgUdId = (int)$disk->get($this->configs['cache_key']);
                } catch (Throwable) {
                    $tgUdId = 0;
                }

                try {
                    $telegram_bot_api->onMessage($tgUdId
                        , function (string $update_id, array $requestData)
                        use ($disk, $telegram_bot_task_gen, $callback) {
                            if (!is_null($jobFn = $telegram_bot_task_gen->getJobFn($requestData))) {
                                $callback(OctaneHelper::taskStart(fn() => $jobFn()->handle()));
                            }

                            $disk->put($this->configs['cache_key'], $update_id);
                        }
                    );
                } catch (Throwable $e) {
                    report($e);
                }
            }
            , true
        );
    }
}
