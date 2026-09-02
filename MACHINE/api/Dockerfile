FROM debian:latest
COPY api /var/www/html
COPY challenges /var/www/data/challenges
COPY --chmod=0700 init_db.sql /init_db.sql
COPY --chmod=0700 prepare.sh /prepare.sh
COPY --chmod=0700 prepareDB.sh /prepareDB.sh
COPY --chmod=0700 fillDBWithChallenges.php /fillDBWithChallenges.php
COPY --chmod=0700 firstUser.php /firstUser.php
RUN /prepare.sh
ENTRYPOINT ["/prepareDB.sh"]