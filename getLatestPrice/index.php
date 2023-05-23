<?php

// Read the database credentials from the external file
include_once( dirname(__FILE__) . '/../.config.php');

// Connect to the database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    echo '{ "errorCode": -1, "errorDescription": "Internal API error." }';
    die("Connection failed: " . $conn->connect_error);
}

// Define the query to get the latest price
$sql = "SELECT * FROM tmg_prices ORDER BY epoch DESC LIMIT 1";

$result = $conn->query($sql);
$latest_price = $result->fetch_assoc();
if ($latest_price['epoch'] == null) {
    // If there is no result, return error
    echo '{ "errorCode": 1, "errorDescription": "No records of TMG price." }';
    $conn->close();
    exit(1);
}

echo json_encode($latest_price);

$conn->close();
