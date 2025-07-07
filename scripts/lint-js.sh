#!/bin/sh

# Convert absolute paths to relative paths for the container
RELATIVE_FILES=""
for file in "$@"; do
    # Remove the project root path to get relative path
    RELATIVE_FILE=${file#$(pwd)/}
    RELATIVE_FILES="$RELATIVE_FILES $RELATIVE_FILE"
done

# Execute eslint in the container with relative paths
docker exec node_itsmint ./node_modules/.bin/eslint --fix --max-warnings=0 $RELATIVE_FILES 