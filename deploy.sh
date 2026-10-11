#!/bin/bash

# Cloud Run Deployment Script
# Usage: ./deploy.sh [staging|production]

set -e

ENV=${1:-staging}

if [[ "$ENV" != "staging" && "$ENV" != "production" ]]; then
    echo "❌ Invalid environment. Use: ./deploy.sh [staging|production]"
    exit 1
fi

echo "🚀 Deploying to Cloud Run ($ENV)..."

# Configuration
PROJECT_ID="your-gcp-project-id"  # TODO: Replace with your GCP project ID
REGION="europe-west1"  # Change to your preferred region
SERVICE_NAME="stock-market-app-$ENV"
IMAGE_NAME="gcr.io/$PROJECT_ID/$SERVICE_NAME"

# Build the Docker image
echo "📦 Building Docker image..."
docker build -f Dockerfile -t $IMAGE_NAME .

# Push to Google Container Registry
echo "⬆️  Pushing image to GCR..."
docker push $IMAGE_NAME

# Deploy to Cloud Run
echo "🌐 Deploying to Cloud Run..."
gcloud run deploy $SERVICE_NAME \
    --image $IMAGE_NAME \
    --platform managed \
    --region $REGION \
    --allow-unauthenticated \
    --port 80 \
    --memory 512Mi \
    --cpu 1 \
    --min-instances 0 \
    --max-instances 10 \
    --env-vars-file .env.$ENV \
    --project $PROJECT_ID

# Get the service URL
SERVICE_URL=$(gcloud run services describe $SERVICE_NAME --platform managed --region $REGION --format 'value(status.url)' --project $PROJECT_ID)

echo ""
echo "✅ Deployment complete!"
echo "🔗 Service URL: $SERVICE_URL"
echo ""
echo "📋 Next steps:"
echo "1. Set Telegram webhook: https://api.telegram.org/bot<TOKEN>/setWebhook?url=\$SERVICE_URL/api/telegram/webhook/<TOKEN>"
echo "2. Configure Cloud Scheduler for cron jobs (see scheduler-setup.sh)"
