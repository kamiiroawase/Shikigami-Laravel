<?php

namespace App\Libs;

use App\Jobs\OpenaiRequestJob;
use App\Jobs\TelegramBotSendJob;
use Illuminate\Support\Facades\RateLimiter;

class TelegramBotTaskGenerator
{
    private array $result;

    public function __construct(private readonly array $configs)
    {
        //
    }

    private function initResult(): void
    {
        $this->result = [
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
    }

    public function taskGen(array $request_data): void
    {
        // 重置结果
        $this->initResult();

        // 处理结果
        $this->handleResult($request_data);

        // 生成请求任务
        $this->doTaskGen();
    }

    private function doTaskGen(): void
    {
        $result = $this->result;

        // 这里开始不能使用 $this->，否则反序列化会失败
        match ($result['code']) {
            0 => dispatch(new OpenaiRequestJob([
                'proxy' => $result['configs']['proxy'],
                'bot_token' => $result['configs']['bot_token'],
                'admin_chat_id' => $result['configs']['admin_chat_id'],

                'command' => $result['command'],
                'vision' => $result['command_data']['vision'],
                'openai_key' => $result['command_data']['key'],
                'openai_type' => $result['command_data']['type'],
                'openai_model' => $result['command_data']['model'],
                'openai_proxy' => $result['command_data']['proxy'],
                'openai_api_url' => $result['command_data']['api_url'],
                'chat_messages' => $result['command_data']['options']['chat_messages'],

                'chat_id' => $result['request_data']['message']['chat']['id'],
                'message_id' => $result['request_data']['message']['message_id'],
                'from_username' => $result['request_data']['message']['from']['username'] ?? '',

                'file_id' => $result['file_id'],
                'is_bot_self' => $result['is_bot_self'],
                'message_text' => $result['message_text'],
                'is_private_chat' => $result['is_private_chat'],
                'message_reply_text' => $result['message_reply_text'],
            ]))->onQueue('default'),

            1 => dispatch(new TelegramBotSendJob([
                'chat_id' => $result['request_data']['message']['chat']['id'],
                'message_id' => $result['request_data']['message']['message_id'],
                'bot_token' => $result['configs']['bot_token'],
                'proxy' => $result['configs']['proxy'],
                'text' => $result['telegram_text'],
            ]))->onQueue('high'),

            default => null,
        };
    }

    private function handleResult(array $request_data): void
    {
        if (!$this->determineRequestData($request_data)) {
            return;
        }

        if (!$this->determineCommandHit()) {
            return;
        }

        $cache_key = 'ccd611fd-ba88-4e59-a4e7-bd451188fc94::'
            . $this->result['request_data']['message']['from']['id'];

        switch ($this->result['command_data']['type']) {
            case 'start':
                $remaining = RateLimiter::remaining($cache_key, 1);

                RateLimiter::hit($cache_key);

                if ($remaining > 0) {
                    $this->result['telegram_text'] = $this->result['command_data']['options']['say'];
                    $this->result['code'] = 1;
                }
                break;
            case 'openai_chat':
            case 'deepseek_chat':
                $remaining = RateLimiter::remaining($cache_key, 12);

                RateLimiter::hit($cache_key);

                if ($remaining === 0) {
                    $this->result['telegram_text'] = '您的请求过于频繁，请稍后再试';
                    $this->result['code'] = 1;
                }

                if ($remaining > 0) {
                    if (!$this->determineRequestFile()) {
                        $this->result['telegram_text'] = '抱歉！暂无法识别动图或视频！';
                        $this->result['code'] = 1;
                    }
                    else {
                        $this->result['code'] = 0;
                    }
                }
                break;
        }
    }

    private function determineRequestData(array $request_data): bool
    {
        $this->result['request_data'] = $request_data;

        $this->result['is_private_chat'] = $this->result['request_data']['message']['chat']['type'] === 'private';

        // 群聊不在允许 Chat id 列表内则返回 false （允许私聊）
        if (!empty($this->configs['allowed_chat_ids'])) {
            if (!in_array($this->result['request_data']['message']['chat']['id'], $this->configs['allowed_chat_ids'])) {
                if ($this->result['request_data']['message']['from']['is_bot'] ?? true) {
                    return false;
                }
                if (!$this->result['is_private_chat']) {
                    return false;
                }
            }
        }

        $this->result['message_text'] =
            $this->result['request_data']['message']['text']
            ?? $this->result['request_data']['message']['caption']
            ?? null;

        if (!is_string($this->result['message_text'])) {
            return false;
        }

        if (!str_starts_with($this->result['message_text'], '/')) {
            return false;
        }

        $this->result['message_reply_text'] =
            $this->result['request_data']['message']['reply_to_message']['text']
            ?? $this->result['request_data']['message']['reply_to_message']['caption']
            ?? null;

        if (!is_null($this->result['message_reply_text']) and !is_string($this->result['message_reply_text'])) {
            return false;
        }

        $message_from_id = (string)($this->result['request_data']['message']['reply_to_message']['from']['id'] ?? null);

        $this->result['is_bot_self'] = $message_from_id === $this->configs['bot_chat_id'];

        return true;
    }

    private function determineCommandHit(): bool
    {
        $bot_username = $this->configs['bot_username'];

        $determine_command_hit_fn = function (string $command, bool $with_space) use ($bot_username) {
            if (str_starts_with($this->result['message_text'], "{$command}@{$bot_username}")) {
                if ($with_space) {
                    if (str_starts_with($this->result['message_text'], "{$command}@{$bot_username} ")) {
                        if ($this->result['message_text'] !== "{$command}@{$bot_username} ") {
                            $this->result['command_hit'] = true;
                        }
                    }
                }
                elseif ($this->result['message_text'] === "{$command}@{$bot_username}") {
                    $this->result['command_hit'] = true;
                }
            }

            elseif ($with_space) {
                if (str_starts_with($this->result['message_text'], "{$command} ")) {
                    if ($this->result['message_text'] !== "{$command} ") {
                        $this->result['command_hit'] = true;
                    }
                }
            }

            elseif ($this->result['message_text'] === $command) {
                $this->result['command_hit'] = true;
            }
        };

        $command_hit_fn = function (string $command, array $value) {
            if ($this->result['command_hit']) {
                $this->result['command_data'] = $value;
                $this->result['command'] = $command;
            }
        };

        foreach ($this->configs['commands'] as $command => $value) {
            if (!str_starts_with($this->result['message_text'], $command)) {
                continue;
            }

            if ($value['type'] === 'start') {
                $determine_command_hit_fn($command, false);
            }

            else {
                $determine_command_hit_fn($command, true);
            }

            $command_hit_fn($command, $value);

            break;
        }

        return $this->result['command_hit'];
    }

    private function determineRequestFile(): bool
    {
        $photos = $this->result['request_data']['message']['photo']
            ?? $this->result['request_data']['message']['reply_to_message']['photo']
            ?? null;

        if (!is_null($photos)) {
            $this->result['file_id'] = end($photos)['file_id'];
            return true;
        }

        $sticker = $this->result['request_data']['message']['sticker']
            ?? $this->result['request_data']['message']['reply_to_message']['sticker']
            ?? null;

        if (!is_null($sticker)) {
            if ($sticker['is_video'] or $sticker['is_animated']) {
                return false;
            }

            $this->result['file_id'] = $sticker['file_id'];
            return true;
        }

        $video = $this->result['request_data']['message']['video']
            ?? $this->result['request_data']['message']['reply_to_message']['video']
            ?? null;

        if (!is_null($video)) {
            return false;

//            $this->result['file_id'] = $sticker['file_id'];
//            return true;
        }

        return true;
    }
}
