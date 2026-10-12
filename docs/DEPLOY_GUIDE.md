# 🚀 DEPLOYMENT GUIDE: Stock Market App

This guide summarizes everything needed to put the application into production. The architecture has been optimized to be robust, asynchronous, and secure.

---

## 1. 🔑 Environment Configuration (Setup .env.production)

> **Important:** The `.env.production` (and `.env.staging`) files are committed to the repository with **dummy values** to protect sensitive information on GitHub.

Before launching the app, ensure you have replaced these placeholders with your real credentials in the `.env.production` file:

| Service | Variable | Where to find it |
| :--- | :--- | :--- |
| **Database** | `DB_HOST` | Supabase project URL (e.g., `db.xyz.supabase.co`) |
| | `DB_PASSWORD` | DB password chosen during creation |
| **Telegram** | `TELEGRAM_BOT_TOKEN` | From [@BotFather](https://t.me/botfather) |
| | `TELEGRAM_BOT_NAME`| Bot username on Telegram |
| **Email (SMTP)**| `MAIL_PASSWORD` | API Key from Brevo/SendGrid/Mailgun |
| **Security** | `CRON_SECRET` | Generate a random string (e.g., 32 characters) |

---

## 2. 🏗️ Deployment Strategy (Recommended: VPS)

The app is configured to run optimally with **Docker Compose**. This is the simplest and most performant path.

### Server Steps:
1. **Clone the project** onto the server.
2. **Configure environment**: 
   ```bash
   cp .env.production .env
   ```
3. **Start everything**: 
   ```bash
   ./run production
   ```
   *This command will start the application, web proxy, WebSocket server, background worker, and scheduler (plus the local database when using the local Compose file):*
   - `app`: The Laravel core.
   - `web`: Nginx to handle requests.
   - `websocket`: Laravel Reverb; Nginx proxies same-origin WebSocket upgrades.
   - `worker`: To process prices and reports in the background.
   - `scheduler`: To refresh active option subscriptions every 10 seconds and handle scheduled jobs.

4. **Initialize the DB**:
   ```bash
   ./sail artisan migrate --force
   ```

---

## ☁️ Special: Google Cloud Platform (GCP) Deployment

The single-container `deploy.sh` Cloud Run deployment does not start the Reverb server or the continuously running Laravel scheduler. Use the Docker Compose VPS deployment above for live option updates, or deploy Reverb and the market-refresh scheduler as separate always-on services and configure the WebSocket proxy accordingly.

If you prefer using **Google Cloud Run** instead of a VPS, you need to handle Crons and Queues differently:

### 1. Cloud Scheduler (Replaces Laravel Scheduler)
Since Cloud Run is serverless and "spins down" when there are no requests, the built-in scheduler won't run. You must use **Google Cloud Scheduler** to call the secret endpoints we've prepared:

1. Run the script: `./scheduler-setup.sh production`
2. The script will configure two Jobs on your GCP panel:
   - `fetch-stock-data`: Every 30 min (Calls `/api/cron/fetch-stocks`)
   - `daily-report`: Every day at 19:00 (Calls `/api/cron/daily-report`)

### 2. Queue Management
Cloud Run is not designed for a constantly active `queue:work` (it would be expensive).
- **Recommendation**: If using the AlphaVantage free tier (which requires 12-second delays), **use a VPS**.
- **If you insist on GCP**: You must change `QUEUE_CONNECTION=sync` in the `.env.production` file. 
  - *Warning*: This will make API calls synchronous. If you have many tickers, the request might timeout or block the bot until finished.

---

## 4. 🏁 Telegram Webhook Activation (CRITICAL)

Without this step, the bot will not receive messages. We have implemented a **secret path** for security. Replace `<TOKEN>` and `your-domain.com`:

```bash
curl "https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://your-domain.com/api/telegram/webhook/<TOKEN>"
```

---

## 🛠️ Maintenance and Logs

### See what the bot is doing:
```bash
./sail artisan log:tail
```

### See Job status (price updates):
```bash
docker logs stock-market-worker-prod -f
```

### Force an immediate manual fetch:
```bash
./sail artisan stocks:fetch
```

### Send daily reports instantly:
```bash
./sail artisan report:daily
```

---

Declared victory. The system is now a battleship ready for combat! 🥂🚀
