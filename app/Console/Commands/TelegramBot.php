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

    private array $configs;

    private array $processes = [];

    public function __construct()
    {
        parent::__construct();

        $this->configs = config('shikigami.telegram_bots');
    }

    public function handle(): void
    {
        // 子进程管理启动
        OctaneHelper::boot();

        // 获取子进程列表
        $this->getProcesses();

        // 等待子进程结束
        $this->waitProcesses();
    }

    private function waitProcesses(): void
    {
        foreach ($this->processes as $process) {
            try {
                $process->wait();
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    private function getProcesses(): void
    {
        foreach ($this->configs as $config) {
            try {
                $this->processes[] = OctaneHelper::taskStart(
                    fn() => new TelegramBotOnMessageJob($config)->handle()
                );
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
