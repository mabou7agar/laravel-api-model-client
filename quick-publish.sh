#!/bin/bash

# Quick Publish Script for Laravel API Model Client
# Simple script for quick commits and tag creation

set -e

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}🚀 Quick Publish - Laravel API Model Client${NC}"
echo ""

# Get current version from composer.json
current_version=$(grep -o '"version": *"[^"]*"' composer.json | grep -o '[0-9]\+\.[0-9]\+\.[0-9]\+')
echo "Current version: $current_version"

# Increment patch version
IFS='.' read -ra VERSION_PARTS <<< "$current_version"
major=${VERSION_PARTS[0]}
minor=${VERSION_PARTS[1]}
patch=$((${VERSION_PARTS[2]} + 1))
new_version="$major.$minor.$patch"

echo "New version: $new_version"

# Update composer.json
if [[ "$OSTYPE" == "darwin"* ]]; then
    sed -i '' "s/\"version\": *\"[^\"]*\"/\"version\": \"$new_version\"/" composer.json
else
    sed -i "s/\"version\": *\"[^\"]*\"/\"version\": \"$new_version\"/" composer.json
fi

# Git operations
echo "Adding changes..."
git add .

echo "Committing..."
git commit -m "Release v$new_version"

echo "Creating tag..."
git tag -a "v$new_version" -m "Release v$new_version"

echo "Pushing to remote..."
git push origin master
git push origin --tags

echo -e "${GREEN}✅ Published v$new_version successfully!${NC}"
echo "Check Packagist.org for updates or manually trigger at:"
echo "https://packagist.org/packages/m-tech-stack/laravel-api-model-client"
