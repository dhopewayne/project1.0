<?php
// Load environment variables from .env file
require_once 'vendor/autoload.php'; // Make sure the Composer autoloader is included

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Fetch credentials from environment variables
$localDbServer = getenv('LOCAL_DB_SERVER');
$localDbUsername = getenv('LOCAL_DB_USERNAME');
$localDbPassword = getenv('LOCAL_DB_PASSWORD');
$localDbName = getenv('LOCAL_DB_NAME');

// Connect to local MSSQL database using environment variables
function connectMSSQL() {
    global $localDbServer, $localDbUsername, $localDbPassword, $localDbName;

    $connectionOptions = array(
        "Database" => $localDbName,
        "Uid" => $localDbUsername,
        "PWD" => $localDbPassword
    );
    
    $conn = sqlsrv_connect($localDbServer, $connectionOptions);
    if(!$conn) {
        die(print_r(sqlsrv_errors(), true));
    }
    return $conn;
}

// Function to send data to the remote API
function sendDataToAPI($data) {
    $ch = curl_init('https://weeme.hstn.me//index.php?sync');
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    
    // Execute the request
    $response = curl_exec($ch);
    
    // Check for errors
    if(curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }
    
    // Close the cURL session
    curl_close($ch);
    
    return $response;
}

// Fetch data from local MSSQL database
$mssqlConn = connectMSSQL();
$sql = "SELECT id, cusName, email, phone, adress, city, zip, cusState, country, createdDate, createdTime, createdAt FROM custormers";
$stmt = sqlsrv_query($mssqlConn, $sql);

if (!$stmt) {
    die(print_r(sqlsrv_errors(), true));
}

// Prepare data for API
$dataToSend = [];

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $dataToSend[] = [
        'id' => $row['id'],
        'cusName' => $row['cusName'],
        'email' => $row['email'],
        'phone' => $row['phone'],
        'adress' => $row['adress'],
        'city' => $row['city'],
        'zip' => $row['zip'],
        'cusState' => $row['cusState'],
        'country' => $row['country'],
        'createdDate' => $row['createdDate']->format('Y-m-d'),
        'createdTime' => $row['createdTime']->format('H:i:s'),
        'createdAt' => $row['createdAt']->format('Y-m-d H:i:s')
    ];
}

// Send the data to the remote API
foreach ($dataToSend as $data) {
    $response = sendDataToAPI($data);
    echo "Response: " . $response . "\n";
}

// Close the MSSQL connection
sqlsrv_close($mssqlConn);
?>
