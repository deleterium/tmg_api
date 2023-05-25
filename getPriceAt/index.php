<?php

// Read the database credentials from the external file
include_once( dirname(__FILE__) . '/../.config.php');
include_once( dirname(__FILE__) . '/../.libs.php');

// Get the start and end timestamps from the GET variables
$epoch = retInt($_GET['epoch']);
$blockheight = retInt($_GET['blockheight']);
if (!(($epoch === null) xor ($blockheight === null))) {
    echo '{ "errorCode": 3, "errorDescription": "Only one of `epoch` or `blockheight` must be supplied." }';
    exit(1);
}

// Connect to the database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    echo '{ "errorCode": -1, "errorDescription": "Internal API error." }';
    die("Connection failed: " . $conn->connect_error);
}

// Define the query to find the price
if ($epoch) {
    $sql = "SELECT * FROM tmg_prices WHERE epoch <= ? ORDER BY epoch DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $epoch);
} else {
    $sql = "SELECT * FROM tmg_prices WHERE blockheight <= ? ORDER BY epoch DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $blockheight);
}
$stmt->execute();

$result = $stmt->get_result();
$dataAtPast = $result->fetch_assoc();
if ($dataAtPast['epoch'] == null) {
    // If there is no result, return error
    echo '{ "errorCode": 1, "errorDescription": "No records of TMG price." }';
    $conn->close();
    exit(1);
}

echo json_encode($dataAtPast);

$conn->close();
