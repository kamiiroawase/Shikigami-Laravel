<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class QueueJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    protected array $data = [];

    /**
     * @noinspection PhpUnused
     */
    abstract public function handle();

    /**
     * @noinspection PhpUnused
     */
    public function failed(Throwable $exception): void
    {
        $this->failedError($exception, $this->data);
    }

    protected function failedError(Throwable $exception, $jobData): void
    {
        $exceptionName = get_class($exception);
        $exceptionMessage = $exception->getMessage();

        Log::error("处理任务失败：【{$exceptionName}】{$exceptionMessage}", $jobData);
    }
}
