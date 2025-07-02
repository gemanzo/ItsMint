#!/bin/bash

# Convert absolute paths to relative paths for the container
RELATIVE_FILES=""
for file in "$@"; do
    # Remove the project root path to get relative path
    RELATIVE_FILE=${file#$(pwd)/}
    RELATIVE_FILES="$RELATIVE_FILES $RELATIVE_FILE"
done

# Execute pint in the container with relative paths
docker exec php_itsmint ./vendor/bin/pint $RELATIVE_FILES 