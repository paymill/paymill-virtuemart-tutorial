<?php
if( !defined( '_VALID_MOS' ) && !defined( '_JEXEC' ) ) die( 'Direct Access to '.basename(__FILE__).' is not allowed.' );

/**
 * This payment class handles Paymill payment requests. Since VirtueMart does not specifically
 * provide a payment plugin interface this is a bit of a workaround. You need at least one other 
 * payment method than paymill to make this work.
 */
class ps_paymill {

    var $classname = "ps_paymill";
  
    /**
    * Show all configuration parameters for this payment method
    * @returns boolean False when the Payment method has no configration
    */
    function show_configuration() {
        global $VM_LANG;
        
        // Read current Configuration
        include_once(CLASSPATH ."payment/".__CLASS__.".cfg.php");

        ?>
        <table class="adminform">
          <tr class="row1">
            <td><strong>Geheimer Paymill Key</strong></td>
            <td><input type="text" name="PAYMILL_PRIVATE_KEY" class="inputbox" size="50" value="<?php  echo PAYMILL_PRIVATE_KEY ?>" /></td>
          </tr>
          <tr class="row0">
            <td><strong>Öffentlicher Paymill Key</strong></td>
            <td><input type="text" name="PAYMILL_PUBLIC_KEY" class="inputbox" size="50" value="<?php  echo PAYMILL_PUBLIC_KEY ?>" /></td>
          </tr>
        </table>
    <?php
    }
    
    function has_configuration() {
        return true;
    }
   
    /**
  	 * Returns the "is_writeable" status of the configuration file
  	 * @param void
  	 * @returns boolean True when the configuration file is writeable, false when not
  	 */
    function configfile_writeable() {
        return is_writeable( CLASSPATH."payment/".$this->classname.".cfg.php" );
    }
   
    /**
  	 * Returns the "is_readable" status of the configuration file
  	 * @param void
  	 * @returns boolean True when the configuration file is writeable, false when not
  	 */
    function configfile_readable() {
        return is_readable( CLASSPATH."payment/".$this->classname.".cfg.php" );
    }
   
    /**
  	 * Writes the configuration file for this payment method
  	 * @param array An array of objects
  	 * @returns boolean True when writing was successful
  	 */
    function write_configuration( &$d ) {
        global $vmLogger;

        $my_config_array = array(
          "PAYMILL_PRIVATE_KEY" => vmget($d,'PAYMILL_PRIVATE_KEY'),
          "PAYMILL_PUBLIC_KEY" => vmget($d,'PAYMILL_PUBLIC_KEY')
        );

        $config = "<?php\n";
        $config .= "if( !defined( '_VALID_MOS' ) && !defined( '_JEXEC' ) ) die( 'Direct Access to '.basename(__FILE__).' is not allowed.' ); \n\n";
        foreach( $my_config_array as $key => $value ) {
          $config .= "define ('$key', '$value');\n";
        }
        $config .= "?>";

        if ($fp = fopen(CLASSPATH ."payment/".__CLASS__.".cfg.php", "w")) {
            fputs($fp, $config, strlen($config));
            fclose ($fp);
            return true;
        } else { 
          return false;
        }
    }
   
    /**
     * Perfoms the payment processing. If processing fails an error is shown
     * and the order is not submitted.
     */
    function process_payment($order_number, $order_total, &$d) {
        global $vmLogger;

        // load config
        require_once( CLASSPATH."payment/".__CLASS__.".cfg.php" );  

        // read the configuration for Paymill library
        $paymillPrivateApiKey = PAYMILL_PRIVATE_KEY;
        $paymillApiEndpoint = 'https://api.paymill.com/v2/';

        // get the paymill token from request
        $paymillToken = $_POST['paymill_token'];

        if (!$paymillToken) {
            $vmLogger->err("Die Zahlung konnte nicht ausgeführt werden. Es wurde kein Transaktionsschlüssel übertragen.");
            return false;
        }

        // setup client params
        $user =& JFactory::getUser();
        $clientParams = array(
            'email' => $user->email,
            'description' => $user->name
        );

        $libBase = CLASSPATH . '/paymill/v2/lib/';

        // process the payment
        $result = $this->_process_payment(array(
            'token' => $paymillToken,
            'amount' => round(($order_total * 100)),
            'currency' => 'EUR',
            'name' => $user->name,
            'email' => $user->email,
            'description' => $user->name,
            'libBase' => $libBase,
            'privateKey' => $paymillPrivateApiKey,
            'apiUrl' => $paymillApiEndpoint,
            'loggerCallback' => array('ps_paymill', 'logAction')
        )); 

        if (!$result) {
            die("Fehler");
        }

        return true;
    }

    public function _process_payment($params) {  
        
        // setup the logger
        $logger = $params['loggerCallback'];
                       
        // setup client params
        $clientParams = array(
            'email' => $params['email'],
            'description' => $params['name']
        );

        // setup credit card params
        $paymentParams = array(
            'token' => $params['token']
        );

        // setup transaction params
        $transactionParams = array(
            'amount' => $params['amount'],
            'currency' => $params['currency'],
            'description' => $params['description']
        );
                
        require_once $params['libBase'] . 'Services/Paymill/Transactions.php';
        require_once $params['libBase'] . 'Services/Paymill/Clients.php';
        require_once $params['libBase'] . 'Services/Paymill/Payments.php';

        $clientsObject = new Services_Paymill_Clients(
            $params['privateKey'], $params['apiUrl']
        );
        $transactionsObject = new Services_Paymill_Transactions(
            $params['privateKey'], $params['apiUrl']
        );
        $paymentsObject = new Services_Paymill_Payments(
            $params['privateKey'], $params['apiUrl']
        );
        
        // perform conection to the Paymill API and trigger the payment
        try {

            $client = $clientsObject->create($clientParams);
            if (!isset($client['id'])) {
                call_user_func_array($logger, array("No client created " . var_export($client, true) . "; " . var_export($params, true)));
                return false;
            } else {
                call_user_func_array($logger, array("Client created: " . $client['id']));
            }

            // create card
            $paymentParams['client'] = $client['id'];
            $payment = $paymentsObject->create($paymentParams);
            if (!isset($payment['id'])) {
                call_user_func_array($logger, array("No payment (credit card) created: " . var_export($payment, true) . " with params " . var_export($paymentParams, true)));
                return false;
            } else {
                call_user_func_array($logger, array("Payment (credit card) created: " . $payment['id']));
            }            

            // create transaction
            //$transactionParams['client'] = $client['id'];
            $transactionParams['payment'] = $payment['id'];
            $transaction = $transactionsObject->create($transactionParams);
            if (!isset($transaction['id'])) {
                call_user_func_array($logger, array("No transaction created" . var_export($transaction, true)));
                return false;
            } else {
                call_user_func_array($logger, array("Transaction created: " . $transaction['id']));
            }

            // check result
            if (is_array($transaction) && array_key_exists('status', $transaction)) {
                if ($transaction['status'] == "closed") {
                    // transaction was successfully issued
                    return true;
                } elseif ($transaction['status'] == "open") {
                    // transaction was issued but status is open for any reason
                    call_user_func_array($logger, array("Status is open."));
                    return false;
                } else {
                    // another error occured
                    call_user_func_array($logger, array("Unknown error." . var_export($transaction, true)));
                    return false;
                }
            } else {
                // another error occured
                call_user_func_array($logger, array("Transaction could not be issued."));
                return false;
            }

        } catch (Services_Paymill_Exception $ex) {
            // paymill wrapper threw an exception
            call_user_func_array($logger, array("Exception thrown from paymill wrapper: " . $ex->getMessage()));
            return false;
        }        
        return true;
    }

    public function logAction($message) {
        $logfile = dirname(dirname(__FILE__)) . '/paymill/log.txt';
        if (is_writable($logfile)) {
            $handle = fopen($logfile, 'a');
            fwrite($handle, "[" . date(DATE_RFC822) . "] " . $message . "\n");
            fclose($handle);
        }
    }
}
