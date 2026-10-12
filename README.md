# 📈 Stock Market Portfolio Tracker

A Laravel-based stock portfolio tracking application with Telegram bot integration, automated daily reports, and real-time stock price updates via AlphaVantage API.

## 🚀 Features

- **Telegram Bot Integration**: Search stocks, add to portfolio, get instant recaps
- **Real-time Stock Prices**: Powered by AlphaVantage API
- **Automated Reports**: Daily email and Telegram notifications with P/L analysis
- **Performance Tracking**: Daily, MTD, and YTD performance metrics
- **RESTful API**: Full CRUD operations for portfolio management
- **Portfolio dashboard**: Sign in or create an account to review holdings, returns, and recent transactions, and record buys or sells.
- **Option chain preview**: Browse a clearly labeled simulated index option chain by underlying and expiry; connect a live options provider before relying on market values.
- **Angel One SmartAPI connection**: Authenticate from the option-chain panel; the PIN and TOTP are used only for login, while API credentials and returned tokens are encrypted in the signed-in session. After connecting, the option panel loads the nearest listed expiry for NIFTY by default and lists upcoming expiries for BANKNIFTY, SENSEX, and CRUDEOIL from Angel One's instrument master. The app uses Angel One's native Greeks endpoint where it works and falls back to live FULL quotes to calculate Black-Scholes/Black-76 Greeks for any supported underlying when native Greeks are unavailable. These locally calculated values are estimates from last-traded prices, not provider-published Greeks. The market quote endpoint requires exchange/token IDs and does not enumerate expiries.
- **Cloud-Ready**: Optimized for Google Cloud Run deployment
- **Type-Safe**: Strict PHP 8.4 typing with `declare(strict_types=1)`
- **Tested**: Comprehensive Pest test suite

## � Documentation

For detailed information on configuration and deployment, please refer to the following guides:

- [Deployment Guide](docs/DEPLOY_GUIDE.md) - Standard VPS and Docker deployment.
- [Google Cloud Run Guide](docs/GOOGLE_CLOUD_RUN_GUIDE.md) - Serverless deployment steps.
- [Google Drive Setup](docs/GOOGLE_DRIVE_SETUP.md) - How to configure cloud storage for reports.
- [Implementation Report](docs/IMPLEMENTATION_REPORT.md) - Project status and completed tasks.

## �📋 Requirements

- Docker & Docker Compose
- PHP 8.4+
- Docker Compose with PostgreSQL (local) or a PostgreSQL server (production)
- AlphaVantage API Key
- Telegram Bot Token (optional)

## 🛠️ Local Development

### Quick Start

1. **Clone and setup**:
   ```bash
   git clone <repository-url>
   cd stock-market-app
   ```

2. **Configure environment**:
   Edit `.env` and set your real integration credentials:
   - `ALPHA_VANTAGE_API_KEY`
   - `TELEGRAM_BOT_TOKEN` (optional)
   - `CRON_SECRET` (generate a random string)
   - For Angel One login, set `ANGEL_ONE_CLIENT_LOCAL_IP`, `ANGEL_ONE_CLIENT_PUBLIC_IP`, and `ANGEL_ONE_MAC_ADDRESS` to the app server's network identity. These values are sent as SmartAPI headers; the login form does not expose them. The request `state` is generated on the server for each login and is not user-provided.

   The local Compose setup starts its own persistent PostgreSQL database. Production deployments should use their configured PostgreSQL server.

3. **Start the application**:
   ```bash
   docker compose -f docker-compose.yml up -d --build
   ```

4. **Run migrations**:
   ```bash
   docker compose -f docker-compose.yml exec app php artisan migrate --force
   ```

5. **Access the app**:
   - Web: http://localhost:8081
   - API: http://localhost:8081/api
   - Create an account or sign in to open the portfolio dashboard. Trades entered in the dashboard are portfolio records only; no brokerage orders are placed.

   The local Compose stack also starts the queue worker and scheduler.

### Available Commands

```bash
# Start staging environment
./run staging

# Start production environment
./run production

# Run artisan commands
./sail artisan <command>

# Run tests
./test

# Manual stock fetch
./sail artisan stocks:fetch

# Generate daily report
./sail artisan report:daily

# Seed test data (Safe for staging/local)
./sail artisan db:seed-test
```

## 🤖 Telegram Bot Commands

- `/start` - Welcome message and command list
- `/search <symbol>` - Search for stock symbols (e.g., `/search AAPL`)
- `/add <symbol> <quantity>` - Add stocks to portfolio (e.g., `/add AAPL 10`)
- `/recap` - Get current portfolio summary with P/L

### Setting up Telegram Webhook

```bash
# Webhook now uses your bot token as a secret path for security
curl "https://api.telegram.org/bot<YOUR_TOKEN>/setWebhook?url=https://your-domain.com/api/telegram/webhook/<YOUR_TOKEN>"
```

## 📊 API Endpoints

### Portfolio Management (Authenticated)

```http
GET    /api/portfolio          # List all holdings
POST   /api/portfolio          # Add transaction
DELETE /api/portfolio/{id}     # Remove transaction
```

### Cron Jobs (Protected)

```http
GET /api/cron/fetch-stocks     # Trigger stock price update
GET /api/cron/daily-report     # Trigger daily report generation
```

## ☁️ Google Cloud Run Deployment

### Prerequisites

1. Install [Google Cloud SDK](https://cloud.google.com/sdk/docs/install)
2. Authenticate: `gcloud auth login`
3. Create a GCP project
4. Enable APIs:
   ```bash
   gcloud services enable run.googleapis.com
   gcloud services enable cloudscheduler.googleapis.com
   gcloud services enable containerregistry.googleapis.com
   ```

### Deploy

1. **Update configuration**:
   Edit `deploy.sh` and set `PROJECT_ID`

2. **Configure production environment**:
   The `.env.production` file contains dummy values. Edit it with your **real** production credentials.

3. **Deploy to Cloud Run**:
   ```bash
   ./deploy.sh production
   ```

4. **Setup Cloud Scheduler** (replaces cron):
   ```bash
   ./scheduler-setup.sh production
   ```

### Cost Estimation (Free Tier)

- **Cloud Run**: 2M requests/month FREE
  - Expected usage: ~3,000 requests/month
  - **Cost: $0/month**

- **Cloud Scheduler**: 3 jobs/month FREE
  - We use 2 jobs
  - **Cost: $0/month**

- **Supabase**: 500MB database FREE
  - Expected usage: <100MB
  - **Cost: $0/month**

**Total estimated cost: $0/month** ✅

## 🧪 Testing

```bash
# Run all tests
./test

# Run specific test file
./test tests/Feature/StockServiceTest.php

# Run with coverage
./sail artisan test --coverage
```

### 🛠️ Test Data Seeding

To get up and running quickly with a consistent environment, you can use the built-in test data seeder.

> [!WARNING]
> This command will **TRUNCATE** all data in the following tables: `users`, `tickers`, `transactions`, `daily_metrics`, `settings`, and `personal_access_tokens`. Use it only in development or staging environments.

```bash
./sail artisan db:seed-test
```

The seeder provides:
- **Admin User**: `admin@example.com` / `password`
- **Sample Users**: 5 additional random users
- **Tickers**: 10 active stock symbols
- **Daily Metrics**: 30 days of historical data per ticker
- **Transactions**: 15 transactions per user
- **Default Settings**: Pre-configured application settings

#### 🔗 Linking Telegram ID (Testing only)

For testing purposes, you can link your Telegram account to any existing user ID from the database using the `/start` command. This allows you to "impersonate" a specific user and view their portfolio.

1.  Find the UUID of the user you want to link (e.g., from the `users` table or seeder output).
2.  In Telegram, send: `/start <user_uuid>`

> [!NOTE]
> If the Telegram ID is already linked to another user, it will be automatically moved to the specified UUID to maintain the uniqueness of the `telegram_id` field.

### Test Coverage

- ✅ Stock Service (API integration, error handling)
- ✅ Report Service (daily/MTD/YTD calculations)
- ✅ Transaction Service (CRUD, holdings calculation)
- ✅ Market Hours Service (timezone handling)

## 📁 Project Structure

```
app/
├── Console/Commands/      # Artisan commands
│   ├── FetchStockData.php
│   └── GenerateDailyReport.php
├── Http/
│   ├── Controllers/       # API & Telegram controllers
│   └── Middleware/        # Cron authentication
├── Models/                # Eloquent models
│   ├── User.php
│   ├── Ticker.php
│   ├── Transaction.php
│   └── DailyMetric.php
├── Services/              # Business logic
│   ├── AlphaVantageService.php
│   ├── TelegramService.php
│   ├── StockService.php
│   ├── ReportService.php
│   ├── TransactionService.php
│   ├── MetricService.php
│   └── MarketHoursService.php
└── Mail/
    └── DailyPortfolioReport.php

database/
├── migrations/            # Database schema
└── factories/             # Model factories for testing

tests/
├── Feature/               # Integration tests
└── Unit/                  # Unit tests
```

## 🔒 Security

- **Cron endpoints** protected by bearer token or OIDC (Cloud Run)
- **API endpoints** protected by Laravel Sanctum
- **Database** connections use SSL in production
- **Passwords** hashed with bcrypt
- **CSRF** protection on web routes

## 📧 Email Reports

Daily reports are sent via email with:
- Portfolio summary
- Daily P/L per ticker
- Month-to-Date performance
- Year-to-Date performance
- CSV attachment with detailed data

Configure SMTP in `.env`:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-api-key
```

## 🐛 Troubleshooting

### Database connection fails
- Verify Supabase credentials in `.env`
- Check if Supabase project is active (not paused)
- Ensure SSL mode is correct (`prefer` for staging, `require` for production)

### Stock fetch returns no data
- Verify `ALPHA_VANTAGE_API_KEY` is valid
- Check API rate limits (5 requests/minute for free tier)
- Ensure market hours check is working

### Telegram bot not responding
- Verify webhook is set correctly
- Check `TELEGRAM_BOT_TOKEN` in `.env`
- Review logs: `./sail artisan log:tail`

## 📝 License

This project is open-source and available under the MIT License.

## 🤝 Contributing

Contributions are welcome! Please:
1. Fork the repository
2. Create a feature branch
3. Write tests for new features
4. Submit a pull request

## 📞 Support

For issues and questions, please open a GitHub issue.

---

Built with ❤️ using Laravel 12, PHP 8.4, and modern senior best practices.
# MD-Stock-Market
