# CloudPayments модуль для Joomla - VirtueMart
###Совместимость:
VirtueMart >= 3.2.12 
Joomla >= 3.8.4

###Возможности:
• Одностадийная схема оплаты;
• Двухстадийная схема оплаты;
• Отмена, подтверждение и возврат платежей из ЛК CMS;
• Поддержка онлайн-касс (ФЗ-54);
• Отправка чеков по email;
• Отправка чеков по SMS;

### Установка через панель управления

В панели адмниистратора зайти в раздел "Расширения/Менеджер расширений/Установка"

![1](https://github.com/cloudpayments/CMS-Joomla-VirtueMart-CP/blob/master/Images/1.PNG) и загрузить архив.


### Ручная установка

Распаковать из архива каталог cloudpayments и загрузить в папку plugins/vmpayment
![2](https://github.com/cloudpayments/CMS-Joomla-VirtueMart-CP/blob/master/Images/2.PNG).

### Настройка модуля

1. Перейти в настройки модуля "Система/Панель управления" -> "Менеджер языков" -> "Переопределение констант" 
![3](https://github.com/cloudpayments/CMS-Joomla-VirtueMart-CP/blob/master/Images/3.PNG).


2. Переопределить значение константы COM_VIRTUEMART_ORDER_STATUS_CP_AUTHORIZED для используемого языка панели управления на "CP-Платёж авторизован (Деньги заблокированы)"
![4](https://github.com/cloudpayments/CMS-Joomla-VirtueMart-CP/blob/master/Images/4.PNG)


3. Перейти в раздел "VirtueMart/Payment Methods", создать способ оплаты "CloudPayments" и указать все необходимые настройки:
![5](https://github.com/cloudpayments/CMS-Joomla-VirtueMart-CP/blob/master/Images/5.PNG)
![6](https://github.com/cloudpayments/CMS-Joomla-VirtueMart-CP/blob/master/Images/6.PNG)
![7](https://github.com/cloudpayments/CMS-Joomla-VirtueMart-CP/blob/master/Images/7.PNG)


### Настройка вебхуков:

Так как  настройка ЧПУ(Семантический URL) может быть отличной от параметра по умолчаню,
 то для корректной настройки вебхуков лучше всего будет использовать след. способ:

Копируем линк ниже для соответсвующего вебхука:

* (Check) 		index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&cloudpayments_check
* (Pay) 		index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&cloudpayments_pay
* (Confirm)		index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&cloudpayments_confirm
* (Refund)		index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&cloudpayments_refund

Открываем свой сайт, например,  http://**yourdomain.name** или https://**yourdomain.name**, где **yourdomain.name** - доменное имя сайта.

В итоге, должно получится что-то вроде:
http://**yourdomain.name**/index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&cloudpayments_check

Открыв его, вы увидите ответ:
![8](https://github.com/cloudpayments/CMS-Joomla-VirtueMart-CP/blob/master/Images/8.PNG)
Если все верно, то настройки ЧПУ преобразуют ссылку,
например, в http://**yourdomain.name**/ru/?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&cloudpayments_check

Таким образом с полученными ссылками настройте вебхуки в ЛК CloudPayments

![9](https://github.com/cloudpayments/CMS-Joomla-VirtueMart-CP/blob/master/Images/9.PNG)


Внимание!!! Убедитесь, что используемые валюты в глобальных настройках VirtueMart'а,
 а именно их трехбуквенные наименования совпадают с  параметрами, поддерживающимися сервисом отправки чеков.
https://cloudpayments.ru/Docs/Directory#currencies
Так же будьте внимательны с настройками НДС.