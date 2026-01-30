<?php

namespace App\Jobs;

use App\Libs\OpenaiApi;
use App\Libs\TelegramBotApi;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OpenaiRequestJob extends QueueJob
{
    public int $timeout;

    public function __construct(array $data)
    {
        $this->data = $data;

        $this->onQueue('openai');

        $this->timeout = $this->data['openai_type'] === 'deepseek_chat' ? 300 : 180;
    }

    /**
     * @throws ConnectionException
     *
     * @noinspection PhpUnused
     */
    public function handle(): void
    {
        $result = $this->prepareOpenaiChatMessages();

        match ($result['code']) {
            0 => (function () use ($result) {
                if ($this->data['is_private_chat']) {
                    if ($this->data['admin_chat_id'] !== (string)$this->data['chat_id']) {
                        $this->toAdmin(
                            "【收到用户消息】\n@{$this->data['from_username']}:\n"
                            . $this->data['message_text']
                            . (is_null($result['photo_url']) ? '' : "\n{$result['photo_url']}")
                        );
                    }
                }

                if (!is_null($result['photo_url']) and !$this->data['vision']) {
                    $this->reply('模型未支持图片识别功能');
                    return;
                }

                $api = new OpenaiApi([
                    'api_url' => $this->data['openai_api_url'],
                    'proxy' => $this->data['openai_proxy'],
                    'model' => $this->data['openai_model'],
                    'key' => $this->data['openai_key'],
                ]);

                $chatResult = $api->chat($result['chat_messages']);

                if ($chatResult['code'] >= 0) {
                    if (!empty($chatResult['telegram_text'])) {
                        $this->reply($chatResult['telegram_text']);
                    }
                }
            })(),

            1 => $this->reply($result['telegram_text']),

            default => null,
        };
    }

    /**
     * @throws ConnectionException
     */
    protected function reply(string $text): void
    {
        new TelegramBotSendJob([
            'chat_id' => $this->data['chat_id'],
            'message_id' => $this->data['message_id'],
            'bot_token' => $this->data['bot_token'],
            'proxy' => $this->data['proxy'],
            'text' => $text,
        ])->handle();
    }

    /**
     * @throws ConnectionException
     */
    protected function toAdmin(string $text): void
    {
        new TelegramBotSendJob([
            'chat_id' => $this->data['admin_chat_id'],
            'bot_token' => $this->data['bot_token'],
            'proxy' => $this->data['proxy'],
            'text' => $text,
        ])->handle();
    }

    /**
     * @throws ConnectionException
     */
    protected function prepareOpenaiChatMessages(): array
    {
        $result = [
            'code' => 0,
            'photo_url' => null,
            'telegram_text' => '获取图片失败',
            'chat_messages' => !is_null($this->data['chat_messages'])
                ? $this->data['chat_messages']
                : [],
        ];

        $text = mb_substr($this->data['message_text'], mb_strlen($this->data['command']));

        $replyText = $this->data['message_reply_text'];

        if (is_string($replyText) and !$this->data['is_bot_self']) {
            if (str_starts_with($replyText, $this->data['command'])) {
                $replyText = mb_substr($replyText, mb_strlen($this->data['command']));
            }
        }

        if (!is_null($this->data['file_id'])) {
            $result['photo_url'] = new TelegramBotApi($this->data['bot_token'], $this->data['proxy'])
                ->getFile($this->data['file_id']);

            if (is_null($result['photo_url'])) {
                $result['code'] = 1;
                return $result;
            }

            $file_request_client = Http::connectTimeout(6)->timeout(30);

            $file_request_client->retry(3, 100, function (Exception $e) {
                return $e instanceof ConnectionException;
            });

            if (!is_null($this->data['proxy'])) {
                $file_request_client->withOptions([
                    'proxy' => $this->data['proxy']['proxy'],
                    'version' => $this->data['proxy']['version'],
                ]);
            }

            $file_request_response = $file_request_client->get($result['photo_url']);

            if ($file_request_response->ok()) {
                $userContent = [
                    [
                        'type' => 'text',
                        'text' => $text,
                    ], [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => 'data:image/jpeg;base64,'
                                . base64_encode($file_request_response->body()),
                        ],
                    ],
                ];
            }

            else {
                return $result;
            }
        }

        else {
            $userContent = $text;
        }

        if (is_string($replyText)) {
            if ($this->data['openai_type'] === 'deepseek_chat' and $this->data['is_bot_self']) {
                $result['chat_messages'][] = [
                    'role' => 'user',
                    'content' => '',
                ];
            }

            $result['chat_messages'][] = [
                'role' => $this->data['is_bot_self'] ? 'assistant' : 'user',
                'content' => $replyText,
            ];

            if ($this->data['openai_type'] === 'deepseek_chat' and !$this->data['is_bot_self']) {
                $result['chat_messages'][] = [
                    'role' => 'assistant',
                    'content' => '',
                ];
            }
        }

        $result['chat_messages'][] = [
            'role' => 'user',
            'content' => $userContent,
        ];

        return $result;
    }
}
