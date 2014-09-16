<?php

require_once "TradingClient.php";
/**
 *
 * @brief define the strategy on selecting the recipient group for product offer
 *
 * @arguments:
 * $client: client object
 * $out_transactions: transactions which are made by my group in previous rounds. The status of the transaction will help you 
 * to infer which group needs your product
 *
 * @return:
 * an array of recipient group ids
 */
function offer_strategy($client, $out_transactions){

   /// query products related to my group
   /// retrieve product set that are associated to my group. They include,
   /// 1) $my_products["sell"] : products that are available to sell.
   /// 2) $my_products["consumed"]: products that are consumed by my group
   /// 3) $my_products["produced"]: products that are produced by my group
   /// Note: 
   ///	1) $my_products["sell"] will reduce after the group offers products to other groups
   ///	2) $my_products["sell"] and $my_products["produced"] are array of elements with the following fields:
   ///	   { "id" => product id, "cost" => the production cost}
   ///	   and $my_products["consumed"] is array of elements with the following fields:
   ///	   { "id" => product id, "utility" => the utility of consuming the product}
   $my_products = $client->query_my_product_info();

   /// products that are produced by my group
   $products_for_sale = $my_products["sell"];

   // call count() function to get the number of elements in an array
   $num_to_sell = count($products_for_sale);

   /// make sure there are products left to sell
   if($num_to_sell == 0){
      echo "!!!!!!!!!!!!!!!!! No More Products to Sell! !!!!!!!!!!!!!!\n";

      /// return immediately
      return;
   }

   // retrieve other group ids
   $other_groups = $client->get_other_groups();

   /**
    * analyze the out going transactions. $out_transactions contains two fields
    * 1) $out_transactions["offers"] which are the offer transactions and
    * 2) $out_transactions["referrals"] which are the referral transactions
    *
    * each of above fields is further divided classified by transaction status. Take $out_transactions["offers"] for example,
    * 1) $out_transactions["offers"]["pending"]: offer transactions which are made but not accepted by the recipient. They could go expired after certain rounds
    * 2) $out_transactions["offers"]["expired"]: expired offer transactions. This happens if offer is not accepted by the recipient.
    * 3) $out_transactions["offers"]["purchased"]: the offer is accepted by the recipient.
    * 4) $out_transactions["offers"]["referred"]: the offered product is further referred by the recipient. 
    *
    * Each transaction in $out_transactions["offers"]["pending"] (the same to other status) has the following fields,
    * 1) $out_transactions["offers"]["pending"]["id"] : transaction id. 
    * 2) $out_transactions["offers"]["pending"]["product.id"] : product involved in the transaction
    * 3) $out_transactions["offers"]["pending"]["price"] : price of the product offer
    * 4) $out_transactions["offers"]["pending"]["first.ref.fee"] : first degree referral fee
    * 5) $out_transactions["offers"]["pending"]["second.ref.fee"] : second degree referral fee
    * 6) $out_transactions["offers"]["pending"]["post.period"] : an integer indicating at which period the transaction is made
    * 7) $out_transactions["offers"]["pending"]["ref.degree"] : degree of referral. It should be zero for offer transactions. 
    * 8) $out_transactions["offers"]["pending"]["from.id"] : which group made the offer
    * 9) $out_transactions["offers"]["pending"]["to.id"] :  the recipient group of the offer
    * 9) $out_transactions["offers"]["pending"]["refer.id"] : 0 for offer transactions and positive integer for referral transactions.  
    *
    */      
   /// the robot simply pick recipients based on the probability of transactions going successful (purchased or referred)
   /// build a histogram for success percentage for recipient groups
   $group_score_map = array();
   foreach($other_groups as $other_group){
      $group_score_map[$other_group] = 0;
   }

   // only refer to offer transactions
   $transactions = $out_transactions["offers"];

   // track whether some of the offers are taken or referred
   static $no_response_cnt = 0;
   $offer_taken_cnt = 0;
   $status_score = array("pending" => -0.5, "expired" => -1, "purchased" => 1, "referred" => 0.5);

   foreach($transactions as $status => $status_transactions){
      foreach($status_transactions as $transaction){
	 $recipient = $transaction["to.id"];
	 $group_score_map[$recipient] += $status_score[$status];
	 if($status == "purchased" or $status == "referred"){
	    $offer_taken_cnt++;
	 }
      }
   }
   if($offer_taken_cnt == 0){
      $no_response_cnt++;
   }else{
      # there are human players active, so reset the counter
      echo "!!!!!!!!!!!!!! No response from human players !!!!!!!!!!!!!!!!!!!\n";
      $no_response_cnt = 0;
   }

   # if no response for more than one round, it means there are no human players are active. 
   # stop offering products to avoid running out of products
   if($no_response_cnt >= 2){
      echo "!!!!!!!!!!!!!!! Hold on offering product !!!!!!!!!!!!!!!!1\n";
      return;
   }

   // sort the groups by their scores in descending order
   arsort($group_score_map);
   $recipient_groups =  array_keys($group_score_map);


   /// get the maximum number of offers per round
   $max_offer = $client->get_max_offer_send();

   /// sell the product to the identified recipients
   for($i = 0; $i < $num_to_sell && $i < $max_offer; $i++){

      /// get the group id of the offer recipient
      $recipient = $recipient_groups[$i];
      $recipient_score = $group_score_map[$recipient];
      if($recipient_score < 0){
	 /// hold the offer, since this recipient has never accepted my offer
	 continue;
      }

      // get the product structure which has "id" and "cost" fields
      $product = $products_for_sale[$i];

      // get the cost of the product
      $cost = $product["cost"];

      // get the product id
      $product_id = $product["id"];

      /// name the price, simply multiply a constant factor
      /// Note: you should have your own pricing strategy
      $price = $cost + 10;

      /// name the referral fees which are 20% and 10% of the profit
      /// Again, replace your own smarter strategy
      $first_ref_fee = ($price - $cost) * 0.2;
      $second_ref_fee = ($price - $cost) * 0.1;

      /// make the product offer
      $response = $client->offer_product($recipient, $product_id, $price, $first_ref_fee, $second_ref_fee);

      /// check the response
      if($response["status"] == "fail"){
	 echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! Error in offering product !!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n";
	 print_r($response);
      }
   }
}

/**
 *
 * @brief define the strategy on select group for product referral
 *
 * @arguments:
 * $client: client object
 * $in_transactions: transactions that target your group as the recipient.
 * $out_transactions : transactions that are made by our group
 *
 * @return:
 * an associated array which indicates which group should the referral targets
 *
 * Note:
 * 1) Please refer to select_offer_recipient_strategy for documentation on transaction
 * 2) the robot does not analyze the $out_transactions to improve the referral effectivenes. You are strongly
 *   encouraged to use this information.
 */
function referral_strategy($client,$in_transactions, $out_transactions){
   // retrieve other group ids
   $other_groups = $client->get_other_groups();
   $pending_offer_transactions = $in_transactions["offers"]["pending"];
   $pending_referral_transactions = $in_transactions["referrals"]["pending"];

   // get products that your group consume
   $my_products = $client->query_my_product_info();
   $my_consume = $my_products["consumed"];
   $my_consume_products = array();
   foreach($my_consume as $product){
      $my_consume_products[$product["id"]] = 1;
   }


   $transaction_recipient_map = array();
   $all_pending_transactions = array_merge($pending_offer_transactions, $pending_referral_transactions);
   // go through the offer transactions
   foreach($all_pending_transactions as $transaction){
      // DO NOT refer products that your group consume
      $product_id = $transaction["product.id"];
      if(isset($my_consume_products[$product_id])){
	 continue;
      }
      // randomly refer to another group other than the referral maker
      $transaction_id = $transaction["id"];
      $from_group_id = $transaction["from.id"];
      $candidate_group_ids = array_diff($other_groups,array($from_group_id));
      $random_group_key = array_rand($candidate_group_ids,1);
      $random_group = $candidate_group_ids[$random_group_key];
      $transaction_recipient_map[$transaction_id] = $random_group;
   }

   $referral_cnt = 0;

   /// get the maximum number of referrals allowd per round
   $max_referral_cnt = $client->get_max_ref_send();

   /// make referrals to the identified recipient groups
   foreach($transaction_recipient_map as $transaction_id => $group_id ){
	/// for debugging purpose
	echo ">>> refer transaction $transaction_id to $group_id\n";
      $client->refer_product($group_id,$transaction_id);
      $referral_cnt++;

      //// don't exceed the limit
      if($referral_cnt >= $max_referral_cnt){
	 break;
      }
   }
}

/**
 * @brief accept offer or refferal
 * 
 * as you the number of offers or referrals each round is limited, so you need to prioritize the them.
 */

function accept_offer_referral($client, $in_transactions){
   // get products that your group consume
   $my_products = $client->query_my_product_info();
   $my_consume = $my_products["consumed"];
   $my_consume_products = array();
   foreach($my_consume as $product){
      $my_consume_products[$product["id"]] = $product;
   }

   // you can only accept pending offer or referral
   $pending_offer_transactions = $in_transactions["offers"]["pending"];
   $pending_referral_transactions = $in_transactions["referrals"]["pending"];
   $pending_transactions = array_merge($pending_offer_transactions, $pending_referral_transactions);

   /// counter for number of referrals/offers have been accepted
   $accept_cnt = 0;

   /// maximum number of offer/referral allowed per round
   $max_accept = $client->get_max_offer_recv();

   /// accept the offer
   foreach($pending_transactions as $transaction){
      $transaction_id = $transaction["id"];
      $product_id = $transaction["product.id"];
      $price = $transaction["price"];

      if(isset($my_consume_products[$product_id])){

	 //// IMPORTANT: make sure the price is not above the utility
	 $utility = $my_consume_products[$product_id]["utility"];
	 if($utility <= $price){
	    echo "!!!!!! price is above the utility\n";
	    continue;
	 }
	 /// take the offer or referral
	 $response =  $client->accept_offer_referral($transaction_id);
	 $accept_cnt++;
	 if($response["status"] == "fail"){
	    print_r($response);
	 }
	 if($accept_cnt > $max_accept){
	    break;
	 }
      }
   }
}
?>
