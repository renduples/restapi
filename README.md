# High Availability RESTful JSON API
This application uses the Slim PHP Framework with PSR-7 and PHP-DI autowire container implementation. 

See: https://php-di.org/

For this implementation we opted for fine grained control with on the wire caching between the Web Server(s) and the MySQL Server(s) using proxySQL. This allows us to scale to 100K+ connections across hundreds of servers, with simplified performance monitoring, caching and security. 

See https://proxysql.com/

### Documentation: https://www.slimframework.com/

To install a skeleton API for development simply clone the project in a web directory [my-app-dir]
```bash
git clone git@github.com:renduples/restapi.git
```

Install or Update dependencies
```bash
cd [my-app-dir]
composer install
```

Test the application in development, without a database
```bash
composer start
```

Or use `docker-compose` to run the app with `docker`
```bash
docker-compose up -d
```

After that, open `http://localhost:8080/users` in your browser.

You should see output like this:
```bash
{
    "statusCode": 200,
    "data": [
        {
            "id": 1,
            "username": "bill.gates",
            "firstName": "Bill",
            "lastName": "Gates"
        },
        {
            "id": 2,
            "username": "steve.jobs",
            "firstName": "Steve",
            "lastName": "Jobs"
        },
        {
            "id": 3,
            "username": "mark.zuckerberg",
            "firstName": "Mark",
            "lastName": "Zuckerberg"
        },
        {
            "id": 4,
            "username": "evan.spiegel",
            "firstName": "Evan",
            "lastName": "Spiegel"
        },
        {
            "id": 5,
            "username": "jack.dorsey",
            "firstName": "Jack",
            "lastName": "Dorsey"
        }
    ]
}
```

To run the unit test suite your App directory must be writable:
```bash
sudo chown -R $USER:$USER [my-app-dir]
composer test
```

## Run the application with ProxySQL and a sample Database
Configure your web server to point to the `public` directory.

See: https://www.slimframework.com/docs/v4/start/web-servers.html

Set the correct file permissions:
```bash
sudo chown www-data:www-data -R [my-app-dir]
sudo find [my-app-dir] -type d -exec chmod 755 {} \;
sudo find [my-app-dir] -type f -exec chmod 644 {} \;
```

Connect to your MySQL server and create the sample inventory database:
```bash
CREATE DATABASE IF NOT EXISTS inventory;
```

Configure a dedicated user to monitor your MySQL server(s) with ProxySQL
and create a dedicated MySQL user for the REST API.

The credentials should match those used in your `app/settings`:
```bash
CREATE USER 'monitor'@'%' IDENTIFIED BY 'a-strong-mysql-monitor_password';
GRANT SELECT on sys.* to 'monitor'@'%';
CREATE USER 'rest-api'@'127.0.0.1' IDENTIFIED BY 'a-strong-rest-api_password';
GRANT ALL PRIVILEGES ON inventory.* TO 'rest-api'@'127.0.0.1' IDENTIFIED BY 'a-strong-rest-api_password';
FLUSH PRIVILEGES;
```

Import the sample products table located at `src/Infrastructure/inventory.sql`.

As per https://datatracker.ietf.org/doc/html/rfc7159 Json documents is supported since MySQL 5.8 which approximates to the storage of LONGBLOB or LONGTEXT data. 

See: https://www.mysqltutorial.org/mysql-json/

```bash
cd [my-app-dir]
mysql -u rest-api -pa-strong-rest-api_password -h localhost inventory<src/Infrastructure/inventory.sql
```

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
Follow these instructions https://proxysql.com/documentation/installing-proxysql/

Once installed, test and start the proxySQL service
```bash
proxysql --version
service proxysql start
```

Lets do a basic configuration with the proxySQL Admin Interface:
```bash
mysql -u admin -padmin -h 127.0.0.1 -P6032 --prompt='Admin> '
```

First we set the credentials for the monitor user:
```bash
UPDATE global_variables SET variable_value='monitor' WHERE variable_name='mysql-monitor_username';
UPDATE global_variables SET variable_value='monitor' WHERE variable_name='a-strong-mysql-monitor_password';
LOAD MYSQL VARIABLES TO RUNTIME;
SAVE MYSQL VARIABLES TO DISK;
```

Then we add a dedicated MySQL user. The credentials should match those used in your `app/settings`
```bash
INSERT INTO mysql_users(username,password,default_hostgroup) VALUES ('rest-api','a-strong-rest-api_password',1);
LOAD MYSQL USERS TO RUNTIME;
SAVE MYSQL USERS TO DISK;
```

Monitoring intervals can be viewed like this:
```bash
SELECT * FROM global_variables WHERE variable_name LIKE 'mysql-monitor_%';
```

Now add at least one backend MySQL Server.
```bash
INSERT INTO mysql_servers(hostgroup_id,hostname,port) VALUES (1,'127.0.0.1',3306);
LOAD MYSQL SERVERS TO RUNTIME;
SAVE MYSQL SERVERS TO DISK;
```

Check your MySQL Server(s) health with:
```bash
SELECT * FROM mysql_servers;
```

The RESTful API is now ready to serve traffic and we can analyse expensive queries:
```bash
SELECT hostgroup hg, sum_time, count_star, digest_text FROM stats_mysql_query_digest ORDER BY sum_time DESC;
+----+----------+------------+------------------------------------------------+
| hg | sum_time | count_star | digest_text                                    |
+----+----------+------------+------------------------------------------------+
| 1  | 5528     | 5          | SELECT * FROM products ORDER BY id ASC LIMIT ? |
+----+----------+------------+------------------------------------------------+
```

To improve our API's performance lets cache some queries for 6000 milliseconds:
```bash
INSERT INTO mysql_query_rules (rule_id,active,username,match_pattern,cache_ttl,apply) VALUES (10,1,'rest-api','^SELECT',6000,1);
LOAD MYSQL QUERY RULES TO RUNTIME;
SAVE MYSQL QUERY RULES TO DISK;
```

Use CURL or a browser to rapidly test the GET endpoints a few times (Replace the domain name with your own)
```bash
curl -v https://rest.herebetalent.com/products

curl -v https://rest.herebetalent.com/products/1

curl -v https://rest.herebetalent.com/products/2
```

Our stats show a zero sum time and hostgroup -1 entry which means no backend database was used for rapid SELECT queries
```bash
SELECT hostgroup hg, sum_time, count_star, digest_text FROM stats_mysql_query_digest ORDER BY sum_time DESC;
+----+----------+------------+-----------------------------------------------------+
| hg | sum_time | count_star | digest_text                                         |
+----+----------+------------+-----------------------------------------------------+
| 1  | 5528     | 17         | SELECT * FROM products ORDER BY id ASC LIMIT ?      |
| 1  | 2947     | 1          | INSERT INTO products (sku,attributes) VALUES (?, ?) |
| 1  | 459      | 1          | SELECT * FROM products WHERE id = ?                 |
| -1 | 0        | 10         | SELECT * FROM products ORDER BY id ASC LIMIT ?      | < --- Cached Queries
+----+----------+------------+-----------------------------------------------------+
```

Finally test the POST endpoint with some Json (Replace the domain name with your own)
```bash
curl -H "Content-type: application/json" \
  -d '{"sku": "SKU0006", "attributes": "{\"foo\": \"bar\", \"size\": \"xxx-large\", \"grams\": \"500\"}"}' \
  https://rest.herebetalent.com/products 

Product created with id: 6
```

For more information on benchmarking and query caching see: https://proxysql.com/documentation/query-cache/

For read write splits see: https://proxysql.com/documentation/proxysql-read-write-split-howto/

View the API log:
```bash
tail -f logs/app.log
```