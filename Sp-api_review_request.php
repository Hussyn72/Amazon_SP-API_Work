<?
date_default_timezone_set("Asia/Calcutta");

file_put_contents('/tmp/SP-api_request_review.log', date("Y-m-d H:i:s") . '*********************' . 'Inside SP-API Request Reviews Script Log'.'********************** '.  PHP_EOL, FILE_APPEND);

//DB CONNECTION
global $DBConn;
$DBConn = pg_connect("dbname='e2fax' user='domains'");

if(!$DBConn)
{
        echo "Error : Unable to open database\n";
        file_put_contents('/tmp/SP-api_request_review.log', date("Y-m-d H:i:s") . ' Error : Unable to open database'.  PHP_EOL, FILE_APPEND);
}
else
{
        //  echo "Opened database successfully\n";
        file_put_contents('/tmp/SP-api_request_review.log', date("Y-m-d H:i:s") . ' DB Connection Established'.  PHP_EOL, FILE_APPEND);
}


file_put_contents('/tmp/SP-api_request_review.log', date("Y-m-d H:i:s") . '*********************' . 'START'.'********************** '.  PHP_EOL, FILE_APPEND);

	//Getting Data of 25th day from putchase date.
        $Query = "select DISTINCT  aod.amazonorderid,aod.purchase_date,aod.marketplaceid,sp.marketplace,sp.endpoint from amazon_order_details  as aod left join sp_api_credentials as sp on sp.marketplace_id = aod.marketplaceid WHERE DATE_TRUNC('day', aod.purchase_date)::date = (CURRENT_DATE - INTERVAL '25 days')::date and aod.orderstatus not in ('Canceled','Pending') ";
        file_put_contents('/tmp/SP-api_request_review.log', date("Y-m-d H:i:s") . $Query . PHP_EOL, FILE_APPEND);
        $GettingDataQuery = pg_query($DBConn, $Query);

        if ($GettingDataQuery)
        {
                $rows = pg_fetch_all($GettingDataQuery);

                foreach ($rows as $row)
                {
                        $MarketplaceID = $row['marketplaceid'];
                        $Marketplace = $row['marketplace'];
			$endpoint = $row['endpoint'];
			$amazonorderid = $row['amazonorderid'];

                        echo "Marketplace: $Marketplace" . "<br>";
                        echo "Marketplace ID: $MarketplaceID" ."<br>";
			echo "Endpoint :".$endpoint ."<br>";
			echo "Amazon Order ID :".$amazonorderid."<br>";
                        echo "------------------------" . "<br>";
			echo "<br>";

			file_put_contents('/tmp/SP-api_request_review.log', date("Y-m-d H:i:s").'     '.$amazonorderid.'        '.$MarketplaceID.'    '.$Marketplace.'        '.$endpoint.'    '. PHP_EOL, FILE_APPEND);



			$URL = $endpoint.'/solicitations/v1/orders/'.$amazonorderid.'/solicitations/productReviewAndSellerFeedback?marketplaceIds='.$MarketplaceID;	

			file_put_contents('/tmp/SP-api_request_review.log', date("Y-m-d H:i:s") .'URL FOR MAKING REQUEST '. $URL . PHP_EOL, FILE_APPEND);

			$Response = makeCurlRequest($URL ,$Marketplace);	

			file_put_contents('/tmp/SP-api_request_review.log', date("Y-m-d H:i:s") . $Response . PHP_EOL, FILE_APPEND);

			if (is_array($Response) && empty($Response)) 
			{
				file_put_contents('/tmp/SP-api_request_review.log', date("Y-m-d H:i:s") .'Product Review and Seller FeedBack Request Sent Successfully for amazon order--------->'.$amazonorderid. PHP_EOL, FILE_APPEND);     
				echo "Success: JSON response is an empty object.";
			} 
			else 
			{
				file_put_contents('/tmp/SP-api_request_review.log', date("Y-m-d H:i:s") .'Product Review and Seller FeedBack Request Action is not available for this amazon order--------->'.$amazonorderid. PHP_EOL, FILE_APPEND);
			    	echo "Error: JSON response is not an empty object.";
			}

				
				
			
		sleep(5);
		}//foreach Loop of Getting individual amazonorderid



	}
	else
	{
		 file_put_contents('/tmp/SP-api_request_review.log', date("Y-m-d H:i:s") .'Query Failed'. PHP_EOL, FILE_APPEND);
	}






function makeCurlRequest($url ,$Marketplace)
{
        global $DBConn;
        file_put_contents('/tmp/SP-api_request_review.log', date("Y-m-d H:i:s") . 'Inside make Curl Request Function' . PHP_EOL, FILE_APPEND);
        sleep(5);
        $Query = "SELECT token FROM sp_api_credentials WHERE token_type='LWA_access_token' and marketplace='$Marketplace'";
        file_put_contents('/tmp/SP-api_request_review.log', date("Y-m-d H:i:s") . $Query . PHP_EOL, FILE_APPEND);
        $GettingAccessTokenQuery = pg_query($DBConn, $Query);
        $GettingAcessToken = pg_fetch_assoc($GettingAccessTokenQuery);
        $Access_Token = $GettingAcessToken['token'];

        $headers = array(
		'x-amz-access-token: ' .$Access_Token,
		'Content-Type : application/json' 

        );

        file_put_contents('/tmp/SP-api_request_review.log', date("Y-m-d H:i:s") . ' Got the Access token inside  makeCurlRequest Function ' . PHP_EOL, FILE_APPEND);

        // Initialize cURL session
        $curl = curl_init($url);


        // Set the cURL options
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

	//FOR POST REQUEST
	curl_setopt($curl, CURLOPT_POST, true);
	

        // Set the cURL options
        $response = curl_exec($curl);

        // Check for errors
        if ($response === false) {
        echo 'Error: ' . curl_error($curl);
        file_put_contents('/tmp/SP-api_request_review.log', date("Y-m-d H:i:s") . '***Curl Request Fail ERROR :  ' . curl_error($curl) . PHP_EOL, FILE_APPEND);
        return false; // Return false on error
        } else {
        // Process the response
        $decodedResponse = json_decode($response, true);
        print_r($decodedResponse);  // Display the response data
        //echo "<br>"; 
        //echo "<br>"; 
        // file_put_contents('/tmp/SP-API_getOrdersAPI.log', date("Y-m-d H:i:s") . '***Curl Requests OUTPUT :  ' .  print_r($decodedResponse, true) . PHP_EOL, FILE_APPEND);
        file_put_contents('/tmp/SP-api_request_review.log', date("Y-m-d H:i:s") . '***Curl Requests OUTPUT :  ' . $decodedResponse . PHP_EOL, FILE_APPEND);
        file_put_contents('/tmp/SP-api_request_review.log', date("Y-m-d H:i:s") . ' Requests OUTPUT :  ' . $response . PHP_EOL, FILE_APPEND);

        return $decodedResponse; // Return the decoded response
        }

        sleep(5);
        // Close cURL session
        curl_close($curl);
        file_put_contents('/tmp/SP-api_request_review.log', date("Y-m-d H:i:s") . ' Make Curl Request Function Ends' . PHP_EOL, FILE_APPEND);

}//make Curl Request Function Ends here




 file_put_contents('/tmp/SP-api_request_review.log', date("Y-m-d H:i:s") . '***********Script Ends****************' . PHP_EOL, FILE_APPEND);

?>
