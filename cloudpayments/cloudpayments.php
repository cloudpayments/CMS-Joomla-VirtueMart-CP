<?php
if (!defined('_VALID_MOS') && !defined('_JEXEC'))
	die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

if (!class_exists('vmPSPlugin'))
	require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

if (!class_exists('VmConfig'))
	require(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart' . DS . 'helpers' . DS . 'config.php');

VmConfig::loadConfig();

if (!class_exists('VmModel'))
	require(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart' . DS . 'helpers' . DS . 'vmmodel.php');

if (!class_exists('VirtueMartModelOrders')) {
	require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
}

class plgVmPaymentCloudPayments extends vmPSPlugin
{
	
	public $methodId;
	
	function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
		$lang = JFactory::getLanguage();
		$this->_loggable = true;
		$this->tableFields = array_keys($this->getTableSQLFields());
		
		if (version_compare(JVM_VERSION, '3', 'ge')) {
			$varsToPush = $this->getVarsToPush();
		} else {
			$varsToPush = array(
				'public_id' => array('', 'string'),
				'payment_message' => array('', 'string'),
				'status_success' => array('', 'char'),
				'status_canceled' => array('X', 'char'),
				"status_pending" => array('P', 'char'),
				"status_cp_authorized" => array('A', 'char'),
				"status_cp_confirmed" => array('O', 'char'),
			);
		}
		$this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
	}
	/**
	 * Срабатывает по урлу ?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived
	 * @param $html
	 * @param $d
	 * @return mixed
	 */
	function plgVmOnPaymentResponseReceived(&$html)
	{
		/**
		 * URL для проверки (check)
		 * index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&cloudpayments_check
		 */
		if (isset($_GET['cloudpayments_check'])) {
			return $this->CloudPaymentsCheck();
		}
		/**
		 * URL для проверки (pay)
		 * index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&cloudpayments_pay
		 */
		if (isset($_GET['cloudpayments_pay'])) {
			return $this->CloudPaymentsPay();
		}
		/**
		 * URL для проверки (confirm)
		 * index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&cloudpayments_confirm
		 */
		if (isset($_GET['cloudpayments_confirm'])) {
			return $this->CloudPaymentsConfirm();
		}
		/**
		 * URL для проверки (confirm)
		 * index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&cloudpayments_refund
		 */
		if (isset($_GET['cloudpayments_refund'])) {
			return $this->CloudPaymentsRefund();
		}
		/**
		 * Дефолтная логика
		 */
		vmDebug('plgVmOnPaymentResponseReceived');
//		return $this->plgVmOnPaymentResponseReceived($html);
	}
	
	/**
	 * Проверяем айпи адреса с которых пришли запросы
	 */
	private function CheckAllowedIps()
	{
		return true;
		if (!in_array($_SERVER['REMOTE_ADDR'], ['130.193.70.192', '185.98.85.109'])) throw new Exception('CloudPayments: Hacking atempt!');
	}
	
	/**
	 * Проверяем коректность запроса
	 */
	private function CheckHMAC($sSercet)
	{
		if (!$sSercet) throw new Exception('CloudPayments: Sercet key is not defined');
		$sPostData    = file_get_contents('php://input');
		$sCheckSign   = base64_encode(hash_hmac('SHA256', $sPostData, $sSercet, true));
		$sRequestSign = isset($_SERVER['HTTP_CONTENT_HMAC']) ? $_SERVER['HTTP_CONTENT_HMAC'] : '';
		if ($sCheckSign !== $sRequestSign) {
			throw new Exception('CloudPayments: Hacking atempt!');
		};
		return true;
	}
	
	/**
	 * Проверяем сумму заказа перед совершением платежа
	 *
	 * @since version
	 */
	private function CloudPaymentsCheck()
	{
		$this->CheckAllowedIps();
		$oOrder = $this->CheckDataFromSystemAndGetOrder();
		$method = $this->getVmPluginMethod($oOrder['details']['BT']->virtuemart_paymentmethod_id);
		$this->CheckHMAC($method->api_password);
		if ($oOrder['details']['BT']->order_total != $_POST['Amount']) exit('{"code":11}'); // Неверная сумма
		exit('{"code":0}');
	}
	
	/**
	 * SMS: Меняем статус заказа на оплачено
	 * DMS: Меняем статус заказа на авторизовано
	 */
	private function CloudPaymentsPay()
	{
		$this->CheckAllowedIps();
		$oOrder = $this->CheckDataFromSystemAndGetOrder();
		$oCurrencyModel = VmModel::getModel('currency');
		$oCurrency = $oCurrencyModel->getCurrency($oOrder['details']['BT']->order_currency);
		$method = $this->getVmPluginMethod($oOrder['details']['BT']->virtuemart_paymentmethod_id);
		$this->CheckHMAC($method->api_password);
		$oOrderModel = VmModel::getModel('orders');
		if($method->scheme == 'sms') {
			$aOrder['order_status'] = $method->status_success; // Заказ Оплачен
		} else if($method->scheme == 'dms') {
			$aOrder['order_status'] = $method->status_cp_authorized; // Платеж авторизован (Деньги заблокированы)
		} else {
			throw new Exception('CloudPayments: Undefined scheme of payments!');
		}
		$oOrderModel->updateStatusForOneOrder($oOrder['details']['BT']->virtuemart_order_id, $aOrder, TRUE);
		/**
		 * Сохраняем данные по заказу
		 */
		$iTransactionId = JRequest::getVar('TransactionId');
		$oDb = JFactory::getDBO();
		$sSql = "SELECT `transaction_id` FROM `#__virtuemart_payment_plg_cloudpayments` WHERE `transaction_id` = ".$iTransactionId." LIMIT 1";
		$oDb->setQuery($sSql);
		$sRow = $oDb->loadResult();
		if (!$sRow) {
			$dbValues['order_number'] = $oOrder['details']['BT']->order_number;
			$dbValues['payment_name'] = $method->payment_name;
			$dbValues['virtuemart_paymentmethod_id'] = $oOrder['details']['BT']->virtuemart_paymentmethod_id;
			$dbValues['payment_currency'] = $oCurrency->currency_code_3;
			$dbValues['email_currency'] = $oOrder['details']['BT']->email;
			$dbValues['payment_order_total'] = $oOrder['details']['BT']->order_total;
			$dbValues['transaction_id'] = JRequest::getVar('TransactionId');
			$this->storePSPluginInternalData($dbValues);
		}
		exit('{"code":0}');
	}
	
	/**
	 * Меняем статус заказа на оплачено при DMS
	 */
	private function CloudPaymentsConfirm()
	{
		$this->CheckAllowedIps();
		$oOrder = $this->CheckDataFromSystemAndGetOrder();
		$method = $this->getVmPluginMethod($oOrder['details']['BT']->virtuemart_paymentmethod_id);
		$this->CheckHMAC($method->api_password);
		$oOrderModel = VmModel::getModel('orders');
		if($method->scheme == 'sms') {
			throw new Exception('СloudPayments: This method used only for DMS scheme');
		} else if($method->scheme == 'dms') {
			$aOrder['order_status'] = $method->status_cp_confirmed; // Заказ подтвержден
		} else {
			throw new Exception('CloudPayments: Undefined scheme of payments!');
		}
		$oOrderModel->updateStatusForOneOrder ($oOrder['details']['BT']->virtuemart_order_id, $aOrder, TRUE);
		exit('{"code":0}');
	}
	
	/**
	 * Меняем статус заказа на возврат
	 */
	private function CloudPaymentsRefund()
	{
		$this->CheckAllowedIps();
		$oOrder = $this->CheckDataFromSystemAndGetOrder();
		$method = $this->getVmPluginMethod($oOrder['details']['BT']->virtuemart_paymentmethod_id);
		$this->CheckHMAC($method->api_password);
		$oOrderModel = VmModel::getModel('orders');
		$aOrder['order_status'] = $method->status_refund; // Возврат заказа
		$oOrderModel->updateStatusForOneOrder ($oOrder['details']['BT']->virtuemart_order_id, $aOrder, TRUE);
		exit('{"code":0}');
	}
	
	/**
	 * Проверяем данные которые пришли от платежной системе и возвращаем заказ
	 * @return mixed
	 */
	private function CheckDataFromSystemAndGetOrder()
	{
		/**
		 * Делаем проверку с системой платежей
		 */
		if (!isset($_POST['Data'])) {
			exit('{"code":13}'); // Платеж не может быть принят
		} else {
			$aData = json_decode($_POST['Data']);
			if (!isset($aData->order_number)) exit('{"code":13}'); // Платеж не может быть принят
			/**
			 * Получаем заказ
			 */
			$oOrderModel = VmModel::getModel('orders');
			$iOrderId = $oOrderModel->getOrderIdByOrderNumber($aData->order_number);
			$oOrder = $oOrderModel->getOrder($iOrderId);
			return $oOrder;
		}
	}
	
	/**
	 * Проверка статуса заказа CP-Authorized
	 */
	private function CheckStatusCloudPaymentsAvaliable()
	{
		$oDb = JFactory::getDBO();
		$sSql = "SELECT `order_status_name` FROM `#__virtuemart_orderstates` WHERE `order_status_name` = 'COM_VIRTUEMART_ORDER_STATUS_CP_CONFIRMED' LIMIT 1";
		$oDb->setQuery($sSql);
		$sRow = $oDb->loadResult();
		if (!$sRow) {
			$oDb->setQuery("INSERT INTO `#__virtuemart_orderstates` (order_status_code, order_status_name, order_stock_handle, ordering) VALUES ('O','COM_VIRTUEMART_ORDER_STATUS_CP_CONFIRMED', 'R', 9)");
			$oDb->loadResult();
		}
		return null;
	}
	
	/**
	 * Срабатывает при сохранении в админке
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 * @author Valérie Isaksen
	 *
	 */
	public function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
	{
		/**
		 * Проверим наличие статусов CloudPayments
		 */
		$this->CheckStatusCloudPaymentsAvaliable();
		return $this->onStoreInstallPluginTable($jplugin_id);
	}
	
	/**
	 * Срабатывает после того как пользователь нажал кнопку подтвердить в корзине
	 * @param $cart
	 * @param $order
	 * @return bool|null
	 */
	public function plgVmConfirmedOrder($cart, $order)
	{
		if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		
		/**
		 * Если метод оплаты CloudPayments, то редиректим на страницу заказа для оплаты
		 */
		if ($method->virtuemart_paymentmethod_id == $this->methods[0]->virtuemart_paymentmethod_id) {
			$script = "console.log('{$order['details']['BT']->order_number}')";
			$script = "window.setTimeout(function(){ window.location.href = '/index.php?option=com_virtuemart&view=orders&task=edit&layout=details&order_number={$order['details']['BT']->order_number}&order_pass={$order['details']['BT']->order_pass}';}, 3000);";
			$document = JFactory::getDocument();
			$document->addScriptDeclaration($script);
		}
		/**
		 * Чистим корзину
		 */
		$cart->emptyCart ();
		/**
		 * Сообщаем о перенаправлении.
		 */
		vmInfo('<p>Сейчас вы будете перенаправлены на страницу заказа для совершения оплаты</p>');
		return null;
	}
	
	/**
	 * Срабатывает когда показываем детали по конкретному заказу. Здесь будем оплачивать
	 * This method is fired when showing the order details in the frontend.
	 * It displays the method-specific data.
	 *
	 * @param integer $order_id The order ID
	 * @return mixed Null for methods that aren't active, text (HTML) otherwise
	 * @author Max Milbers
	 * @author Valerie Isaksen
	 */
	public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
	{
		vmDebug('plgVmOnShowOrderFEPayment');
		$this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
		
		
		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		$result = $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
		if (JRequest::getVar('option') == 'com_virtuemart' &&
			Jrequest::getVar('view') == 'orders' &&
			Jrequest::getVar('layout') == 'details') {
			if (!class_exists('CurrencyDisplay'))
				require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');
			if (!class_exists('VirtueMartModelOrders'))
				require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
			$orderModel = VmModel::getModel('orders');
			$oOrder = $orderModel->getOrder($virtuemart_order_id);
			if ($method->status_pending == $oOrder['details']['BT']->order_status) {
				$sDescription = 'Order number: ' .$oOrder['details']['BT']->order_number;
				$oCurrencyModel = VmModel::getModel('currency');
        $currency = $oCurrencyModel->getCurrency($method->currency_id);
				$sCurrencyName = $currency->currency_code_3 ?? 'RUB';
        $AdditionalReceiptInfos = false;
				$sItems = '[';
				foreach ($oOrder['items'] as $oProduct) {
					$aTax = current($oProduct->prices['Tax']);

          foreach ($oProduct->customfields as $field) {
            if($field->custom_title === 'CodeIKPU') $spic = $field->customfields_value;
            elseif($field->custom_title === 'PackageCode') $packageCode = $field->customfields_value;
          }

          if (isset($spic) && isset($packageCode)) {
            $sItems .= "{
              \"label\": '".$oProduct->product_name."', //наименование товара
              \"price\": ".round($oProduct->product_final_price,2).", //цена
              \"quantity\": ".$oProduct->product_quantity.", //количество
              \"amount\": ".round($oProduct->product_subtotal_with_tax,2).", //сумма
              \"vat\": ".(isset($aTax[1]) ? round($aTax[1], 2) : 0).", //ставка НДС
              \"spic\": '".$spic."', //код ИКПУ
              \"packageCode\": '".$packageCode."' //код упаковки
            },";

            if(!$AdditionalReceiptInfos) {
              $AdditionalReceiptInfos = true;
            }
          } else {
            $sItems .= "{
              \"label\": '".$oProduct->product_name."', //наименование товара
              \"price\": ".round($oProduct->product_final_price,2).", //цена
              \"quantity\": ".$oProduct->product_quantity.", //количество
              \"amount\": ".round($oProduct->product_subtotal_with_tax,2).", //сумма
              \"vat\": ".(isset($aTax[1]) ? round($aTax[1], 2) : 0)." //ставка НДС
            },";
          }
				}
				/**
				 * Доставка
				 */
				if ($oOrder['details']['BT']->order_billTax || $oOrder['details']['BT']->order_shipment) {
					$aTax = current(json_decode($oOrder['details']['BT']->order_billTax));
          if (isset($method->spic) && isset($method->packageCode)) {
            $sItems .= "{
              \"label\": 'Доставка',
              \"price\": ".round($oOrder['details']['BT']->order_shipment + $oOrder['details']['BT']->order_shipment_tax,2).", //цена
              \"quantity\": 1, //количество
              \"amount\": ".round($oOrder['details']['BT']->order_shipment + $oOrder['details']['BT']->order_shipment_tax,2).", //сумма
              \"vat\": ".round($aTax->calc_value,2).", //ставка НДС
              \"spic\": '".$method->spic."', //код ИКПУ доставки
              \"packageCode\": '".$method->packageCode."' //код упаковки доставки
            },";
          } else {
            $sItems .= "{
              \"label\": 'Доставка',
              \"price\": ".round($oOrder['details']['BT']->order_shipment + $oOrder['details']['BT']->order_shipment_tax,2).", //цена
              \"quantity\": 1, //количество
              \"amount\": ".round($oOrder['details']['BT']->order_shipment + $oOrder['details']['BT']->order_shipment_tax,2).", //сумма
              \"vat\": ".round($aTax->calc_value,2)." //ставка НДС 
            },";
          }
				}
				$sItems = trim($sItems, ',').']';
//				$sDescription .= 'Доставка: ' . round($oOrder['details']['BT']->order_shipment, 2).$currency->currency_code_3;
				$html = 'CloudPayments ';
					$this->getPaymentCurrency($method);
					$paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
					$totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $oOrder['details']['BT']->order_total, false), 2);
					$sPhone = $oOrder['details']['BT']->phone_1 ? $oOrder['details']['BT']->phone_1 : ($oOrder['details']['BT']->phone_2 ? $oOrder['details']['BT']->phone_2 : '');
          if ($AdditionalReceiptInfos) $customerReceipt = "{ customerReceipt: { Items:".$sItems.", taxationSystem: ".$method->tax_system.", email: '".$oOrder['details']['BT']->email."', phone: '".$sPhone."', AdditionalReceiptInfos: ['Вы стали обладателем права на 1% cashback']} }";
          else $customerReceipt = "{ customerReceipt: { Items:".$sItems.", taxationSystem: ".$method->tax_system.", email: '".$oOrder['details']['BT']->email."', phone: '".$sPhone."'} }";
					$html .= "<div id=\"cloudpayment_pay\"><button id=\"cloudpayment_pay_button\">Оплатить</button></div>
					<script src=\"https://widget.cloudpayments.ru/bundles/cloudpayments\"></script>
					<script>
						var oBut = document.getElementById('cloudpayment_pay_button');
						var oButWrap = document.getElementById('cloudpayment_pay');
						oBut.onclick =  function () {
							
							var widget = new cp.CloudPayments({language:'".($method->localization ? $method->localization: 'ru_RU')."'});
							
							widget.".($method->scheme == 'sms' ? 'charge' : 'auth')."({ // options
									publicId: '" . $method->public_id . "',  //id из личного кабинета
									description: \"" . $sDescription . "\", //назначение
									amount: " . $totalInPaymentCurrency . ", //сумма
									currency: '" . $sCurrencyName . "', //валюта
									invoiceId: '" . $oOrder['details']['BT']->order_number . "', //номер заказа  (необязательно)
									accountId: " . $oOrder['details']['BT']->virtuemart_user_id . ", //идентификатор плательщика (необязательно)
									email: '". $oOrder['details']['BT']->email ."',
									data: {
										order_number: '" . $oOrder['details']['BT']->order_number . "', //произвольный набор параметров
										cloudPayments: ". ($method->send_check ? $customerReceipt : "") ."
									}
								},
								function (options) { // success
									oButWrap.innerHTML = '(Оплачено)';
								},
								function (reason, options) { // fail
									// location.reload();
								});
						};
				</script>";
			} else {
				if (!class_exists('shopFunctionsF')) {
					require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
				}
				$html = '<div>('.shopFunctionsF::getOrderStatusName($oOrder['details']['BT']->order_status).')</div>';
			}
			
			$payment_name .= $html;
		}
		return $result;
		
	}
	
	/**
	 * Срабатывает до показа корзины
	 * plgVmDisplayListFEPayment
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
	 *
	 * @param object $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on succes, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
	 *
	 */
	public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
	{
		$method = $this->methods[0];
		$methodId = $method->virtuemart_paymentmethod_id;
		$name = $method->payment_name;
		
		if ($selected == $methodId) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		
		$html = '<input type="radio" name="' . $this->_idName . '" data-dynamic-update="1" id="' . $this->_psType . '_id_' . $methodId . '"   value="' . $methodId . '" ' . $checked . ">\n"
			. '<label for="' . $this->_psType . '_id_' . $methodId . '">' . '<span class="' . $this->_type . '">' . $name . "</span></label>\n";
		
		$htmlIn [] = [$html];
		
		return true;
	}
	
	/**
	 * Срабатывает после оформления заказа /  При обнослении статуса заказа в админке
	 * Срабатывает при сохранении статуса заказ в админке
	 * Save updated order data to the method specific table
	 *
	 * @param array $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.
	 * @author Oscar van Eijk
	 */
	public function plgVmOnUpdateOrderPayment(&$_formData)
	{
		if (isset($_GET['task']) && $_GET['task'] == 'pluginresponsereceived')  return true; // если вызывается вебхук, то ничего не делаем, иначе не изменится статус заказа
		/**
		 * Отправляем CloudPayments сообщение:
		 *  - заказ подтвержден / отменен
		 * Дальше будет вызван вебхук confirm и изменится статус заказа на status_cp_confirmed
		 */
		$method = $this->getVmPluginMethod($_formData->virtuemart_paymentmethod_id);
		$oOrderModel = VmModel::getModel('orders');
		$iOrderId = $oOrderModel->getOrderIdByOrderNumber($_formData->order_number);
		$oOrder = $oOrderModel->getOrder($iOrderId);
		if  ($method->payment_element == 'cloudpayments') {
			if ($oOrder['details']['BT']->order_status == 'A' && $_formData->order_status == $method->status_cp_confirmed) {
				// не даем возможности через админку поменять статус заказа с status_cp_authorized (DMS) на status_cp_confirmed (DMS)
				$_formData->order_status = $oOrder['details']['BT']->order_status;
				$oPlgCloudPayments = $this->getDataByOrderId($_formData->virtuemart_order_id);
				if (!$oPlgCloudPayments) {
					vmError('CloudPayments error: TransactionId not found');
					return false;
				}
				$this->makeRequest($method, 'payments/confirm', [
					'TransactionId' => $oPlgCloudPayments->transaction_id,
					'Amount' => $_formData->order_total
				]);
				
				vmInfo(vmText::_('COM_VIRTUEMART_PLUGIN_CLOUDPAYMENTS_SENDED_STATUS_CP_CONFIRMED'));
			} elseif ($_formData->order_status == $method->status_refund) {
				// не даем возможности через админку поменять заказ на status_cp_refund (возврат)
				$_formData->order_status = $oOrder['details']['BT']->order_status;
				$oPlgCloudPayments = $this->getDataByOrderId($_formData->virtuemart_order_id);
				if (!$oPlgCloudPayments) {
					vmError('CloudPayments error: TransactionId not found');
					return false;
				}
				$this->makeRequest($method, 'payments/refund', [
					'TransactionId' => $oPlgCloudPayments->transaction_id,
					'Amount' => $_formData->order_total
				]);
				
				vmInfo(vmText::_('COM_VIRTUEMART_PLUGIN_CLOUDPAYMENTS_SENDED_STATUS_CP_REFUND'));
			} elseif ($_formData->order_status == $method->status_canceled) {
				$oPlgCloudPayments = $this->getDataByOrderId($_formData->virtuemart_order_id);
				if ($oPlgCloudPayments) {
					$this->makeRequest($method, 'payments/void', [
						'TransactionId' => $oPlgCloudPayments->transaction_id
					]);
					vmInfo(vmText::_('COM_VIRTUEMART_PLUGIN_CLOUDPAYMENTS_SENDED_STATUS_CANCELED'));
				}
			}
		}
		// 	return
	}
	
	/**
	 * Метод для отправки запросов системе
	 * @param string $location
	 * @param array  $request
	 * @return bool|array
	 */
	private function makeRequest($method, $location, $request = array()) {
		if (!$this->curl) {
			$auth       = $method->public_id . ':' . $method->api_password;
			$this->curl = curl_init();
			curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, 30);
			curl_setopt($this->curl, CURLOPT_TIMEOUT, 30);
			curl_setopt($this->curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
			curl_setopt($this->curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			curl_setopt($this->curl, CURLOPT_USERPWD, $auth);
		}
		
		curl_setopt($this->curl, CURLOPT_URL, 'https://api.cloudpayments.ru/' . $location);
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, array(
			"content-type: application/json"
		));
		curl_setopt($this->curl, CURLOPT_POST, true);
		curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode($request));
		
		$response = curl_exec($this->curl);
		if ($response === false || curl_getinfo($this->curl, CURLINFO_HTTP_CODE) != 200) {
			vmDebug('CloudPayments Failed API request' .
				' Location: ' . $location .
				' Request: ' . print_r($request, true) .
				' HTTP Code: ' . curl_getinfo($this->curl, CURLINFO_HTTP_CODE) .
				' Error: ' . curl_error($this->curl)
			);
			
			return false;
		}
		$response = json_decode($response, true);
		if (!isset($response['Success']) || !$response['Success']) {
			vmError('CloudPayments error: '.$response['Message']);
			vmDebug('CloudPayments Failed API request' .
				' Location: ' . $location .
				' Request: ' . print_r($request, true) .
				' HTTP Code: ' . curl_getinfo($this->curl, CURLINFO_HTTP_CODE) .
				' Error: ' . curl_error($this->curl)
			);
			
			return false;
		}
		
		return $response;
	}
	
	/**
	 * Срабатывает при вызове в админке
	 * @param $data
	 * @return bool
	 */
	public function plgVmDeclarePluginParamsPaymentVM3(&$data)
	{
		return $this->declarePluginParams('payment', $data);
	}
	
	/**
	 * Fields to create the payment table
	 * @return string SQL Fileds
	 */
	function getTableSQLFields()
	{
		$SQLfields = array(
			'id' => 'int(11) unsigned NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id' => 'int(11) UNSIGNED',
			'order_number' => 'char(32)',
			'virtuemart_paymentmethod_id' => 'mediumint(1)',
			'payment_name' => 'varchar(5000)',
			'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
			'payment_currency' => 'char(3) ',
			'cost_per_transaction' => ' decimal(10,2)',
			'cost_percent_total' => ' decimal(10,2)',
			'tax_id' => 'smallint(11)',
			'transaction_id' => 'int(11) UNSIGNED',
		);
		
		return $SQLfields;
	}
	
	/**
	 * Срабатывает до показа корзины
	 * @param VirtueMartCart $cart
	 * @param int $method
	 * @param array $cart_prices
	 *
	 * @return bool
	 *
	 * @since version
	 */
	protected function checkConditions($cart, $method, $cart_prices)
	{
		return true;
	}
	
	/**
	 * Срабатывает при клике по платежной системе в коризине
	 * This event is fired after the payment method has been selected. It can be used to store
	 * additional payment info in the cart.
	 *
	 * @author Max Milbers
	 * @author Valérie isaksen
	 *
	 * @param VirtueMartCart $cart : the actual cart
	 * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
	 *
	 */
	public function plgVmOnSelectCheckPayment(VirtueMartCart $cart)
	{
		return $this->OnSelectCheck($cart);
	}
	
	/**
	 * Срабатывает до показа корзины
	 * plgVmonSelectedCalculatePricePayment
	 * Calculate the price (value, tax_id) of the selected method
	 * It is called by the calculator
	 * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
	 * @author Valerie Isaksen
	 * @cart: VirtueMartCart the current cart
	 * @cart_prices: array the new cart prices
	 * @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
	 *
	 *
	 */
	public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
	{
		return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
	}
	
	/**
	 * Срабатывает когда заказ оформлен
	 * @param $virtuemart_paymentmethod_id
	 * @param $paymentCurrencyId
	 * @return bool|null
	 */
	function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
	{
		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		$this->getPaymentCurrency($method);
		
		$paymentCurrencyId = $method->payment_currency;
	}
	
	/**
	 * Срабатывает во время оформления заказа. Заказа в базе еще нет
	 * plgVmOnCheckAutomaticSelectedPayment
	 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	 * The plugin must check first if it is the correct type
	 * @author Valerie Isaksen
	 * @param VirtueMartCart cart: the cart object
	 * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
	 *
	 */
	function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array())
	{
		return $this->onCheckAutomaticSelected($cart, $cart_prices);
	}
	
	/**
	 * Срабатывает до показа корзины
	 * Не подходит для проверки, т.к. пользователь должен быть авторизован
	 * This event is fired during the checkout process. It can be used to validate the
	 * method data as entered by the user.
	 *
	 * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
	 * @author Max Milbers
	 */
	public function plgVmOnCheckoutCheckDataPayment(VirtueMartCart $cart)
	{
		return null;
	}
	
	
	/**
	 * This method is fired when showing when priting an Order
	 * It displays the the payment method-specific data.
	 *
	 * @param integer $_virtuemart_order_id The order ID
	 * @param integer $method_id method used for this order
	 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
	 * @author Valerie Isaksen
	 */
	function plgVmonShowOrderPrintPayment($order_number, $method_id)
	{
		return $this->onShowOrderPrint($order_number, $method_id);
	}
	
	function plgVmDeclarePluginParamsPayment($name, $id, &$data)
	{
		return $this->declarePluginParams('payment', $name, $id, $data);
	}
	
	/**
	 * Срабатывает при сохранении настроек в админке
	 * @param $name
	 * @param $id
	 * @param $table
	 *
	 * @return bool
	 *
	 * @since version
	 */
	function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
	{
		return $this->setOnTablePluginParams($name, $id, $table);
	}
	
	//Notice: We only need to add the events, which should work for the specific plugin, when an event is doing nothing, it should not be added

	
	/**
	 * Save updated orderline data to the method specific table
	 *
	 * @param array $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.
	 * @author Oscar van Eijk
	 */
	public function plgVmOnUpdateOrderLine($_formData)
	{
		return null;
	}
	
	/**
	 * Данного метода нет PayPal
	 * plgVmOnEditOrderLineBE
	 * This method is fired when editing the order line details in the backend.
	 * It can be used to add line specific package codes
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise
	 * @author Oscar van Eijk
	 */
	public function plgVmOnEditOrderLineBEPayment($_orderId, $_lineId)
	{
		return null;
	}
	
	/**
	 * Данного метода нет PayPal
	 * This method is fired when showing the order details in the frontend, for every orderline.
	 * It can be used to display line specific package codes, e.g. with a link to external tracking and
	 * tracing systems
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise
	 * @author Oscar van Eijk
	 */
	public function plgVmOnShowOrderLineFE($_orderId, $_lineId)
	{
		return null;
	}
	
	/**
	 * This event is fired when the  method notifies you when an event occurs that affects the order.
	 * Typically,  the events  represents for payment authorizations, Fraud Management Filter actions and other actions,
	 * such as refunds, disputes, and chargebacks.
	 *
	 * NOTE for Plugin developers:
	 *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
	 *
	 * @param $return_context : it was given and sent in the payment form. The notification should return it back.
	 * Used to know which cart should be emptied, in case it is still in the session.
	 * @param int $virtuemart_order_id : payment  order id
	 * @param char $new_status : new_status for this order id.
	 * @return mixed Null when this method was not selected, otherwise the true or false
	 *
	 * @author Valerie Isaksen
	 *
	 **/
	
	/**
	 * @return bool|null
	 */
	public function plgVmOnPaymentNotification()
	{
		return null;
	}
	
	function plgVmOnUserPaymentCancel()
	{
		return $this->plgVmOnUserPaymentCancel();

	}
}