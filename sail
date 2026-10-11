#!/bin/bash

# A quick wrapper to use docker exec like Sail
# Usage: ./sail artisan migrate

ENV=${1:-staging}
CONTAINER="stock-market-app-staging"
PREFIX="php"

if [ "$ENV" == "prod" ]; then
    CONTAINER="stock-market-app-prod"
    shift
else
    # If first arg is not prod, assume staging and don't shift unless it was explicitly 'staging'
    if [ "$ENV" == "staging" ]; then
        shift
    fi
fi

if [ "$1" == "composer" ]; then
    PREFIX="composer"
    shift
fi

docker exec -it $CONTAINER $PREFIX "$@"
