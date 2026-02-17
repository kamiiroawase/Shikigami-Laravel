<?php

namespace App\Libs;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class TelegramBotApi
{
    protected string $botToken;
    protected ?array $proxyConfig;

    public function __construct(string $botToken, ?array $proxyConfig = null)
    {
        $this->botToken = $botToken;
        $this->proxyConfig = $proxyConfig;
    }

    /**
     * @throws Throwable
     */
    public function getFile(string $fileId): string|null
    {
        $response = $this->getClient()->post('/getFile', [
            'file_id' => $fileId,
        ]);

        $data = $response->ok() ? $response->json() : null;

        $filePath = $data['result']['file_path'] ?? null;

        if (($data['ok'] ?? false) and !is_null($filePath)) {
            return "https://api.telegram.org/file/bot{$this->botToken}/{$filePath}";
        }

        return null;
    }

    /**
     * @throws Throwable
     */
    public function send(string $chatId, string $text): void
    {
        $this->getClient()->post('/sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
        ]);
    }

    /**
     * @throws Throwable
     */
    public function reply(string $chatId, string $messageId, string $text): void
    {
        $this->getClient()->post('/sendMessage', [
            'reply_parameters' => json_encode([
                'allow_sending_without_reply' => true,
                'message_id' => (int)$messageId,
                'chat_id' => (int)$chatId,
            ]),
            'chat_id' => $chatId,
            'text' => $text,
        ]);
    }

    /**
     * @throws Throwable
     */
    public function onMessage(int $tgUdId, callable $handleUpdateFn): void
    {
        $response = $this->getClient()->post('getUpdates', [
            'allowed_updates' => ['message'],
            'offset' => $tgUdId + 1,
        ]);

        $data = $response->ok() ? $response->json() : null;

        if (empty($data) or !($data['ok'] ?? false) or empty($data['result'])) {
            return;
        }

        foreach ($data['result'] as $result) {
            if (is_numeric($result['update_id'] ?? null)) {
                $handleUpdateFn((string)$result['update_id'], $result);
            }
        }
    }

    private function getClient(): PendingRequest
    {
        $client = Http::baseUrl("https://api.telegram.org/bot{$this->botToken}/");

        if (!is_null($this->proxyConfig)) {
            $client->withOptions([
                'proxy' => $this->proxyConfig['proxy'],
                'version' => $this->proxyConfig['version'],
            ]);
        }

        $client->connectTimeout(6)->timeout(30);

        return $client;
    }
}
