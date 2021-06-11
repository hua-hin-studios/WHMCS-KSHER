<?php

use WHMCS\Database\Capsule;

/**
 * WHMCS Ksher Gateway Module
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @see https://developers.whmcs.com/payment-gateways/meta-data-params/
 *
 * @return array
 */
function ksher_MetaData()
{
    return array(
        'DisplayName' => 'Ksher',
        'APIVersion' => '3.0.0'
    );
}

/**
 * Define gateway configuration options.
 * @return array
 */
function ksher_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Ksher Payments',
        ),
        'appid' => array(
            'FriendlyName' => 'App ID',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter your App ID here',
        ),
        'privatekey' => array(
            'FriendlyName' => 'Private Key Path',
            'Type' => 'text',
            'Size' => '40',
            'Default' => __DIR__ . '/ksher/Mch37793_PrivateKey.pem',
            'Description' => 'Enter Private Path key',
        ),
        'wechat' => array(
            'FriendlyName' => 'Wechat',
            'Type'        => 'yesno',
            'Description' => 'China based mobile payment platform',
            'Default'     => 'no'
        ),
        'alipay' => array(
            'FriendlyName' => 'Ali Pay',
            'Type'        => 'yesno',
            'Description' => 'China based online payment platform',
            'Default'     => 'no'
        ),
        'truemoney' => array(
            'FriendlyName' => 'True Money',
            'Type'        => 'yesno',
            'Description' => 'Thailand based mobile payment platform',
            'Default'     => 'no'
        ),
        'promptpay' => array(
            'FriendlyName' => 'Promptpay',
            'Type'        => 'yesno',
            'Description' => 'Thailand based QR Payment for all Thai banks',
            'Default'     => 'no'
        ),
        'linepay' => array(
            'FriendlyName' => 'LinePay',
            'Type'        => 'yesno',
            'Description' => 'Thailand based mobile payment platform',
            'Default'     => 'no'
        ),
        'airpay' => array(
            'FriendlyName'       => 'ShopeePay',
            'Type'        => 'yesno',
            'Description' => 'Thailand based mobile payment platform',
            'Default'     => 'no'
        ),
        'ktbcard' => array(
            'FriendlyName' => 'KTB Card',
            'Type'        => 'yesno',
            'Description' => 'Thai bank processing all credit/debit cards',
            'Default'     => 'no'
        ),
        'savecard' => array(
            'FriendlyName' => 'Save Card',
            'Type'        => 'yesno',
            'Description' => 'Enable Savecard for Credit Card(KTB Card)',
            'Default'     => 'yes',
        ),
        'testMode' => array(
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable test mode',
        )
    );
}

/**
 * Payment link.
 * @return string
 */
function ksher_link($params)
{
    if (!Capsule::schema()->hasTable('mod_ksher')) {
        Capsule::schema()
            ->create(
                'mod_ksher',
                function ($table) {
                    /** @var \Illuminate\Database\Schema\Blueprint $table */
                    $table->increments('id');
                    $table->text('mch_order_no');
                    $table->text('ksher_order_no');
                    $table->text('channel_order_no');
                    $table->text('invoiceid');
                    $table->text('channel');
                }
            );
    }

    if ($_POST['ksher'] == 'ksher') {
        require_once __DIR__ . '/ksher/ksher_pay_sdk.php';
        $request = new KsherPay($params['appid'], file_get_contents($params['privatekey']));
        // System Parameters
        $systemUrl = $params['systemurl'];
        $returnUrl = $params['returnurl'];
        $langPayNow = $params['langpaynow'];
        $moduleDisplayName = $params['name'];
        $moduleName = $params['paymentmethod'];
        $whmcsVersion = $params['whmcsVersion'];

        $url = 'https://gateway.ksher.com/api/gateway_pay';
        $device = 'PC';
        $channel_list = array();
        $savecard = false;
        if ($params['wechat'] == 'on') {
            array_push($channel_list, 'wechat');
        }
        if ($params['alipay'] == 'on') {
            array_push($channel_list, 'alipay');
        }
        if ($params['truemoney'] == 'on') {
            array_push($channel_list, 'truemoney');
        }
        if ($params['promptpay'] == 'on') {
            array_push($channel_list, 'bbl_promptpay');
        }
        if ($params['linepay'] == 'on') {
            array_push($channel_list, 'linepay');
        }
        if ($params['airpay'] == 'on') {
            array_push($channel_list, 'airpay');
        }
        if ($params['ktbcard'] == 'on') {
            array_push($channel_list, 'ktbcard');
        }
        if ($params['savecard'] == 'on') {
            $savecard = true;
        }
        $channel_list = implode(',', $channel_list);
        $timestamp = date('YmdHis');
        $mch_order_no = $params['invoiceid'] . '-' . $timestamp;
        if (strpos($channel_list, 'ktbcard') && $savecard) {
            $member_id = strval('ks-whmcs-' . $params['clientdetails']['id']);
            $data = array(
                'channel_list' => $channel_list,
                'device' => $device,
                'fee_type' => $params['currency'],
                'mch_code' => $params['invoiceid'],
                'mch_order_no' => $mch_order_no,
                'mch_redirect_url' => $returnUrl,
                'mch_redirect_url_fail' => $returnUrl,
                'product_name' => $params['description'],
                'refer_url' => $systemUrl,
                'total_fee' => $params['amount'] * 100,
                'mch_notify_url' =>  $systemUrl . '/modules/gateways/callback/' . $moduleName . '.php',
                'member_id' => $member_id,
                'lang' => 'en'
            );
        } else {
            $data = array(
                'channel_list' => $channel_list,
                'device' => $device,
                'fee_type' => $params['currency'],
                'mch_code' => $params['invoiceid'],
                'mch_order_no' => $mch_order_no,
                'mch_redirect_url' => $returnUrl,
                'mch_redirect_url_fail' => $returnUrl,
                'product_name' => $product,
                'refer_url' => $systemUrl,
                'total_fee' => $params['amount'] * 100,
                'mch_notify_url' =>  $systemUrl . '/modules/gateways/callback/' . $moduleName . '.php',
                'lang' => 'en'
            );
        }
        // print_r($data); 
        // $encoded_sign = $request->ksher_sign($data);
        // $data['sign'] = $encoded_sign;
        $response = $request->gateway_pay($data);
        // print_r($response);
        // exit();
        if ($response) {
            $body = json_decode($response, true);
            if ($body['code'] == 0) {
                $verify = false;
                $verify = $request->verify_ksher_sign($body['data'], $body['sign']);
                if ($verify) {
                    // $check =  json_encode($data, true);
                    header("location: " . $body['data']['pay_content']);
                } else {
                    $msg = "Some thing wrong";
                }
            } else if ($body['code'] == 1) {
                $msg = "Order is Paid";
            } else {
                $msg = 'Order Error ' . $body['msg'] . $member_id;
            }
        } else {
            $msg = 'Connection error.';
        }
    }
    $htmlOutput = '<form method="post" action="">';
    $htmlOutput .= '<input type="hidden" name="ksher" value="ksher" />';
    $htmlOutput .= '<input type="submit" value="Pay" />';
    $htmlOutput .= '</form><br>' . $msg;

    return $htmlOutput;
}

/**
 * Refund transaction.
 *
 * Called when a refund is requested for a previously successful transaction.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/refunds/
 *
 * @return array Transaction response status
 */
function ksher_refund($params)
{
    require_once __DIR__ . '/ksher/ksher_pay_sdk.php';
    $request = new KsherPay($params['appid'], file_get_contents($params['privatekey']));
    $orderdata = Capsule::table('mod_ksher')->where('invoiceid', $params['invoiceid'])->first();
    // Transaction Parameters
    $transactionIdToRefund = $params['transid'];
    $data['channel'] = $orderdata->channel;
    $data['fee_type'] = $params['currency'];
    $data['mch_order_no'] = $orderdata->mch_order_no;
    $data['refund_fee'] = $params['amount'];
    $data['mch_refund_no'] = 'refund' . $orderdata->mch_order_no;
    $data['total_fee'] = $orderdata->total_fee;
    $response = $request->order_refund($data);
    // print_r($response);
    // exit();
    if ($response) {
        $body = json_decode($response, true);
        if ($body['code'] == 0) {
            return array(
                'status' => 'success',
                'rawdata' => $response,
                'transid' => $body['data']['ksher_refund_no'],
                'fees' => $body['data']['cash_refund_fee'],
            );
        } else {
            return array(
                'status' => 'error',
                'rawdata' => $response,
                'transid' => '',
                'fees' => 0,
            );
        }
    }
}
