<?php

namespace App\Libs;

use Illuminate\Console\Application;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Process\InvokedProcess;
use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Laravel\SerializableClosure\SerializableClosure;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\Telescope;
use Throwable;

class OctaneHelper
{
    public static string $shutdownCacheKey = 'be89d18e-3114-428e-b7ed-cfa698ed402a';

    /**
     * 子进程管理启动，通过文件系统管理状态
     */
    public static function boot(): void
    {
        $disk = self::getShutdownCallbackDisk();

        $disk->put(self::$shutdownCacheKey, '1');

        pcntl_signal(SIGINT, function () use ($disk) {
            $disk->put(self::$shutdownCacheKey, '0');
        });

        pcntl_signal(SIGTERM, function () use ($disk) {
            $disk->put(self::$shutdownCacheKey, '0');
        });
    }

    /**
     * 进入任务循环，但是等待一组进程完成
     */
    public static function whileTrueForProcesses(callable $callback, bool $resetLog = false): void
    {
        $processes = [];

        static::whileTrue($callback, function () use (&$processes) {
            $isUnset = false;

            /** @var InvokedProcess $process */
            foreach ($processes as $processIndex => $process) {
                if (!$process->running()) {
                    try {
                        $process->wait();
                    } catch (Throwable $e) {
                        report($e);
                    }
                    unset($processes[$processIndex]);
                    $isUnset = true;
                }
            }

            if ($isUnset) {
                $processes = array_values($processes);
            }
        }, $processes, $resetLog);

        /** @var InvokedProcess $process */
        foreach ($processes as $process) {
            try {
                $process->wait();
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * 进入任务循环，但是多个循环并行运行，无等待进程完成
     */
    public static function whileTrue(callable $callback, ?callable $frontCallback = null, ?array &$processes = null, bool $resetLog = false): void
    {
        if ($resetLog) {
            /** @var LogManager $logManager */
            $logManager = app('log');
        }

        $disk = self::getShutdownCallbackDisk();

        try {
            $entriesRepository = app(EntriesRepository::class);
        } catch (Throwable) {
            $entriesRepository = null;
        }

        $getProcessCallback = function (InvokedProcess $process) use (&$processes) {
            $processes[] = $process;
        };

        while (true) {
            if (!is_null($frontCallback)) {
                $frontCallback();
            }

            try {
                if ($disk->get(self::$shutdownCacheKey) === '0') {
                    break;
                }
            } catch (Throwable $e) {
                report($e);
            }

            if (!is_null($entriesRepository)) {
                Telescope::store($entriesRepository);
            }

            if ($resetLog) {
                $logChannels = array_keys($logManager->getChannels());

                foreach ($logChannels as $channel) {
                    $logManager->forgetChannel($channel);
                }
            }

            $callback($getProcessCallback);
        }
    }

    /**
     * 子进程启动
     */
    public static function taskStart(callable $task): InvokedProcess
    {
        return Process::forever()->path(base_path())
            ->env(['LARAVEL_INVOKABLE_CLOSURE' => serialize(new SerializableClosure($task))])
            ->start(Application::formatCommandString('invoke-serialized-closure'));
    }

    /**
     * 获取文件管理器
     */
    private static function getShutdownCallbackDisk(): Filesystem
    {
        return Storage::disk('local');
    }
}
