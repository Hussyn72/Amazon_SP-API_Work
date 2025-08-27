<?php
date_default_timezone_set("Asia/Calcutta");

file_put_contents('/tmp/Sp-API_LWA_access_token_generator.log', date("Y-m-d H:i:s") . '*********************' . 'Inside SP-API Generate LWA TOKEN '.'********************** '.  PHP_EOL, FILE_APPEND);

//DB CONNECTION 
$DBConn = pg_connect("dbname='e2fax' user='domains'");

if(!$DBConn)
{
        echo "Error : Unable to open database\n";
        file_put_contents('/tmp/Sp-API_LWA_access_token_generator.log', date("Y-m-d H:i:s") . ' Error : Unable to open database'.  PHP_EOL, FILE_APPEND);
}
else
{
        //  echo "Opened database successfully\n";
        file_put_contents('/tmp/Sp-API_LWA_access_token_generator.log', date("Y-m-d H:i:s") . ' DB Connection Established'.  PHP_EOL, FILE_APPEND);
}


//HAVE TO CHANGE CLIENT ID & CLIENT SECRET ON ROTATION DATE
//Rotation Deadline : 2023-11-28T06:39:37.305Z 

$client_id = "YOUR ID HERE";
//amzn1.application-oa2-client.281868543e99448bade39d6962b10745
$Query = "SELECT value FROM config_values WHERE key = 'amazon_client_secret' AND name = 'Amazon'";
file_put_contents('/tmp/Sp-API_LWA_access_token_generator.log', date("Y-m-d H:i:s") . ' - Query for getting Amazon client secret -> ' . $Query . PHP_EOL, FILE_APPEND);

$ExecuteQuery = pg_query($DBConn, $Query);

if ($ExecuteQuery === false) {
    file_put_contents('/tmp/Sp-API_LWA_access_token_generator.log', date("Y-m-d H:i:s") . ' - Error executing query: ' . pg_last_error($DBConn) . PHP_EOL, FILE_APPEND);
    exit("Database query failed.");
}

$GetClientSecret = pg_fetch_all($ExecuteQuery);

if (!$GetClientSecret || empty($GetClientSecret[0]['value'])) {
    file_put_contents('/tmp/Sp-API_LWA_access_token_generator.log', date("Y-m-d H:i:s") . ' - Client secret not found in DB result.' . PHP_EOL, FILE_APPEND);
    exit("Client secret not found.");
}

$client_secret = $GetClientSecret[0]['value'];
file_put_contents('/tmp/Sp-API_LWA_access_token_generator.log', date("Y-m-d H:i:s") . ' - client_secret -----------> ' . $client_secret . PHP_EOL, FILE_APPEND);

//$client_secret = "amzn1.oa2-cs.v1.00257ac3023a508a5e5793f2593f9b5187db405dceb1643b7e1bf51504d8c76e";


//Market Place of Sellers (For Refresh Token)
$MarketPlace_of_Refresh_tokens = pg_query($DBConn,"select token,marketplace from sp_api_credentials where token_type='refresh_token'");
$rows = pg_fetch_all($MarketPlace_of_Refresh_tokens);

//Getting Refresh Token
foreach($rows as $row)
{
	$refresh_token = $row['token'];
	$MarketPlace = $row['marketplace'];



	file_put_contents('/tmp/Sp-API_LWA_access_token_generator.log', date("Y-m-d H:i:s") . '*********************' . 'Inside the For LOop START  '.'********************** '.  PHP_EOL, FILE_APPEND);
	echo "MarketPlace  ::::::::".$MarketPlace ."<br>";
	echo "Refresh Token  <br>";
	echo $refresh_token . "<br>";

	file_put_contents('/tmp/Sp-API_LWA_access_token_generator.log', date("Y-m-d H:i:s") . "GOT Refresh Token For Market place". $MarketPlace." Token is ----> ". $refresh_token  .  PHP_EOL, FILE_APPEND);


	// Generate LWA Access Token

	file_put_contents('/tmp/Sp-API_LWA_access_token_generator.log', date("Y-m-d H:i:s") . "Going to make a curl request for generating access token for". $MarketPlace  .  PHP_EOL, FILE_APPEND);

	$curl = curl_init();

	curl_setopt_array($curl, array(
	  CURLOPT_URL => 'https://api.amazon.com/auth/o2/token',
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_POST => true,
	  CURLOPT_POSTFIELDS => http_build_query(array(
	    'grant_type' => 'refresh_token',
	    'refresh_token' => $refresh_token,
	    'client_id' => $client_id,
	    'client_secret' => $client_secret
	  )),
	));

	$response = curl_exec($curl);

	if ($response === false) {
	  die('Error: ' . curl_error($curl));
	}

	curl_close($curl);
	

	$data = json_decode($response, true);
	file_put_contents(
    '/tmp/Sp-API_LWA_access_token_generator.log',
    date("Y-m-d H:i:s") . ' ' . print_r($data, true) . PHP_EOL,
    FILE_APPEND
);
	$access_token = $data['access_token'];

	echo "This is Access Token For Each Different Market Place :::::::  <br>";
	echo $access_token."<br>" ;
	echo "<br>";
	echo "<br>";
	file_put_contents('/tmp/Sp-API_LWA_access_token_generator.log', date("Y-m-d H:i:s") . "GOT LWA Access Token For Market place". $MarketPlace." Access Token is ---->". $access_token  .  PHP_EOL, FILE_APPEND);



	// Updating  Access Token in the database for each MarketPlace
	$updatequery = "update sp_api_credentials set token='$access_token' where token_type='LWA_access_token' and marketplace='$MarketPlace'";
	$Updating_DB = pg_query($DBConn,$updatequery);
	file_put_contents('/tmp/Sp-API_LWA_access_token_generator.log', date("Y-m-d H:i:s") . $updatequery  .  PHP_EOL, FILE_APPEND);
	if($Updating_DB)
		file_put_contents('/tmp/Sp-API_LWA_access_token_generator.log', date("Y-m-d H:i:s") . "updated database table sp-api_credentials with Access Token For Market place ". $MarketPlace ." and Token Type"  .  PHP_EOL, FILE_APPEND);
	else
		file_put_contents('/tmp/Sp-API_LWA_access_token_generator.log', date("Y-m-d H:i:s") . "updated database table FAILED".  PHP_EOL, FILE_APPEND);

}

?>
