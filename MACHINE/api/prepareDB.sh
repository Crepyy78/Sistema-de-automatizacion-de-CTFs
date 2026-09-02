#!/bin/bash

grant_group_access() {
    path="$1"
    fallback_name="$2"
    gid=$(stat -c '%g' "$path")
    group=$(getent group "$gid" | cut -d: -f1)
    if [ -z "$group" ]; then
        group="$fallback_name"
        groupadd -g "$gid" "$group"
    fi
    usermod -aG "$group" www-data
    echo "$group"
}

grant_group_access /var/run/docker.sock dockerhost > /dev/null

CHAL_GROUP=$(grant_group_access /var/www/data/challenges chalhost)
chgrp -R "$CHAL_GROUP" /var/www/data/challenges
chmod -R g+w /var/www/data/challenges

mkdir -p /var/www/.docker
chown www-data:www-data /var/www/.docker

mariadbd-safe --datadir=/var/lib/mysql &

until mysqladmin ping --silent; do
  sleep 1
done

mysql -u root < /init_db.sql

php firstUser.php

echo "" > /firstUser.php

php fillDBWithChallenges.php

echo "" > /fillDBWithChallenges.php

exec apache2ctl -D FOREGROUND
