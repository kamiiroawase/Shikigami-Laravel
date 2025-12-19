<?php

namespace App\Libs;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenaiApi
{
    public function __construct(protected array $data, protected bool $isDeepSeek = false)
    {
        //
    }

    public function chat(array $messages): array
    {
        $result = [
            'code' => 1,
            'telegram_text' => '',
        ];

        try {
            $response = $this->getClient()->post('/v1/chat/completions', array_merge([
                'model' => $this->data['model'],
                'messages' => $messages,
            ], str_starts_with($this->data['model'], 'gpt-5') ? [] : [
                'max_tokens' => 4096,
            ]));

            if ($response->ok()) {
                $result['telegram_text'] = $response->json()['choices'][0]['message']['content'] ?? null;
                if (is_null($result['telegram_text'])) {
                    $result['telegram_text'] = '傻逼 DeepSeek 返回了：' . $response->body();
                }
                else {
                    $result['code'] = 0;
                }
            }
            else {
                $result['telegram_text'] = '收到 API 信息：' . $response->body();
            }
        } catch (Throwable $exception) {
            $result['telegram_text'] = '处理出错：' . $exception->getMessage();
            report($exception);
        }

        return $result;
    }

    private function getClient(): PendingRequest
    {
        $client = $this->isDeepSeek
            ? Http::baseUrl('https://api.deepseek.com/')
            : Http::baseUrl('https://www.dmxapi.cn/');

        return $client->connectTimeout(6)
            ->timeout($this->isDeepSeek ? 150 : 60)
            ->retry(6, 100, function (Exception $e) {
                return $e instanceof ConnectionException;
            })
            ->withHeaders([
                'Authorization' => "Bearer {$this->data['key']}",
            ]);
    }
}
