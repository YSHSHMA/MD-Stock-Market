# 🚀 Google Cloud Run Deployment Guide

## Prerequisites

### 1. Install Google Cloud SDK
```bash
# macOS
brew install --cask google-cloud-sdk

# Or download from: https://cloud.google.com/sdk/docs/install
```

### 2. Authenticate
```bash
gcloud auth login
gcloud auth configure-docker
```

### 3. Create GCP Project
```bash
# Create new project
gcloud projects create stock-market-app-prod --name="Stock Market App"

# Set as default
gcloud config set project stock-market-app-prod

# Enable billing (required for Cloud Run)
# Visit: https://console.cloud.google.com/billing
```

### 4. Enable Required APIs
```bash
gcloud services enable run.googleapis.com
gcloud services enable cloudscheduler.googleapis.com
gcloud services enable containerregistry.googleapis.com
gcloud services enable cloudresourcemanager.googleapis.com
```

---

## 📝 Configuration

### 1. Update `deploy.sh`
```bash
# Edit deploy.sh and replace:
PROJECT_ID="stock-market-app-prod"  # Your actual project ID
REGION="europe-west1"                # Your preferred region
```

### 2. Configure `.env.production`

> **Note:** `.env.production` is safely committed to the repository using dummy values.

```bash
# Edit .env.production and replace the dummy values with your production credentials:

# Database (Supabase)
DB_HOST=aws-1-eu-west-1.pooler.supabase.com
DB_USERNAME=postgres.xxxxxxxxxxxxx
DB_PASSWORD=your-secure-password
DB_DATABASE=postgres

# AlphaVantage API
ALPHA_VANTAGE_API_KEY=your-alpha-vantage-api-key

# Telegram Bot
TELEGRAM_BOT_TOKEN=123456789:ABCdefGHIjklMNOpqrsTUVwxyz
TELEGRAM_BOT_NAME=YourBotName

# Email (SendGrid recommended)
MAIL_HOST=smtp.sendgrid.net
MAIL_USERNAME=apikey
MAIL_PASSWORD=SG.xxxxxxxxxxxxxxxxxxxxxxxx

# Cron Secret (optional, OIDC is primary)
CRON_SECRET=  # Leave empty to rely on OIDC only
```

---

## 🚀 Deployment Steps

### Step 1: Deploy to Cloud Run
```bash
./deploy.sh production
```

This will:
1. Build Docker image
2. Push to Google Container Registry
3. Deploy to Cloud Run
4. Output the service URL

**Expected output:**
```
✅ Deployment complete!
🔗 Service URL: https://stock-market-app-production-xxxxxxxxx-ew.a.run.app
```

### Step 2: Setup Cloud Scheduler
```bash
# Update scheduler-setup.sh with your PROJECT_ID
./scheduler-setup.sh production
```

This creates two Cloud Scheduler jobs:
- `fetch-stock-data-production`: Every 30 min (9AM-6PM IT, Mon-Fri)
- `daily-report-production`: Daily at 7PM IT

### Step 3: Configure Telegram Webhook
```bash
# Replace <TOKEN> and <SERVICE_URL> with your values
curl "https://api.telegram.org/bot<TOKEN>/setWebhook?url=<SERVICE_URL>/api/telegram/webhook"
```

**Example:**
```bash
curl "https://api.telegram.org/bot123456789:ABCdef/setWebhook?url=https://stock-market-app-production-abc123-ew.a.run.app/api/telegram/webhook"
```

### Step 4: Run Initial Migration
```bash
# Get Cloud Run service name
SERVICE_NAME="stock-market-app-production"
REGION="europe-west1"

# Run migration via Cloud Run
gcloud run jobs create migrate-db \
  --image gcr.io/stock-market-app-prod/stock-market-app-production \
  --region $REGION \
  --command php \
  --args "artisan,migrate,--force" \
  --env-vars-file .env.production

gcloud run jobs execute migrate-db --region $REGION
```

---

## 🔍 Verification

### 1. Check Cloud Run Service
```bash
gcloud run services describe stock-market-app-production \
  --region europe-west1 \
  --format="value(status.url)"
```

### 2. Test Cron Endpoints
```bash
SERVICE_URL="https://your-service-url.run.app"

# Test fetch-stocks (should return 401 without auth)
curl $SERVICE_URL/api/cron/fetch-stocks

# Test with CRON_SECRET (if configured)
curl -H "Authorization: Bearer your-cron-secret" \
  $SERVICE_URL/api/cron/fetch-stocks
```

### 3. Check Cloud Scheduler Jobs
```bash
gcloud scheduler jobs list --location europe-west1
```

### 4. Test Telegram Bot
Send `/start` to your bot on Telegram

---

## 📊 Monitoring

### View Logs
```bash
# Real-time logs
gcloud run services logs tail stock-market-app-production \
  --region europe-west1

# Filter by severity
gcloud run services logs read stock-market-app-production \
  --region europe-west1 \
  --filter="severity>=ERROR"
```

### Check Metrics
```bash
# Visit Cloud Console
https://console.cloud.google.com/run/detail/europe-west1/stock-market-app-production/metrics
```

### Monitor Costs
```bash
# Visit Billing Dashboard
https://console.cloud.google.com/billing
```

---

## 🔧 Troubleshooting

### Issue: "Permission denied" during deployment
**Solution:**
```bash
# Grant yourself necessary roles
gcloud projects add-iam-policy-binding stock-market-app-prod \
  --member="user:your-email@gmail.com" \
  --role="roles/run.admin"

gcloud projects add-iam-policy-binding stock-market-app-prod \
  --member="user:your-email@gmail.com" \
  --role="roles/iam.serviceAccountUser"
```

### Issue: Cloud Scheduler jobs fail with 403
**Solution:**
```bash
# Ensure service account has invoker role
SA_EMAIL="cloud-scheduler-invoker@stock-market-app-prod.iam.gserviceaccount.com"

gcloud run services add-iam-policy-binding stock-market-app-production \
  --member="serviceAccount:$SA_EMAIL" \
  --role="roles/run.invoker" \
  --region europe-west1
```

### Issue: Database connection fails
**Solution:**
1. Check Supabase project is active (not paused)
2. Verify credentials in `.env.production`
3. Ensure `DB_SSLMODE=require` is set
4. Check Cloud Run logs for detailed error

### Issue: High cold start latency
**Solution:**
```bash
# Set minimum instances to 1 (will incur small cost)
gcloud run services update stock-market-app-production \
  --region europe-west1 \
  --min-instances 1
```

---

## 🔄 Updates & Rollbacks

### Deploy New Version
```bash
# Just run deploy again
./deploy.sh production
```

### Rollback to Previous Version
```bash
# List revisions
gcloud run revisions list \
  --service stock-market-app-production \
  --region europe-west1

# Rollback to specific revision
gcloud run services update-traffic stock-market-app-production \
  --region europe-west1 \
  --to-revisions REVISION_NAME=100
```

---

## 💰 Cost Optimization Tips

1. **Use Cloud Run's scale-to-zero**: Already configured (min-instances=0)
2. **Optimize container size**: Multi-stage Dockerfile already implemented
3. **Use Cloud Scheduler wisely**: Only 2 jobs configured (within free tier)
4. **Monitor API usage**: AlphaVantage free tier = 5 calls/min, 500/day
5. **Use Supabase free tier**: 500MB database, 2GB bandwidth/month

---

## 🎯 Production Checklist

- [ ] GCP project created and billing enabled
- [ ] APIs enabled (Run, Scheduler, Container Registry)
- [ ] `.env.production` configured with real credentials
- [ ] `deploy.sh` updated with correct PROJECT_ID
- [ ] Application deployed to Cloud Run
- [ ] Cloud Scheduler jobs created
- [ ] Telegram webhook configured
- [ ] Database migrated
- [ ] Logs monitored for errors
- [ ] Test all Telegram commands
- [ ] Test cron jobs manually
- [ ] Verify email reports are sent
- [ ] Monitor costs in GCP Console

---

## 📞 Support

If you encounter issues:
1. Check Cloud Run logs: `gcloud run services logs tail ...`
2. Verify environment variables: `gcloud run services describe ...`
3. Test endpoints manually with `curl`
4. Review [Cloud Run documentation](https://cloud.google.com/run/docs)

---

**Estimated deployment time: 15-20 minutes**  
**Expected monthly cost: $0 (Free Tier)**

Good luck! 🚀
