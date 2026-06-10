# AiDvor Market

Separate Laravel application for `market.aidvor.ru`.

## Local Run

From repository root:

```bash
docker compose up -d market-db market-server
docker compose exec -T market-server php artisan migrate --seed
```

Open:

- storefront: `http://localhost:3100`
- admin login: `http://localhost:3100/admin/login`

Default local administrator is created by `php artisan migrate --seed`:

- email: `admin@aidvor.ru`
- password: `MARKET_ADMIN_PASSWORD`

For production set `MARKET_ADMIN_PASSWORD` to a strong secret outside the repository.

## Assets

This app uses Vite + Tailwind.

Host Node must be compatible with Vite 8, or build through Docker:

```bash
docker run --rm -v "$PWD":/app -w /app/market-app node:22 npm install
docker run --rm -v "$PWD":/app -w /app/market-app node:22 npm run build
```

## Checks

```bash
docker compose exec -T market-server php artisan test --testsuite=Feature
```

## Telegram Requests

Set these values in the production environment:

```env
MARKET_TELEGRAM_ENABLED=true
MARKET_TELEGRAM_BOT_USERNAME=
MARKET_TELEGRAM_BOT_TOKEN=
MARKET_TELEGRAM_ADMIN_CHAT_ID=
MARKET_TELEGRAM_WEBHOOK_SECRET=
```

Webhook URL:

```text
https://market.aidvor.ru/api/telegram/webhook/<MARKET_TELEGRAM_WEBHOOK_SECRET>
```

Product links use:

```text
https://t.me/<bot_username>?start=item_<slug>
```

Cart links use short-lived context tokens:

```text
https://t.me/<bot_username>?start=cart_<token>
```
