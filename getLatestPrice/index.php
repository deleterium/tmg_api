<?php

// Read the database credentials from the external file
include_once '../.config.php';

// Connect to the database
$conn = new mysqli($servername, $username, $password, $dbname);

// Define the query to get the latest price
$sql = "SELECT * FROM tmg_prices ORDER BY epoch DESC LIMIT 1";

// Execute the query
$result = $conn->query($sql);

// Get the latest price row as an associative array
$row = $result->fetch_assoc();

if ($row['epoch'] == null) {
    // If there is no result, return error
    echo '{ "errorCode": 1, "errorDescription": "No records of TMG price." }';
    $conn->close();
    exit(1);
}
// Create a JSON object with the latest price data
$latest_price = array(
    'epoch' => $row['epoch'],
    'price' => $row['price'],
    'blockheight' => $row['blockheight']
);

// Encode the JSON object and return it
echo json_encode($latest_price);

// Close the database connection
$conn->close();
