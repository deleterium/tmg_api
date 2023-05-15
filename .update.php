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

function getCummulativeVolume($dataStream)
{
    if (strlen($dataStream) < 22*16) {
        return null;
    }
    return hexToDecimal(substr($dataStream, 21*16, 16)) / 1e8;
}

function mylog($text)
{
    echo "$text\n";
    error_log(date("[Y-m-d H:i:s]") ."\t". $text ."\n", 3, dirname(__FILE__) ."/update.log");
}

function getValuesFromQuery($query) {
    if ($query === null || !empty($query["errorCode"])) {
        mylog('Failed to get contract latest details.');
        exit(1);
    }
    $epoch = getEpochFromBlock($query["nextBlock"]);
    $price = getPriceFromMachineData($query["machineData"]);
    $blockheight = $query["nextBlock"];
    $volume = getCummulativeVolume($query["machineData"]);
    if ($price === null || $blockheight === null || $epoch === null || $volume === null) {
        mylog("Error parsing values.");
        exit(1);
    }
    return compact("epoch", "price", "blockheight", "volume");
}

// Connect to the database
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    mylog("Connection failed: " . $conn->connect_error);
    exit(1);
}

// loop initial setup
$result = $conn->query("SELECT * FROM tmg_prices ORDER BY epoch DESC LIMIT 1");
$latestInDB = $result->fetch_assoc();
if ($latestInDB == null) {
    // Database is empty
    $latestInDB = [
        "epoch" => 0,
        "price" => 0,
        "blockheight" => 0,
        "volume" => 0
    ];
}
$prevValues = $latestInDB;
$queryResult = file_get_contents($signumNode."/burst?requestType=getAT&at=".$contractId."&includeDetails=true");
$decodedQuery = json_decode($queryResult, true);
$currValues = getValuesFromQuery($decodedQuery);

// loop condition
while ($currValues["volume"] > $latestInDB["volume"]) {
    // Insert current value
    $stmt = $conn->prepare("INSERT INTO tmg_prices (epoch, price, blockheight, volume) VALUES (?, ?, ?, ?)");
    $stmt->bind_param(
        'idid',
        $currValues["epoch"],
        $currValues["price"],
        $currValues["blockheight"],
        $currValues["volume"]
    );
    // Execute the query
    $stmt->execute();
    // Check if the query was successful
    if ($stmt->affected_rows == 0) {
        // If the query failed, return an error message
        mylog("Error adding price: " . $stmt->error);
        exit(1);
    }
    $stmt->close();
    mylog("Added blockheight ". $currValues["blockheight"] ." with price ". $currValues["price"]);
    if ($currValues["volume"] == $prevValues["volume"]) {
        // previous operation (we are going backwards!) was not sell/buy. Remove from DB.
        $stmt = $conn->prepare('DELETE FROM tmg_prices WHERE epoch=?');
        $stmt->bind_param('i', $prevValues["epoch"]);
        // Execute the query
        $stmt->execute();
        // Check if the query was successful
        if ($stmt->affected_rows == 0) {
            // If the query failed, return an error message
            mylog("Error removing price at height ". $prevValues["blockheight"] ." ". $stmt->error);
            exit(1);
        }
        $stmt->close();
        mylog("Removed unwanted record at height ". $prevValues["blockheight"]);
    }

    // loop prepare next iteration
    $prevValues = $currValues;
    $queryResult = file_get_contents(
        $signumNode ."/burst?requestType=getATDetails&at=". $contractId. "&height=". ($currValues["blockheight"] - 1)
    );
    $decodedQuery = json_decode($queryResult, true);
    $currValues = getValuesFromQuery($decodedQuery);
}

if ($latestInDB["volume"] == 0) {
    // End gracefully if database was empty
    mylog("Sync ended gracefully at blockheight=". $prevValues["blockheight"] ." and epoch=". $prevValues["epoch"]);
}

// Close the database connection
$conn->close();
