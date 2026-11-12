# API и события

## Сервис FtbDiscountService

**Класс:** `Ms3FirstTimeBuyerDiscount\Services\FtbDiscountService`
**Ключ в контейнере:** `ms3ftb_discount`

### Конструктор

```php
public function __construct(modX $modx)
```

### Публичные методы

#### isEligible

Проверяет право на скидку: настройка включена, 0 оплаченных заказов. Для авторизованных — по `userId`; для гостей — по email/телефону из `draft` (Address).

```php
public function isEligible(int $userId, ?object $draft = null): bool
```

| Параметр | Тип | Описание |
|----------|-----|----------|
| `userId` | int | ID пользователя |
| `draft`  | object\|null | Объект черновика заказа (опционально), для расширений |

**Возврат:** `true` если скидку можно применить, иначе `false`.

---

#### getPaidOrdersCount

Количество оплаченных заказов пользователя (статусы из `ms3_status_for_stat`). Второй параметр исключает заказ из подсчёта (например, текущий черновик).

```php
public function getPaidOrdersCount(int $userId, int $excludeOrderId = 0): int
```

| Параметр | Тип | Описание |
|----------|-----|----------|
| `userId` | int | ID пользователя |
| `excludeOrderId` | int | ID заказа, не учитывать в подсчёте (0 — не исключать) |

**Возврат:** число заказов (0 и больше).

---

#### calculateDiscount

Вычисляет стоимость после скидки (процент или фиксированная сумма).

```php
public function calculateDiscount(float $cost, string $type, float $value): float
```

| Параметр | Тип | Описание |
|----------|-----|----------|
| `cost`   | float | Исходная сумма |
| `type`   | string | `percent` или `fixed` |
| `value`  | float | Процент (0–100) или фиксированная сумма |

**Возврат:** новая сумма (для percent — не меньше 0, для fixed — `max(0, cost - value)`).

---

#### apply

Полный цикл: проверка права → ftbOnBeforeApply → расчёт → ftbOnApply. Используется плагином из `msOnGetCartCost`.

```php
public function apply(array $scriptProperties): ?float
```

**Вход (scriptProperties):** массив из события `msOnGetCartCost`:

| Ключ   | Тип    | Описание |
|--------|--------|----------|
| `cost` | float  | Текущая стоимость корзины |
| `cart` | mixed  | Данные корзины (зависит от MS3) |
| `draft`| object\|null | Черновик заказа (если есть) |

**Возврат:** новая стоимость (float) при успешном применении скидки, иначе `null`.

---

## События MODX

### ftbOnBeforeApply

Вызывается до расчёта скидки. Плагины могут отменить применение или подменить базовую сумму.

**Параметры:**

| Ключ       | Тип    | Описание |
|------------|--------|----------|
| `user_id`  | int    | ID пользователя |
| `cost`     | float  | Текущая стоимость |
| `draft`    | object\|null | Черновик заказа |
| `cart`     | mixed  | Корзина |
| `settings` | array  | Текущие настройки (ftb_enabled, ftb_discount_type, ftb_discount_value) |

**Возвращаемые значения (returnedValues):**

| Ключ   | Тип   | Описание |
|--------|-------|----------|
| `apply`| bool  | `false` — отменить применение скидки |
| `cost` | float | Подменить сумму, к которой применяется скидка |

Если `apply === false`, сервис возвращает `null`. Если задан `cost`, он используется вместо исходного `cost` для расчёта.

---

### ftbOnApply

Уведомление после успешного применения скидки (для логирования, аналитики).

**Параметры:**

| Ключ             | Тип    | Описание |
|------------------|--------|----------|
| `user_id`        | int    | ID пользователя |
| `cost_before`    | float  | Стоимость до скидки |
| `cost_after`     | float  | Стоимость после скидки |
| `discount_amount`| float  | Размер скидки |
| `draft`          | object\|null | Черновик заказа |

---

## Системные настройки

Префикс в конфиге: `ms3firsttimebuyerdiscount_` (например, `ms3firsttimebuyerdiscount_ftb_enabled`).

| Ключ | xtype | По умолчанию | Описание |
|------|--------|--------------|----------|
| `ftb_enabled` | combo-boolean | true | Включить скидку для первых покупок |
| `ftb_discount_type` | textfield | percent | Тип: `percent` (процент) или `fixed` (фиксированная сумма). Ввод вручную; сравнение без учёта регистра. |
| `ftb_discount_value` | number | 10 | Значение: процент (0–100) или сумма в валюте |
| `ftb_allow_combination` | combo-boolean | true | Разрешить совмещение с другими скидками корзины (если false и в корзине уже есть скидка, FTB не применяется) |

**В коде:** `$modx->getOption('ms3firsttimebuyerdiscount_ftb_enabled', null, true)`.
