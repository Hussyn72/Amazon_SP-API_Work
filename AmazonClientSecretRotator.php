<?php
date_default_timezone_set("Asia/Calcutta");
$log_file = '/tmp/AmazonClientSecretRotator.log';


file_put_contents($log_file, date("Y-m-d H:i:s") . '*********************' . 'Inside AmazonClientSecretRotator  Script Log'.'********************** '.  PHP_EOL, FILE_APPEND);


//DB CONNECTION
global $DBConn;
$DBConn = pg_connect("dbname='e2fax' user='domains'");

if(!$DBConn)
{
	echo "Error : Unable to open database\n";
	file_put_contents($log_file, date("Y-m-d H:i:s") . ' Error : Unable to open database'.  PHP_EOL, FILE_APPEND);
}
else
{
	//  echo "Opened database successfully\n";
	file_put_contents($log_file, date("Y-m-d H:i:s") . ' DB Connection Established'.  PHP_EOL, FILE_APPEND);
}

require '/home/staff/omv3/ownmail/scripts/aws-autoloader.php';

use Aws\Sqs\SqsClient;

file_put_contents($log_file, date("Y-m-d H:i:s") . '*********************' . 'START'.'********************** '.  PHP_EOL, FILE_APPEND);



//AWS Credentials
$clientId = 'YOUR ID HERE';
//amzn1.application-oa2-client.281868543e99448bade39d6962b10745
//Getting Current Client Secret from DB
$Query = "SELECT value FROM config_values WHERE key = 'amazon_client_secret' AND name = 'Amazon'";
file_put_contents($log_file, date("Y-m-d H:i:s") . ' - Query for getting Amazon client secret -> ' . $Query . PHP_EOL, FILE_APPEND);

$ExecuteQuery = pg_query($DBConn, $Query);

if ($ExecuteQuery === false) {
	file_put_contents($log_file, date("Y-m-d H:i:s") . ' - Error executing query: ' . pg_last_error($DBConn) . PHP_EOL, FILE_APPEND);
	exit("Database query failed.");
}

$GetClientSecret = pg_fetch_all($ExecuteQuery);

if (!$GetClientSecret || empty($GetClientSecret[0]['value'])) {
	file_put_contents($log_file, date("Y-m-d H:i:s") . ' - Client secret not found in DB result.' . PHP_EOL, FILE_APPEND);
	exit("Client secret not found.");
}

$clientSecret = $GetClientSecret[0]['value'];
file_put_contents($log_file, date("Y-m-d H:i:s") . ' - client_secret -----------> ' . $clientSecret . PHP_EOL, FILE_APPEND);







// Step 1: Get LWA Access Token
function getLwaAccessToken($clientId, $clientSecret, $log_file)
{
	$url = 'https://api.amazon.com/auth/o2/token';
	$data = [
		'grant_type'    => 'client_credentials',
		'scope' => 'sellingpartnerapi::client_credential:rotation',
		'client_id'     => $clientId,
		'client_secret' => $clientSecret
	];

	$options = [
		CURLOPT_URL            => $url,
		CURLOPT_POST           => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
		CURLOPT_POSTFIELDS     => http_build_query($data)
	];

	$ch = curl_init();
	curl_setopt_array($ch, $options);
	$response = curl_exec($ch);
	curl_close($ch);

	$json = json_decode($response, true);

	if (isset($json['access_token'])) {
		file_put_contents($log_file, date("Y-m-d H:i:s") . " ✅ LWA access token retrieved." . PHP_EOL, FILE_APPEND);
		return $json['access_token'];
	} else {
		file_put_contents($log_file, date("Y-m-d H:i:s") . " ❌ Failed to retrieve LWA access token: $response" . PHP_EOL, FILE_APPEND);
		return null;
	}
}

// Step 2: Call Rotate Client Secret API
function rotateClientSecret($accessToken, $log_file)
{
	$url = "https://sellingpartnerapi-na.amazon.com/applications/2023-11-30/clientSecret";
	file_put_contents($log_file, date("Y-m-d H:i:s") . " URL ->  $url" . PHP_EOL, FILE_APPEND);

	file_put_contents($log_file, date("Y-m-d H:i:s") . " Access Token ->  $accessToken" . PHP_EOL, FILE_APPEND);


	$headers = [
		"x-amz-access-token: $accessToken",
		"Content-Type: application/json",
		"Accept: application/json"
	];

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

	$response = curl_exec($ch);
	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	if ($httpCode == 204) {
		file_put_contents($log_file, date("Y-m-d H:i:s") . " ✅ Client secret Access Token Generated successfully." . PHP_EOL, FILE_APPEND);
		return true;
	} else {
		file_put_contents($log_file, date("Y-m-d H:i:s") . " ❌ Failed to rotate client secret. HTTP $httpCode: $response" . PHP_EOL, FILE_APPEND);
		return null;
	}
}


//Step 3: Now Process the notification
function ProcessNotification()
{
	global $log_file, $DBConn;

	try {
		$config = [
			'region' => 'eu-north-1',
			'version' => 'latest',
		];

		$sqs = new SqsClient($config);

		$queueUrl = 'https://sqs.eu-north-1.amazonaws.com/971958456953/Amazon_client_secret_rotation_queue';

		while (true) {
			$result = $sqs->receiveMessage([
				'QueueUrl' => $queueUrl,
				'MaxNumberOfMessages' => 1,
				'WaitTimeSeconds' => 20,
			]);

			$messages = $result->get('Messages');

			if (empty($messages)) {
				file_put_contents($log_file, date("Y-m-d H:i:s") . ' The Queue is Empty Now.' . PHP_EOL, FILE_APPEND);
				break;
			}

			foreach ($messages as $message) {
				$messageBody = $message['Body'];
				file_put_contents($log_file, date("Y-m-d H:i:s") . " -----Raw SQS Body--- " . $messageBody . PHP_EOL, FILE_APPEND);

				// Decode SNS wrapper
				//$outer = json_decode($messageBody, true);
				$notification = json_decode($messageBody, true);

				if (isset($notification['notificationType']) && $notification['notificationType'] === 'APPLICATION_OAUTH_CLIENT_NEW_SECRET') {
					$newSecret = $notification['payload']['applicationOAuthClientNewSecret']['newClientSecret'] ?? null;

					if ($newSecret) {
						file_put_contents($log_file, date("Y-m-d H:i:s") . " ✅ Extracted new client secret from notification." . PHP_EOL, FILE_APPEND);
						file_put_contents($log_file, date("Y-m-d H:i:s") . " ✅ Client Secret -> ".$newSecret . PHP_EOL, FILE_APPEND);

						$updateResult = updateClientSecretInDB($DBConn, $newSecret, $log_file);

						if ($updateResult) {
							$sqs->deleteMessage([
								'QueueUrl' => $queueUrl,
								'ReceiptHandle' => $message['ReceiptHandle']
							]);
							file_put_contents($log_file, date("Y-m-d H:i:s") . " ✅ Deleted message from SQS queue." . PHP_EOL, FILE_APPEND);
							return true;
						}
					}
				}

				file_put_contents($log_file, date("Y-m-d H:i:s") . " ❌ Notification payload missing expected structure." . PHP_EOL, FILE_APPEND);
			}
		}

	} catch (Exception $e) {
		file_put_contents($log_file, date("Y-m-d H:i:s") . " ❌ SQS Exception: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
	}

	return false;
}



// Step 4: Update new client secret in DB
function updateClientSecretInDB($dbConn, $newSecret, $log_file)
{
	$key = 'amazon_client_secret';
	$name = 'Amazon';

	$prepare = pg_prepare($dbConn, "update_secret", "UPDATE config_values SET value = $1 WHERE key = $2 AND name = $3");
	if (!$prepare) {
		file_put_contents($log_file, date("Y-m-d H:i:s") . " ❌ Failed to prepare SQL statement." . PHP_EOL, FILE_APPEND);
		return false;
	}

	//logging the query 
	// Manually create a debug-friendly version of the query
	$debugQuery = sprintf(
	    "UPDATE config_values SET value = '%s' WHERE key = '%s' AND name = '%s';",
	    pg_escape_string($newSecret),
	    pg_escape_string($key),
	    pg_escape_string($name)
	);
	file_put_contents($log_file, date("Y-m-d H:i:s") . " 🐛 Executing query: " . $debugQuery . PHP_EOL, FILE_APPEND);



	$result = pg_execute($dbConn, "update_secret", [$newSecret, $key, $name]);
	if ($result) {
		file_put_contents($log_file, date("Y-m-d H:i:s") . " ✅ New client secret updated in DB." . PHP_EOL, FILE_APPEND);
		return true;
	} else {
		file_put_contents($log_file, date("Y-m-d H:i:s") . " ❌ Failed to update client secret in DB." . PHP_EOL, FILE_APPEND);
		return false;
	}
}

// RUN FLOW
$accessToken = getLwaAccessToken($clientId, $clientSecret, $log_file);
if ($accessToken) {
	$rotationResponse = rotateClientSecret($accessToken, $log_file);
	if ($rotationResponse) {
		$isNotificationProcessed = ProcessNotification();
		if($isNotificationProcessed)
		{
			file_put_contents($log_file, date("Y-m-d H:i:s") . " ✅ CLIENT SECRET UPDATED IN THE SYSTEM SUCESSFULLY." . PHP_EOL, FILE_APPEND);
		}
	}
}

file_put_contents($log_file, date("Y-m-d H:i:s") . " ✅ Script execution complete." . PHP_EOL, FILE_APPEND);
file_put_contents($log_file, str_repeat('*', 80) . PHP_EOL, FILE_APPEND);







?>
