# Running integration tests locally

## 1. Start the MySQL container

```bash
docker run -d \
  --name centreon-test-db \
  -e MYSQL_ROOT_PASSWORD=password \
  -e MYSQL_DATABASE=centreon \
  -e MYSQL_USER=centreon \
  -e MYSQL_PASSWORD=password \
  -p 3306:3306 \
  mysql:8.0
```

Then create the second database:

```bash
docker exec centreon-test-db mysql -uroot -ppassword \
  -e "CREATE DATABASE centreon_storage; GRANT ALL ON centreon_storage.* TO 'centreon'@'%';"
```

## 2. Configure environment variables

Create `centreon/.env.test.local`:

```
db=centreon
dbcstg=centreon_storage
user=centreon
password=password
hostCentreon=127.0.0.1
hostCentstorage=127.0.0.1
port=3306
```

## 3. Run the tests

From `centreon/centreon/`:

```bash
# App tests (API Platform)
php -d memory_limit=512M vendor/bin/phpunit -c phpunit.new.xml --no-coverage

# Core tests (Symfony controllers)
php -d memory_limit=512M vendor/bin/phpunit -c phpunit.core-integration.xml --no-coverage
```

## 4. Reset the database (if needed)

If tables are corrupted or you want a fresh start:

```bash
docker exec centreon-test-db mysql -uroot -ppassword \
  -e "DROP DATABASE centreon; CREATE DATABASE centreon; DROP DATABASE centreon_storage; CREATE DATABASE centreon_storage; GRANT ALL ON centreon.* TO 'centreon'@'%'; GRANT ALL ON centreon_storage.* TO 'centreon'@'%';"
```

The PHP bootstrap automatically recreates tables and base data on the next run.

## 5. Run a specific test

```bash
php -d memory_limit=512M vendor/bin/phpunit -c phpunit.new.xml --no-coverage --filter TestName
```
