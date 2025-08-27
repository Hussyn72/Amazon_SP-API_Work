<?
date_default_timezone_set("Asia/Calcutta");
$log_file = '/tmp/SP-api_monthly_transaction_report.log';


file_put_contents($log_file, date("Y-m-d H:i:s") . '*********************' . 'Inside SP-API Monthly Transaction Report Script Log'.'********************** '.  PHP_EOL, FILE_APPEND);


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


file_put_contents($log_file, date("Y-m-d H:i:s") . '*********************' . 'START'.'********************** '.  PHP_EOL, FILE_APPEND);

file_put_contents($log_file, date("Y-m-d H:i:s") .'---Receiving Notification Data---'. PHP_EOL, FILE_APPEND);




//FOR PREVIOUS 90 DAYS DATA
file_put_contents($log_file, date("Y-m-d H:i:s") . 'Retrieving Last 90 days data.'.  PHP_EOL, FILE_APPEND);

//$Query = pg_query($DBConn,"select marketplace,endpoint,marketplace_id from sp_api_credentials WHERE token_type='LWA_access_token' and marketplace not in ('United States')");
$Query = pg_query($DBConn,"select marketplace,endpoint,marketplace_id from sp_api_credentials WHERE token_type='LWA_access_token'");
if($Query)
{
	$GetDatas = pg_fetch_all($Query);

	foreach ($GetDatas as $GetData)
	{
		
		$Endpoint = $GetData['endpoint'];
		$Marketplace = $GetData['marketplace'];
		$MarketplaceID = $GetData['marketplace_id'];

		//CUSTOMIZE THE DATE RANGE BELOW
		$URL = $Endpoint.'/reports/2021-06-30/reports?reportTypes=GET_V2_SETTLEMENT_REPORT_DATA_XML&pageSize=100'; // &createdSince=2025-06-30&createdUntil=2025-08-07'; //&createdSince=2023-07-30';     //&createdSince=2023-06-20&createdUntil=2023-08-15';

		file_put_contents($log_file, date("Y-m-d H:i:s") . $URL . PHP_EOL, FILE_APPEND);
		file_put_contents($log_file, date("Y-m-d H:i:s") . 'Making Curl Request for getting reportdocumentIDs'. PHP_EOL, FILE_APPEND);

		do{
		$Response = makeCurlRequest($URL ,$Marketplace);
		file_put_contents($log_file, date("Y-m-d H:i:s") . 'COMPLETED THE MAKECURL REQUEst FUCNTION '. PHP_EOL, FILE_APPEND);

		$Reports = $Response['reports'];

		file_put_contents($log_file, date("Y-m-d H:i:s") . $Reports. PHP_EOL, FILE_APPEND);

		foreach ($Reports  as $Report) 
		{
			$ReportID = $Report['reportId'];
			$ReportDocumentID = $Report['reportDocumentId'];

			// Do something with the values (e.g., print them)
			echo "Report ID: $ReportID, Report Document ID: $ReportDocumentID\n";

			file_put_contents($log_file, date("Y-m-d H:i:s") .'Calling Process Report Function'. PHP_EOL, FILE_APPEND);
			file_put_contents($log_file, date("Y-m-d H:i:s") .'Report ID : '.$ReportID.'ReportDocument ID : '.$ReportDocumentID . PHP_EOL, FILE_APPEND);

			retrieveAndProcessReport($ReportID, $ReportDocumentID,$Endpoint,$Marketplace ,$MarketplaceID);
			sleep(5);
		}

			if (!empty($Response['nextToken'])) {
		            $nextToken = urlencode($Response['nextToken']);
		            $URL = "$Endpoint/reports/2021-06-30/reports?nextToken=$nextToken";
		            file_put_contents($log_file, date("Y-m-d H:i:s") . " Next token present for endpoint $Endpoint, fetching next page\n", FILE_APPEND);
		        } else {
		            $URL = null;
	        	}
		} while ($URL);
	}

}
else
{
	file_put_contents($log_file, date("Y-m-d H:i:s") . ' Query Failed '.  PHP_EOL, FILE_APPEND);
}





/*

require '/home/staff/omv3/ownmail/scripts/aws-autoloader.php'; // Include Composer autoloader or manually require AWS SDK files

use Aws\Sqs\SqsClient;

try 
{
	// Configure the AWS SDK and SQS client
	$config = [
	    'region' => 'eu-north-1', // Replace with your desired region
	    'version' => 'latest',
	];

	$sqs = new SqsClient($config);

	// Your queue URL
	$queueUrl = 'https://sqs.eu-north-1.amazonaws.com/971958456953/MyQueue_for_settlementReport_Notifications';

	// Send a message to the SQS queue
	$result = $sqs->sendMessage([
	    'QueueUrl' => $queueUrl,
	    'MessageBody' => 'Request to retrieve a report', // Customize the message as needed
	]);

	echo "Message sent. MessageId: " . $result['MessageId'];

	file_put_contents($log_file, date("Y-m-d H:i:s") . "Result-----" . $result . PHP_EOL, FILE_APPEND);

	// Polling loop
	while (true) 
	{
		$result = $sqs->receiveMessage([
			'QueueUrl' => $queueUrl,
			'MaxNumberOfMessages' => 1, // Number of messages to retrieve in one batch
			'WaitTimeSeconds' => 20, // Long polling (20 seconds wait time)
		]);

		$messages = $result->get('Messages');

		if (empty($messages)) 
		{
			file_put_contents($log_file, date("Y-m-d H:i:s") .'The Queue is Empty Now. '. PHP_EOL, FILE_APPEND);
			// No more messages in the queue, exit the loop
			break;
		}





		foreach ($messages as $message) 
		{
			$messageBody = $message['Body'];

			file_put_contents($log_file, date("Y-m-d H:i:s") . "-----Message Body ---" . $messageBody . PHP_EOL, FILE_APPEND);

		/*	
				if (strpos($messageBody, 'Request to retrieve a report') !== false)
				{
					file_put_contents($log_file, date("Y-m-d H:i:s") . "-----Found the pattern in the message body" . PHP_EOL, FILE_APPEND);

					// Delete the message from the queue
					$sqs->deleteMessage([
						'QueueUrl' => $queueUrl,
						'ReceiptHandle' => $message['ReceiptHandle'],
					]);

					file_put_contents($log_file, date("Y-m-d H:i:s") . "-----Message Deleted  ---" . PHP_EOL, FILE_APPEND);
				}
 */




/*
			$notification=json_decode($messageBody, true);


			if (isset($notification['notificationType']) && $notification['notificationType'] === 'REPORT_PROCESSING_FINISHED')
			{


				$ReportData = $notification['payload']['reportProcessingFinishedNotification'];

				if($ReportData['reportType']=='GET_V2_SETTLEMENT_REPORT_DATA_XML')
				{
					$ReportID = $ReportData['reportId'];
					$ReportDocumentID = $ReportData['reportDocumentId'];

					file_put_contents($log_file, date("Y-m-d H:i:s") .'Calling Process Report Function'. PHP_EOL, FILE_APPEND);
					file_put_contents($log_file, date("Y-m-d H:i:s") .'Report ID : '.$ReportID.'     ReportDocument ID : '.$ReportDocumentID . PHP_EOL, FILE_APPEND);

					//$isdownloaded = retrieveAndProcessReport($ReportID, $ReportDocumentID);
					$isdownloaded = ProcessNotification($ReportID, $ReportDocumentID);
					if($isdownloaded)
					{
						 // Delete the message from the queue
						      $sqs->deleteMessage([
						    'QueueUrl' => $queueUrl,
						    'ReceiptHandle' => $message['ReceiptHandle'],
						]);


						file_put_contents($log_file, date("Y-m-d H:i:s") . "---REPORT  DOWNLOADED AND--Message Deleted  ---" . PHP_EOL, FILE_APPEND);

					}


				}
				else
				{
					file_put_contents($log_file, date("Y-m-d H:i:s") .'Report type is not XML'. PHP_EOL, FILE_APPEND);

					// Delete the message from the queue
					$sqs->deleteMessage([
					    'QueueUrl' => $queueUrl,
					    'ReceiptHandle' => $message['ReceiptHandle'],
					]);


					file_put_contents($log_file, date("Y-m-d H:i:s") . "-----Message Deleted  ---" . PHP_EOL, FILE_APPEND);
				}


			}

		}//For each messagebody loop	

	}//While Loop 
}
catch (Exception $e) 
{
	echo 'Error: ' . $e->getMessage();
	file_put_contents($log_file, date("Y-m-d H:i:s") . "---ERROR--".$e->getMessage() . PHP_EOL, FILE_APPEND);
}








function ProcessNotification($ReportID, $ReportDocumentID)
{
	global $DBConn,$log_file;

	file_put_contents($log_file, date("Y-m-d H:i:s") .'Inside ProcessNotification Function'. PHP_EOL, FILE_APPEND);
	sleep(5);

	$Query = pg_query($DBConn,"select marketplace,endpoint,marketplace_id from sp_api_credentials WHERE token_type='LWA_access_token'");
	if($Query)
	{
		$GetDatas = pg_fetch_all($Query);

		foreach ($GetDatas as $GetData)
		{
			$endpoint = $GetData['endpoint'];
			$Marketplace = $GetData['marketplace'];
			$MarketplaceID = $GetData['marketplace_id'];

			file_put_contents($log_file, date("Y-m-d H:i:s") . 'Got the Record for MarketPlace :  '. $Marketplace.', MarketplaceID : '.$MarketplaceID.', Endpoint : '.$endpoint  . PHP_EOL, FILE_APPEND);

			$url = $endpoint.'/reports/2021-06-30/documents/'.$ReportDocumentID;
			file_put_contents($log_file, date("Y-m-d H:i:s") . $url . PHP_EOL, FILE_APPEND);

			file_put_contents($log_file, date("Y-m-d H:i:s") . 'Making Curl Request calling the function'. PHP_EOL, FILE_APPEND);

			$Response = makeCurlRequest($url ,$Marketplace);

			$errorCode = $Response['errors'][0]['code'];
			if($errorCode == "InvalidInput")
			{
				file_put_contents($log_file, date("Y-m-d H:i:s") . 'Invalid Input error indicates here mis match of Marketplace ,Endpoint and following report 10 seconds sleep .' . PHP_EOL, FILE_APPEND);

				sleep(10);
				 // Skip the current iteration and proceed to the next one
				continue;

			}



			file_put_contents($log_file, date("Y-m-d H:i:s") . 'Now Processing URL '. PHP_EOL, FILE_APPEND);

			// URL of the settlement report XML
			$xmlUrl = $Response['url'];

			file_put_contents($log_file, date("Y-m-d H:i:s") . $xmlUrl. PHP_EOL, FILE_APPEND);

			$xmlContent = file_get_contents($xmlUrl);

			if ($xmlContent !== false)
			{
				// Parse the XML content
				$simpleXML = new SimpleXMLElement($xmlContent);

				$simpleXML = simplexml_load_string($xmlContent);

				file_put_contents($log_file, date("Y-m-d H:i:s") . '----------------------------------------------------------------' . PHP_EOL, FILE_APPEND);

				$checkMarketplace = $simpleXML->Message->SettlementReport->Order[0]->MarketplaceName;		

				file_put_contents($log_file, date("Y-m-d H:i:s") . 'Got the marketplace -> '.$checkMarketplace. PHP_EOL, FILE_APPEND);


				$QueryforMarketplace = "SELECT s.marketplace as marketplace, s.token as token, s.marketplace_id as marketplace_id,s.endpoint as endpoint FROM sp_api_credentials s join amazon_fba_marketplaces a on s.marketplace_id=a.marketplaceid WHERE s.token_type='LWA_access_token' and a.marketplace = '$checkMarketplace'";
				file_put_contents($log_file, date("Y-m-d H:i:s") . $QueryforMarketplace. PHP_EOL, FILE_APPEND);
				$QueryExec1 = pg_query($DBConn,$QueryforMarketplace);
				if($QueryExec1)
				{
					$GetData1 = pg_fetch_assoc($QueryExec1);

					$endpoint1 = $GetData1['endpoint'];
					$Marketplace1 = $GetData1['marketplace'];
					$MarketplaceID1 = $GetData1['marketplace_id'];
					$Access_Token1 = $GetData1['token'];

					echo "Marketplace: $Marketplace1" . "<br>";
					echo "Marketplace ID: $MarketplaceID1" ."<br>";
					echo "Endpoint :".$endpoint1 ."<br>";
					echo "Access Token: $Access_Token1" ."<br>";
					echo "------------------------" . "<br>";
					echo "<br>";

					file_put_contents($log_file, date("Y-m-d H:i:s") . 'Got the Record for MarketPlace :  '. $Marketplace1.', MarketplaceID : '.$MarketplaceID1.', Endpoint : '.$endpoint1  . PHP_EOL, FILE_APPEND);

					$isdownloaded = retrieveAndProcessReport($ReportID, $ReportDocumentID,$endpoint1,$Marketplace1,$MarketplaceID1,$Access_Token1);

					if($isdownloaded)
						return true;
					else
						return false;




				}
				else
				{
					file_put_contents($log_file, date("Y-m-d H:i:s") . 'Query Failed for getting marketplace of report '  . PHP_EOL, FILE_APPEND);
				}


				unset($endpoint1,$Marketplace1,$MarketplaceID1,$Access_Token1);


			}
			else
			{
				echo "Failed to retrieve XML content.";
			}

			unset($MarketplaceID,$Marketplace,$endpoint);




		}
	}





}


 */














// Function to retrieve and process the report using SP-API
//function retrieveAndProcessReport($ReportID, $ReportDocumentID)
//function retrieveAndProcessReport($ReportID, $ReportDocumentID,$endpoint,$Marketplace,$MarketplaceID) 
function retrieveAndProcessReport($ReportID, $ReportDocumentID,$endpoint,$Marketplace ,$MarketplaceID)
	//function retrieveAndProcessReport($ReportID, $ReportDocumentID,$endpoint,$Marketplace,$MarketplaceID,$Access_Token)
{
	global $DBConn,$log_file;

	file_put_contents($log_file, date("Y-m-d H:i:s") .'Inside retrieveAndProcessReport Function'. PHP_EOL, FILE_APPEND);
	sleep(5);

	file_put_contents($log_file, date("Y-m-d H:i:s") . 'Got the Record for MarketPlace :  '. $Marketplace.', MarketplaceID : '.$MarketplaceID.', Endpoint : '.$endpoint  . PHP_EOL, FILE_APPEND);

	$url = $endpoint.'/reports/2021-06-30/documents/'.$ReportDocumentID;    
	file_put_contents($log_file, date("Y-m-d H:i:s") . $url . PHP_EOL, FILE_APPEND);
	file_put_contents($log_file, date("Y-m-d H:i:s") . 'Making Curl Request calling the function'. PHP_EOL, FILE_APPEND);


	$Response = makeCurlRequest($url ,$Marketplace);
	file_put_contents($log_file, date("Y-m-d H:i:s") . 'Now Processing URL '. PHP_EOL, FILE_APPEND);

	// URL of the settlement report XML
	$xmlUrl = $Response['url'];

	file_put_contents($log_file, date("Y-m-d H:i:s") . $xmlUrl. PHP_EOL, FILE_APPEND);


	// Download the XML content
	$xmlContent = file_get_contents($xmlUrl);
	file_put_contents($log_file, date("Y-m-d H:i:s") . 'Downloading the file'. PHP_EOL, FILE_APPEND);
	//file_put_contents($log_file, date("Y-m-d H:i:s") . $xmlContent. PHP_EOL, FILE_APPEND);



	if ($xmlContent !== false) 
	{
		file_put_contents($log_file, date("Y-m-d H:i:s") . 'Inside XML if Condition.'. PHP_EOL, FILE_APPEND);

		// Parse the XML content
		$simpleXML = new SimpleXMLElement($xmlContent);

		$simpleXML = simplexml_load_string($xmlContent);

		file_put_contents($log_file, date("Y-m-d H:i:s") . '----------------------------------------------------------------' . PHP_EOL, FILE_APPEND);

		file_put_contents($log_file, date("Y-m-d H:i:s") . ' Done till here  ' . PHP_EOL, FILE_APPEND);	

		$settlementID = $simpleXML->Message->SettlementReport->SettlementData->AmazonSettlementID;

		$file_name = '/tmp/Rawxmldataofsettlementreport_'.$settlementID;


		if (file_exists($file_name)) 
		{
			file_put_contents($log_file, date("Y-m-d H:i:s") .'Raw data file already exists ' . PHP_EOL, FILE_APPEND);
		} 
		else 
		{
			file_put_contents($log_file, date("Y-m-d H:i:s") . 'Data Dumped in file name ->  '.$file_name . PHP_EOL, FILE_APPEND);  

			//Dumping the Raw Data to a File.
			file_put_contents($file_name,$xmlContent);
		}



		// Convert XML to json
		$json = json_encode($simpleXML);

		$checkQuery = "select amazonsettlementid from settlement_data where amazonsettlementid='$settlementID'";
		file_put_contents($log_file, date("Y-m-d H:i:s") . $checkQuery . PHP_EOL, FILE_APPEND);
		$ExecuteCheckQuery = pg_query($DBConn,$checkQuery);
		$checkSettlementID = pg_fetch_assoc($ExecuteCheckQuery);
		$ISsettlementIdpresentinsettlementdatatable = $checkSettlementID['amazonsettlementid'];

		$checkQuery2 = "select settlement_id from settlement_report where settlement_id='$settlementID' limit 1";
		file_put_contents($log_file, date("Y-m-d H:i:s") . $checkQuery2 . PHP_EOL, FILE_APPEND);
		$ExecuteCheckQuery2 = pg_query($DBConn,$checkQuery2);
		$checkSettlementID2 = pg_fetch_assoc($ExecuteCheckQuery2);
		$ISsettlementId2_presentinsettlementreporttable = $checkSettlementID2['settlement_id'];

		if($ISsettlementIdpresentinsettlementdatatable > 0 && $ISsettlementId2_presentinsettlementreporttable > 0)
		{
			file_put_contents($log_file, date("Y-m-d H:i:s") .'Settlement ID already present in the table avoid duplication not entering ' . PHP_EOL, FILE_APPEND);
		}
		else 
		{
			$insertdataquery = "INSERT INTO settlement_data (marketplace, amazonsettlementid, json_data) VALUES ($1, $2, $3)";
			file_put_contents($log_file, date("Y-m-d H:i:s") . $insertdataquery . PHP_EOL, FILE_APPEND);

			$insertQueryExecute = pg_query_params($DBConn, $insertdataquery, [$Marketplace, $settlementID, $json]);

			if ($insertQueryExecute) 
			{
				file_put_contents($log_file, date("Y-m-d H:i:s") . 'json Data inserted into settlement data in JSONB table Successfully' . PHP_EOL, FILE_APPEND);
			} 
			else 
			{
				file_put_contents($log_file, date("Y-m-d H:i:s") . 'json Data inserted into settlement data in JSONB table Failed' . PHP_EOL, FILE_APPEND);

				$error = pg_last_error($DBConn);

				if (strpos($error, '23505') !== false) 
				{
					// Handle the case where the primary key constraint is violated
					file_put_contents($log_file, date("Y-m-d H:i:s") . 'Primary key violation: Data already present in settlement data table' . PHP_EOL, FILE_APPEND);
				} 
				else 
				{
					// Handle other types of PostgreSQL errors
					file_put_contents($log_file, date("Y-m-d H:i:s") . 'Error: ' . $error . PHP_EOL, FILE_APPEND);
				}
			}

			$checkQuery3 = "select settlement_id from settlement_report where settlement_id='$settlementID' limit 1";
			file_put_contents($log_file, date("Y-m-d H:i:s") . $checkQuery3 . PHP_EOL, FILE_APPEND);
			$ExecuteCheckQuery3 = pg_query($DBConn,$checkQuery3);
			$checkSettlementID3 = pg_fetch_row($ExecuteCheckQuery3);
			//$ISsettlementId2_presentinsettlementreporttable = $checkSettlementID2['settlement_id'];

			if($checkSettlementID3 <= 0)
			{

				$checkMarketplace = $simpleXML->Message->SettlementReport->Order[0]->MarketplaceName;
				$checkMarketplace = empty($checkMarketplace)?$Marketplace:$checkMarketplace;

				file_put_contents($log_file, date("Y-m-d H:i:s") . "**************** CURRENT MARKETPLACE AND MARKETPLACE IDs ITERATION **************" . PHP_EOL, FILE_APPEND);
				file_put_contents($log_file, date("Y-m-d H:i:s") .  'Marketplace -----> '.  $checkMarketplace .'          Marketplace_ID -----> '. $MarketplaceID . PHP_EOL, FILE_APPEND);

				//$Query = "select marketplaceid from amazon_fba_marketplaces where marketplace = '$checkMarketplace'";
				$Query = "select a.marketplaceid,s.marketplace_id from amazon_fba_marketplaces a join sp_api_credentials s on a.marketplaceid = s.marketplace_id where (s.marketplace='$checkMarketplace' OR a.marketplace='$checkMarketplace') limit 1";
				file_put_contents($log_file, date("Y-m-d H:i:s") . $Query . PHP_EOL, FILE_APPEND);
				$ExecQuery = pg_query($DBConn,$Query);
				$getcheckMarketplaceid = pg_fetch_assoc($ExecQuery);
				$checkMarketplaceid = $getcheckMarketplaceid['marketplaceid'];
				if($checkMarketplaceid == $MarketplaceID)
				{

					//Adding the bigger loop to have all type of orders,refund,retrocharges,other Transaction,etc.
					foreach($simpleXML->Message->SettlementReport as $SettlementReport)
					{
						//THis is Settlement Data
						$amazonSettlementID = (string) $SettlementReport->SettlementData->AmazonSettlementID;
						$totalAmount = (float) $SettlementReport->SettlementData->TotalAmount;
						$currency = (string) $SettlementReport->SettlementData->TotalAmount['currency']; 
						$startDate = (string) $SettlementReport->SettlementData->StartDate;
						$endDate = (string) $SettlementReport->SettlementData->EndDate;
						$depositDate = (string) $SettlementReport->SettlementData->DepositDate;
						$MarketplaceName = (string) $SettlementReport->Order[0]->MarketplaceName;	

						file_put_contents($log_file, date("Y-m-d H:i:s") . 'SETTLEMENT DATA' . PHP_EOL, FILE_APPEND);
						file_put_contents($log_file, date("Y-m-d H:i:s") . $amazonSettlementID . PHP_EOL, FILE_APPEND);
						file_put_contents($log_file, date("Y-m-d H:i:s") . $totalAmount . PHP_EOL, FILE_APPEND);
						file_put_contents($log_file, date("Y-m-d H:i:s") . $startDate . PHP_EOL, FILE_APPEND);
						file_put_contents($log_file, date("Y-m-d H:i:s") . $endDate . PHP_EOL, FILE_APPEND);
						file_put_contents($log_file, date("Y-m-d H:i:s") . $depositDate . PHP_EOL, FILE_APPEND);

						$insertQuery = "INSERT INTO settlement_report (date_time, settlement_id, type, order_id, sku, description, quantity, marketplace,fulfillment, order_city, order_state, order_postal, tax_collection_model, product_sales,product_sales_tax, shipping_credits, shipping_credits_tax, gift_wrap_credits,giftwrap_credits_tax, regulatory_fee, tax_on_regulatory_fee, promotional_rebates,promotional_rebates_tax, marketplace_withheld_tax, selling_fees, fba_fees,other_transaction_fees, other, currency,amazonOrderItemCode,shipmentid_or_adjustmentid,marketplace_id) VALUES ('$startDate', '$amazonSettlementID', 'Total Settlement Amount', '0', '','', '0', '$MarketplaceName', '', '', '', '', '','0','0','0','0','0','0','0',' 0','0','0','0','0','0','0', '-$totalAmount','$currency','0','0','$MarketplaceID')";



						file_put_contents($log_file, date("Y-m-d H:i:s") . $insertQuery . PHP_EOL, FILE_APPEND);
						$insertQueryExec = pg_query($DBConn, $insertQuery);
						sleep(1);
						if($insertQueryExec)
						{
							file_put_contents($log_file, date("Y-m-d H:i:s") .'Total Amount'.$totalAmount .'Inserted Suceessfully' . PHP_EOL, FILE_APPEND);

						}
						else
						{
							file_put_contents($log_file, date("Y-m-d H:i:s") .'Total Amount'.$totalAmount .'Insertion Failed' . PHP_EOL, FILE_APPEND);
						}


						foreach ($SettlementReport->Order as $order) 
						{
							$amazonOrderId = (string) $order->AmazonOrderID;
							$merchantOrderId = (string) $order->MerchantOrderID;
							$shipmentId = (string) $order->ShipmentID;
							$marketplaceName = (string) $order->MarketplaceName;
							$merchandFulfilmentId = (string) $order->Fulfillment->MerchantFulfillmentID;
							$postedDate = (string) $order->Fulfillment->PostedDate;
							$currency0 = (string) $order->Fulfillment->Item->ItemPrice->Component->Amount['currency'];
							$regulatory_fee = (string) $order->Fulfillment->Item->CostOfPointsGranted->Amount;



							file_put_contents($log_file, date("Y-m-d H:i:s") . 'ORDER DATA ' . PHP_EOL, FILE_APPEND);
							file_put_contents($log_file, date("Y-m-d H:i:s") .'amazonOrderId -> '.$amazonOrderId . PHP_EOL, FILE_APPEND);
							file_put_contents($log_file, date("Y-m-d H:i:s") .' merchantOrderId -> '.$merchantOrderId . PHP_EOL, FILE_APPEND);
							file_put_contents($log_file, date("Y-m-d H:i:s") .'shipmentId -> '.$shipmentId . PHP_EOL, FILE_APPEND);
							file_put_contents($log_file, date("Y-m-d H:i:s") .'marketplaceName -> ' .$marketplaceName . PHP_EOL, FILE_APPEND);
							file_put_contents($log_file, date("Y-m-d H:i:s") .'merchandFulfilmentId -> ' .$merchandFulfilmentId . PHP_EOL, FILE_APPEND);
							file_put_contents($log_file, date("Y-m-d H:i:s") .'postedDate -> '. $postedDate . PHP_EOL, FILE_APPEND);
							file_put_contents($log_file, date("Y-m-d H:i:s") . 'regulatory_fee -> '.$regulatory_fee . PHP_EOL, FILE_APPEND);
							//					file_put_contents($log_file, date("Y-m-d H:i:s") .  . PHP_EOL, FILE_APPEND);
							//					file_put_contents($log_file, date("Y-m-d H:i:s") .  . PHP_EOL, FILE_APPEND);

							foreach($order->Fulfillment->Item as $item)
							{
								$amazonOrderItemCode = (string) $item->AmazonOrderItemCode;
								$sku = (string) $item->SKU;
								$quantity = (int) $item->Quantity;


								//							$fba_fee = (float) $item->ItemFees->Fee[0]->Amount;
								//							$selling_fee = (float) $item->ItemFees->Fee[1]->Amount;
								//							$ShippingChargeback = (float) $item->ItemFees->Fee[2]->Amount;
								$promotional_rebates = (float) $item->Promotion->Amount; 

								foreach($item->ItemFees->Fee as $fee)
								{
									$type = (string) $fee->Type;
									$amount = (float) $fee->Amount;

									// Check the Type and assign the Amount accordingly
									if ($type === 'FBAPerUnitFulfillmentFee')
									{
										$fba_fee = $amount;
									}
									elseif ($type === 'Commission')
									{
										$selling_fee = $amount;
									}
									elseif($type === 'ShippingChargeback')
									{
										$ShippingChargeback = $amount;
									}
								}
								$fba_fee = empty($fba_fee)?0:$fba_fee;
								$selling_fee = empty($selling_fee)?0:$selling_fee;
								$ShippingChargeback = empty($ShippingChargeback)?0:$ShippingChargeback;




								foreach ($item->ItemPrice->Component as $component) 
								{
									$type = (string) $component->Type;
									$amount = (float) $component->Amount;

									// Check the Type and assign the Amount accordingly
									if ($type === 'Principal') 
									{
										$product_sales = $amount;
									} 
									elseif ($type === 'Shipping') 
									{
										$shipping_credits = $amount;
									} 
									elseif ($type === 'Tax') 
									{
										$product_sales_tax = $amount;
									} 
									elseif ($type === 'MarketplaceFacilitatorTax-Principal') 
									{
										$marketplace_withheld_tax = $amount;
										$tax_collection_model = $type;
									}
									elseif ($type === "ShippingTax")
									{
										$shipping_credits_tax = $amount;

									}
									elseif($type === "MarketplaceFacilitatorVAT-Principal")
									{
										$marketplace_withheld_tax = $amount;
										$tax_collection_model = $type;
									}
									elseif($type === 'MarketplaceFacilitatorTax-Shipping')
									{
										$marketplace_shipping_tax = $amount;
									}
									elseif($type === 'MarketplaceFacilitatorVAT-Shipping')
									{
										$marketplace_shipping_tax = $amount;
									}

								}

								$marketplace_shipping_tax = empty($marketplace_shipping_tax)?0:$marketplace_shipping_tax;

								$marketplace_withheld_tax = $marketplace_withheld_tax + $marketplace_shipping_tax;


								file_put_contents($log_file, date("Y-m-d H:i:s") . 'ORDER ITEM DATA ' . PHP_EOL, FILE_APPEND);
								file_put_contents($log_file, date("Y-m-d H:i:s") . $amazonOrderItemCode . PHP_EOL, FILE_APPEND);
								file_put_contents($log_file, date("Y-m-d H:i:s") . $sku . PHP_EOL, FILE_APPEND);
								file_put_contents($log_file, date("Y-m-d H:i:s") . $quantity . PHP_EOL, FILE_APPEND);
								file_put_contents($log_file, date("Y-m-d H:i:s") . $product_sales . PHP_EOL, FILE_APPEND);
								file_put_contents($log_file, date("Y-m-d H:i:s") . $fba_fee . PHP_EOL, FILE_APPEND);
								file_put_contents($log_file, date("Y-m-d H:i:s") . $currency0 . PHP_EOL, FILE_APPEND);
								file_put_contents($log_file, date("Y-m-d H:i:s") . $selling_fee . PHP_EOL, FILE_APPEND);
								file_put_contents($log_file, date("Y-m-d H:i:s") . $promotional_rebates . PHP_EOL, FILE_APPEND);
								file_put_contents($log_file, date("Y-m-d H:i:s") . $product_sales_tax . PHP_EOL, FILE_APPEND);


								$shipping_credits = empty($shipping_credits)?0:$shipping_credits;
								$product_sales = empty($product_sales)?0:$product_sales;
								$product_sales_tax = empty($product_sales_tax)?0:$product_sales_tax;
								$promotional_rebates = empty($promotional_rebates)?0:$promotional_rebates;
								$marketplace_withheld_tax = empty($marketplace_withheld_tax)?0:$marketplace_withheld_tax;
								$selling_fee = empty($selling_fee)?0:$selling_fee;
								$fba_fee = empty($fba_fee)?0:$fba_fee;
								$ShippingChargeback = empty($ShippingChargeback)?0:$ShippingChargeback;
								$fba_fee = $fba_fee + $ShippingChargeback;
								$amazonOrderItemCode = empty($amazonOrderItemCode)?0:$amazonOrderItemCode;
								$shipping_credits_tax =  empty($shipping_credits_tax)?0:$shipping_credits_tax;
								$regulatory_fee = empty($regulatory_fee)?0:$regulatory_fee;

								$insertQuery = "INSERT INTO settlement_report (date_time, settlement_id, type, order_id, sku, description, quantity, marketplace,fulfillment, order_city, order_state, order_postal, tax_collection_model, product_sales,product_sales_tax, shipping_credits, shipping_credits_tax, gift_wrap_credits,giftwrap_credits_tax, regulatory_fee, tax_on_regulatory_fee, promotional_rebates,promotional_rebates_tax, marketplace_withheld_tax, selling_fees, fba_fees,other_transaction_fees, other, currency,amazonOrderItemCode,shipmentid_or_adjustmentid,marketplace_id) VALUES ('$postedDate', '$amazonSettlementID', 'Order', '$amazonOrderId', '$sku','', '$quantity', '$marketplaceName', '$merchandFulfilmentId', '', '', '', '$tax_collection_model','$product_sales','$product_sales_tax','$shipping_credits','$shipping_credits_tax','0','0','$regulatory_fee',' 0','$promotional_rebates','0','$marketplace_withheld_tax','$selling_fee','$fba_fee','0', '0', '$currency0','$amazonOrderItemCode','$shipmentId','$MarketplaceID')";

								file_put_contents($log_file, date("Y-m-d H:i:s") . $insertQuery . PHP_EOL, FILE_APPEND);
								$insertQueryExec = pg_query($DBConn, $insertQuery);
								sleep(1);
								if($insertQueryExec)
								{
									file_put_contents($log_file, date("Y-m-d H:i:s") .'Amazon order No'.$amazonOrderId .'Inserted Suceessfully' . PHP_EOL, FILE_APPEND);
								}
								else
								{
									file_put_contents($log_file, date("Y-m-d H:i:s") .'Amazon order No'.$amazonOrderId .'Insertion Failed' . PHP_EOL, FILE_APPEND);
								}

								echo $amazonOrderID ."<br>";	
								echo $shipmentID ."<br>"; 			
								echo $marketplaceName ."<br>";
								echo $fulfillmentID ."<br>";
								echo $postedDate ."<br>";
								echo $sku ."<br>";
								echo $quantity ."<br>";
								echo $itemPrice ."<br>";
								//							echo $fees ."<br>";
								//							echo $fbaFulfillmentFee ."<br>";
								//							echo $commissionFee ."<br>";

								sleep(1);

								unset($marketplace_withheld_tax,$shipping_credits,$sku,$quantity,$fba_fee,$selling_fee,$promotional_rebates,$product_sales,$product_sales_tax,$shipping_credits_tax,$marketplace_shipping_tax,$ShippingChargeback);

							}//Order Item Foreach Loop			

							unset($amazonOrderId,$merchantOrderId,$shipmentId,$marketplaceName,$merchandFulfilmentId,$postedDate,$currency0,$regulatory_fee);



						}//Order Foreach Loop



						//Refund Order For Loop		
						foreach ($SettlementReport->Refund as $refund)
						{
							$amazonOrderId = (string) $refund->AmazonOrderID;
							$merchantOrderId = (string) $refund->MerchantOrderID;
							$adjustmentId = (string) $refund->AdjustmentID;
							$marketplaceName = (string) $refund->MarketplaceName;
							$merchandFulfilmentId = (string) $refund->Fulfillment->MerchantFulfillmentID;
							$postedDate = (string) $refund->Fulfillment->PostedDate;
							$currency1 = (string) $refund->Fulfillment->AdjustedItem->ItemPriceAdjustments->Component->Amount['currency'];


							file_put_contents($log_file, date("Y-m-d H:i:s") . 'ORDER DATA ' . PHP_EOL, FILE_APPEND);
							file_put_contents($log_file, date("Y-m-d H:i:s") . $amazonOrderId . PHP_EOL, FILE_APPEND);
							file_put_contents($log_file, date("Y-m-d H:i:s") . $merchantOrderId . PHP_EOL, FILE_APPEND);
							file_put_contents($log_file, date("Y-m-d H:i:s") . $shipmentId . PHP_EOL, FILE_APPEND);
							file_put_contents($log_file, date("Y-m-d H:i:s") . $marketplaceName . PHP_EOL, FILE_APPEND);
							file_put_contents($log_file, date("Y-m-d H:i:s") . $merchandFulfilmentId . PHP_EOL, FILE_APPEND);
							file_put_contents($log_file, date("Y-m-d H:i:s") . $postedDate . PHP_EOL, FILE_APPEND);
							//                                      file_put_contents($log_file, date("Y-m-d H:i:s") .  . PHP_EOL, FILE_APPEND);
							//                                      file_put_contents($log_file, date("Y-m-d H:i:s") .  . PHP_EOL, FILE_APPEND);



							foreach($refund->Fulfillment->AdjustedItem as $item)
							{
								$amazonOrderItemCode = (string) $item->AmazonOrderItemCode;
								$sku = (string) $item->SKU;
								$quantity = (int) $item->Quantity;
								//							$product_sales = (float) $item->ItemPriceAdjustments->Component[0]->Amount;
								//							$product_sales_tax = (float) $item->ItemPriceAdjustments->Component[1]->Amount;
								//							$marketplace_withheld_tax = (float) $item->ItemPriceAdjustments->Component[2]->Amount;
								//							$selling_fee = (float) $item->ItemFeeAdjustments->Fee[0]->Amount;
								//							$RefundCommission = (float) $item->ItemFeeAdjustments->Fee[1]->Amount;
								//							$fba_fees = (float) $item->ItemFeeAdjustments->Fee[2]->Amount;
								$promotional_rebates = (float) $item->PromotionAdjustment->Amount;

								foreach($item->ItemFeeAdjustments->Fee as $fee)
								{
									$type = (string) $fee->Type;
									$amount = (float) $fee->Amount;

									if ($type === 'Commission')
									{
										$selling_fee = $amount;
									}
									elseif ($type === 'RefundCommission')
									{
										$RefundCommission = $amount;
									}
									elseif($type === 'ShippingChargeback')
									{
										$fba_fees = $amount;
									}

								}

								foreach ($item->ItemPriceAdjustments->Component as $component)
								{
									$type = (string) $component->Type;
									$amount = (float) $component->Amount;

									// Check the Type and assign the Amount accordingly
									if ($type === 'Principal')
									{
										$product_sales = $amount;
									}
									elseif ($type === 'Shipping')
									{
										$shipping_credits = $amount;
									}
									elseif ($type === 'Tax')
									{
										$product_sales_tax = $amount;
									}
									elseif ($type === 'MarketplaceFacilitatorTax-Principal')
									{
										$marketplace_withheld_tax = $amount;
										$tax_collection_model = $type;
									}
									elseif($type === "MarketplaceFacilitatorVAT-Principal")
									{
										$marketplace_withheld_tax = $amount;
										$tax_collection_model = $type;
									}
									elseif($type === 'RestockingFee')
									{
										$other = $amount;
									}
									elseif($type === 'MarketplaceFacilitatorTax-RestockingFee')
									{
										$restockingfeetax = $amount;
									}
								}


								$restockingfeetax= empty($restockingfeetax)?0: $restockingfeetax;
								$fba_fees = empty($fba_fees)?0:$fba_fees;
								$selling_fee = empty($selling_fee)?0:$selling_fee;
								$RefundCommission = empty($RefundCommission)?0:$RefundCommission;							
								$other = empty($other)?0:$other;

								$other = $other + $restockingfeetax;




								file_put_contents($log_file, date("Y-m-d H:i:s") . 'ORDER ITEM DATA ' . PHP_EOL, FILE_APPEND);
								file_put_contents($log_file, date("Y-m-d H:i:s") . $amazonOrderItemCode . PHP_EOL, FILE_APPEND);
								file_put_contents($log_file, date("Y-m-d H:i:s") . $merchantOrderItemID . PHP_EOL, FILE_APPEND);
								file_put_contents($log_file, date("Y-m-d H:i:s") . $sku . PHP_EOL, FILE_APPEND);
								file_put_contents($log_file, date("Y-m-d H:i:s") . $quantity . PHP_EOL, FILE_APPEND);
								file_put_contents($log_file, date("Y-m-d H:i:s") . $product_sales . PHP_EOL, FILE_APPEND);
								file_put_contents($log_file, date("Y-m-d H:i:s") . $fba_fee . PHP_EOL, FILE_APPEND);
								file_put_contents($log_file, date("Y-m-d H:i:s") . $currency1 . PHP_EOL, FILE_APPEND);
								file_put_contents($log_file, date("Y-m-d H:i:s") . $selling_fee . PHP_EOL, FILE_APPEND);
								file_put_contents($log_file, date("Y-m-d H:i:s") . $promotional_rebates . PHP_EOL, FILE_APPEND);
								file_put_contents($log_file, date("Y-m-d H:i:s") . $product_sales_tax . PHP_EOL, FILE_APPEND);

								file_put_contents($log_file, date("Y-m-d H:i:s") .$selling_fees . PHP_EOL, FILE_APPEND);
								file_put_contents($log_file, date("Y-m-d H:i:s") .$RefundCommission  . PHP_EOL, FILE_APPEND);



								file_put_contents($log_file, date("Y-m-d H:i:s") .'Selling Fees BeFORE Refund COMISSION -------------->'.$selling_fee . PHP_EOL, FILE_APPEND);
								$final_selling_fee = $selling_fee + $RefundCommission;
								file_put_contents($log_file, date("Y-m-d H:i:s") .'SELLING FEES AFTER MINUS REFUND COMMISSION++++++++++++>>'.$final_selling_fee . PHP_EOL, FILE_APPEND);




								$product_sales = empty($product_sales)?0:$product_sales;
								$product_sales_tax = empty($product_sales_tax)?0:$product_sales_tax;
								$promotional_rebates = empty($promotional_rebates)?0:$promotional_rebates;
								$marketplace_withheld_tax = empty($marketplace_withheld_tax)?0:$marketplace_withheld_tax;
								$final_selling_fee = empty($final_selling_fee)?0:$final_selling_fee;
								$amazonOrderItemCode = empty($amazonOrderItemCode)?0:$amazonOrderItemCode;
								$shipping_credits =  empty($shipping_credits)?0:$shipping_credits;
								//							$quantity = empty($quantity)?null:$quantity;



								$insertQuery = "INSERT INTO settlement_report (date_time, settlement_id, type, order_id, sku, description, marketplace, fulfillment, order_city, order_state, order_postal, tax_collection_model, product_sales, product_sales_tax, shipping_credits, shipping_credits_tax, gift_wrap_credits, giftwrap_credits_tax, regulatory_fee, tax_on_regulatory_fee, promotional_rebates, promotional_rebates_tax, marketplace_withheld_tax, selling_fees, fba_fees, other_transaction_fees, other, currency,amazonOrderItemCode,shipmentid_or_adjustmentid,marketplace_id) VALUES ('$postedDate', '$amazonSettlementID', 'Refund', '$amazonOrderId', '$sku', '','$marketplaceName', '$merchandFulfilmentId', '', '', '', '$tax_collection_model','$product_sales','$product_sales_tax','$shipping_credits','0','0','0','0','0','$promotional_rebates','0','$marketplace_withheld_tax','$final_selling_fee', '$fba_fees', 0, $other, '$currency1','$amazonOrderItemCode','$adjustmentId','$MarketplaceID')";




								file_put_contents($log_file, date("Y-m-d H:i:s") . $insertQuery . PHP_EOL, FILE_APPEND);
								$insertQueryExec = pg_query($DBConn, $insertQuery);
								sleep(1);
								if($insertQueryExec)
								{
									file_put_contents($log_file, date("Y-m-d H:i:s") .'Amazon order No'.$amazonOrderId .'Inserted Suceessfully' . PHP_EOL, FILE_APPEND);

								}
								else
								{
									file_put_contents($log_file, date("Y-m-d H:i:s") .'Amazon order No'.$amazonOrderId .'Insertion Failed' . PHP_EOL, FILE_APPEND);
								}

								unset($marketplace_withheld_tax,$shipping_credits,$quantity,$fba_fee,$final_selling_fee,$product_sales,$product_sales_tax,$other,$restockingfeetax,$fba_fees,$selling_fee,$RefundCommission,$promotional_rebates);


							}//Foreach Loop for Refund Order Items.


							unset($amazonOrderId,$merchantOrderId,$adjustmentId,$marketplaceName,$merchandFulfilmentId,$postedDate,$currency1);


							sleep(1);

						}//Foreach Loop for Refund orders









						//ForeachLoop For Other Transactions
						foreach($SettlementReport->OtherTransaction as $otherTransaction)
						{
							$AmazonOrderID = (string) $otherTransaction->AmazonOrderID;
							$TransactionType = (string) $otherTransaction->TransactionType;
							$TransactionID = (string) $otherTransaction->TransactionID;
							$PostedDate = (string) $otherTransaction->PostedDate;
							//						$Amount = (float) $otherTransaction->Amount;
							//						$Amount = (float) $otherTransaction->Fee->Amount;
							//						$Amount = (float) $otherTransaction->Charge->Amount;
							$Amount = 0; // Initialize to zero

							if (isset($otherTransaction->Amount)) {
								$Amount = (float) $otherTransaction->Amount;
							} elseif (isset($otherTransaction->Fee->Amount)) {
								$Amount = (float) $otherTransaction->Fee->Amount;
							} elseif (isset($otherTransaction->Charge->Amount)) {
								$Amount = (float) $otherTransaction->Charge->Amount;
							}						
							$SKU = (string) $otherTransaction->OtherTransactionItem->SKU;
							$Quantity = (int) $otherTransaction->OtherTransactionItem->Quantity;
							$marketplaceName = (string) $SettlementReport->Order->MarketplaceName;


							$SKU = empty($SKU)?null:$SKU;
							//						$Quantity =empty($Quantity)?null:$Quantity;



							$Amount = empty($Amount)?0:$Amount;					
							$amazonOrderItemCode = empty($amazonOrderItemCode)?0:$amazonOrderItemCode;
							$shipmentid_or_adjustmentid = empty($TransactionID)?0:$TransactionID;

							$insertQuery = "INSERT INTO settlement_report (date_time, settlement_id, type, order_id, sku, description, quantity, marketplace, fulfillment, order_city, order_state, order_postal, tax_collection_model,product_sales, product_sales_tax, shipping_credits, shipping_credits_tax, gift_wrap_credits, giftwrap_credits_tax, regulatory_fee, tax_on_regulatory_fee, promotional_rebates, promotional_rebates_tax, marketplace_withheld_tax, selling_fees, fba_fees, other, currency,amazonOrderItemCode,shipmentid_or_adjustmentid,marketplace_id) VALUES ('$PostedDate', '$amazonSettlementID', '".$TransactionType."_other_transaction', '$AmazonOrderID', '$SKU', '', '$Quantity', '$marketplaceName', '', '', '', '', '','0', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, $Amount, '$currency','$amazonOrderItemCode','$shipmentid_or_adjustmentid','$MarketplaceID')";


							file_put_contents($log_file, date("Y-m-d H:i:s") . $insertQuery . PHP_EOL, FILE_APPEND);
							$insertQueryExec = pg_query($DBConn, $insertQuery);
							sleep(1);
							if($insertQueryExec)
							{
								file_put_contents($log_file, date("Y-m-d H:i:s") .'Amazon order No '.$amazonOrderId .' Inserted Suceessfully' . PHP_EOL, FILE_APPEND);
							}
							else
							{
								file_put_contents($log_file, date("Y-m-d H:i:s") .'Amazon order No '.$amazonOrderId .' Insertion Failed' . PHP_EOL, FILE_APPEND);
							}


							unset($amazonOrderItemCode,$AmazonOrderID,$TransactionType,$TransactionID,$PostedDate,$Amount,$SKU,$Quantity,$marketplaceName);

						}//Loop End For Other Transactions







						//ForeachLoop for Retrocharge
						foreach($SettlementReport->Retrocharge as $retrocharge)
						{
							$PostedDate = (string)$retrocharge->PostedDate;
							$BaseTax = (float)$retrocharge->BaseTax->Amount;
							$ShippingTax = (float)$retrocharge->ShippingTax->Amount;
							$MarketplaceName = (string)$retrocharge->MarketplaceName;
							$AmazonOrderID = (string)$retrocharge->AmazonOrderID;
							$marketplace_withheld_tax = (float)$retrocharge->Charge->Amount;


							$marketplace_withheld_tax = empty($marketplace_withheld_tax)?0:$marketplace_withheld_tax;
							$ShippingTax = empty($ShippingTax)?0:$ShippingTax;
							$BaseTax = empty($BaseTax)?0:$BaseTax;
							$shipmentid_or_adjustmentid = empty($shipmentid_or_adjustmentid)?0:$shipmentid_or_adjustmentid;	
							$amazonOrderItemCode = empty($amazonOrderItemCode)?0:$amazonOrderItemCode;

							$insertQuery = "INSERT INTO settlement_report (date_time, settlement_id, type, order_id, sku, description, quantity, marketplace,fulfillment, order_city, order_state, order_postal, tax_collection_model, product_sales, product_sales_tax, shipping_credits, shipping_credits_tax, gift_wrap_credits, giftwrap_credits_tax, regulatory_fee, tax_on_regulatory_fee, promotional_rebates, promotional_rebates_tax, marketplace_withheld_tax, selling_fees, fba_fees, other, currency,amazonOrderItemCode,shipmentid_or_adjustmentid,marketplace_id) VALUES ('$PostedDate', '$amazonSettlementID', 'Order_retrocharge', '$AmazonOrderID', '', '', 0,'$MarketplaceName','', '', '', '', '', 0, $BaseTax, 0, $ShippingTax, 0, 0, 0, 0, 0, 0,'$marketplace_withheld_tax', 0, 0, 0, '$currency','$amazonOrderItemCode','$shipmentid_or_adjustmentid','$MarketplaceID')";


							file_put_contents($log_file, date("Y-m-d H:i:s") . $insertQuery . PHP_EOL, FILE_APPEND);
							$insertQueryExec = pg_query($DBConn, $insertQuery);
							sleep(1);
							if($insertQueryExec)
							{
								file_put_contents($log_file, date("Y-m-d H:i:s") .'Amazon order No'.$amazonOrderId .'Inserted Suceessfully' . PHP_EOL, FILE_APPEND);
							}
							else
							{	
								file_put_contents($log_file, date("Y-m-d H:i:s") .'Amazon order No'.$amazonOrderId .'Insertion Failed' . PHP_EOL, FILE_APPEND);
							}

							unset($amazonOrderItemCode,$PostedDate,$ShippingTax,$MarketplaceName,$AmazonOrderID,$BaseTax,$marketplace_withheld_tax);

						}//Looop End for the Retrocharge Transaction







						//Foreach Loop for Liquidations
						foreach($SettlementReport->Liquidations as $liquidations)
						{
							$PostedDate = (string) $liquidations->PostedDate;
							$orderId = (string)$liquidations->OrderId;

							$Quantity = (int) $liquidations->Item->Quantity;
							$SKU = (string)$liquidations->Item->SKU;
							$ShipmentID = (string) $liquidations->Item->ShipmentID;


							foreach ($liquidations->Item->Component as $component)
							{
								$type = (string) $component->Type;
								$amount = (float) $component->Amount;

								// Check the Type and assign the Amount accordingly
								if ($type === 'Principal')
								{
									$product_sales = $amount;
								}
								elseif ($type === 'Tax')
								{
									$product_sales_tax = $amount;
								}
								elseif ($type === 'MarketplaceFacilitatorTax-Principal')
								{
									$marketplace_withheld_tax = $amount;
									$tax_collection_model = $type;
								}
								elseif($type === "LiquidationsBrokerageFee")
								{
									$other_transaction_fees = $amount;
									$tax_collection_model = $type;
								}

							}


							$marketplaceName = (string) $SettlementReport->Order->MarketplaceName;


							$product_sales = empty($product_sales)?0:$product_sales;
							$product_sales_tax = empty($product_sales_tax)?0:$product_sales_tax;
							$marketplace_withheld_tax = empty($marketplace_withheld_tax)?0:$marketplace_withheld_tax;
							$other_transaction_fees = empty($other_transaction_fees)?0:$other_transaction_fees;
							$amazonOrderItemCode = empty($amazonOrderItemCode)?0:$amazonOrderItemCode;
							$ShipmentID = empty($ShipmentID)?0:$ShipmentID;
							//						$Quantity = empty($Quantity)?0:$Quantity;

							$insertQuery = "INSERT INTO settlement_report (date_time, settlement_id, type, order_id, sku, description, quantity, marketplace, fulfillment, order_city, order_state, order_postal, tax_collection_model,product_sales, product_sales_tax, shipping_credits, shipping_credits_tax, gift_wrap_credits, giftwrap_credits_tax, regulatory_fee, tax_on_regulatory_fee, promotional_rebates, promotional_rebates_tax, marketplace_withheld_tax, selling_fees, fba_fees, other_transaction_fees, other, currency,amazonOrderItemCode,shipmentid_or_adjustmentid,marketplace_id) VALUES ('$PostedDate', '$amazonSettlementID', 'Liquidation', '$orderId', '$SKU', '', $Quantity, '$marketplaceName', '', '', '', '', '$tax_collection_model','$product_sales', '$product_sales_tax', 0, 0, 0, 0, 0, 0, 0, 0, $marketplace_withheld_tax, 0, 0,'$other_transaction_fees', 0, '$currency','$amazonOrderItemCode','$ShipmentID','$MarketplaceID')";

							file_put_contents($log_file, date("Y-m-d H:i:s") . $insertQuery . PHP_EOL, FILE_APPEND);
							$insertQueryExec = pg_query($DBConn, $insertQuery);
							sleep(1);
							if($insertQueryExec)
							{
								file_put_contents($log_file, date("Y-m-d H:i:s") .'Amazon order No'.$amazonOrderId .'Inserted Suceessfully' . PHP_EOL, FILE_APPEND);
							}
							else
							{
								file_put_contents($log_file, date("Y-m-d H:i:s") .'Amazon order No'.$amazonOrderId .'Insertion Failed' . PHP_EOL, FILE_APPEND);
							}

							unset($amazonOrderItemCode,$PostedDate,$orderId,$SKU,$ShipmentID,$product_sales,$other_transaction_fees,$Quantity,$marketplaceName,$product_sales_tax,$marketplace_withheld_tax);

						}//Loop End for the Liquidations Transactions








						//foreach Loop for Chargeback
						foreach($SettlementReport->Chargeback as $chargeback)
						{
							$AmazonOrderID = (string)$chargeback->AmazonOrderID;
							$AdjustmentID = (string)$chargeback->AdjustmentID;
							$MarketplaceName = (string)$chargeback->MarketplaceName;
							$PostedDate =(string)$chargeback->Fulfillment->PostedDate;
							$MerchantFulfillmentID = (string)$chargeback->Fulfillment->MerchantFulfillmentID;
							$SKU = (string)$chargeback->Fulfillment->AdjustedItem->SKU;
							$product_sales = (float)$chargeback->Fulfillment->AdjustedItem->ItemPriceAdjustments->Component->Amount;

							$product_sales = empty($product_sales)?0:$product_sales;
							$amazonOrderItemCode = empty($amazonOrderItemCode)?0:$amazonOrderItemCode;
							$AdjustmentID = empty($AdjustmentID)?0:$AdjustmentID;	

							$insertQuery = "INSERT INTO settlement_report (date_time, settlement_id, type, order_id, sku, description, quantity, marketplace, fulfillment, order_city, order_state, order_postal, tax_collection_model, product_sales, product_sales_tax, shipping_credits, shipping_credits_tax, gift_wrap_credits, giftwrap_credits_tax, regulatory_fee, tax_on_regulatory_fee, promotional_rebates, promotional_rebates_tax, marketplace_withheld_tax, selling_fees, fba_fees, other_transaction_fees, other, currency, amazonOrderItemCode,shipmentid_or_adjustmentid,marketplace_id) VALUES ('$PostedDate', '$amazonSettlementID', 'Chargeback', '$AmazonOrderID', '$SKU', '', 0,'$MarketplaceName','$MerchantFulfillmentID', '', '', '', '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,'$product_sales', 0, 0, 0, '$currency','$amazonOrderItemCode','$AdjustmentID','$MarketplaceID')";


							file_put_contents($log_file, date("Y-m-d H:i:s") . $insertQuery . PHP_EOL, FILE_APPEND);
							$insertQueryExec = pg_query($DBConn, $insertQuery);
							sleep(1);
							if($insertQueryExec)
							{
								file_put_contents($log_file, date("Y-m-d H:i:s") .'Amazon order No'.$amazonOrderId .'Inserted Suceessfully' . PHP_EOL, FILE_APPEND);
							}
							else
							{
								file_put_contents($log_file, date("Y-m-d H:i:s") .'Amazon order No'.$amazonOrderId .'Insertion Failed' . PHP_EOL, FILE_APPEND);
							}


							unset($amazonOrderItemCode,$AmazonOrderID,$AdjustmentID,$MarketplaceName,$PostedDate,$MerchantFulfillmentID,$SKU,$product_sales);


						}//Loop End for the Chargeback Transactions








						foreach($SettlementReport->AdvertisingTransactionDetails as $AdvertisingTransactionDetails)
						{
							$TransactionType = (string)$AdvertisingTransactionDetails->TransactionType;
							$PostedDate = (string)$AdvertisingTransactionDetails->PostedDate;
							$InvoiceId = (string)$AdvertisingTransactionDetails->InvoiceId;
							$BaseAmount = (float)$AdvertisingTransactionDetails->BaseAmount;
							$TaxAmount = (float)$AdvertisingTransactionDetails->TaxAmount;
							$TransactionAmount = (float)$AdvertisingTransactionDetails->TransactionAmount;//earliear added to others but now removed 
							$marketplaceName = (string) $SettlementReport->Order->MarketplaceName;


							$BaseAmount = empty($BaseAmount)?0:$BaseAmount;
							$TaxAmount = empty($TaxAmount)?0:$TaxAmount;
							$TransactionAmount = empty($TransactionAmount)?0:$TransactionAmount;

							$insertQuery = "INSERT INTO settlement_report (date_time, settlement_id, type, order_id, sku, description, quantity, marketplace, fulfillment, order_city, order_state, order_postal, tax_collection_model, product_sales, product_sales_tax, shipping_credits, shipping_credits_tax, gift_wrap_credits, giftwrap_credits_tax, regulatory_fee, tax_on_regulatory_fee, promotional_rebates, promotional_rebates_tax, marketplace_withheld_tax, selling_fees, fba_fees, other_transaction_fees, other, currency, amazonOrderItemCode,shipmentid_or_adjustmentid,marketplace_id) VALUES ('$PostedDate', '$amazonSettlementID', '$TransactionType', '$InvoiceId', '', '', 0,'$marketplaceName','', '', '', '', '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,'$BaseAmount', 0, 0,$TaxAmount, '$currency','0','0','$MarketplaceID')";


							file_put_contents($log_file, date("Y-m-d H:i:s") . $insertQuery . PHP_EOL, FILE_APPEND);
							$insertQueryExec = pg_query($DBConn, $insertQuery);
							sleep(1);
							if($insertQueryExec)
							{
								file_put_contents($log_file, date("Y-m-d H:i:s") .'Amazon order No'.$amazonOrderId .'Inserted Suceessfully' . PHP_EOL, FILE_APPEND);
							}
							else
							{
								file_put_contents($log_file, date("Y-m-d H:i:s") .'Amazon order No'.$amazonOrderId .'Insertion Failed' . PHP_EOL, FILE_APPEND);
							}

							unset($PostedDate,$amazonSettlementID,$TransactionType,$InvoiceId,$BaseAmount,$TaxAmount,$TransactionAmount);						

						}//End of AdvertisingTransactionDetails Loop 


					}//Order Type Loop, Order,Refund,other Transactions,etc.

				}//checking marketplace and marketplace_ID
				else
				{
					file_put_contents($log_file, date("Y-m-d H:i:s") .'Marketplace and Markerplace Id is not matching' . PHP_EOL, FILE_APPEND);
				}



			}
			else
			{
				file_put_contents($log_file, date("Y-m-d H:i:s") .'Report data is already present in settlement_report table.' . PHP_EOL, FILE_APPEND);
			}
		}//not existing settlement_id entering it.


		return true;
		unset($xmlContent,$settlementID);
	}
	else 
	{
		return false;
		echo "Failed to retrieve XML content.";			
	}

	unset($MarketplaceID,$Marketplace,$endpoint);       
	//this also form here		
/*		}//MarketPlace Foreach Loop

	}
	else
	{
		// Query execution failed
		echo "Error executing query:Failed to get MarketPlace and Access Token " . pg_last_error($DBConn);
		file_put_contents($log_file, date("Y-m-d H:i:s") . ' Error executing query:  Failed to get MarketPlace and Access Token' . pg_last_error($DBConn) . PHP_EOL, FILE_APPEND);
		return false;

	}
 */
//till here 
sleep(5);			
file_put_contents($log_file, date("Y-m-d H:i:s") .'retrieveAndProcessReport Function Ends here ' . PHP_EOL, FILE_APPEND);
}//End of the retrieveAndProcessReport Function.	









//Function for Making Curl Request
function makeCurlRequest($url, $Marketplace)
{
	global $DBConn, $log_file;
	file_put_contents($log_file, date("Y-m-d H:i:s") . 'Inside make Curl Request Function' . PHP_EOL, FILE_APPEND);
	sleep(5);

	$Query = "SELECT token FROM sp_api_credentials WHERE token_type='LWA_access_token' and marketplace='$Marketplace'";
	file_put_contents($log_file, date("Y-m-d H:i:s") . $Query . PHP_EOL, FILE_APPEND);
	$GettingAccessTokenQuery = pg_query($DBConn, $Query);
	$GettingAcessToken = pg_fetch_all($GettingAccessTokenQuery);
	$Access_Token = $GettingAcessToken[0]['token'];

	$headers = array(
		'x-amz-access-token: ' . $Access_Token
	);

	file_put_contents($log_file, date("Y-m-d H:i:s") . ' Got the Access token inside  makeCurlRequest Function ' . PHP_EOL, FILE_APPEND);

	// Initialize cURL session
	$curl = curl_init($url);

	// Set the cURL options
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

	// Set the cURL options
	$response = curl_exec($curl);

	// Check for errors
	if ($response === false) 
	{
		echo 'Error: ' . curl_error($curl);
		file_put_contents($log_file, date("Y-m-d H:i:s") . '***Curl Request Fail ERROR :  ' . curl_error($curl) . PHP_EOL, FILE_APPEND);
		return false; // Return false on error
	} 
	else 
	{
		// Process the response
		$decodedResponse = json_decode($response, true);

		//print_r($decodedResponse);  // Display the response data
		//echo "<br>"; 
		//echo "<br>"; 

		file_put_contents($log_file, date("Y-m-d H:i:s") . '***Curl Requests OUTPUT :  ' . $decodedResponse . PHP_EOL, FILE_APPEND);
		file_put_contents($log_file, date("Y-m-d H:i:s") . ' Requests OUTPUT :  ' . $response . PHP_EOL, FILE_APPEND);

		$errorCode = $decodedResponse['errors'][0]['code'];
/*		if($errorCode == "InvalidInput")
		{
			file_put_contents($log_file, date("Y-m-d H:i:s") . 'Invalid Input error indicates here mis match of Marketplace ,Endpoint and following report .' . PHP_EOL, FILE_APPEND);

			 // Skip the current iteration and proceed to the next one
			 continue;
		}
 */
if ($errorCode == "QuotaExceeded") 
{
	file_put_contents($log_file, date("Y-m-d H:i:s") . 'Quota got exceeded adding a time sleep for 5 minutes.' . PHP_EOL, FILE_APPEND);
	sleep(300);

	// Retry the request after sleep
	return makeCurlRequest($url, $Marketplace);
}

// Close cURL session
curl_close($curl);

return $decodedResponse; // Return the decoded response
	}

	sleep(5);
	file_put_contents($log_file, date("Y-m-d H:i:s") . ' Make Curl Request Function Ends' . PHP_EOL, FILE_APPEND);
}




file_put_contents($log_file, date("Y-m-d H:i:s") . '-----------Script  Ends----------' . PHP_EOL, FILE_APPEND); 

?>
