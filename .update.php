<?php

// Read the database credentials from the external file
include_once '.config.php';

function getEpochFromBlock($height)
{
    $query = file_get_contents(
        $GLOBALS['signumNode'] ."/burst?requestType=getBlock&height=". $height ."&includeTransactions=false"
    );
    $jQuery = json_decode($query, true);
    if (!empty($jQuery["errorCode"])) {
        return null;
    }
    return $jQuery["timestamp"] + 1407722400;
}

function hexToDecimal($input)
{
    $binaryString = hex2bin($input);
    // Unpack the binary string as a 64-bit integer
    $unpacked = unpack('P', $binaryString);
    return $unpacked[1];
}

function getPriceFromMachineData($dataStream)
{
    if (strlen($dataStream) < 18*16) {
        return null;
    }
    $signaTotal = hexToDecimal(substr($dataStream, 16*16, 16));
    $assetTotal = hexToDecimal(substr($dataStream, 17*16, 16));
    if ($assetTotal == 0) {
        return -1;
    }
    return ($signaTotal / 1e6) / $assetTotal;
}

function mylog($text)
{
    echo "$text\n";
    error_log(date("[Y-m-d H:i:s]") ."\t". $text ."\n", 3, dirname(__FILE__) ."/update.log");
}

// Connect to the database
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    mylog("Connection failed: " . $conn->connect_error);
    exit(1);
}
$sql = "SELECT MAX(blockheight) FROM tmg_prices";
// Execute the query to get the latest price
$result = $conn->query($sql);
// Get the latest price row as an associative array
$row = $result->fetch_assoc();

if ($row["MAX(blockheight)"] == null) {
    // Empty database
    $latestInDB = 0;
} else {
    $latestInDB = $row["MAX(blockheight)"];
}

$queryResult = file_get_contents($signumNode."/burst?requestType=getAT&at=".$contractId."&includeDetails=true");
$decodedQuery = json_decode($queryResult, true);
if (!empty($decodedQuery["errorCode"])) {
    mylog('Failed to get contract latest details.');
    exit(1);
}

$sql = "INSERT INTO tmg_prices (epoch, price, blockheight) VALUES (?, ?, ?)";

while ($latestInDB < $decodedQuery["nextBlock"]) {
    $price = getPriceFromMachineData($decodedQuery["machineData"]);
    $blockheight = $decodedQuery["nextBlock"];
    $epoch = getEpochFromBlock($blockheight);

    if ($price < 0) {
        // End gracefully if liquity pool was empty at that block
        break;
    }
    if ($price === null || $blockheight === null || $epoch === null) {
        mylog("Error parsing price.");
        exit(1);
    }

    // Prepare the query statement
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('idi', $epoch, $price, $blockheight);
    // Execute the query
    $stmt->execute();
    // Check if the query was successful
    if ($stmt->affected_rows == 0) {
        // If the query failed, return an error message
        mylog("Error adding price: " . $stmt->error);
        exit(1);
    }
    $stmt->close();

    mylog("Added blockheight $blockheight with price $price");

    // Get next values
    $blockheight--;
    $queryResult = file_get_contents(
        $signumNode ."/burst?requestType=getATDetails&at=". $contractId. "&height=". $blockheight
    );
    $decodedQuery = json_decode($queryResult, true);
    if (!empty($decodedQuery["errorCode"])) {
        mylog("Failed to get contract details at height ". $blockheight);
        exit(1);
    }
}

// Close the database connection
$conn->close();
