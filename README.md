# High Availability RESTful JSON API
A skeleton application using the Slim Framework with PSR-7 and PHP-DI container implementation. 
See: https://php-di.org/
This application was built for Composer making setup quick and easy.

## Documentation: https://www.slimframework.com/

## Prepare Database
For better perfomance NDB Cluster or in MEMORY(heap tables) should be considered
In this implementation we opted for fine grained control with on the wire caching 
between the VM instance(s) and the MySQL Server(s) with proxySQL.
This allows us to scale to 100K+ connections across thousands of servers and simplifies performance monitoring and security.
See https://proxysql.com/

```bash
CREATE DATABASE IF NOT EXISTS inventory;
```

As per https://datatracker.ietf.org/doc/html/rfc7159 Json documents is supported since MySQL 5.8 which approximate to the storage of LONGBLOB or LONGTEXT data.
Create a table with a Json Data Type See: https://www.mysqltutorial.org/mysql-json/

```bash
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sku` varchar(255) NOT NULL COMMENT 'Unique Stock Keeping Code',
  `attributes` json NOT NULL COMMENT 'Variable key value pairs',
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Products Table';
```

We use the PHP Data Objects (PDO) extension which provides a data-access abstraction layer, 
so regardless of database the same functions can be used to issue queries and fetch data.
Simply install the driver for Oracle, PostgreSQL, MySQL or other preferred database. 
See: https://www.php.net/manual/en/book.pdo.php


## Install the Application
Run this command from the directory in which you want to install your new Slim Framework application.

```bash
composer create-project slim/slim-skeleton [my-app-name]
```

Replace `[my-app-name]` with the desired directory name for your new application. You'll want to:

* Point your virtual host document root to your new application's `public/` directory.
* Ensure `logs/` is web writable.

To run the application in development, you can run these commands 

```bash
cd [my-app-name]
composer start
```

Or you can use `docker-compose` to run the app with `docker`, so you can run these commands:
```bash
cd [my-app-name]
docker-compose up -d
```
After that, open `http://localhost:8080` in your browser.

Run this command in the application directory to run the test suite

```bash
composer test
```
