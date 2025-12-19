<?php

namespace App\Jobs;

use App\Libs\TelegramBotApi;
use Illuminate\Http\Client\ConnectionException;

class TelegramBotSendJob extends QueueJob
{
    public function __construct(array $data)
    {
        $this->data = $data;

        $this->onQueue('telegram');
    }

    /**
     * @throws ConnectionException
     *
     * @noinspection PhpUnused
     */
    public function handle(): void
    {
        $api = new TelegramBotApi($this->data['bot_token'], $this->data['proxy']);

        if (is_null($this->data['message_id'] ?? null)) {
            $api->send($this->data['chat_id'], $this->data['text']);
        }
        else {
            $api->reply($this->data['chat_id'], $this->data['message_id'], $this->data['text']);
        }
    }
}
