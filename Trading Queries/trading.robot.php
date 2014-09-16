<?php
   /* 
   * By Qi Zhao (manazhao@soe.ucsc.edu)
   * April 16th 2014
   */

   # include the TradingClient class
   require_once "./TradingClient.php";
   require_once "./trading.strategy.php";

   # parse the command line arguments to get the token
   if(count($argv) != 2){
      echo "please supply the client token through the command line\n";
      exit(1);
   }

   # IMPORTANT: change $localMode to false when you run the script.
   $localMode = true;
   $token = $argv[1];

   # create a TradingClient object and it will be used all through the trading
   # process
   $client = new TradingClient($token,$localMode);
   $aLotEqual = "=================";
   # dump the trading client information
   $client->print_client_info();

   $trading_is_over = false;

   # experimental setting variables, do NOT change.
   # maximum number of offers and referrals one group can make per round
   $max_offer = $client->get_max_offer_send();
   $max_referral = $client->get_max_ref_send();
   $max_accept = $client->get_max_offer_recv();

   $offer_round_done = false;
   $accept_round_done = false;

   while(!$trading_is_over){
      #  check the trading clock
      #  whether it's a product offering or referring round
      print("query the trading clock\n");
      $is_offer_round = $client->is_round_a();

      # retrieve incoming and outgoing transactions
      print("query outgoing and incoming transactions\n");
      $out_transactions = $client->query_out_transactions();
      $in_transactions = $client->query_in_transactions();

      if($is_offer_round && !$offer_round_done){

	 print(">>> offer product round\n");

	 # retrieve product set that are associated to my group. They include,
	 # 1) $my_products["sell"] : products that are available to sell.
	 # 2) $my_products["consumed"]: products that are consumed by my group
	 # 3) $my_products["produced"]: products that are produced by my group
	 # Note: 
	 #	1) $my_products["sell"] will reduce after the group offers products to other groups
	 #	2) $my_products["sell"] and $my_products["produced"] are array of elements with the following fields:
	 #	   { "id" => product id, "cost" => the production cost}
	 #	   and $my_products["consumed"] is array of elements with the following fields:
	 #	   { "id" => product id, "utility" => the utility of consuming the product}
	 $my_products = $client->query_my_product_info();

	 # get the products to sell
	 $products_for_sale = $my_products["sell"];

	 # call count() function to get the number of elements in an array
	 $num_to_sell = count($products_for_sale);

	 # identify the recipient by calling the offer strategy function
	 $recipient_groups = select_offer_recipient_strategy($client, $out_transactions);

	 for($i = 0; $i < $num_to_sell && $i < $max_offer; $i++){

	    # get the group id of the offer recipient
	    $recipient = $recipient_groups[$i];

	    # get the product structure which has "id" and "cost" fields
	    $product = $products_for_sale[$i];

	    # get the cost of the product
	    $cost = $product["cost"];

	    # get the product id
	    $product_id = $product["id"];

	    # name the price, simply multiply a constant factor
	    $price = $cost * 1.1;

	    # name the referral fees which are 20% and 10% of the profit. Again, replace your own smarter strategy
	    $first_ref_fee = ($price - $cost) * 0.2;
	    $second_ref_fee = ($price - $cost) * 0.1;

	    # make the product offer
	    $response = $client->offer_product($recipient, $product_id, $price, $first_ref_fee, $second_ref_fee);

	    # check the response
	    if($response["status"] == "fail"){
	       print_r($response);
	    }
	 }

	 print(">>> refer products\n");
	 # get the transactions for referral. The returned is an associated array which maps transaction id to group id
	 # namely, refer the transaction to the group
	 $referral_recipient_info = select_referral_recipient_strategy($client, $in_transactions, $out_transactions);
	 $i = 0;
	 foreach($referral_recipient_info as $transaction_id => $group_id ){
	    $client->refer_product($group_id,$transaction_id);
	    $i++;
	    if($i >= $max_referral){
	       break;
	    }
	 }

	 # mark the offer round is done
	 $offer_round_done = true;
	 $accept_round_done = false;
      }

      # check whether it's round B where you accept offers and referrals
      if(!$is_offer_round && !$accept_round_done){
	 print(">>> accept offer or referrals\n");
	 accept_offer_referral($client,$in_transactions,$max_accept);
	 $accept_round_done = true;
	 $offer_round_done = false;
      }

      # wait a certain amount of time: 10 seconds here
      print(">>> wait a while for next round\n");
      sleep(10);
   }

?>
