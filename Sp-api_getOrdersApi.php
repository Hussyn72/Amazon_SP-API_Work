<?
date_default_timezone_set("Asia/Calcutta");

file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . '*********************' . 'Inside SP-API Get Orders API Log'.'********************** '.  PHP_EOL, FILE_APPEND);

//DB CONNECTION
global $DBConn; 
$DBConn = pg_connect("dbname='e2fax' user='domains'");

if(!$DBConn) 
{
        echo "Error : Unable to open database\n";
	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . ' Error : Unable to open database'.  PHP_EOL, FILE_APPEND);
}
else 
{
      	//  echo "Opened database successfully\n";
	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . ' DB Connection Established'.  PHP_EOL, FILE_APPEND);
}




file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . '*********************' . 'START'.'********************** '.  PHP_EOL, FILE_APPEND);


$Query = "SELECT marketplace, token, marketplace_id,endpoint FROM sp_api_credentials WHERE token_type='LWA_access_token'";
//$Query = "SELECT marketplace, token, marketplace_id,endpoint FROM sp_api_credentials WHERE token_type='LWA_access_token' and marketplace='United States'";
file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . $Query . PHP_EOL, FILE_APPEND);
$GettingAccessTokenQuery = pg_query($DBConn, $Query);
	
if ($GettingAccessTokenQuery) 
{
	$rows = pg_fetch_all($GettingAccessTokenQuery);

	foreach ($rows as $row) 
	{
	        $Access_Token = $row['token'];
	        $MarketplaceID = $row['marketplace_id'];
	        $Marketplace = $row['marketplace'];
		$endpoint = $row['endpoint'];
    
	    	echo "Marketplace: $Marketplace" . "<br>";
	    	echo "Marketplace ID: $MarketplaceID" ."<br>";
		echo "Endpoint :".$endpoint ."<br>";
	    	echo "Access Token: $Access_Token" ."<br>";
	    	echo "------------------------" . "<br>";
	    	echo "<br>";

		file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . '*****INSIDE MARKETPLACE FOR LOOP***** '.  PHP_EOL, FILE_APPEND);
//		file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . '***MarketPlace ' . $Marketplace .'***AND Access Token '/* .$Access_Token . PHP_EOL, FILE_APPEND);

			
		
		$today = new DateTime();
		$today->sub(new DateInterval('PT2M'));
		$updatedDate = $today->format('Y-m-d');

			

		// Create headers for the request
		$headers = array(
	    		'x-amz-access-token: ' .$Access_Token
	    	);
			

		//$GetCreatedAFterDate = "select coalesce (max(purchase_date), now()- interval '5 days') from amazon_order_details";
		$GetCreatedAFterDate = "select coalesce (max(purchase_date), now()- interval '5 days') from amazon_order_details where marketplaceid ='$MarketplaceID'";
		$GetDate = pg_query($DBConn, $GetCreatedAFterDate);
		$GettingCreatedAfterDate = pg_fetch_assoc($GetDate);
		$CreatedAfter = $GettingCreatedAfterDate['coalesce'];
		// Convert the date to ISO format
		$isoDate = date('Y-m-d', strtotime($CreatedAfter));
//		$isoDate = "2023-07-01";
		echo $isoDate."<br>";
		file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'Created After Date = '. $isoDate  . PHP_EOL, FILE_APPEND);


	        $url = $endpoint.'/orders/v0/orders'.'?MarketplaceIds=' . $MarketplaceID .'&CreatedAfter='.$isoDate; // .'&CreatedBefore='.$updatedDate;
		
	    	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . '***URL :  ' . $url . PHP_EOL, FILE_APPEND);


		//Making Curl Request			
		file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'Going to Call makeCurlRequest Function For the First time' . PHP_EOL, FILE_APPEND);

		$Response = makeCurlRequest($url ,$Marketplace);


		foreach ($Response["payload"]["Orders"] as $order) 
		{
			file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'INSIDE ORDER LIST FOR LOOP'.  PHP_EOL, FILE_APPEND);
			file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'Calling Process Order Function For the First time' . PHP_EOL, FILE_APPEND);
    			processOrder($order,$endpoint,$Marketplace);
			sleep(2);
		}

		$encodedNextToken = isset($Response['payload']['NextToken']) ? $Response['payload']['NextToken'] : null;
		$nextToken = str_replace(['+', '='], ['%2B', '%3D'], $encodedNextToken);
		$createdBefore = isset($Response['payload']['CreatedBefore']) ? $Response['payload']['CreatedBefore'] : null;
		file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'This is Created Before Date'.$createdBefore.'This is Next Token IF Present '.$nextToken . PHP_EOL, FILE_APPEND);


		while ($encodedNextToken !== null) 
		{
			file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'Inside While Loop'.  PHP_EOL, FILE_APPEND);

    			$url1 = $endpoint .'/orders/v0/orders'.'?MarketplaceIds=' . $MarketplaceID. '&NextToken='.$nextToken;//.'&OrderStatuses=Pending';// &CreatedAfter='.$isoDate.'&CreatedBefore='.$createdBefore;
				
			file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'Inside While Loop New URL for Next Token Request  : '. $url1. PHP_EOL, FILE_APPEND);


			file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'Going to Call makeCurlRequest Function For the Second time inside While Loop' . PHP_EOL, FILE_APPEND);

    			$response1 = makeCurlRequest($url1 ,$Marketplace ); // Make request using current nextToken

    			if (isset($response1['payload']) && is_array($response1['payload']) && isset($response1['payload']['Orders']) && is_array($response1['payload']['Orders'])) 
			{
        			// Process the response data and insert into your data table using a foreach loop or other method
        			foreach ($response1['payload']['Orders'] as $order1) 
				{
					file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'Calling Process Order Function For the second time' . PHP_EOL, FILE_APPEND);
		            		processOrder($order1,$endpoint,$Marketplace);
		            		sleep(2);
        			}

		        	// Check if there is a next token
		        	if (isset($response1['payload']['NextToken'])) 	
				{
		        		$encodedNextToken = $response1['payload']['NextToken'];
					$nextToken = str_replace(['+', '='], ['%2B', '%3D'], $encodedNextToken);
		            		$createdBefore = isset($response1['payload']['CreatedBefore']) ? $response1['payload']['CreatedBefore'] : null;
        			}
				else 
				{
					$encodedNextToken = null; // No more next token, end the loop
		        	}		
	    		}	 			
			else
 			{
		        	// Handle the case where the payload is missing or invalid
		        	echo "Invalid API response. Payload data not found.";

		        	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'Invalid API response. Payload data not found.' . PHP_EOL, FILE_APPEND);

    			}

		    sleep(2);

		}//Next tokens While loop

	sleep(2);

	}//Foreach Loop for MarketPlace

}//First Query IF
else 
{
    // Query execution failed
    echo "Error executing query: " . pg_last_error($DBConn);
    file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . ' Error executing query: ' . pg_last_error($DBConn) . PHP_EOL, FILE_APPEND);
}

	


file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . '*******************************UPDATING PENDING ORDERS**************************************************************************' . PHP_EOL, FILE_APPEND);


//$QuerytogetPendingOrders = pg_query($DBConn,"SELECT a.amazonorderid, s.endpoint, a.orderstatus, s.marketplace FROM amazon_order_details AS a JOIN sp_api_credentials AS s ON s.marketplace_id = a.marketplaceid WHERE a.orderstatus in ('Pending','Unshipped') AND token_type = 'LWA_access_token' AND a.purchase_date >= (SELECT MAX(purchase_date) - INTERVAL '5 days' FROM amazon_order_details)");
$QuerytogetPendingOrders = pg_query($DBConn,"SELECT a.amazonorderid, s.endpoint, a.orderstatus, s.marketplace FROM amazon_order_details AS a JOIN sp_api_credentials AS s ON s.marketplace_id = a.marketplaceid WHERE a.orderstatus in ('Pending','Unshipped') AND token_type = 'LWA_access_token'");
$rows2 = pg_fetch_all($QuerytogetPendingOrders);
foreach($rows2 as $row1)
{
	$Pendingamazonorderid = $row1['amazonorderid'];
        $Pendingamazonorderstatus = $row1['orderstatus'];
	$endpoint2 = $row1['endpoint'];
	$Marketplace2 = $row1['marketplace'];


	
        $urlforPendingOrder = $endpoint2.'/orders/v0/orders/'.$Pendingamazonorderid;
       	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") .'******URL FOR ORDER DEATILS PENDING REQUEST'. $urlforPendingOrder .  PHP_EOL, FILE_APPEND);
	sleep(2);
        $OrderResponseforPendingOrders = makeCurlRequest($urlforPendingOrder,$Marketplace2);

	$orderStatus = $OrderResponseforPendingOrders['payload']['OrderStatus'];
	if($Pendingamazonorderstatus==$orderStatus)
	{
		file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") .'This Order Still in Pending Status'.  PHP_EOL, FILE_APPEND);	
	}
	else
	{
		$OrderChangeStatus = $OrderResponseforPendingOrders["payload"];
			
		$buyer_email = $OrderChangeStatus["BuyerInfo"]["BuyerEmail"];
		$amazon_order_id = $OrderChangeStatus["AmazonOrderId"];
		$sales_channel = $OrderChangeStatus["SalesChannel"];
		$order_status = $OrderChangeStatus["OrderStatus"];
		$num_items_shipped = $OrderChangeStatus["NumberOfItemsShipped"];
		$num_items_unshipped = $OrderChangeStatus["NumberOfItemsUnshipped"];
        	$marketplace_id = $OrderChangeStatus["MarketplaceId"];
		$purchase_date = $OrderChangeStatus["PurchaseDate"];
		$shipping_address = $OrderChangeStatus["ShippingAddress"];
		$postal_code = $shipping_address["PostalCode"];
		$city1 = $shipping_address["City"];
		$city = str_replace("'", "", $city1);
		$country_code = $shipping_address["CountryCode"];
		$order_total = $OrderChangeStatus["OrderTotal"];
		$currency_code = $order_total["CurrencyCode"];
		$amount = $order_total["Amount"];
		$last_update_date = $OrderChangeStatus["LastUpdateDate"];
		$purchase_date = $OrderChangeStatus["PurchaseDate"];


		$buyer_email = empty($buyer_email) ? '' : $buyer_email;
		$postal_code = empty($postal_code) ? '' : $postal_code;
	  	$city = empty($city) ? '' : $city;
	        $country_code = empty($country_code) ? '' : $country_code;
	       	$currency_code = empty($currency_code) ? '' : $currency_code;
		$amount = empty($amount) ? 0 : $amount;
		$num_items_shipped = empty($num_items_shipped) ? 0 : $num_items_shipped;
		$order_status = empty($order_status) ? '' :$order_status;
		$num_items_unshipped = empty($num_items_unshipped) ? 0 : $num_items_unshipped;



		$updating = "UPDATE amazon_order_details SET buyeremail='$buyer_email',shippingaddress_postalcode='$postal_code',shippingaddress_city='$city',shippingaddress_countrycode='$country_code',ordertotal_currencycode='$currency_code',ordertotal_amount='$amount',numberofitemsshipped='$num_items_shipped',numberofitemsunshipped='$num_items_unshipped',orderstatus='$order_status' WHERE amazonorderid='$amazon_order_id'";
	
		file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . $updating .  PHP_EOL, FILE_APPEND);
		$ExecuteUpdate = pg_query($DBConn,$updating);
		if($ExecuteUpdate)
			file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") .'******Update Query executed sucessfully.'.  PHP_EOL, FILE_APPEND);
		else
			file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") .'*****UPdate Query execution failed' .  PHP_EOL, FILE_APPEND);

		  //updating clienttrip
                        $updateClienttrip = "select * from clienttrip where order_no='$amazon_order_id'";
                        file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'Updating clienttrip if present '.$updateClienttrip .  PHP_EOL, FILE_APPEND);
                        $Executecheckclienttripquery = pg_query($DBConn,$updateClienttrip);
                        $getrows = pg_fetch_all($Executecheckclienttripquery);
                        if($getrows > 0)
                        {
                                $updateclienttripquery = "update clienttrip set status = '$order_status' where order_no='$amazon_order_id'";
                                file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'clienttrip update query -'.$updateclienttripquery .  PHP_EOL, FILE_APPEND);
                                $ExecuteUpdateclienttripquery = pg_query($DBConn,$updateclienttripquery);
                                if($ExecuteUpdateclienttripquery)
                                        file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'clienttrip update query SUCCESS '.  PHP_EOL, FILE_APPEND);
                                else
                                        file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'clienttrip update query FAILED' .  PHP_EOL, FILE_APPEND);
                        }







		$urlforPendingOrderItem = $endpoint2.'/orders/v0/orders/'.$amazon_order_id.'/orderItems';
		file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") .'******URL FOR ORDER ITEM DEATILS REQUEST'. $urlforPendingOrderItem .  PHP_EOL, FILE_APPEND);

	        file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") .'Calling MakeCurlRequest Function From ProcessORder Function' .  PHP_EOL, FILE_APPEND);
		sleep(2);
		$PendingOrderItemResponse = makeCurlRequest($urlforPendingOrderItem ,$Marketplace2);


		$PendingorderItems = $PendingOrderItemResponse["payload"]['OrderItems'];
		file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'INSIDE ORDER ITEM DEtails Updating'.  PHP_EOL, FILE_APPEND);
		file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'Calling Process Order ITEM  Function Updating' . PHP_EOL, FILE_APPEND);
		processOrderItem($PendingorderItems,$amazon_order_id);

	}
				
	sleep(2);
	
       	unset($buyer_email,$postal_code,$city,$country_code,$currency_code,$amount,$num_items_shipped,$num_items_unshipped,$order_status,$amazon_order_id);

}







//Function for Making Curl Request
function makeCurlRequest($url ,$Marketplace) 
{
	global $DBConn;


	sleep(2);
	$Query = "SELECT token FROM sp_api_credentials WHERE token_type='LWA_access_token' and marketplace='$Marketplace'";
       	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . $Query . PHP_EOL, FILE_APPEND);
       	$GettingAccessTokenQuery = pg_query($DBConn, $Query);
	$GettingAcessToken = pg_fetch_all($GettingAccessTokenQuery);
	$Access_Token = $GettingAcessToken[0] ['token'];




    	$headers = array(
		'x-amz-access-token: ' .$Access_Token
	);
	


	
     	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . ' Inside makeCurlRequest Function ' . PHP_EOL, FILE_APPEND);

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
        	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . '***Curl Request Fail ERROR :  ' . curl_error($curl) . PHP_EOL, FILE_APPEND);
        	return false; // Return false on error
    	} 
	else 
	{
        	// Process the response
        	$decodedResponse = json_decode($response, true);
        	//print_r($decodedResponse);  // Display the response data
        	//echo "<br>"; 
        	//echo "<br>"; 
       		//file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . '***Curl Requests OUTPUT :  ' .  print_r($decodedResponse, true) . PHP_EOL, FILE_APPEND);
        	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . '***Curl Requests OUTPUT :  ' . $decodedResponse . PHP_EOL, FILE_APPEND);
        	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . ' Requests OUTPUT :  ' . $response . PHP_EOL, FILE_APPEND);

        	return $decodedResponse; // Return the decoded response
    	}

    	// Close cURL session
    	curl_close($curl);
    	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . ' Make Curl Request Function Ends' . PHP_EOL, FILE_APPEND);

}//make Curl Request Function Ends here








//Function for assigning and storing Order Details
function processOrder($order, $endpoint, $Marketplace)
{
	global $DBConn;
	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'INSIDE PROCESS ORDER FUNCTION' .  PHP_EOL, FILE_APPEND);

	$buyer_email = $order["BuyerInfo"]["BuyerEmail"];
	$amazon_order_id = $order["AmazonOrderId"];
       	$sales_channel = $order["SalesChannel"];
        $order_status = $order["OrderStatus"];
        $num_items_shipped = $order["NumberOfItemsShipped"];
	$num_items_unshipped = $order["NumberOfItemsUnshipped"];
        $marketplace_id = $order["MarketplaceId"];
        $purchase_date = $order["PurchaseDate"];
        $shipping_address = $order["ShippingAddress"];
        $postal_code = $shipping_address["PostalCode"];
        $city1 = $shipping_address["City"];
	$city = str_replace("'", "", $city1);
        $country_code = $shipping_address["CountryCode"];
        $order_total = $order["OrderTotal"];
        $currency_code = $order_total["CurrencyCode"];
        $amount = $order_total["Amount"];
        $last_update_date = $order["LastUpdateDate"];
	$purchase_date = $order["PurchaseDate"];

	// Process or print the retrieved values as needed
       	echo "**********************ORDER LIST DETAILS ***************************** <br>";
       	echo "Buyer Email: " . $buyer_email . "<br>";
       	echo "Amazon Order ID: " . $amazon_order_id . "<br>";
       	echo "Sales Channel: " . $sales_channel . "<br>";
       	echo "Order Status: " . $order_status . "<br>";
       	echo "Number of Items Shipped: " . $num_items_shipped . "<br>";
       	echo "Marketplace ID: " . $marketplace_id . "<br>";
       	echo "Purchase Date: " . $purchase_date . "<br>";
       	echo " Shipping Postal Code: " . $postal_code . "<br>";
       	echo " Shipping City: " . $city . "<br>";
       	echo " Shipping Country Code: " . $country_code . "<br>";
       	echo " Order Total Currency Code: " . $currency_code . "<br>";
       	echo " Order Total Amount: " . $amount . "<br>";
       	echo "Last Update Date: " . $last_update_date . "<br>";
       	echo "Purchase Date :". $purchase_date."<br>";
       	echo "Number of items unshipped :".$num_items_unshipped ."<br>";
       	echo "----------------------------------------<br>";
       	echo "<br>";


	$CheckingOrderNoQuery = "SELECT amazonorderid,orderstatus from amazon_order_details WHERE amazonorderid='$amazon_order_id'";
	$CheckingExistence = pg_query($DBConn, $CheckingOrderNoQuery);
	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") .$CheckingOrderNoQuery .  PHP_EOL, FILE_APPEND);
       	$row = pg_fetch_Assoc($CheckingExistence);
	$Exists = $row['amazonorderid'];
	$OrderStatus = $row['orderstatus'];
	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s").'---------CHECKING COUNT!1-------------->' .$Exists .  PHP_EOL, FILE_APPEND);
        if($Exists > 0)
        {
		if($OrderStatus=='Pending' || $OrderStatus=='Unshipped')
		{
			echo "Order Already Present in the Table But Order Status was in Pending so Updating <br>";	
			file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'Order Already Present in the Table But Order Status was in Pending so Updating ' .  PHP_EOL, FILE_APPEND);

			$buyer_email = empty($buyer_email) ? '' : $buyer_email;
			$postal_code = empty($postal_code) ? '' : $postal_code;
			$city = empty($city) ? '' : $city;
			$country_code = empty($country_code) ? '' : $country_code;
			$currency_code = empty($currency_code) ? '' : $currency_code;
			$amount = empty($amount) ? 0 : $amount;
			$num_items_shipped = empty($num_items_shipped) ? 0 : $num_items_shipped;
			$num_items_unshipped = empty($num_items_unshipped) ? 0 : $num_items_unshipped;	
			$order_status = empty($order_status) ? '' :$order_status;

			$updating = "UPDATE amazon_order_details SET 
			                buyeremail='$buyer_email',
			                shippingaddress_postalcode='$postal_code',
			                shippingaddress_city='$city',
			                shippingaddress_countrycode='$country_code',
			                ordertotal_currencycode='$currency_code',
			                ordertotal_amount='$amount',
			                numberofitemsshipped='$num_items_shipped',
					numberofitemsunshipped='$num_items_unshipped',
					orderstatus='$order_status'
			                WHERE amazonorderid='$amazon_order_id'";

			file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . $updating .  PHP_EOL, FILE_APPEND);
			$ExecuteUpdate = pg_query($DBConn,$updating);

			//updating clienttrip
			$updateClienttrip = "select * from clienttrip where order_no='$amazon_order_id'";
			file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'Updating clienttrip if present '.$updateClienttrip .  PHP_EOL, FILE_APPEND);
			$Executecheckclienttripquery = pg_query($DBConn,$updateClienttrip);
			$getrows = pg_fetch_all($Executecheckclienttripquery);
			if($getrows > 0)
			{
				$updateclienttripquery = "update clienttrip set status = '$order_status' where order_no='$amazon_order_id'";
				file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'clienttrip update query -'.$updateclienttripquery .  PHP_EOL, FILE_APPEND);
				$ExecuteUpdateclienttripquery = pg_query($DBConn,$updateclienttripquery);
				if($ExecuteUpdateclienttripquery)
					file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'clienttrip update query SUCCESS '.  PHP_EOL, FILE_APPEND);
				else
					file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'clienttrip update query FAILED' .  PHP_EOL, FILE_APPEND);
			}



		}
		else
		{
			echo"ORDER IS ALREADY IN DATA TABLE DO NOT INSERT <br>";
                	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'ORDER IS ALREADY IN DATA TABLE DO NOT INSERT' .  PHP_EOL, FILE_APPEND);
		}

        }
        else
        {
		$inserting = "INSERT INTO amazon_order_details (buyeremail, amazonorderid, saleschannel, orderstatus, shippingaddress_postalcode, shippingaddress_city, shippingaddress_countrycode, ordertotal_currencycode, ordertotal_amount, items_downloaded, numberofitemsshipped, marketplaceid, lastupdatedate, purchase_date,numberofitemsunshipped) VALUES (";

		// Check and modify each column value if it is null
		$buyer_email = $buyer_email ?? '';
		$sales_channel = $sales_channel ?? '';
		$order_status = $order_status ?? '';
		$postal_code = $postal_code ?? '';
		$city = $city ?? '';
		$country_code = $country_code ?? '';
		$currency_code = $currency_code ?? '';
		$amount = is_numeric($amount) ? $amount : 0;
		$num_items_shipped = $num_items_shipped ?? '';
		$marketplace_id = $marketplace_id ?? '';
		$last_update_date = $last_update_date ?? '';
		$purchase_date = $purchase_date ?? '';
		$num_items_unshipped = $num_items_unshipped ?? '';

		// Build the insert query
		$inserting .= "'$buyer_email', '$amazon_order_id', '$sales_channel', '$order_status', '$postal_code', '$city', '$country_code', '$currency_code', '$amount', false, '$num_items_shipped', '$marketplace_id', '$last_update_date', '$purchase_date','$num_items_unshipped')";

		file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . $inserting .  PHP_EOL, FILE_APPEND);

		$insert = pg_query($DBConn, $inserting);
		if($insert)
	      	{
        		file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'Order Details Record Inserted Successfully '.  PHP_EOL, FILE_APPEND);
                }
	        else
        	{
                	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'Order Details Record Insertion Failed'.  PHP_EOL, FILE_APPEND);
		}


        }

 	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'Doing Order Items Work'.  PHP_EOL, FILE_APPEND);

	$urlforOrderItem = $endpoint.'/orders/v0/orders/'.$amazon_order_id.'/orderItems';
	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") .'******URL FOR ORDER ITEM DEATILS REQUEST'. $urlforOrderItem .  PHP_EOL, FILE_APPEND);

	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") .'Calling MakeCurlRequest Function From ProcessORder Function' .  PHP_EOL, FILE_APPEND);
	$OrderItemResponse = makeCurlRequest($urlforOrderItem ,$Marketplace);
 
		
	$orderItems = $OrderItemResponse["payload"]['OrderItems'];
	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'INSIDE ORDER ITEM DETAILS FOR LOOP'.  PHP_EOL, FILE_APPEND);
        file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'Calling Process Order ITEM  Function For the First time' . PHP_EOL, FILE_APPEND);
        processOrderItem($orderItems,$amazon_order_id);
        sleep(2);

 	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'Process Order Function Ends'.  PHP_EOL, FILE_APPEND);
		
	unset($buyer_email,$amazon_order_id,$sales_channel,$order_status,$postal_code,$city,$country_code,$currency_code,$amount,$num_items_shipped,$marketplace_id,$last_update_date,$purchase_date,$num_items_unshipped);

}//process order Function Ends here




function processOrderItem($orderItems,$amazon_order_id)
{
	global $DBConn;
	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'INSIDE PROCESS ORDER ITEM FUNCTION' .  PHP_EOL, FILE_APPEND);
	
	foreach($orderItems as $orderItem) 
	{

		$numberOfItems = $orderItem['ProductInfo']['NumberOfItems'];
		$itemPrice = $orderItem['ItemPrice'];
		$currencyCode = $itemPrice['CurrencyCode'];
 		$amount = $itemPrice['Amount'];
 		$asin = $orderItem['ASIN'];
 		$sellerSKU = $orderItem['SellerSKU'];
 		$title1 = $orderItem['Title'];
 		file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . $title1 . PHP_EOL, FILE_APPEND);
 		$title = str_replace("'", "", $title1);
		file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") .'Title ----------------->'. $title . PHP_EOL, FILE_APPEND);
 		$orderItemId = $orderItem['OrderItemId'];
		$QuantityOrdered = $orderItem['QuantityOrdered'];
	


 		echo "**********************ORDER ITEM DETAILS ***************************** <br>";
        	echo "Amazon Order ID :". $amazon_order_id . "<br>"; 
        	echo "NumberOfItems: " . $numberOfItems . "<br>";
        	echo "CurrencyCode: " . $currencyCode . "<br>";
        	echo "Amount: " . $amount . "<br>";
        	echo "ASIN: " . $asin . "<br>";
        	echo "SellerSKU: " . $sellerSKU . "<br>";
        	echo "Title: " . $title . "<br>";
        	echo "OrderItemId: " . $orderItemId . "<br>";
		echo "QuantityOrdered :".$QuantityOrdered."<br>";
        	echo "-----------------<br>";


	
		$query = "SELECT * from amazon_order_item_details WHERE amazonorderid='$amazon_order_id'";
        	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . $query . PHP_EOL, FILE_APPEND);
  		$CheckingOrderNoQuery = pg_query($DBConn, $query);
        	$AmazonOrderNoChecking = pg_fetch_all($CheckingOrderNoQuery);
        	$CheckingAmazonOrderExist = $AmazonOrderNoChecking['amazonorderid'];
		$CheckingOrderItemIdExist = $AmazonOrderNoChecking['orderitemid'];
        	if($CheckingAmazonOrderExist > 0)
        	{
			if ($CheckingOrderItemIdExist==$orderItemId) 
			{
				$currencyCode = empty($currencyCode) ? '' : $currencyCode;
                                $amount = empty($amount) ? '0' : $amount;
				$QuantityOrdered1 = empty($QuantityOrdered) ? '0' : $QuantityOrdered;

			
	
			   	echo"ORDER ITEM IS ALREADY IN DATA TABLE DO NOT INSERT BUT UPDATE <br>";
				file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'ORDER ITEMS ID'.$CheckingOrderItemIdExist .  PHP_EOL, FILE_APPEND);
                        	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'ORDER ITEMS IS ALREADY IN DATA TABLE DO NOT INSERT BUT UPDATE' .  PHP_EOL, FILE_APPEND);
                        	$updating1 = "update amazon_order_item_details set itemprice_currencycode='$currencyCode',itemprice_amount='$amount',quantityordered='$QuantityOrdered1'  where amazonorderid='$amazon_order_id' and orderitemid='$orderItemId'";
                        	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . $updating1 . PHP_EOL, FILE_APPEND); 
				$ExecuteUpdate = pg_query($DBConn,$updating1); 
			} 
			else 
			{		
				file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'Inserting OrderItems more then one Times '.  PHP_EOL, FILE_APPEND);
 				$currencyCode = empty($currencyCode) ? '' : $currencyCode;
	                        $amount = empty($amount) ? '0' : $amount;
				$QuantityOrdered1 = empty($QuantityOrdered) ? '0' : $QuantityOrdered;	

				$CheckingExistence = "select * from amazon_order_item_details where orderitemid='$orderItemId' and amazonorderid='$amazon_order_id'";
	                        file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . $CheckingExistence . PHP_EOL, FILE_APPEND);
        	                $ExecQuery = pg_query($DBConn,$CheckingExistence);
                	        $affectedRows1 = pg_num_rows($ExecQuery);
                        	if($affectedRows1 > 0)
                                {
                                        $currencyCode = empty($currencyCode)?'':$currencyCode;
                                        $amount = empty($amount)?0:$amount;
                                        $QuantityOrdered1 = empty($QuantityOrdered)?0:$QuantityOrdered;
                                 	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'Order Item Details Record Already Present in Table Updating'.  PHP_EOL, FILE_APPEND);
                                        file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'ORDER ITEMS IS ALREADY IN DATA TABLE DO NOT INSERT BUT UPDATE' .  PHP_EOL, FILE_APPEND);
                                        $updating3 = "update amazon_order_item_details set itemprice_currencycode='$currencyCode',itemprice_amount='$amount',quantityordered='$QuantityOrdered1'  where amazonorderid='$amazon_order_id' and orderitemid='$orderItemId'";
                                        file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . $updating3 . PHP_EOL, FILE_APPEND);
                                        $ExecuteUpdate = pg_query($DBConn,$updating3);
                        	}
                        	else
                        	{


				   	$inserting2 = "INSERT INTO amazon_order_item_details (amazonorderid, numberofitems, itemprice_currencycode, itemprice_amount, asin, sellersku, title, orderitemid,quantityordered) VALUES ('$amazon_order_id', '$numberOfItems', '$currencyCode', '$amount', '$asin', '$sellerSKU', '$title', '$orderItemId','$QuantityOrdered1')";


							
        	                	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . $inserting2 . PHP_EOL, FILE_APPEND);
                	        	$insert2 = pg_query($DBConn,$inserting2);
                        		if($insert2)
                        		{                       
                                		file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'Order Item Details Record Inserted Successfully '.  PHP_EOL, FILE_APPEND);
                       	 		}
                        		else
                        		{
                                  		file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'Order Item Details Record Insertion Failed '.  PHP_EOL, FILE_APPEND);
					}

				}

  			}
			
        	}
        	else
        	{	

			$currencyCode = empty($currencyCode) ? '' : $currencyCode;
			$amount = empty($amount) ? '0' : $amount;
			$QuantityOrdered1 = empty($QuantityOrdered) ? '0' : $QuantityOrdered;

			$CheckingExistence = "select * from amazon_order_item_details where orderitemid='$orderItemId' and amazonorderid='$amazon_order_id'";
			file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . $CheckingExistence . PHP_EOL, FILE_APPEND);
			$ExecQuery = pg_query($DBConn,$CheckingExistence);
			$affectedRows1 = pg_num_rows($ExecQuery);
			if($affectedRows1 > 0)
			{
				  file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'Order Item Details Record Already Present in Table. UPdating  '.  PHP_EOL, FILE_APPEND);
                                file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'ORDER ITEMS IS ALREADY IN DATA TABLE DO NOT INSERT BUT UPDATE' .  PHP_EOL, FILE_APPEND);
                                $updating2 = "update amazon_order_item_details set itemprice_currencycode='$currencyCode',itemprice_amount='$amount',quantityordered='$QuantityOrdered1'  where amazonorderid='$amazon_order_id' and orderitemid='$orderItemId'";
                                file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . $updating2 . PHP_EOL, FILE_APPEND);
                                $ExecuteUpdate = pg_query($DBConn,$updating2);
			}
			else
			{

	
				$inserting1 = "INSERT INTO amazon_order_item_details (amazonorderid, numberofitems, itemprice_currencycode, itemprice_amount, asin, sellersku, title, orderitemid,quantityordered) VALUES ('$amazon_order_id', '$numberOfItems', '$currencyCode', '$amount', '$asin', '$sellerSKU', '$title', '$orderItemId','$QuantityOrdered1')";

				

                                file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . $inserting1 . PHP_EOL, FILE_APPEND);
                                $insert1 = pg_query($DBConn,$inserting1);
                                if($insert1)
                                {
                               		file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'Order Item Details Record Inserted Successfully '.  PHP_EOL, FILE_APPEND);
                           	}
                               	else
                                {
                               		file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . 'Order Item Details Record Insertion Failed '.  PHP_EOL, FILE_APPEND);
                               	}

			}
        	}
		
		sleep(2);

		
		unset($numberOfItems,$itemPrice,$currencyCode,$amount,$asin,$sellerSKU,$title,$orderItemId,$QuantityOrdered1);

	}


	$Itemsdownloaded = "UPDATE amazon_order_details AS aod SET items_downloaded = TRUE WHERE orderstatus not in ('Pending','Canceled') AND items_downloaded = False AND aod.amazonorderid = '$amazon_order_id' AND aod.numberofitemsshipped + aod.numberofitemsunshipped = ( SELECT SUM(aoid.quantityordered) FROM amazon_order_item_details AS aoid WHERE aoid.amazonorderid = '$amazon_order_id')";

        file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") .$Itemsdownloaded  . PHP_EOL, FILE_APPEND);
        $ExecutingQuery = pg_query($DBConn, $Itemsdownloaded);
	$affectedRows = pg_affected_rows($ExecutingQuery);

	if($affectedRows > 0) 
        {
                file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") .'Item_downloaded updated to true for order no '.$amazon_order_id . PHP_EOL, FILE_APPEND);
        }
        else
        {
                file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") .'Item_downloaded NOT updated to true for order no '.$amazon_order_id . PHP_EOL, FILE_APPEND);
	}

	$Itemsdownloaded2 = "update amazon_order_details set items_downloaded = true where items_downloaded = false and orderstatus = 'Shipped' and amazonorderid ='$amazon_order_id'";
	file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") .$Itemsdownloaded2  . PHP_EOL, FILE_APPEND);
        $ExecutingQuery1 = pg_query($DBConn, $Itemsdownloaded2);
        $affectedRows1 = pg_affected_rows($ExecutingQuery1);

        if($affectedRows1 > 0)
        {
                file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") .'Item_downloaded updated to true for order no '.$amazon_order_id . PHP_EOL, FILE_APPEND);
        }
        else
        {
                file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") .'Item_downloaded NOT updated to true for order no '.$amazon_order_id . PHP_EOL, FILE_APPEND);
        }
	


	unset($amazon_order_id);

}


 file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . '************************* Script Ends*******************  ' . PHP_EOL, FILE_APPEND);
?>
