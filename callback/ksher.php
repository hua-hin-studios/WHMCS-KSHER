<?php
use WHMCS\Database\Capsule;
/**
 * WHMCS Ksher Payment Callback File
 */

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

// Retrieve data returned in payment gateway callback
include_once dirname(__DIR__) . '/ksher/ksher_pay_sdk.php';
$appid = $gatewayParams['appid'];
$time = date("Y-m-d H:i:s", time());
$class = new KsherPay($appid, file_get_contents($params['privatekey']));

//1.接收参数
$input = file_get_contents("php://input");
$query = urldecode($input);
if (!$query) {
    logTransaction($gatewayParams['name'], 'NO data found', 'Logs');
    echo json_encode(array('result' => 'FAIL', "msg" => 'NO RETURN DATA'));
    exit;
}
//2.验证参数
$data_array = json_decode($query, true);
logTransaction($gatewayParams['name'], $query, 'Logs');
if (!isset($data_array['data']) || !isset($data_array['data']['mch_order_no']) || !$data_array['data']['mch_order_no']) {
    logTransaction($gatewayParams['name'], "notify data FAIL", 'Logs');
    echo json_encode(array('result' => 'FAIL', "msg" => 'RETURN DATA ERROR'));
    exit;
}
//3.处理订单
if (
    array_key_exists("code", $data_array)
    && array_key_exists("sign", $data_array)
    && array_key_exists("data", $data_array)
    && array_key_exists("result", $data_array['data'])
    && $data_array['data']["result"] == "SUCCESS"
) {
    //3.1验证签名
    $verify_sign = $class->verify_ksher_sign($data_array['data'], $data_array['sign']);
    logTransaction($gatewayParams['name'], $verify_sign, 'Verify sign Logs');
    if ($verify_sign == 1) {
        $make_order = explode('-', $data_array['data']['mch_order_no']);
        $invoiceId = $make_order['0'];
        $transactionId = $data_array['data']['pay_mch_order_no'];
        $invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);
        checkCbTransID($transactionId);
        addInvoicePayment(
            $invoiceId,
            $transactionId,
            $data_array['data']['total_fee'] / 100,
            $paymentFee,
            $gatewayModuleName
        );
        Capsule::table('mod_ksher')->insert([
            'mch_order_no' => $data_array['data']['mch_order_no'],
            'ksher_order_no' => $data_array['data']['ksher_order_no'],
            'channel_order_no' => $data_array['data']['channel_order_no'],
            'channel' => $data_array['data']['channel'],
            'total_fee' => $data_array['data']['total_fee'],
            'invoiceid' => $invoiceId
        ])
    }
}
//4.返回信息
echo json_encode(array('result' => 'SUCCESS', "msg" => 'OK'));
