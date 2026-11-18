# Running integration tests locally

## 1. Start the MySQL container

```bash
docker run -d \
  --name centreon-test-db \
  -e MYSQL_ROOT_PASSWORD=password \
  -e MYSQL_USER=centreon \
  -e MYSQL_PASSWORD=password \
  -p 3306:3306 \
  mysql:8.0
```

The PHP bootstrap automatically creates the databases, grants privileges, and populates tables on the first run.

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

If your MySQL root user has a different password, add:

```
rootUser=root
rootPassword=your_root_password
```

## 3. Run the tests

From `centreon/`:

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
  -e "DROP DATABASE centreon; DROP DATABASE centreon_storage;"
```

The PHP bootstrap automatically recreates everything on the next run.

## 5. Run a specific test

```bash
php -d memory_limit=512M vendor/bin/phpunit -c phpunit.new.xml --no-coverage --filter TestName
```
