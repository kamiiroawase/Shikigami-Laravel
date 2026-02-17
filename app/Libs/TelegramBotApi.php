<?php

namespace App\Libs;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class TelegramBotApi
{
    protected string $bot_token;
    protected ?array $proxy_config;

    public function __construct(string $bot_token, ?array $proxy_config = null)
    {
        $this->bot_token = $bot_token;
        $this->proxy_config = $proxy_config;
    }

    /**
     * @throws Throwable
     */
    public function getFile(string $file_id): string|null
    {
        $response = $this->getClient()->post('/getFile', [
            'file_id' => $file_id,
        ]);

        $data = $response->ok() ? $response->json() : null;

        $file_path = $data['result']['file_path'] ?? null;

        if (($data['ok'] ?? false) and !is_null($file_path)) {
            return "https://api.telegram.org/file/bot{$this->bot_token}/{$file_path}";
        }

        return null;
    }

    /**
     * @throws Throwable
     */
    public function send(string $chat_id, string $text): void
    {
        $this->getClient()->post('/sendMessage', [
            'chat_id' => $chat_id,
            'text' => $text,
        ]);
    }

    /**
     * @throws Throwable
     */
    public function reply(string $chat_id, string $message_id, string $text): void
    {
        $this->getClient()->post('/sendMessage', [
            'reply_parameters' => json_encode([
                'allow_sending_without_reply' => true,
                'message_id' => (int)$message_id,
                'chat_id' => (int)$chat_id,
            ]),
            'chat_id' => $chat_id,
            'text' => $text,
        ]);
    }

    /**
     * @throws Throwable
     */
    public function onMessage(int $last_update_id, callable $handle_update_fn): void
    {
        $response = $this->getClient()->post('getUpdates', [
            'allowed_updates' => ['message'],
            'offset' => $last_update_id + 1,
        ]);

        $data = $response->ok() ? $response->json() : null;

        if (empty($data) or !($data['ok'] ?? false) or empty($data['result'])) {
            return;
        }

        foreach ($data['result'] as $result) {
            if (is_numeric($result['update_id'] ?? null)) {
                $handle_update_fn((string)$result['update_id'], $result);
            }
        }
    }

    private function getClient(): PendingRequest
    {
        $client = Http::baseUrl("https://api.telegram.org/bot{$this->bot_token}/");

        if (!is_null($this->proxy_config)) {
            $client->withOptions([
                'proxy' => $this->proxy_config['proxy'],
                'version' => $this->proxy_config['version'],
            ]);
        }

        $client->connectTimeout(6)->timeout(30);

        return $client;
    }
}
