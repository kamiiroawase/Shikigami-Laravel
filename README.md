## 关于本项目

- 自用分享，Telegram 大模型聊天机器人
- 部署于个人电脑虚拟机，24小时拉取消息（支持代理）
- 访问机器人：https://t.me/momiji_shikigami_bot

## 如何使用

本项目使用 PHP 框架 Laravel 12，通过 Docker 运行

首先进入目录，从模板复制配置文件

```bash
cp ./.env.example ./.env && cp ./config/shikigami.php.example ./config/shikigami.php
```

修改配置文件，填入 Telegram Bot Token 和 Openai Api 兼容大模型 Key

修改配置 bot_chat_id，bot_username，admin_chat_id，allowed_chat_ids

```bash
vim ./config/shikigami.php
```

设置目录权限

```bash
chmod 777 ./storage ./storage/logs ./storage/sockets ./bootstrap/cache
```

从这里开始需要 Docker

通过 Composer 安装 PHP 依赖库，若服务器在国内，注意配置好代理

```bash
sudo docker run --rm -it --volume $PWD:/app --user $(id -u):$(id -g) composer:2.9.1 --ignore-platform-req=ext-pcntl install --no-dev
```

构建镜像，若服务器在国内，注意配置好代理

```bash
sudo docker compose build
```

机器人，启动！

```bash
sudo docker compose up -d
```
