#!/bin/bash
echo "Get latest information from git"
git fetch --all
TAG=$(git describe --abbrev=0 --tags $(git rev-list --tags --max-count=1))

echo "Checkout to latest tag"
git checkout $TAG

echo "Install composer dependencies"
composer install

echo "clear cache"
php bin/console cache:clear

echo "run migrations"
php bin/console doctrine:migrations:migrate --no-interaction --query-time

echo "run upgrade script"
bash upgradefiles/upgrade.sh

echo "install assets"
php bin/console assets:install public

echo "warmup cache"
php bin/console cache:warmup