# Расширение для разработчиков

## Подмена сервиса

Сервис регистрируется в `bootstrap.php` под ключом `ms3ftb_discount`. Чтобы подменить реализацию:

1. Подключить свой код до вызова плагина (например, в отдельном плагине с более ранним приоритетом или в резолвере пакета).
2. Зарегистрировать свою фабрику:

```php
$modx->services->add('ms3ftb_discount', function () use ($modx) {
    return new \YourNamespace\YourFtbService($modx);
});
```

Реализация должна предоставлять минимум метод `apply(array $scriptProperties): ?float`; остальные публичные методы — по необходимости при вызове из своего кода.

## Наследование FtbDiscountService

Сервис спроектирован так, чтобы ключевую логику можно было переопределить в наследнике:

- **isEligible(int $userId, ?object $draft): bool** — изменить условия права на скидку (например, по группе пользователя или по полю черновика).
- **getPaidOrdersCount(int $userId, int $excludeOrderId = 0): int** — изменить способ подсчёта оплаченных заказов; второй параметр исключает заказ из подсчёта (например, текущий черновик).
- **calculateDiscount(float $cost, string $type, float $value): float** — изменить формулу (например, минимум суммы заказа).

Пример: скидка только для группы «Партнёр»:

```php
namespace YourNamespace\Services;

use Ms3FirstTimeBuyerDiscount\Services\FtbDiscountService;

class PartnerFtbService extends FtbDiscountService
{
    public function isEligible(int $userId, ?object $draft = null): bool
    {
        if (!parent::isEligible($userId, $draft)) {
            return false;
        }
        $user = $this->modx->getObject(\MODX\Revolution\modUser::class, $userId);
        if (!$user) {
            return false;
        }
        return $user->isMember('Партнёр');
    }
}
```

Зарегистрируйте `PartnerFtbService` вместо стандартного сервиса (см. выше).

## Плагины на ftbOnBeforeApply

Отмена применения скидки (например, для определённого способа доставки):

```php
if (($modx->event->name ?? '') !== 'ftbOnBeforeApply') {
    return;
}
$draft = $modx->event->params['draft'] ?? null;
if ($draft && is_object($draft) && $draft->get('delivery_id') == 5) {
    $modx->event->returnedValues['apply'] = false;
}
```

Подмена базовой суммы (к которой применяется скидка):

```php
if (($modx->event->name ?? '') !== 'ftbOnBeforeApply') {
    return;
}
$cost = (float) ($modx->event->params['cost'] ?? 0);
$modx->event->returnedValues['cost'] = $cost * 0.95; // 5% базовая скидка, затем FTB
```

## Плагины на ftbOnApply

Логирование или отправка в аналитику:

```php
if (($modx->event->name ?? '') !== 'ftbOnApply') {
    return;
}
$params = $modx->event->params;
$modx->log(modX::LOG_LEVEL_INFO, sprintf(
    'FTB applied: user=%s, discount=%s',
    $params['user_id'],
    $params['discount_amount']
), '', 'ftb');
// или отправить в CRM/аналитику по $params['user_id'], $params['discount_amount']
```

## Константы и настройки

В сервисе используются внутренние константы типов скидки: `percent`, `fixed`. Ключи настроек: `ftb_enabled`, `ftb_discount_type`, `ftb_discount_value`. Полный список и типы см. в [api.md](api.md#системные-настройки).

## Логирование

Плагин пишет в лог MODX при успешном применении скидки:

- **Категория:** `ftb`
- **Уровень:** INFO
- **Сообщение:** `[FTB] Applied: user_id=..., discount=..., cost_before=..., cost_after=...`

Ошибки (например, сервис недоступен) логируются с уровнем ERROR и той же категорией `ftb`.
