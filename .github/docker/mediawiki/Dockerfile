FROM mediawiki:1.45

COPY database/my_wiki.sqlite /var/www/data/
COPY database/my_wiki_l10n_cache.sqlite /var/www/data/
COPY database/wikicache.sqlite /var/www/data/
COPY database/my_wiki_jobqueue.sqlite /var/www/data/
RUN chown -R www-data:www-data /var/www/data

COPY LocalSettings.php /var/www/html/
