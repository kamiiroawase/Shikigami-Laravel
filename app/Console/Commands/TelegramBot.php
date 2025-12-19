<?php

namespace App\Console\Commands;

use App\Jobs\TelegramBotOnMessageJob;
use App\Libs\OctaneHelper;
use Throwable;

/**
 * @noinspection PhpUnused
 */

class TelegramBot extends Command
{
    protected $signature = 'telegram-bot';

    public function handle(): void
    {
        // 子进程管理启动
        OctaneHelper::boot();

        // 获取子进程列表
        $processes = $this->getProcesses();

        // 等待子进程结束
        $this->waitProcesses($processes);
    }

    private function waitProcesses(array $processes): void
    {
        foreach ($processes as $process) {
            try {
                $process->wait();
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    private function getProcesses(): array
    {
        $processes = [];

        $configs = config('shikigami.telegram_bots');

        foreach ($configs as $config) {
            try {
                $processes[] = OctaneHelper::taskStart(
                    fn() => new TelegramBotOnMessageJob($config)->handle()
                );
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $processes;
    }
}
