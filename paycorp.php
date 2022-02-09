<?php

if (!defined('_PS_VERSION_')) {
	exit;
}

$include_dir = dirname(__FILE__) . '/vendor/paycorp/';
require_once $include_dir . 'au.com.gateway.client.utils/IJsonHelper.php';
require_once $include_dir . 'au.com.gateway.client/GatewayClient.php';
require_once $include_dir . 'au.com.gateway.client.config/ClientConfig.php';
require_once $include_dir . 'au.com.gateway.client.component/RequestHeader.php';
require_once $include_dir . 'au.com.gateway.client.component/CreditCard.php';
require_once $include_dir . 'au.com.gateway.client.component/TransactionAmount.php';
require_once $include_dir . 'au.com.gateway.client.component/Redirect.php';
require_once $include_dir . 'au.com.gateway.client.facade/BaseFacade.php';
require_once $include_dir . 'au.com.gateway.client.payment/PaymentCompleteResponse.php';
require_once $include_dir . 'au.com.gateway.client.facade/Payment.php';
require_once $include_dir . 'au.com.gateway.client.payment/PaymentInitRequest.php';
require_once $include_dir . 'au.com.gateway.client.payment/PaymentInitResponse.php';
require_once $include_dir . 'au.com.gateway.client.helpers/PaymentCompleteJsonHelper.php';
require_once $include_dir . 'au.com.gateway.client.payment/PaymentCompleteRequest.php';
require_once $include_dir . 'au.com.gateway.client.root/PaycorpRequest.php';
require_once $include_dir . 'au.com.gateway.client.helpers/PaymentInitJsonHelper.php';
require_once $include_dir . 'au.com.gateway.client.utils/HmacUtils.php';
require_once $include_dir . 'au.com.gateway.client.utils/CommonUtils.php';
require_once $include_dir . 'au.com.gateway.client.utils/RestClient.php';
require_once $include_dir . 'au.com.gateway.client.enums/TransactionType.php';
require_once $include_dir . 'au.com.gateway.client.enums/Version.php';
require_once $include_dir . 'au.com.gateway.client.enums/Operation.php';
require_once $include_dir . 'au.com.gateway.client.facade/Vault.php';
require_once $include_dir . 'au.com.gateway.client.facade/Report.php';
require_once $include_dir . 'au.com.gateway.client.facade/AmexWallet.php';

class paycorp extends PaymentModule
{
	protected $_errors = array();
	public $pg_domain = '';
	public $client_id = '';
	public $hmac_secret = '';
	public $auth_token = '';
	public $transaction_type = '';

	public function __construct()
	{
		$this->name = 'paycorp';
		$this->displayName = $this->l('Paycorp');
		$this->description = $this->l('Paycorp');
		$this->author = 'Paycorp';
		$this->version = '1.0.0';
		$this->tab = 'payments_gateways';
		$this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
		$this->need_instance = 1;
		$this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
		$this->bootstrap = true;

		// Init Configuration
		$config = Configuration::getMultiple(array('PAYCORP_PGDOMAIN', 'PAYCORP_CLIENTID', 'PAYCORP_HMACSECRET', 'PAYCORP_AUTHTOKEN', 'PAYCORP_TRANSACTION_TYPE'));
		$this->pg_domain = isset($config['PAYCORP_PGDOMAIN']) ? $config['PAYCORP_PGDOMAIN'] : $this->pg_domain;
		$this->client_id = isset($config['PAYCORP_CLIENTID']) ? $config['PAYCORP_CLIENTID'] : $this->client_id;
		$this->hmac_secret = isset($config['PAYCORP_HMACSECRET']) ? $config['PAYCORP_HMACSECRET'] : $this->hmac_secret;
		$this->auth_token = isset($config['PAYCORP_AUTHTOKEN']) ? $config['PAYCORP_AUTHTOKEN'] : $this->auth_token;
		$this->transaction_type = isset($config['PAYCORP_TRANSACTION_TYPE']) ? $config['PAYCORP_TRANSACTION_TYPE'] : $this->transaction_type;

		parent::__construct();

		if (empty($this->merchantID) || empty($this->password) || empty($this->secretCode)) {
			$this->warning = $this->l('Please configure module');
		}
	}

	public function install()
	{
		return parent::install() &&
		       $this->registerHook('header') &&
		       $this->registerHook('backOfficeHeader') &&
		       $this->registerHook('payment') &&
		       $this->registerHook('paymentReturn') &&
		       $this->registerHook('actionPaymentConfirmation') &&
		       $this->registerHook('displayPayment');
	}

	public function uninstall()
	{
		Configuration::deleteByName('PAYCORP_PGDOMAIN');
		Configuration::deleteByName('PAYCORP_CLIENTID');
		Configuration::deleteByName('PAYCORP_HMACSECRET');
		Configuration::deleteByName('PAYCORP_AUTHTOKEN');
		Configuration::deleteByName('PAYCORP_TRANSACTION_TYPE');
		return parent::uninstall();
	}


	public function hookPayment($params)
	{
		$this->smarty->assign(array(
			'this_path' => $this->_path,
			'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/paycorp/'
		));
		return $this->display(__FILE__, 'payment.tpl');
	}

	/**
	 * Hook: Payment Return
	 * @param $params
	 * @return bool
	 */
	public function hookPaymentReturn($params)
	{
		if (!$this->active) {
			return;
		}

		$message = '';
		$order = $params['objOrder'];
		switch ($order->current_state) {
			case Configuration::get('PS_OS_PAYMENT'):
				$status = 'ok';
				break;
			case Configuration::get('PS_OS_ERROR'):
				$status = 'error';
				$message = $this->l('Payment error');
				break;
			default:
				$status = 'error';
				$message = $this->l('Order error');
		}

		$this->smarty->assign(array(
			'message' => $message,
			'status' => $status,
			'id_order' => $order->id
		));

		if (property_exists($order, 'reference') && !empty($order->reference)) {
			$this->smarty->assign('reference', $order->reference);
		}

		return $this->display(__FILE__, 'confirmation.tpl');
	}

	public function getContent()
	{
		if (Tools::isSubmit('Paycorp_UpdateSettings'))
		{
			$this->pg_domain = Tools::getValue('pg_domain');
			$this->client_id = Tools::getValue('client_id');
			$this->hmac_secret = Tools::getValue('hmac_secret');
			$this->auth_token = Tools::getValue('auth_token');
			$this->transaction_type = 'PURCHASE';

			Configuration::updateValue('PAYCORP_PGDOMAIN', $this->pg_domain);
			Configuration::updateValue('PAYCORP_CLIENTID', $this->client_id);
			Configuration::updateValue('PAYCORP_HMACSECRET', $this->hmac_secret);
			Configuration::updateValue('PAYCORP_AUTHTOKEN', $this->auth_token);
			Configuration::updateValue('PAYCORP_TRANSACTION_TYPE', $this->transaction_type);
		}

		$this->context->smarty->assign(array(
			'action' => Tools::htmlentitiesUTF8($_SERVER['REQUEST_URI']),
			'pg_domain' => $this->pg_domain,
			'client_id' => $this->client_id,
			'hmac_secret' => $this->hmac_secret,
			'auth_token' => $this->auth_token,
			'transaction_type' => $this->transaction_type,
			'this_path' => $this->_path,
			'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/'
		));

		return $this->display(__FILE__, 'views/templates/admin/config.tpl');
	}

	/**
	 * Get an order by its cart id
	 *
	 * @param integer $id_cart Cart id
	 * @return array Order details
	 */
	public static function getOrderByCartId($id_cart)
	{
		$sql = 'SELECT `id_order`
				FROM `'._DB_PREFIX_.'orders`
				WHERE `id_cart` = '.(int)($id_cart)
		       . (_PS_VERSION_ < '1.5' ? '' : Shop::addSqlRestriction());
		$result = Db::getInstance()->getRow($sql, false);

		return isset($result['id_order']) ? $result['id_order'] : false;
	}
}
