#!/bin/bash

source .env
containerName="php_$APP_NAME"
docker exec -it -u 0 $containerName pecl install xdebug
docker exec -it -u 0 $containerName touch /var/log/xdebug.log
chown -R 33 /var/log/

docker cp ./docker/configs/phpfpm/xdebug.ini $containerName:/usr/local/etc/php/conf.d/.
