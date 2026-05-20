#!/bin/bash
# Deploy PFM Panel to panel.pfm-qa.com
# Usage: ./deploy.sh
# Run from: wp-content/plugins/pfm-panel/

set -e

REMOTE_USER="root"
REMOTE_HOST="31.220.56.146"
REMOTE_PATH="/var/www/panel.pfm-qa.com"

echo "🔨 Building for production..."
cd app && npm run build && cd ..

echo "🚀 Uploading to $REMOTE_HOST..."
scp dist/assets/app.js $REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH/assets/app.js
scp dist/index.html    $REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH/index.html

echo "✅ Deployed successfully to https://panel.pfm-qa.com"
