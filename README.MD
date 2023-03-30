# CloudPayments модуль для Joomla - VirtueMart
Модуль позволит с легкостью добавить на ваш сайт оплату банковскими картами через платежный сервис CloudPayments. 
Для корректной работы модуля необходима регистрация в сервисе.

Порядок регистрации описан в [документации CloudPayments](https://cloudpayments.ru/Docs/Connect)
### Совместимость:
VirtueMart >= 3.2.12   
Joomla >= 3.8.4

### Возможности:  
• Одностадийная схема оплаты;  
• Двухстадийная схема оплаты;  
• Отмена, подтверждение и возврат платежей из ЛК CMS;  
• Поддержка онлайн-касс (ФЗ-54);  
• Отправка чеков по email;  
• Отправка чеков по SMS;  

### Установка через панель управления

В панели администратора зайти в раздел "Расширения/Менеджер расширений/Установка"

![1](https://github.com/cloudpayments/CMS-Joomla-VirtueMart-CP/blob/master/Images/1.PNG) и загрузить архив.


### Ручная установка

1. Распаковать из архива каталог cloudpayments и загрузить в папку plugins/vmpayment
![2](https://github.com/cloudpayments/CMS-Joomla-VirtueMart-CP/blob/master/Images/2.PNG).

2. Зайти в раздел поиска, найти и установить модуль "Расширение/Менеджер расширений/Управление"  
![2-1](https://github.com/cloudpayments/CMS-Joomla-VirtueMart-CP/blob/master/Images/2-1.png).

3. Перейти в управление модулями, найти и включить способ оплаты "Расширение/Менеджер расширений/Найти"  
![2-2](https://github.com/cloudpayments/CMS-Joomla-VirtueMart-CP/blob/master/Images/2-2.png).

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

Так как настройка ЧПУ(Семантический URL) может быть отличной от параметра по умолчанию,
 то для корректной настройки вебхуков лучше всего будет использовать след. способ:

Копируем линк ниже для соответствующего вебхука:

* (Check) 		index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&cloudpayments_check
* (Pay) 		index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&cloudpayments_pay
* (Confirm)		index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&cloudpayments_confirm
* (Refund)		index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&cloudpayments_refund

Открываем свой сайт, например,  http://**yourdomain.name** или https://**yourdomain.name**, где **yourdomain.name** - доменное имя сайта.

В итоге должно получится что-то вроде:
http://**yourdomain.name**/index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&cloudpayments_check

Открыв его, вы увидите ответ:
![8](https://github.com/cloudpayments/CMS-Joomla-VirtueMart-CP/blob/master/Images/8.PNG)
Если все верно, то настройки ЧПУ преобразуют ссылку,
например, в http://**yourdomain.name**/ru/?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&cloudpayments_check

Преобразованная ссылка будет являться необходимым URL для check-уведомления в ЛК CloudPayments.
Аналогичным образом настройте остальные вебхуки.

![9](https://github.com/cloudpayments/CMS-Joomla-VirtueMart-CP/blob/master/Images/9.PNG)


Внимание!!! Убедитесь, что используемые валюты в глобальных настройках VirtueMart'а,
 а именно их трехбуквенные наименования совпадают с параметрами, поддерживающимися сервисом отправки чеков.
https://cloudpayments.ru/Docs/Directory#currencies  
Так же будьте внимательны с настройками НДС.
