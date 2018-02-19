# CloudPayments модуль для VirtueMart на Joomla

### Установка через панель управления

В панели адмниистратора зайти в раздел !["Расширения/Менеджер расширений/Установка"] (https://github.com/cloudpayments/CMS-Joomla-VirtueMart-CP/blob/master/Images/1.PNG) и загрузить архив.


### Ручная установка

Распаковать из архива каталог cloudpayments и загрузить в папку ![plugins/vmpayment]
(https://github.com/cloudpayments/CMS-Joomla-VirtueMart-CP/blob/master/Images/2.PNG).

### Настройка модуля

1. Перейти в настройки модуля "Система/Панель управления" -> "Менеджер языков" -> !["Переопределение констант"](https://github.com/cloudpayments/CMS-Joomla-VirtueMart-CP/blob/master/Images/3.PNG).


2. Переопределить значение константы COM_VIRTUEMART_ORDER_STATUS_CP_AUTHORIZED для используемого языка панели управления на !["CP-Платёж авторизован (Деньги заблокированы)"]
(https://github.com/cloudpayments/CMS-Joomla-VirtueMart-CP/blob/master/Images/4.PNG)


3. Перейти в раздел "VirtueMart/Payment Methods", создать способ оплаты "CloudPayments" и указать все необходимые настройки:
(https://github.com/cloudpayments/CMS-Joomla-VirtueMart-CP/blob/master/Images/5.PNG)
(https://github.com/cloudpayments/CMS-Joomla-VirtueMart-CP/blob/master/Images/6.PNG)
(https://github.com/cloudpayments/CMS-Joomla-VirtueMart-CP/blob/master/Images/7.PNG)


### Настройка вебхуков
Для корректной настройки вебхуков откройте ссылку по адресу
http://yoursite.ru/index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&cloudpayments_check

В некоторых случаях, в частности использовании нескольких языков для посетителей сайта, ссылка может видоизмениться. Например так.
http://yoursite.ru/ru/?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&cloudpayments_check

Данную ссылку и необходимо будет указывать в вебхуках.

Ниже представлены все ссылки для вебхуков:
* (Check) index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&cloudpayments_check
* (Pay) index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&cloudpayments_pay
* (Confirm) index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&cloudpayments_confirm
* (Refund) index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&cloudpayments_refund
