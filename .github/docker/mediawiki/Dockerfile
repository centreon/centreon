FROM mediawiki:1.45

COPY database/my_wiki.sql /tmp/
COPY database/my_wiki_jobqueue.sql /tmp/
COPY database/my_wiki_l10n_cache.sql /tmp/
COPY database/wikicache.sql /tmp/
COPY LocalSettings.php /var/www/html/

RUN bash -e <<EOF

apt-get update
apt-get install -y sqlite3
apt-get clean
rm -rf /var/lib/apt/lists/*

sqlite3 /var/www/data/my_wiki.sqlite < /tmp/my_wiki.sql
sqlite3 /var/www/data/my_wiki_jobqueue.sqlite < /tmp/my_wiki_jobqueue.sql
sqlite3 /var/www/data/my_wiki_l10n_cache.sqlite < /tmp/my_wiki_l10n_cache.sql
sqlite3 /var/www/data/wikicache.sqlite < /tmp/wikicache.sql

chown -R www-data:www-data /var/www/data

EOF
