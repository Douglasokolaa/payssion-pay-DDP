<?php

/**
 * Payssion Form
 *
 * @package Boxvibe Package
 * @author boxvibe.com
 * @copyright 2021
 * @version $Id: ipn.php, 2021-04-12 21:12:05 gewa Exp $
 */
if (!defined("_WOJO"))
  die('Direct access to this location is not allowed.');
?>
<?php 
$api_key =  $this->gateway->extra;
$amount =  $this->cart->grand;
$currency =  ($this->gateway->extra3) ? $this->gateway->extra3 : $this->core->currency;
$order_id =  App::Auth()->sesid . '_' . App::Auth()->uid . '_' . time();
$secret_key =  $this->gateway->extra2;
$payer_email = Auth::$userdata->email;

$msg = implode('|', array($api_key, 'neosurf', $amount, $currency, $order_id, $secret_key));
$api_sig = md5($msg);
Db::run()->update(Product::cxTable, array("order_id" => $order_id), array("user_id" => App::Auth()->sesid));

// var_dump($this->cart); die;
error_log(implode(" |\n\r ", $_POST), 3, "payssion_errorlog.log");

?>

<?php $url = ($this->gateway->live) ? 'www.payssion.com' : 'sandbox.payssion.com'; ?>
<form action="https://<?php echo $url; ?>/payment/create.html" method="post" id="ps_form" name="ps_form" class="content-center">
  <input type="image" src="<?php echo SITEURL; ?>/gateways/payssion/logo_large.png" style="width:200px" name="submit" class="wojo basic primary button" title="Pay With Passion" alt="" onclick="this.form.submit();">
  <input type="hidden" name="api_key" value="<?php echo $api_key; ?>">
  <input type="hidden" name="api_sig" value="<?php echo $api_sig ?>" />
  <input type="hidden" name="order_id" value="<?php echo $order_id ?>" />
  <input type="hidden" name="pm_id" value="neosurf">
  <input type="hidden" name="payer_name" value="<?php echo Auth::$userdata->fname . ' ' . Auth::$userdata->lname;?>">
  <input type="hidden" name="payer_email" value="<?php echo $payer_email ?>" />
  <input type="hidden" name="description" value="" />
  <input type="hidden" name="amount" value="<?php echo $amount; ?>">
  <input type="hidden" name="currency" value="<?php echo $currency ?>" />
  <input type="hidden" name="return_url" value="<?php echo Url::url("/dashboard"); ?>">
</form>