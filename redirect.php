<?php
require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../init.php';
require_once dirname(__FILE__) . '/paycorp.php';

$module = new Paycorp();
$cookie = Context::getContext()->cookie;
$cart = new Cart((int)$cookie->id_cart);
if (!Validate::isLoadedObject($cart)) {
    Tools::redirect('index.php?controller=order&step=1');
}

$customer = new Customer((int)$cart->id_customer);
if (!Validate::isLoadedObject($customer)) {
    Tools::redirect('index.php?controller=order&step=1');
}

$currency = Currency::getCurrency((int)$cart->id_currency);
$lang = Language::getLanguage((int)$cart->id_lang);

$amount = (float)$cart->getOrderTotal(true, Cart::BOTH);
$order_id = $cart->id;

try {
	$clientConfig = new ClientConfig();
	$clientConfig->setServiceEndpoint($module->pg_domain);
	$clientConfig->setAuthToken($module->auth_token);
	$clientConfig->setHmacSecret($module->hmac_secret);
	$clientConfig->setValidateOnly(false);

	$client = new GatewayClient($clientConfig);

	$initRequest = new PaymentInitRequest();
	$initRequest->setClientId($module->client_id);
	$initRequest->setTransactionType($module->transaction_type);
	$initRequest->setClientRef($order_id);
	$initRequest->setComment('');
	$initRequest->setTokenize(false);
	//$initRequest->setExtraData(array('msisdn' => $msisdn));

	$transactionAmount = new TransactionAmount(intval($amount * 100));
	//$transactionAmount->setTotalAmount(intval($amount * 100));
	$transactionAmount->setServiceFeeAmount(0);
	$transactionAmount->setPaymentAmount(intval($amount * 100));
	$transactionAmount->setCurrency($currency['iso_code']);
	$initRequest->setTransactionAmount($transactionAmount);

	$redirect = new Redirect();
	$redirect->setReturnUrl(_PS_BASE_URL_ . __PS_BASE_URI__ . 'modules/paycorp/validation.php?id_cart=' . $cart->id);
	$redirect->setReturnMethod('GET');
	$initRequest->setRedirect($redirect);

	//$initResponse = $client->getPayment()->init( $initRequest );
	$initResponse = $client->payment()->init($initRequest);
} catch (Exception $e) {
	$message = $e->getMessage();

	die(Tools::displayError('Error: ' . $message));
}

// Redirect
Tools::redirect($initResponse->getPaymentPageUrl());

