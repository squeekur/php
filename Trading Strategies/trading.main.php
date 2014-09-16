<?php
   /* 
   * By Qi Zhao (manazhao@soe.ucsc.edu)
   * April 16th 2014
   */

   # include the TradingClient class
   require_once "./TradingClient.php";
   require_once "./trading.strategy.sample.php";

   # parse the command line arguments to get the token
   //if(count($argv) < 2){
   //   echo "please supply the client token through the command line\n";
   //   exit(1);
   //}

   # IMPORTANT: change $localMode to false when you run the script.
   $localMode = false;

   //if(count($argv) == 3){
   //   $localMode = true;
   //}
   
   //$token = $argv[1];

    $token = "b0083120032678672ea0ddb0fdae1f1c";
   # create a TradingClient object and it will be used all through the trading
   # process
   $client = new TradingClient($token,$localMode);
   # dump the trading client information
   $client->print_client_info();

   $trading_is_over = false;
   $offer_round_done = false;
   $accept_round_done = false;	

   while(!$trading_is_over){
      #  check the trading clock
      #  whether it's a product offering or referring round
      $client->print_client_info();
      print(">>> query the trading clock\n");
      $is_offer_round = $client->is_round_a();

      # retrieve incoming and outgoing transactions
      print(">>> query outgoing and incoming transactions\n");
      $out_transactions = $client->query_out_transactions();
      $in_transactions = $client->query_in_transactions();

      if($is_offer_round && !$offer_round_done){

	 print(">>> offer products\n");

	 # identify the recipient by calling the offer strategy function
	 # Note: you should replace your own offer strategy
	 offer_strategy($client, $out_transactions);

	 print(">>> refer products\n");
	 # get the transactions for referral. The returned is an associated array which maps transaction id to group id
	 # namely, refer the transaction to the group
	 referral_strategy($client, $in_transactions, $out_transactions);

	 # mark the offer round is done
	 $offer_round_done = true;
	 $accept_round_done = false;
      }

      # check whether it's round B where you accept offers and referrals
      if(!$is_offer_round && !$accept_round_done){
	 print(">>> accept offer or referrals\n");
	 accept_offer_referral($client,$in_transactions);
	 $accept_round_done = true;
	 $offer_round_done = false;
      }

      # wait a certain amount of time: 10 seconds here
      print(">>> wait a while for next round\n");
      sleep(10);
   }

?>
