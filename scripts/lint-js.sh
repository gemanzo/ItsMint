#!/bin/sh

# Find docker executable
DOCKER_CMD=""
for path in /usr/local/bin/docker /opt/homebrew/bin/docker /usr/bin/docker; do
    if [ -x "$path" ]; then
        DOCKER_CMD="$path"
        break
    fi
done

if [ -z "$DOCKER_CMD" ]; then
    echo "Error: Docker not found. Please ensure Docker is installed and running."
    exit 1
fi

# Get container name from .env file
APP_NAME=$(grep '^APP_NAME=' .env 2>/dev/null | cut -d'=' -f2 || echo 'itsmint')
NODE_CONTAINER="node_${APP_NAME}"

# Convert absolute paths to relative paths for the container
RELATIVE_FILES=""
for file in "$@"; do
    # Remove the project root path to get relative path
    RELATIVE_FILE=${file#$(pwd)/}
    RELATIVE_FILES="$RELATIVE_FILES $RELATIVE_FILE"
done

# Execute eslint in the container with relative paths
$DOCKER_CMD exec $NODE_CONTAINER ./node_modules/.bin/eslint --fix --max-warnings=0 $RELATIVE_FILES 