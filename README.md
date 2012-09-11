# Integration into VirtueMart 1.x

## Copy the files to your VM installation

**paymill.php** & **paymill directoy** to
administrator/components/com_virtuemart/classes/

**ps_paymill.php** & **ps_paymill.cfg.php** to
administrator/components/com_virtuemart/classes/payment

## Edit the following files as follows

### administrator/components/com_virtuemart/classes/ps_payment_method.php

In **line 394** (in the sql statement) add the following to the field list: **payment_class**
The line should look like this:

```php
<?php
$q = "SELECT payment_method_id,payment_method_discount, payment_method_discount_is_percent, payment_method_name, payment_class from #__{vm}_payment_method WHERE ";
?>
```

In **line 436** (before the while loop ends) insert the following:

```php
<?php
if ($db->f("payment_class") == "ps_paymill") {
    require_once( CLASSPATH . 'paymill.php');
    paymill_form();
}
?>
```

### components/com_virtuemart/themes/default/templates/checkout/get_final_confirmation.tpl.php

Add the following to the files end (last line):

```html
<input type="hidden" name="paymill_token" value="<?php echo $_POST['paymill_token']; ?>" />
```

## Setup the payment method

Go to your shop backend and **create a new payment method** (Store > Add Payment Method). Insert the following values to the form:

* **Payment Method Name**: Credit Card (or what you prefer to display on the checkout page)
* **Code**: PM
* **Payment class name**: ps_paymill
* **Payment method type**: HTML-Form based

Leave the remaining options with their default values and save the payment method. Afterwards switch to the **Configuration tab** and insert your public and private Paymill keys. 