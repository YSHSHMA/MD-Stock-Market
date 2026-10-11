#!/bin/bash

# Cloud Scheduler Setup for Cron Jobs
# This replaces the need for a persistent worker container

set -e

ENV=${1:-staging}
PROJECT_ID="your-gcp-project-id"  # TODO: Replace with your GCP project ID
REGION="europe-west1"
SERVICE_NAME="stock-market-app-$ENV"

# Get the Cloud Run service URL
SERVICE_URL=$(gcloud run services describe $SERVICE_NAME --platform managed --region $REGION --format 'value(status.url)' --project $PROJECT_ID)

echo "🕐 Setting up Cloud Scheduler jobs for $ENV..."

# Create a service account for Cloud Scheduler (if not exists)
SA_NAME="cloud-scheduler-invoker"
SA_EMAIL="$SA_NAME@$PROJECT_ID.iam.gserviceaccount.com"

gcloud iam service-accounts create $SA_NAME \
    --display-name "Cloud Scheduler Invoker" \
    --project $PROJECT_ID || echo "Service account already exists"

# Grant invoker role to the service account
gcloud run services add-iam-policy-binding $SERVICE_NAME \
    --member="serviceAccount:$SA_EMAIL" \
    --role="roles/run.invoker" \
    --region=$REGION \
    --project=$PROJECT_ID

# Job 1: Fetch Stock Data (every 30 minutes, 9AM-6PM Italian time)
echo "📊 Creating job: fetch-stock-data..."
gcloud scheduler jobs create http fetch-stock-data-$ENV \
    --location=$REGION \
    --schedule="*/30 9-18 * * 1-5" \
    --time-zone="Europe/Rome" \
    --uri="$SERVICE_URL/api/cron/fetch-stocks" \
    --http-method=GET \
    --oidc-service-account-email=$SA_EMAIL \
    --oidc-token-audience=$SERVICE_URL \
    --project=$PROJECT_ID || echo "Job already exists, updating..."

gcloud scheduler jobs update http fetch-stock-data-$ENV \
    --location=$REGION \
    --schedule="*/30 9-18 * * 1-5" \
    --time-zone="Europe/Rome" \
    --uri="$SERVICE_URL/api/cron/fetch-stocks" \
    --http-method=GET \
    --oidc-service-account-email=$SA_EMAIL \
    --oidc-token-audience=$SERVICE_URL \
    --project=$PROJECT_ID || true

# Job 2: Daily Report (every day at 7PM Italian time)
echo "📧 Creating job: daily-report..."
gcloud scheduler jobs create http daily-report-$ENV \
    --location=$REGION \
    --schedule="0 19 * * *" \
    --time-zone="Europe/Rome" \
    --uri="$SERVICE_URL/api/cron/daily-report" \
    --http-method=GET \
    --oidc-service-account-email=$SA_EMAIL \
    --oidc-token-audience=$SERVICE_URL \
    --project=$PROJECT_ID || echo "Job already exists, updating..."

gcloud scheduler jobs update http daily-report-$ENV \
    --location=$REGION \
    --schedule="0 19 * * *" \
    --time-zone="Europe/Rome" \
    --uri="$SERVICE_URL/api/cron/daily-report" \
    --http-method=GET \
    --oidc-service-account-email=$SA_EMAIL \
    --oidc-token-audience=$SERVICE_URL \
    --project=$PROJECT_ID || true

echo ""
echo "✅ Cloud Scheduler jobs configured!"
echo ""
echo "📋 Jobs created:"
echo "  - fetch-stock-data-$ENV: Every 30 min (9AM-6PM IT, Mon-Fri)"
echo "  - daily-report-$ENV: Daily at 7PM IT"
echo ""
echo "🔍 View jobs: gcloud scheduler jobs list --location=$REGION --project=$PROJECT_ID"
