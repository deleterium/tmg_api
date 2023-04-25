# TMG price API
TMG is a token on Signum cryptocurrency. Price is based on TMG liquidity pool at deleterium.info.

## Setup
* Install and configure Apache.
* Install and configure PHP.
* Install and configure MariaDB.
* Create new database.
* Create new table with the command:
```sql
CREATE TABLE tmg_prices (
  epoch INT(11) NOT NULL,
  price DECIMAL(18,8) NOT NULL,
  blockheight INT(11) NOT NULL,
  PRIMARY KEY (epoch)
);
```
* Create a file named `.config.php` with your own configuration. Use the `.config.php.template` for convenience.
* Run manually the `.update.php`. First time it takes a while to get all values. Best way if you have a signum localhost, but remember database trim must be disabled (it is enabled by default).
* Add a cron job to run every 5 minutes to update the database, running the `.update.php` file. This will keep track of new records.
* Inspect regularly the `update.log` file checking for errors.

## Operations

### getLatestPrice
Returns the latest price from the liquidity pool.

### getPrices
Returns an array of all prices between `start` and `end` timestamps. Timestamps are unix epoch time. `start` must be supplied. `end` is optional and, if not given, will be last available.

### getDailyOHLC
Returns an array of all prices between `start` and last available, grouped by day and with open-high-low-close (OHLC) prices. Array is formatted to use with Highcharts chart library. Array indexes are 0) JS timestamp, 1) Open price, 2) High price, 3) Low price and 4) Close price. `start` timestamp is unix epoch time. `start` must be supplied, although it can be zero.

## Testing the API
A simple API page is served for manual lookups at https://deleterium.info/tmg_api
