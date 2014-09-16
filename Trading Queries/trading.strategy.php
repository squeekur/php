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
   function select_offer_recipient_strategy($client, $out_transactions){
      # retrieve other group ids
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

      # only refer to offer transactions
      $transactions = $out_transactions["offers"];
      $status_score = array("pending" => 0.2, "expired" => -1, "purchased" => 1, "referred" => 0.5);

      foreach($transactions as $status => $status_transactions){
	 foreach($status_transactions as $transaction){
	    $recipient = $transaction["to.id"];
	    $group_score_map[$recipient] += $status_score[$status];
	 }
      }

      # sort the groups by the number of success in descending order
      arsort($group_score_map);
      $group_ids =  array_keys($group_score_map);
      return $group_ids;
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
   function select_referral_recipient_strategy($client,$in_transactions, $out_transactions){
      # retrieve other group ids
      $other_groups = $client->get_other_groups();
      $pending_offer_transactions = $in_transactions["offers"]["pending"];
      $pending_referral_transactions = $in_transactions["referrals"]["pending"];

      # get products that your group consume
      $my_products = $client->query_my_product_info();
      $my_consume = $my_products["consumed"];
      $my_consume_ids = array();
      foreach($my_consume as $product){
	 $my_consume_ids[$product["id"]] = 1;
      }

      $transaction_recipient_map = array();
      $all_pending_transactions = array_merge($pending_offer_transactions, $pending_referral_transactions);
      # go through the offer transactions
      foreach($all_pending_transactions as $transaction){
	 # DO NOT refer products that your group consume
	 $product_id = $transaction["product.id"];
	 if(isset($my_consume_ids[$product_id])){
	    continue;
	 }
	 # randomly refer to another group other than the referral maker
	 $transaction_id = $transaction["id"];
	 $from_group_id = $transaction["from.id"];
	 $candidate_group_ids = array_diff($other_groups,array($from_group_id));
	 $random_group_key = array_rand($candidate_group_ids,1);
	 $random_group = $candidate_group_ids[$random_group_key];
	 $transaction_recipient_map[$transaction_id] = $random_group;
      }
      return $transaction_recipient_map;
   }

   /**
   * @brief accept offer or refferal
   * 
   * this is straight forward, just accept the products you need. 
   * you don't need to modify this function
   */

   function accept_offer_referral($client, $in_transactions, $max_accept){
      # get products that your group consume
      $my_products = $client->query_my_product_info();
      $my_consume = $my_products["consumed"];
      $my_consume_ids = array();
      foreach($my_consume as $product){
	 $my_consume_ids[$product["id"]] = 1;
      }

      # you can only accept pending offer or referral
      $pending_offer_transactions = $in_transactions["offers"]["pending"];
      $pending_referral_transactions = $in_transactions["referrals"]["pending"];
      $pending_transactions = array_merge($pending_offer_transactions, $pending_referral_transactions);
      $i = 0;
      foreach($pending_transactions as $transaction){
	 $transaction_id = $transaction["id"];
	 $product_id = $transaction["product.id"];
	 if(isset($my_consume_ids[$product_id])){
	    /// take the offer or referral
	    $response =  $client->accept_offer_referral($transaction_id);
	    $i++;
	    if($response["status"] == "fail"){
	       print_r($response);
	    }
	    if($i > $max_accept){
	       break;
	    }
	 }
      }
   }
?>
