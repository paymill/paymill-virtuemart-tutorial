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

        // get the paymill token from request
        $paymillToken = $_POST['paymill_token'];

        if (!$paymillToken) {
            $vmLogger->err("Die Zahlung konnte nicht ausgeführt werden. Es wurde kein Transaktionsschlüssel übertragen.");
            return false;
        }

        // load Paymill library
        require_once(CLASSPATH . 'paymill/lib/Services/Paymill/Transactions.php');
        require_once(CLASSPATH . 'paymill/lib/Services/Paymill/Creditcards.php');
        require_once(CLASSPATH . 'paymill/lib/Services/Paymill/Clients.php');

        // convert order amount
        $amount = number_format(($order_total * 100), 0, '', '');

        // setup client params
        $user =& JFactory::getUser();
        $clientParams = array(
            'email' => $user->email,
            'description' => $user->name
        );

        // setup credit card params
        $creditcardParams = array(
            'token' => $paymillToken
        );

        // setup transaction params
        $transactionParams = array(
            'amount' => $amount,
            'currency' => 'eur',
            'description' => 'Order ' . $order_number
        );

        // read the configuration for Paymill library
        $paymillPrivateApiKey = PAYMILL_PRIVATE_KEY;
        $paymillApiEndpoint = 'https://api.paymill.de/v1/';

        // Access objects for the Paymill API
        $clientsObject = new Services_Paymill_Clients(
            $paymillPrivateApiKey, $paymillApiEndpoint
        );
        $creditcardsObject = new Services_Paymill_Creditcards(
            $paymillPrivateApiKey, $paymillApiEndpoint
        );
        $transactionsObject = new Services_Paymill_Transactions(
            $paymillPrivateApiKey, $paymillApiEndpoint
        );
        
        // perform conection to the Paymill API and trigger the payment
        try {
            // create card
            $creditcard = $creditcardsObject->create($creditcardParams);

            // create client
            $clientParams['creditcard'] = $creditcard['id'];
            $client = $clientsObject->create($clientParams);

            // create transaction
            $transactionParams['client'] = $client['id'];
            $transaction = $transactionsObject->create($transactionParams);

            if (is_array($transaction) && array_key_exists('status', $transaction)) {
                if ($transaction['status'] == "closed") {
                    return true;
                } elseif ($transaction['status'] == "open") {
                  $vmLogger->err("Ihre Zahlung konnte nicht ausgeführt werden. Der Zahlungsstatus ist 'open'");
                  return false;
                } else {
                  $vmLogger->err("Ihre Zahlung konnte nicht ausgeführt werden.");
                  return false;
                }
            } else {
            }
        } catch (Services_Paymill_Exception $ex) {
            $vmLogger->err("Ihre Zahlung konnte nicht ausgeführt werden: " . $ex->getMessage());
            return false;
        }  
        return true;
    }
}
