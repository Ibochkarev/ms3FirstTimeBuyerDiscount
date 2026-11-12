<strong>First-Time Buyer Discount для miniShop3</strong>

Автоматическая скидка на первый заказ — без купонов и ручных действий. Повышает конверсию новых клиентов.

<strong>Возможности</strong>

<strong>Скидка и настройки</strong>
<ul>
    <li>Скидка применяется только при 0 оплаченных заказов (статусы из <code>ms3_status_for_stat</code> MiniShop3)</li>
    <li>Тип скидки: процент (<code>percent</code>) или фиксированная сумма (<code>fixed</code>) — настройка в панели</li>
    <li>Включение/выключение одним флажком (<code>ftb_enabled</code>) — без правки кода</li>
    <li>Опция совмещения с другими скидками корзины (<code>ftb_allow_combination</code>)</li>
</ul>

<strong>Плашка на странице оформления</strong>
<ul>
    <li>Сниппет <code>[[!ms3ftbDiscountBanner]]</code> — стиль «чек», иконка ценника, градиентный акцент</li>
    <li>Отображает заголовок, сумму экономии и подсказку <em>«Заполните email или телефон — скидка применится автоматически»</em></li>
    <li>Для гостей: при изменении email/телефона проверка через <code>eligible.php</code> — плашка скрывается, если по контакту уже есть заказы</li>
    <li>Лексикон для перевода заголовка, подписи и подсказки</li>
</ul>

<strong>Расширение</strong>
<ul>
    <li>Событие <strong>ftbOnBeforeApply</strong> — отменить скидку (<code>returnedValues['apply'] = false</code>) или подменить базовую сумму (<code>returnedValues['cost']</code>) по условию (доставка, оплата, группа)</li>
    <li>Событие <strong>ftbOnApply</strong> — логирование, отправка в CRM/аналитику (<code>user_id</code>, <code>discount_amount</code>, <code>cost_before</code>, <code>cost_after</code>)</li>
    <li>Подмена сервиса в DI (<code>ms3ftb_discount</code>) — своя логика <code>isEligible</code>, <code>calculateDiscount</code>, <code>getPaidOrdersCount</code></li>
</ul>

<strong>Как это работает</strong>

MiniShop3 при расчёте стоимости корзины вызывает событие <code>msOnGetCartCost</code>. Плагин компонента получает текущую сумму, проверяет: скидка включена и у пользователя 0 оплаченных заказов (по истории MiniShop3). Если да — пересчитывает стоимость с учётом процента или фиксированной скидки и возвращает новую сумму в MiniShop3. Итог в корзине и на оформлении обновляется автоматически; шаблоны менять не нужно.

<strong>Установка</strong>
<ol>
    <li>Установите пакет через Extras → Installer</li>
    <li>Включите плагин <strong>ms3FirstTimeBuyerDiscount</strong> и привяжите к событию <strong>msOnGetCartCost</strong></li>
    <li>В Настройках (область <code>ms3firsttimebuyerdiscount</code>) задайте: <code>ftb_enabled</code> = да, <code>ftb_discount_type</code> = percent или fixed, <code>ftb_discount_value</code> = 10 или 500</li>
    <li>Опционально: в шаблоне страницы оформления добавьте <code>[[!ms3ftbDiscountBanner]]</code> для плашки</li>
</ol>

<strong>Требования</strong>
<ul>
    <li>MODX Revolution >= 3.0.0</li>
    <li>PHP >= 8.1.0</li>
    <li>MiniShop3 (события корзины, модель <code>msOrder</code>, настройка <code>ms3_status_for_stat</code>)</li>
</ul>
