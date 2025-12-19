<?php

namespace App\Libs;

use App\Jobs\OpenaiRequestJob;
use App\Jobs\TelegramBotSendJob;
use Illuminate\Support\Facades\RateLimiter;

readonly class TelegramBotTaskGen
{
    public function __construct(private array $configs)
    {
        //
    }

    public function getJobFn(array $requestData): callable|null
    {
        $result = $this->shouldHandle($requestData);

        // 这里开始不能使用 $this->，否则反序列化会失败
        return match ($result['code']) {
            0 => fn() => new OpenaiRequestJob([
                'proxy' => $result['configs']['proxy'],
                'bot_token' => $result['configs']['bot_token'],
                'admin_chat_id' => $result['configs']['admin_chat_id'],

                'command' => $result['command'],
                'vision' => $result['command_data']['vision'],
                'openai_key' => $result['command_data']['key'],
                'openai_type' => $result['command_data']['type'],
                'openai_model' => $result['command_data']['model'],
                'chat_messages' => $result['command_data']['options']['chat_messages'],

                'chat_id' => $result['request_data']['message']['chat']['id'],
                'message_id' => $result['request_data']['message']['message_id'],
                'from_username' => $result['request_data']['message']['from']['username'] ?? '',

                'file_id' => $result['file_id'],
                'is_bot_self' => $result['is_bot_self'],
                'message_text' => $result['message_text'],
                'is_private_chat' => $result['is_private_chat'],
                'message_reply_text' => $result['message_reply_text'],
            ]),
            1 => fn() => new TelegramBotSendJob([
                'proxy' => $result['configs']['proxy'],
                'bot_token' => $result['configs']['bot_token'],

                'chat_id' => $result['request_data']['message']['chat']['id'],
                'message_id' => $result['request_data']['message']['message_id'],

                'text' => $result['telegram_text'],
            ]),
            default => null,
        };
    }

    private function shouldHandle(array $requestData): array
    {
        $result = [
            'code' => -1,
            'configs' => $this->configs,
            'request_data' => [],
            'is_bot_self' => false,
            'is_private_chat' => false,

            'message_text' => null,
            'message_reply_text' => null,

            'command' => [],
            'command_data' => [],
            'command_hit' => false,

            'telegram_text' => '',
            'file_id' => null,
        ];

        if (!$this->determineRequestData($requestData, $result)) {
            return $result;
        }

        if (!$this->determineCommandHit($result)) {
            return $result;
        }

        $cacheKey = 'ccd611fd-ba88-4e59-a4e7-bd451188fc94::'
            . $result['request_data']['message']['from']['id'];

        switch ($result['command_data']['type']) {
            case 'start':
                $remaining = RateLimiter::remaining($cacheKey, 1);

                RateLimiter::hit($cacheKey);

                if ($remaining > 0) {
                    $result['telegram_text'] = $result['command_data']['options']['say'];
                    $result['code'] = 1;
                }
                break;
            case 'openai_chat':
            case 'deepseek_chat':
                $remaining = RateLimiter::remaining($cacheKey, 12);

                RateLimiter::hit($cacheKey);

                if ($remaining === 0) {
                    $result['telegram_text'] = '您的请求过于频繁，请稍后再试';
                    $result['code'] = 1;
                }

                if ($remaining > 0) {
                    if (!$this->determineRequestFile($result)) {
                        $result['telegram_text'] = '抱歉！暂无法识别动图或视频！';
                        $result['code'] = 1;
                    }
                    else {
                        $result['code'] = 0;
                    }
                }
                break;
        }

        return $result;
    }

    private function determineRequestData(array $requestData, array &$result): bool
    {
        $result['request_data'] = $requestData;

        $result['is_private_chat'] = $result['request_data']['message']['chat']['type'] === 'private';

        if (!empty($this->configs['allowed_chat_ids'])) {
            if (!in_array($result['request_data']['message']['chat']['id'], $this->configs['allowed_chat_ids'])) {
                if ($result['request_data']['message']['from']['is_bot'] ?? true) {
                    return false;
                }
                if (!$result['is_private_chat']) {
                    return false;
                }
            }
        }

        $result['message_text'] = (
            $result['request_data']['message']['text']
            ?? $result['request_data']['message']['caption']
            ?? null
        );

        if (!is_string($result['message_text'])) {
            return false;
        }

        if (!str_starts_with($result['message_text'], '/')) {
            return false;
        }

        $result['message_reply_text'] = (
            $result['request_data']['message']['reply_to_message']['text']
            ?? $result['request_data']['message']['reply_to_message']['caption']
            ?? null
        );

        if (!is_null($result['message_reply_text']) and !is_string($result['message_reply_text'])) {
            return false;
        }

        $messageFromId = (string)($result['request_data']['message']['reply_to_message']['from']['id'] ?? null);

        $result['is_bot_self'] = $messageFromId === $this->configs['bot_chat_id'];

        return true;
    }

    private function determineCommandHit(array &$result): bool
    {
        $bot_username = $this->configs['bot_username'];

        foreach ($this->configs['commands'] as $command => $value) {
            if (!str_starts_with($result['message_text'], $command)) {
                continue;
            }

            if ($value['type'] === 'start') {
                if (str_starts_with($result['message_text'], "{$command}@")) {
                    if (str_starts_with($result['message_text'], "{$command}@{$bot_username}")) {
                        $result['command_hit'] = true;
                    }
                }
                else {
                    $result['command_hit'] = true;
                }

                if ($result['command_hit']) {
                    $result['command_data'] = $value;
                    $result['command'] = $command;
                }

                break;
            }

            if (str_starts_with($result['message_text'], "{$command}@")) {
                if (str_starts_with($result['message_text'], "{$command}@{$bot_username} ")) {
                    $result['command_hit'] = true;
                }
            }

            elseif (str_starts_with($result['message_text'], "{$command} ")) {
                $result['command_hit'] = true;
            }

            if ($result['command_hit']) {
                if (in_array($result['message_text'], ["{$command} ", "{$command}@{$bot_username} "])) {
                    $result['command_hit'] = false;
                    break;
                }

                $result['command_data'] = $value;
                $result['command'] = $command;
            }

            break;
        }

        return $result['command_hit'];
    }

    private function determineRequestFile(array &$result): bool
    {
        $photos = $result['request_data']['message']['photo']
            ?? $result['request_data']['message']['reply_to_message']['photo']
            ?? null;

        if (is_null($photos)) {
            $sticker = $result['request_data']['message']['sticker']
                ?? $result['request_data']['message']['reply_to_message']['sticker']
                ?? null;

            if (!is_null($sticker)) {
                if ($sticker['is_video'] or $sticker['is_animated']) {
                    return false;
                }

                $result['file_id'] = $sticker['file_id'];
            }
            else {
                $video = $result['request_data']['message']['video']
                    ?? $result['request_data']['message']['reply_to_message']['video']
                    ?? null;

                if (!is_null($video)) {
                    return false;
                }
            }
        }
        else {
            $result['file_id'] = end($photos)['file_id'];
        }

        return true;
    }
}
