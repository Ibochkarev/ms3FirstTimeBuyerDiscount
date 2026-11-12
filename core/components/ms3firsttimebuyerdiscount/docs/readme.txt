First-Time Buyer Discount для MiniShop3

Автоматическая скидка на первый заказ: при 0 оплаченных заказов к стоимости корзины применяется скидка (процент или фиксированная сумма). Учёт по user_id или по email/телефону для гостей.

Requirements:
- MODX >= 3.0.0
- PHP >= 8.1.0
- MiniShop3 (события корзины, модель msOrder, настройка ms3_status_for_stat)

Installation:
1. Установите пакет через Менеджер пакетов (Extras → Installer).
2. Убедитесь, что MiniShop3 установлен и настроен (корзина, заказы, статусы).
3. Включите плагин ms3FirstTimeBuyerDiscount и привяжите к событию msOnGetCartCost.
4. Настройте параметры в Настройках → ms3firsttimebuyerdiscount.

Features:
- Скидка на первый заказ — процент или фиксированная сумма, применяется при расчёте корзины (msOnGetCartCost)
- Учёт по user_id — для авторизованных; по email/телефону — для гостей (черновик заказа, Address)
- Плашка на странице заказа — сниппет [[!ms3ftbDiscountBanner]], стиль «чек», экономия и подсказка
- Скрытие плашки — при смене email/phone запрос к eligible.php; плашка скрывается, если по контакту есть оплаченные заказы
- События для расширения — ftbOnBeforeApply (отмена/подмена суммы), ftbOnApply (логирование, аналитика)
- Подмена сервиса — регистрация своего класса под ключом ms3ftb_discount до первого вызова

Системные настройки (namespace: ms3firsttimebuyerdiscount)
- ms3firsttimebuyerdiscount_ftb_enabled — включить скидку (да/нет)
- ms3firsttimebuyerdiscount_ftb_discount_type — percent или fixed
- ms3firsttimebuyerdiscount_ftb_discount_value — значение (процент или сумма в валюте)
- ms3firsttimebuyerdiscount_ftb_allow_combination — разрешить совмещение с другими скидками корзины

Плашка на странице заказа
В шаблоне страницы оформления заказа добавьте [[!ms3ftbDiscountBanner]]. Плашка выводится при загрузке, если пользователь подходит под условия; при изменении email/phone скрывается по ответу eligible.php. Стили в ftb-banner.css подключаются плагином. Тексты: ftb_discount_banner_title, ftb_discount_banner_savings_label, ftb_discount_banner_hint.
