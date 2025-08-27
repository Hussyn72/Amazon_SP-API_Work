<?
date_default_timezone_set("Asia/Calcutta");
$log_file = '/tmp/amazon_inventory.log';
file_put_contents($log_file, date("Y-m-d H:i:s") . '*********************' . 'Inside Amazon Inventory Script'.'********************** '.  PHP_EOL, FILE_APPEND);

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

$Query = "select * from sp_api_credentials where token_type='LWA_access_token'";
file_put_contents($log_file, date("Y-m-d H:i:s") . $Query . PHP_EOL, FILE_APPEND);
$GettingDataQuery = pg_query($DBConn, $Query);

if ($GettingDataQuery)
{
	$rows = pg_fetch_all($GettingDataQuery);
	
        foreach ($rows as $row)
        {
        	$MarketplaceID = $row['marketplace_id'];
                $Marketplace = $row['marketplace'];
                $endpoint = $row['endpoint'];

                echo "Marketplace: $Marketplace" . "<br>";
                echo "Marketplace ID: $MarketplaceID" ."<br>";
                echo "Endpoint :".$endpoint ."<br>";
                echo "------------------------" . "<br>";
                echo "<br>";

                file_put_contents($log_file, date("Y-m-d H:i:s").'         '.$MarketplaceID.'    '.$Marketplace.'        '.$endpoint.'    '. PHP_EOL, FILE_APPEND);
			
                $URL = $endpoint. '/fba/inventory/v1/summaries?details=true&granularityType=Marketplace&granularityId='.$MarketplaceID.'&marketplaceIds='.$MarketplaceID;
		
		file_put_contents($log_file, date("Y-m-d H:i:s") .'URL FOR MAKING REQUEST '. $URL . PHP_EOL, FILE_APPEND);

		$ResponseData = makeCurlRequest($URL,$Marketplace);

		file_put_contents($log_file, date("Y-m-d H:i:s") .'Response Data - > '. $ResponseData . PHP_EOL, FILE_APPEND);

		$nextToken = $ResponseData['pagination']['nextToken'];

// 		file_put_contents($log_file, date("Y-m-d H:i:s") .'Next Token  - > '. $nextToken . PHP_EOL, FILE_APPEND);

		if($nextToken)
		{

			processdata($ResponseData);


			file_put_contents($log_file, date("Y-m-d H:i:s") .'Next Token inside if - > '. $nextToken . PHP_EOL, FILE_APPEND);
			
			while ($nextToken !== null)
                  	{
      				file_put_contents($log_file, date("Y-m-d H:i:s") .'Inside While Loop' . PHP_EOL, FILE_APPEND);			

      				$url1 = $endpoint .'/fba/inventory/v1/summaries?details=true&granularityType=Marketplace&granularityId='.$MarketplaceID.'&marketplaceIds='.$MarketplaceID.'&nextToken='.$nextToken;
				
				file_put_contents($log_file, date("Y-m-d H:i:s") .'Inside While Loop URL -> '.$url1 . PHP_EOL, FILE_APPEND);

                       		$response = makeCurlRequest($url1 ,$Marketplace); // Make request using current nextToken


                                // Check if there is a next token
                                if (isset($response['pagination']['nextToken']))
                                {
                                	$nextToken = $response['pagination']['nextToken'];
                               	}
                                else
                                {

                	                $nextToken = null; // No more next token, end the loop

                               	}

				
                       		processdata($response);

					

                        }//Next tokens While loop

		}
		else
		{
			processdata($ResponseData);
						
		}	
	
	
	}
}







//Function for insertion 
function processdata($ResponseData)
{
	global $DBConn,$log_file,$mysqli;
	file_put_contents($log_file, date("Y-m-d H:i:s") . 'Inside  Process Data Function' . PHP_EOL, FILE_APPEND);
	$marketplaceId = $ResponseData['payload']['granularity']['granularityId'];
	$getmarketplace = pg_query($DBConn,"select marketplace from sp_api_credentials where marketplace_id='$marketplaceId'");
	$gettingmarketplace = pg_fetch_assoc($getmarketplace);
	$marketplace = $gettingmarketplace['marketplace'];
	
	file_put_contents($log_file, date("Y-m-d H:i:s") . 'Marketplace ID = '.$marketplaceId.' Marketplace = '.$marketplace . PHP_EOL, FILE_APPEND);
		
	foreach($ResponseData['payload']['inventorySummaries'] as $item)
	{
		$asin = $item['asin'];
		$fnSku = $item['fnSku'];
		$sellerSku = $item['sellerSku'];
		$condition = $item['condition'];

		$fulfillableQuantity = $item['inventoryDetails']['fulfillableQuantity'];
		$inboundWorkingQuantity = $item['inventoryDetails']['inboundWorkingQuantity'];
		$inboundShippedQuantity = $item['inventoryDetails']['inboundShippedQuantity'];
		$inboundReceivingQuantity = $item['inventoryDetails']['inboundReceivingQuantity'];

		$totalReservedQuantity = $item['inventoryDetails']['reservedQuantity']['totalReservedQuantity'];
		$pendingCustomerOrderQuantity = $item['inventoryDetails']['reservedQuantity']['pendingCustomerOrderQuantity'];
		$pendingTransshipmentQuantity = $item['inventoryDetails']['reservedQuantity']['pendingTransshipmentQuantity'];
		$fcProcessingQuantity = $item['inventoryDetails']['reservedQuantity']['fcProcessingQuantity'];

		$totalResearchingQuantity = $item['inventoryDetails']['researchingQuantity']['totalResearchingQuantity'];
		$researchingQuantityInShortTerm = $item['inventoryDetails']['researchingQuantity']['researchingQuantityBreakdown'][0]['quantity'];
		$researchingQuantityInMidTerm = $item['inventoryDetails']['researchingQuantity']['researchingQuantityBreakdown'][1]['quantity'];
		$researchingQuantityInLongTerm = $item['inventoryDetails']['researchingQuantity']['researchingQuantityBreakdown'][2]['quantity'];

		$totalUnfulfillableQuantity = $item['inventoryDetails']['unfulfillableQuantity']['totalUnfulfillableQuantity'];
		$customerDamagedQuantity = $item['inventoryDetails']['unfulfillableQuantity']['customerDamagedQuantity'];
		$warehouseDamagedQuantity = $item['inventoryDetails']['unfulfillableQuantity']['warehouseDamagedQuantity'];
		$distributorDamagedQuantity = $item['inventoryDetails']['unfulfillableQuantity']['distributorDamagedQuantity'];
		$carrierDamagedQuantity = $item['inventoryDetails']['unfulfillableQuantity']['carrierDamagedQuantity'];
		$defectiveQuantity = $item['inventoryDetails']['unfulfillableQuantity']['defectiveQuantity'];
		$expiredQuantity = $item['inventoryDetails']['unfulfillableQuantity']['expiredQuantity'];
		
		$reservedFutureSupplyQuantity = $item['inventoryDetails']['futureSupplyQuantity']['reservedFutureSupplyQuantity'];
		$futureSupplyBuyableQuantity = $item['inventoryDetails']['futureSupplyQuantity']['futureSupplyBuyableQuantity'];
		
		$lastUpdatedTime = $item['lastUpdatedTime'];
		$productName = $item['productName'];
		$totalQuantity = $item['totalQuantity'];



		$fulfillableQuantity = isset($fulfillableQuantity) ? $fulfillableQuantity : 0;
		$inboundWorkingQuantity = isset($inboundWorkingQuantity) ? $inboundWorkingQuantity : 0;
		$inboundShippedQuantity = isset($inboundShippedQuantity) ? $inboundShippedQuantity : 0;
		$inboundReceivingQuantity = isset($inboundReceivingQuantity) ? $inboundReceivingQuantity : 0;
		$totalReservedQuantity = isset($totalReservedQuantity) ? $totalReservedQuantity : 0;
		$pendingCustomerOrderQuantity = isset($pendingCustomerOrderQuantity) ? $pendingCustomerOrderQuantity : 0;
		$pendingTransshipmentQuantity = isset($pendingTransshipmentQuantity) ? $pendingTransshipmentQuantity : 0;
		$fcProcessingQuantity = isset($fcProcessingQuantity) ? $fcProcessingQuantity : 0;
		$totalResearchingQuantity = isset($totalResearchingQuantity) ? $totalResearchingQuantity : 0;
		$researchingQuantityInShortTerm = isset($researchingQuantityInShortTerm) ? $researchingQuantityInShortTerm : 0;
		$researchingQuantityInMidTerm = isset($researchingQuantityInMidTerm) ? $researchingQuantityInMidTerm : 0;
		$researchingQuantityInLongTerm = isset($researchingQuantityInLongTerm) ? $researchingQuantityInLongTerm : 0;
		$totalUnfulfillableQuantity = isset($totalUnfulfillableQuantity) ? $totalUnfulfillableQuantity : 0;
		$customerDamagedQuantity = isset($customerDamagedQuantity) ? $customerDamagedQuantity : 0;
		$warehouseDamagedQuantity = isset($warehouseDamagedQuantity) ? $warehouseDamagedQuantity : 0;
		$distributorDamagedQuantity = isset($distributorDamagedQuantity) ? $distributorDamagedQuantity : 0;
		$carrierDamagedQuantity = isset($carrierDamagedQuantity) ? $carrierDamagedQuantity : 0;
		$defectiveQuantity = isset($defectiveQuantity) ? $defectiveQuantity : 0;
		$expiredQuantity = isset($expiredQuantity) ? $expiredQuantity : 0;
		$reservedFutureSupplyQuantity = isset($reservedFutureSupplyQuantity) ? $reservedFutureSupplyQuantity : 0;
		$futureSupplyBuyableQuantity = isset($futureSupplyBuyableQuantity) ? $futureSupplyBuyableQuantity : 0;
	//	$totalQuantity = isset($totalQuantity) ? $totalQuantity : 0;


		$productName = str_replace(array("'", "\"", "`", ":", ";", ","), "", $productName);
		
		
		if($lastUpdatedTime == NULL || $productName == NULL || $lastUpdatedTime == "" || $productName == "")		
		{
			 file_put_contents($log_file, date("Y-m-d H:i:s") . ' Do not insert this record'. PHP_EOL, FILE_APPEND);
		}
		else
		{
			
		

			$Query = "INSERT INTO amazon_inventory (asin, fnsku, sellersku, condition, fulfillablequantity, inboundworkingquantity, inboundshippedquantity, inboundreceivingquantity, totalreservedquantity, pendingcustomerorderquantity, pendingtransshipmentquantity, fcprocessingquantity, totalresearchingquantity, researchingquantityinshortterm, researchingquantityinmidterm, researchingquantityinlongterm, totalunfulfillablequantity, customerdamagedquantity, warehousedamagedquantity, distributordamagedquantity, carrierdamagedquantity, defectivequantity, expiredquantity, reservedfuturesupplyquantity, futuresupplybuyablequantity, lastupdatedtime, productname, totalquantity, marketplace, marketplaceid, downloaded_on) VALUES ('$asin', '$fnSku', '$sellerSku', '$condition', $fulfillableQuantity, $inboundWorkingQuantity, $inboundShippedQuantity, $inboundReceivingQuantity, $totalReservedQuantity, $pendingCustomerOrderQuantity, $pendingTransshipmentQuantity, $fcProcessingQuantity, $totalResearchingQuantity, $researchingQuantityInShortTerm, $researchingQuantityInMidTerm, $researchingQuantityInLongTerm, $totalUnfulfillableQuantity, $customerDamagedQuantity, $warehouseDamagedQuantity, $distributorDamagedQuantity, $carrierDamagedQuantity, $defectiveQuantity, $expiredQuantity, $reservedFutureSupplyQuantity, $futureSupplyBuyableQuantity, '$lastUpdatedTime', '$productName', $totalQuantity, '$marketplace', '$marketplaceId', now())";
		
		
			file_put_contents($log_file, date("Y-m-d H:i:s") . 'Insert Query ->  '.$Query. PHP_EOL, FILE_APPEND);
			$insert = pg_query($DBConn,$Query);


			if ($insert) 
			{
				 file_put_contents($log_file, date("Y-m-d H:i:s") . 'Data Inserted Successfully'. PHP_EOL, FILE_APPEND);

			    	// Query executed successfully, now unset the variables
				unset($asin, $fnSku, $sellerSku, $condition, $fulfillableQuantity, $inboundWorkingQuantity, $inboundShippedQuantity, $inboundReceivingQuantity, $totalReservedQuantity, $pendingCustomerOrderQuantity, $pendingTransshipmentQuantity, $fcProcessingQuantity, $researchingQuantityInShortTerm, $researchingQuantityInMidTerm, $researchingQuantityInLongTerm, $totalUnfulfillableQuantity, $customerDamagedQuantity, $warehouseDamagedQuantity, $distributorDamagedQuantity, $carrierDamagedQuantity, $defectiveQuantity, $expiredQuantity, $reservedFutureSupplyQuantity, $futureSupplyBuyableQuantity, $lastUpdatedTime, $productName, $totalQuantity);

				file_put_contents($log_file, date("Y-m-d H:i:s") . 'Unset the varibles '. PHP_EOL, FILE_APPEND);


			} 
			else 
			{
				file_put_contents($log_file, date("Y-m-d H:i:s") . ' Data Inserted Failed check the Logs '. PHP_EOL, FILE_APPEND);

			}
		
		}

	}
	unset($marketplace,$marketplaceId);
		
}







//Function for making curl Request 
function makeCurlRequest($url ,$Marketplace)
{
        global $DBConn,$log_file;
        file_put_contents($log_file, date("Y-m-d H:i:s") . 'Inside make Curl Request Function' . PHP_EOL, FILE_APPEND);
        sleep(5);
        $Query = "SELECT token FROM sp_api_credentials WHERE token_type='LWA_access_token' and marketplace='$Marketplace'";
        file_put_contents($log_file, date("Y-m-d H:i:s") . $Query . PHP_EOL, FILE_APPEND);
        $GettingAccessTokenQuery = pg_query($DBConn, $Query);
        $GettingAcessToken = pg_fetch_assoc($GettingAccessTokenQuery);
        $Access_Token = $GettingAcessToken['token'];

        $headers = array(
                'x-amz-access-token: ' .$Access_Token
        );

        file_put_contents($log_file, date("Y-m-d H:i:s") . ' Got the Access token inside  makeCurlRequest Function ' . PHP_EOL, FILE_APPEND);
//	file_put_contents($log_file, date("Y-m-d H:i:s") . $Access_Token . PHP_EOL, FILE_APPEND);


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
	        // file_put_contents($log_file, date("Y-m-d H:i:s") . '***Curl Requests OUTPUT :  ' .  print_r($decodedResponse, true) . PHP_EOL, FILE_APPEND);
//        	file_put_contents($log_file, date("Y-m-d H:i:s") . '***Curl Requests OUTPUT :  ' . $decodedResponse . PHP_EOL, FILE_APPEND);
//	        file_put_contents($log_file, date("Y-m-d H:i:s") . ' Requests OUTPUT :  ' . $response . PHP_EOL, FILE_APPEND);
		
		return $decodedResponse; // Return the decoded response
//		return $response;

        }

        sleep(5);
        // Close cURL session
	
	curl_close($curl);
	
        file_put_contents($log_file, date("Y-m-d H:i:s") . ' Make Curl Request Function Ends' . PHP_EOL, FILE_APPEND);

}//make Curl Request Function Ends here

file_put_contents($log_file, date("Y-m-d H:i:s") . '-----------Script  Ends----------' . PHP_EOL, FILE_APPEND);

?>
