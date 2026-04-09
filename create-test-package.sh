#!/bin/bash
set -e

echo "Creating test package for Family Travel Tracker..."

# Step 1: Install Stripe PHP library
echo "Step 1: Installing Stripe PHP library..."
if [ ! -d "lib/stripe-php" ]; then
    mkdir -p lib
    cd lib
    wget https://github.com/stripe/stripe-php/archive/refs/tags/v13.10.0.tar.gz
    tar -xzf v13.10.0.tar.gz
    mv stripe-php-13.10.0 stripe-php
    rm v13.10.0.tar.gz
    cd ..
    echo "✓ Stripe library installed"
else
    echo "✓ Stripe library already exists"
fi

# Step 2: Run build script
echo "Step 2: Building package..."
bash build-package.sh 2.0.0

echo ""
echo "✓ Test package created!"
echo "  Location: download/schedule-collaboration-tracking-v2.0.0.zip"
echo ""
echo "Next steps:"
echo "  1. Upload to test WordPress site"
echo "  2. Configure Stripe test keys in Settings"
echo "  3. Test checkout flow with card: 4242 4242 4242 4242"
