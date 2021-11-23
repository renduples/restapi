# High Availability RESTful JSON API
A skeleton application using the Slim PHP Framework with PSR-7 and PHP-DI container implementation. 
See: https://php-di.org/
Optional scale your API with proxySQL

## Documentation: https://www.slimframework.com/

Use Composer for setting up a skeleton API with no database (Option A).

OR

Run git clone for a skeleton API with a sample database (Option B). 

## A) Install API with no Database

From console:
```bash
composer create-project slim/slim-skeleton [my-app-name]
```
Replace `[my-app-name]` with the desired directory name for your new application. You'll want to:

* Point your virtual host document root to the `public/` directory.
* Ensure `logs/` is web writable.

## B) Install API with sample Database
We use the PHP Data Objects (PDO) extension which provides a data-access abstraction layer, 
so regardless of database the same functions can be used to issue queries and fetch data.
Simply install the driver for Oracle, PostgreSQL, MySQL or other preferred database. 
See: https://www.php.net/manual/en/book.pdo.php

From console:
```bash
git clone git@github.com:renduples/temprest.git
```
For better perfomance NDB Cluster or in MEMORY(heap tables) should be considered. For this implementation we opted for fine grained control with on the wire caching between the VM instance(s) and the MySQL Server(s) with proxySQL. This allows us to scale to 100K+ connections across thousands of servers and simplifies performance monitoring and security.
See https://proxysql.com/

Connect to your MySQL server and create the sample inventory database:
```bash
CREATE DATABASE IF NOT EXISTS inventory;
```

Configure a dedicated user to monitor your MySQL server with ProxySQL
and a dedicated MySQL user for the REST API - the credentials should match those used in `app/settings`
````bash
CREATE USER 'monitor'@'%' IDENTIFIED BY 'a-strong-mysql-monitor_password';
GRANT SELECT on sys.* to 'monitor'@'%';
CREATE USER 'rest-api'@'localhost' IDENTIFIED BY 'a-strong-rest-api_password';
GRANT ALL PRIVILEGES ON inventory.* TO 'rest-api'@'localhost' IDENTIFIED BY 'a-strong-rest-api_password';
FLUSH PRIVILEGES;
```

Import the sample products table located at `src/Infrastructure/inventory.sql`
From the application root dir, do something like this:
```bash
mysql -u rest-api -pa-strong-rest-api_password -h localhost inventory<src/Infrastructure/inventory.sql
```

Alternatively create an empty products table with the Json Data Type 
See: https://www.mysqltutorial.org/mysql-json/

As per https://datatracker.ietf.org/doc/html/rfc7159 Json documents is supported since MySQL 5.8 which approximate to the storage of LONGBLOB or LONGTEXT data.

If you chose not to import sample data, connect to your MySQL server and create an empty products table:
```bash
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sku` varchar(255) NOT NULL COMMENT 'Unique Stock Keeping Code',
  `attributes` json NOT NULL COMMENT 'Variable key value pairs',
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Products Table';
```

## Install and configure proxySQL
Follow the instructions here https://proxysql.com/documentation/installing-proxysql/

Once installed, test and start the proxySQL service
```bash
proxysql --version
service proxysql start
```

Lets do a basic configuration by connecting to the Admin Interface
```bash
mysql -u admin -padmin -h 127.0.0.1 -P6032 --prompt='Admin> '
```

Set the credentials for the monitor user
```bash
UPDATE global_variables SET variable_value='monitor' WHERE variable_name='mysql-monitor_username';
UPDATE global_variables SET variable_value='monitor' WHERE variable_name='a-strong-mysql-monitor_password';
LOAD MYSQL VARIABLES TO RUNTIME;
SAVE MYSQL VARIABLES TO DISK;
```

And add the MySQL user. The credentials should match those used in `app/settings`
```bash
INSERT INTO mysql_users(username,password,default_hostgroup) VALUES ('rest-api','a-strong-rest-api_password',1);
LOAD MYSQL USERS TO RUNTIME;
SAVE MYSQL USERS TO DISK;
```

Monitoring intervals can be viewed like this
```bash
SELECT * FROM global_variables WHERE variable_name LIKE 'mysql-monitor_%';
```

Now add at least one Backend MySQL Server
```bash
INSERT INTO mysql_servers(hostgroup_id,hostname,port) VALUES (1,'localhost',3306);
LOAD MYSQL SERVERS TO RUNTIME;
SAVE MYSQL SERVERS TO DISK;
```

Check your MySQL Server(s) health with
```bash
SELECT * FROM mysql_servers;
```






ProxySQL is now ready to serve traffic on port 6603 (by default):


To run the application in development, you can use these commands 

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
