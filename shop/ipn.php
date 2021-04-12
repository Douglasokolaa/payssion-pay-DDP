<?php

/**
 * Payssion IPN
 *
 * @package Boxvibe Package
 * @author boxvibe.com
 * @copyright 2021
 * @version $Id: ipn.php, 2021-04-12 21:12:05 gewa Exp $
 */
define("_WOJO", true);
require_once("../../../init.php");

ini_set('log_errors', true);
ini_set('error_log', dirname(__file__) . '/ipn_errors.log');

if (isset($_SERVER['CONTENT_TYPE']) && false !== strpos($_SERVER['CONTENT_TYPE'], 'json')) {
    $body = file_get_contents("php://input");
    $body_params = json_decode($body, true);
    if ($body_params) {
        foreach ($body_params as $param_name => $param_value) {
            $_POST[$param_name] = $param_value;
        }
    }
}

if (isset($_POST['notify_sig'])) {
    $rules = array(
        'notify_sig' => array('required|string', "Invalid Signature"),
        'transaction_id' => array('required|string', "Invalid Transaction Id"),
        'order_id' => array('required|string', "Invalid Order Id"),
    );

    $validate = Validator::instance();
    $safe = $validate->doValidate($_POST, $rules);

    list($sesid, $user_id, $time) = explode("_", $_POST['order_id']);

    if (!$cart = Product::getCartContentIpn($sesid)) {
        Message::$msgs['cart'] = Lang::$word->STR_ERR;
    }

    $user = Db::run()->first(Users::mTable, null, array("id" => intval($user_id)));

    if (empty(Message::$msgs)) {
        $api = Db::run()->first(Admin::gTable, array("extra", "extra2", "extra3"), array("name" => "payssion"));
        
        $totals = Product::getCartTotal($sesid);
        $tax = Content::calculateTax($sesid);
        $amount = Utility::numberParse((($totals->tax > 0) ? $totals->grand : $tax * $totals->grand + $totals->grand));
        $items = array();
        $cdkey = array();

        $api_key  = $api->extra; //your api key
        $secret_key = $api->extra2; //your secret key

        // Assign payment notification values to local variables
        $_pm_id = $_POST['pm_id'];
        $_amount = $_POST['amount'];
        $_currency = $_POST['currency'];
        $_order_id = $_POST['order_id'];
        $_state = $_POST['state'];

        $check_array = array(
            $api_key,
            $_pm_id,
            $_amount,
            $_currency,
            $_order_id,
            $_state,
            $secret_key
        );

        $check_msg = implode('|', $check_array);
        $check_sig = md5($check_msg);
        $notify_sig = $_POST['notify_sig'];

        if ($notify_sig == $check_sig) {
            //handle payment notification
            //you must make sure the amount is equal to the order amount you created
            if ($_state == 'completed' && $amount == $_amount) {

                // insert payment record
                foreach ($cart as $k => $item) {
                    $key = Db::run()->getValue(Product::cdTable, "cdkey", "product_id", $item->id);
                    $data = array(
                        'user_id' => $user->id,
                        'product_id' => $item->id,
                        'txn_id' => $safe->transaction_id,
                        'tax' => Utility::numberParse($item->total * $tax),
                        'amount' => Validator::sanitize($item->total, "float"),
                        'total' => Validator::sanitize(($totals->tax > 0) ? $totals->tax : $tax * $item->total + $item->total, "float"),
                        'coupon' => $item->coupon,
                        'cdkey' => ($key) ? $key : "",
                        'pp' => "Payssion",
                        'ip' => Url::getIP(),
                        'file_date' => time(),
                        'currency' => $api->extra3,
                        'status' => 1,
                    );

                    $items[$k]['title'] = $item->title;
                    $items[$k]['qty'] = 1;
                    $items[$k]['price'] = $item->total;
                    $items[$k]['cdkey'] = $data['cdkey'];
                    $cdkey[] = $data['cdkey'];

                    Db::run()->insert(Product::xTable, $data);
                    if ($key) {
                        Db::run()->delete(Product::cdTable, array("cdkey" => $data['cdkey']));
                    }
                }

                // invoice table
                $xdata = array(
                    'invoice_id' => substr(time(), 5),
                    'transaction_id' => $safe->transaction_id,
                    'user_id' => $user->id,
                    'items' => json_encode($items),
                    'coupon' => $totals->discount,
                    'tax' => Utility::numberParse(($totals->subtotal - $totals->discount) * $tax),
                    'subtotal' => $totals->subtotal,
                    'grand' => $amount,
                    'currency' => strtoupper($api->extra3),
                );

                Db::run()->insert(Product::ivTable, $xdata);

                $json['type']    = 'success';
                $json['title']   = Lang::$word->SUCCESS;
                $json['message'] = Lang::$word->STR_POK;
                print json_encode($json);

                /* == Notify Administrator == */
                $mailer = Mailer::sendMail();
                $tpl = Db::run()->first(Content::eTable, array("body", "subject"), array('typeid' => 'payComplete'));
                $core = App::Core();
                $body = str_replace(array(
                    '[LOGO]',
                    '[CEMAIL]',
                    '[COMPANY]',
                    '[DATE]',
                    '[SITEURL]',
                    '[NAME]',
                    '[TYPE]',
                    '[ITEMNAME]',
                    '[CDKEY]',
                    '[PRICE]',
                    '[STATUS]',
                    '[PP]',
                    '[IP]',
                    '[FB]',
                    '[TW]'
                ), array(
                    Utility::getLogo(),
                    $core->site_email,
                    $core->company,
                    date('Y'),
                    SITEURL,
                    $user->name,
                    Lang::$word->PRD_PRODUCT,
                    implode(", ", array_column($items, "title")),
                    implode(", ", array_column($items, "cdkey")),
                    $amount,
                    "Completed",
                    "RazorPay",
                    Url::getIP(),
                    $core->social->facebook,
                    $core->social->twitter
                ), $tpl->body);

                $msg = (new Swift_Message())
                    ->setSubject($tpl->subject)
                    ->setTo(array($core->site_email => $core->company))
                    ->setFrom(array($core->site_email => $core->company))
                    ->setBody($body, 'text/html');
                $mailer->send($msg);

                // empty cart
                Db::run()->delete(Product::cxTable, array("user_id" => $sesid));
                Db::run()->delete(Product::cxTable, array("order_id" => $safe['order_id']));
            } else {
                //please refer to the following URL for more states:
                //https://payssion.com/en/docs/#api-reference-payment-notifications
            }
        }
    } else {
        error_log( "\r Unknown Cart \n" . json_encode($cart), 3, "payssion_errorlog.log");
        Message::msgSingleStatus();
    }

    #######################################################################
} else {
    error_log( "\r No Signature \n" . json_encode($_POST), 3, "payssion_errorlog.log");
}