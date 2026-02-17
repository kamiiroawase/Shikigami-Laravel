<?php

namespace App\Jobs;

use App\Libs\TelegramBotApi;
use Throwable;

class TelegramBotSendJob extends QueueJob
{
    public function __construct(protected array $data)
    {
        //
    }

    /**
     * @throws Throwable
     *
     * @noinspection PhpUnused
     */
    public function handle(): void
    {
        $api = new TelegramBotApi($this->data['bot_token'], $this->data['proxy']);

        if (!is_null($this->data['message_id'] ?? null)) {
            $api->reply($this->data['chat_id'], $this->data['message_id'], $this->data['text']);
        }

        else {
            $api->send($this->data['chat_id'], $this->data['text']);
        }
    }
}
