First-Time Buyer Discount для MiniShop3 (MODX Revolution 3.x)

Автоматическая скидка к первому заказу пользователя: при 0 оплаченных заказов к стоимости корзины применяется скидка (процент или фиксированная сумма). Оплаченные заказы определяются по настройке MiniShop3 ms3_status_for_stat.

Требования
- MODX Revolution 3.x
- PHP 8.1+
- MiniShop3

Установка
Установите пакет через Менеджер пакетов (Extras → Installer). Включите плагин ms3FirstTimeBuyerDiscount и настройте системные параметры в Настройках → ms3FirstTimeBuyerDiscount.

Системные настройки (namespace: ms3firsttimebuyerdiscount)
- ms3firsttimebuyerdiscount_ftb_enabled — включить скидку (да/нет)
- ms3firsttimebuyerdiscount_ftb_discount_type — тип: percent или fixed
- ms3firsttimebuyerdiscount_ftb_discount_value — значение (процент или сумма)
- ms3firsttimebuyerdiscount_ftb_allow_combination — разрешить комбинирование с другими скидками

Логика
Плагин подписан на событие MiniShop3 msOnGetCartCost. Если пользователь авторизован (user_id != 0), количество оплаченных заказов (по ms3_status_for_stat) равно 0 и скидка включена — к переданной стоимости корзины применяется скидка. Результат возвращается через returnedValues['cost']. Применение логируется в лог MODX (категория ftb).

Расширение (для разработчиков)

1) Подмена сервиса
Сервис регистрируется в контейнере MODX под именем ms3ftb_discount (класс Ms3FirstTimeBuyerDiscount\Services\FtbDiscountService). Чтобы подменить логику своей реализацией, зарегистрируйте свой класс до первого вызова (например, в плагине на событие OnMODXInit):

  $modx->services->add('ms3ftb_discount', function ($c) use ($modx) {
      return new \YourNamespace\YourFtbService($modx);
  });

Либо наследуйте FtbDiscountService и переопределите методы isEligible(), getPaidOrdersCount() или calculateDiscount().

2) События
- ftbOnBeforeApply — вызывается после проверки «подходит ли пользователь», до расчёта скидки.
  Параметры: user_id, cost, draft, cart, settings.
  Отмена: в плагине установите $modx->event->returnedValues['apply'] = false.
  Подмена суммы до скидки: $modx->event->returnedValues['cost'] = ваша_сумма.

- ftbOnApply — вызывается после успешного применения скидки.
  Параметры: user_id, cost_before, cost_after, discount_amount, draft.
  Используйте для логирования, аналитики или интеграций.

3) Пример плагина на ftbOnBeforeApply (отмена скидки для группы пользователей)
  case 'ftbOnBeforeApply':
      $userId = $scriptProperties['user_id'];
      $user = $modx->getObject(\MODX\Revolution\modUser::class, $userId);
      if ($user && $user->isMember('NoFtbGroup')) {
          $modx->event->returnedValues['apply'] = false;
      }
      break;

Контакты и ссылки
- Документация MiniShop3: https://docs.modx.pro/components/minishop3/
