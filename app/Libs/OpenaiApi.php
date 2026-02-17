<?php

namespace App\Libs;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenaiApi
{
    public function __construct(protected array $data)
    {
        //
    }

    /**
     * @throws Throwable
     */
    public function chat(array $messages): array
    {
        $result = [
            'code' => 1,
            'telegram_text' => '',
        ];

        $response = $this->getClient()->post('/v1/chat/completions', [
            'model' => $this->data['model'],
            'messages' => $messages,
        ]);

        if ($response->ok()) {
            $result['telegram_text'] = $response->json()['choices'][0]['message']['content'] ?? null;

            if (is_null($result['telegram_text'])) {
                $result['telegram_text'] = "傻逼 {$this->data['model']} 返回了：{$response->body()}";
            }

            else {
                $result['code'] = 0;
            }
        }

        else {
            $result['telegram_text'] .= '收到 API 信息：' . $response->body();
        }

        return $result;
    }

    private function getClient(): PendingRequest
    {
        $client = Http::baseUrl($this->data['api_url']);

        $client->connectTimeout(6)->timeout(60);

        $client->withHeaders([
            'Authorization' => "Bearer {$this->data['key']}",
        ]);

        if (!is_null($this->data['proxy'])) {
            $client->withOptions([
                'proxy' => $this->data['proxy']['proxy'],
                'version' => $this->data['proxy']['version'],
            ]);
        }

        return $client;
    }
}
