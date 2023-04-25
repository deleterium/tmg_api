<?php

// Read the database credentials from the external file
include_once '../.config.php';

// From a mixed var, return an integer value if it is integer.
// Return null if not.
function retInt($s)
{
    if (preg_match('/^\d{1,11}$/', $s)) {
        return intval($s);
    }
    return null;
}

// Get the start and end timestamps from the GET variables
$start = retInt($_GET['start']);

// Error if no start is given
if ($start === null) {
    echo '{ "errorCode": 2, "errorDescription": "You must specify the `start` period." }';
    exit (2);
}
// avoid error in query with negative unix timestamp
if ($start < 183600) {
    $start = 183600;
}

// Connect to the database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    echo '{ "errorCode": -1, "errorDescription": "Internal API error." }';
    die("Connection failed: " . $conn->connect_error);
}

// Define the query to get the prices between the start and end timestamps
$sql = "SELECT
    DATE_FORMAT(FROM_UNIXTIME(epoch), '%Y-%m-%d') AS date,
    MIN(price) AS low,
    MAX(price) AS high,
    SUBSTRING_INDEX(GROUP_CONCAT(price ORDER BY epoch DESC), ',', 1) AS close
FROM
    tmg_prices
WHERE
    epoch >= UNIX_TIMESTAMP(DATE_FORMAT(FROM_UNIXTIME(?), '%Y-%m-%d'))
GROUP BY
    date";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $start);

// Execute the query
$stmt->execute();

// Get the result set
$result = $stmt->get_result();

// Check if there is a result
if ($result->num_rows > 0) {
    // Create an array to store the price data
    $prices = array();

    // Loop through the result set and add the price data to the array
    $first = true;
    while ($row = $result->fetch_assoc()) {
        $dateObj = date_create_immutable_from_format("Y-m-d", $row["date"]);
        $currentDay = date_timestamp_get($dateObj); //->getTimestamp();
        if ($first) {
            $prices[] = array(
                intval($currentDay) * 1000,
                floatval($row["low"]), // BUG! open as lowest is not right. Should be first trade.
                floatval($row["high"]),
                floatval($row["low"]),
                floatval($row["close"])
            );
            $lastClose = $row['close'];
            $first = false;
            continue;
        }

        $prices[] = array(
            intval($currentDay) * 1000,
            floatval($lastClose),
            $lastClose > $row['high'] ? floatval($lastClose) : floatval($row['high']),
            $lastClose < $row['low'] ? floatval($lastClose) : floatval($row['low']),
            floatval($row['close'])
        );
        $lastClose = $row['close'];
    }

    // Encode the array as a JSON object and return it
    echo json_encode($prices);
} else {
    // If there is no result, return an empty JSON array
    echo '[]';
}

// Close the database connection
$stmt->close();
$conn->close();
