#!/bin/bash

# Convert absolute paths to relative paths for the container
RELATIVE_FILES=""
for file in "$@"; do
    # Remove the project root path to get relative path
    RELATIVE_FILE=${file#$(pwd)/}
    RELATIVE_FILES="$RELATIVE_FILES $RELATIVE_FILE"
done

# Execute prettier in the container with relative paths
docker exec node_itsmint ./node_modules/.bin/prettier --write $RELATIVE_FILES