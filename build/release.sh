#/bin/bash

NEXT_VERSION=$1
CURRENT_VERSION=$(cat composer.json | grep version | head -1 | awk -F= "{ print $2 }" | sed 's/[version:,\",]//g' | tr -d '[[:space:]]')

sudo composer dump-autoload -oa

zip -r ./build/laravel-log-monitor-$NEXT_VERSION.zip ./CHANGELOG.md ./README.md -q
