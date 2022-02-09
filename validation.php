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

// Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
$authorized = false;
foreach (Module::getPaymentModules() as $item) {
    if ($item['name'] == $module->name) {
        $authorized = true;
        break;
    }
}

if (!$authorized) {
    die($module->l('This payment method is not available.', 'validation'));
}

// Check Cart Id
$cart_id = Tools::getValue('id_cart');

$clientConfig = new ClientConfig();
$clientConfig->setServiceEndpoint($module->pg_domain);
$clientConfig->setAuthToken($module->auth_token);
$clientConfig->setHmacSecret($module->hmac_secret);

$client = new GatewayClient($clientConfig);

$completeRequest = new PaymentCompleteRequest();
$completeRequest->setClientId($module->client_id);
$completeRequest->setReqid($_GET['reqid']);

$completeResponse = $client->payment()->complete($completeRequest);
$order_id = $completeResponse->getClientRef();
$response_code = $completeResponse->getResponseCode();
$transaction_id = $completeResponse->getTxnReference();

switch ($response_code) {
	case '00':
		// Payment is success
		$message = sprintf('Transaction success. Transaction ID: %s, ', $transaction_id);

		$amount = $cart->getOrderTotal(true, Cart::BOTH);
		$module->validateOrder($cart_id, Configuration::get('PS_OS_PAYMENT'), $amount, $module->displayName, null, array(), null, true, $customer->secure_key);
		$order = new Order($module->currentOrder);
		if (!Validate::isLoadedObject($order)) {
			die(Tools::displayError($module->l('Unable to place order.')));
		}

		// Make Invoice
		$order->setInvoice(true);

		break;
	default:
		$message = sprintf('Transaction failed. Transaction ID: %s. Code: %s', $transaction_id, $response_code);

		// Cancel
		$module->validateOrder($cart_id, Configuration::get('PS_OS_ERROR'), 0, $module->displayName, null, array(), null, true, $customer->secure_key);
		$order = new Order($module->currentOrder);
		if (!Validate::isLoadedObject($order)) {
			die(Tools::displayError($module->l('Unable to place order.')));
		}
		break;
}

// Redirect to Order Confirmation
$redirectUrl = _PS_BASE_URL_ . __PS_BASE_URI__ . 'index.php?controller=order-confirmation&key=' . $customer->secure_key . '&id_cart=' . (int)$cart_id . '&id_module=' . (int)$module->id . '&id_order=' . (int)$module->currentOrder;
Tools::redirect($redirectUrl);
