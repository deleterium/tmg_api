<?php

// Read the database credentials from the external file
include_once( dirname(__FILE__) . '/../.config.php');
include_once( dirname(__FILE__) . '/../.libs.php');

// Get the start and end timestamps from the GET variables
$start = retInt($_GET['start']);

// Error if no start is given
if ($start === null) {
    echo '{ "errorCode": 2, "errorDescription": "You must specify the `start` period." }';
    exit (2);
}
if ($start < $firstTradeEpoch) {
    $start = $firstTradeEpoch;
}

// Connect to the database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    echo '{ "errorCode": -1, "errorDescription": "Internal API error." }';
    die("Connection failed: " . $conn->connect_error);
}

// First, find the most recent record before the start date to get proper lastClose/lastVolume
$previousSql = "SELECT price as close, volume 
                FROM tmg_prices 
                WHERE epoch < ? 
                ORDER BY epoch DESC 
                LIMIT 1";
$previousStmt = $conn->prepare($previousSql);
$previousStmt->bind_param('i', $start);
$previousStmt->execute();
$previousResult = $previousStmt->get_result();

if ($previousRow = $previousResult->fetch_assoc()) {
    $lastClose = $previousRow['close'];
    $lastVolume = $previousRow['volume'];
} else {
    // Fallback if no previous records exist
    $lastClose = $firstTradePrice;
    $lastVolume = 0;
}
$previousStmt->close();

// Define the query to get the prices from the start timestamp onwards
$sql = "SELECT
    DATE_FORMAT(FROM_UNIXTIME(epoch), '%Y-%m-%d') AS date,
    MIN(price) AS low,
    MAX(price) AS high,
    MAX(volume) as volume,
    SUBSTRING_INDEX(GROUP_CONCAT(price ORDER BY epoch DESC), ',', 1) AS close
FROM
    tmg_prices
WHERE
    epoch >= UNIX_TIMESTAMP(DATE_FORMAT(FROM_UNIXTIME(?), '%Y-%m-%d'))
GROUP BY
    date";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $start);
$stmt->execute();

// Get the result set
$result = $stmt->get_result();

// Create an array to store the price data
$prices = array();
$currentDay = null;

// Loop through the result set and add the price data to the array
while ($row = $result->fetch_assoc()) {
    $dateObj = DateTimeImmutable::createFromFormat("!Y-m-d", $row["date"]);
    $currentDay = $dateObj->getTimestamp();
    $prices[] = array(
        intval($currentDay) * 1000,
        floatFormat($lastClose, 4), // open = previous close
        $lastClose > $row['high'] ? floatFormat($lastClose, 4) : floatFormat($row['high'], 4),
        $lastClose < $row['low'] ? floatFormat($lastClose, 4) : floatFormat($row['low'], 4),
        floatFormat($row['close'], 4),
        floatFormat($row['volume'] - $lastVolume, 2)
    );
    
    $lastClose = $row['close'];
    $lastVolume = $row['volume'];
}

// Fill in any missing days after the last available data
if ($currentDay) {
    $currentDay += 86400; // Move to next day
    $lastPrice = floatFormat($lastClose, 4);
    while ($currentDay < time()) {
        // Fill data because there was no trade since some days ago
        $prices[] = array(
            intval($currentDay) * 1000,
            $lastPrice,
            $lastPrice,
            $lastPrice,
            $lastPrice,
            0
        );
        $currentDay += 86400;
    }
}
echo json_encode($prices);

// Close the database connection
$stmt->close();
$conn->close();
