<?php
/**
 * Displays the paymill credit card form. Edit this
 * to fit your styles.
 *
 * @param $paymillPaymentId The vm payment id of the paymill plugin 
 */
function paymill_form($paymillPaymentId) {
    global $ps_checkout;

    // read paymill configuration
    require_once(CLASSPATH . "payment/ps_paymill.cfg.php");
    ?>
    <div style="border: 1px solid #d3d3d3; padding: 10px 20px; text-align: left;">
        <div style="float: left;">
            <div style="clear: both">
                <div style="width: 200px; float: left; line-height: 30px">Kartennummer</div>
                <div style="width: 200px; float: left; text-align: right; line-height: 30px">&nbsp;<input id="card-number" type="text" size="20" maxlength="16" placeholder="Ihre Kreditkartennummer" /></div>
            </div>
            <div style="clear: both">
                <div style="width: 200px; float: left; line-height: 30px">CVC</div>
                <div style="width: 200px; float: left; text-align: right; line-height: 30px">&nbsp;<input id="card-cvc" type="text" size="3" placeholder="CVC" maxlength="3" /></div>
            </div>
            <div style="clear: both">
                <div style="width: 200px; float: left; line-height: 30px">Gültigkeitsdatum</div>
                <div style="width: 200px; float: left; text-align: right; line-height: 30px">
                    &nbsp;
                    <select id="card-expiry-month">
                        <option value="01">01</option>
                        <option value="02">Feb</option>
                        <option value="03">Mär</option>
                        <option value="04">Apr</option>
                        <option value="05">Mai</option>
                        <option value="06">Jun</option>
                        <option value="07">Jul</option>
                        <option value="08">Aug</option>
                        <option value="09">Sep</option>
                        <option value="10">Okt</option>
                        <option value="11">Nov</option>
                        <option value="12">Dez</option>
                    </select>
                    /
                    <select id="card-expiry-year">
                        <option value="<?php echo date('Y') ?>"><?php echo date('Y') ?></option>
                        <option value="<?php echo date('Y') +1 ?>"><?php echo date('Y') +1 ?></option>
                        <option value="<?php echo date('Y') +2 ?>"><?php echo date('Y') +2 ?></option>
                        <option value="<?php echo date('Y') +3 ?>"><?php echo date('Y') +3 ?></option>
                        <option value="<?php echo date('Y') +4 ?>"><?php echo date('Y') +4 ?></option>
                        <option value="<?php echo date('Y') +5 ?>"><?php echo date('Y') +5 ?></option>
                        <option value="<?php echo date('Y') +6 ?>"><?php echo date('Y') +6 ?></option>
                    </select>
                </div>
            </div>
        </div>
        <div style="clear: both"></div>
        <div id="paymentErrors"></div>
    </div>
    <input type="hidden" name="paymill_token" id="paymill_token" />
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.0/jquery.min.js"></script>
    <script type="text/javascript">
      var PAYMILL_PUBLIC_KEY = '<?php echo PAYMILL_PUBLIC_KEY; ?>';
    </script>
    <script type="text/javascript" src="https://bridge.paymill.de/"></script>
    <script type="text/javascript">
        function PaymillResponseHandler(error, result) {
            if (error) {
                paymill_debug(error.apierror);
            } else {
                paymill_debug("Received token: " + result.token);
                $('#paymill_token').val(result.token);
                $('form[name="adminForm"]').get(0).submit();
            }
        }
        $('form[name="adminForm"]').submit(function() {
            if ($('input[name="payment_method_id"][value="<?php echo $paymillPaymentId; ?>"]').attr("checked") == "checked") {
                paymill_debug("Started validation");
                if (false == paymill.validateCardNumber($("#card-number").val())) {
                    $("#paymentErrors").html("<span style='color: #ff0000'>Ungültige Kartennummer</span>");
                    return false;
                }
                if (false == paymill.validateExpiry($("#card-expiry-month").val(), $("#card-expiry-year").val())) {
                    $("#paymentErrors").html("<span style='color: #ff0000'>Ungültiges Gültigkeitsdatum</span>");
                    return false;
                }
                if (false == paymill.validateCvc($("#card-cvc").val())) {
                    $("#paymentErrors").html("<span style='color: #ff0000'>Ungültiger CVC-Code</span>");
                    return false;
                }
                paymill_debug("Validation successful");
                paymill.createToken({
                    number: $('#card-number').val(), 
                    exp_month: $('#card-expiry-month').val(),
                    exp_year: $('#card-expiry-year').val(), 
                    cvc: $('#card-cvc').val(),
                    amount_int: <?php echo round(100 * $GLOBALS['order_total']); ?>, 
                    currency: "EUR"
                }, PaymillResponseHandler);
                return false;
            }
        }); 

        // debug mode
        function paymill_debug(message) {
            console.log("[PaymillCC] " + message);
        }
    </script>
<?php
}
?>