# Потоки (flows)

## Общий поток: расчёт стоимости корзины MiniShop3

MiniShop3 вызывает событие `msOnGetCartCost` с текущей стоимостью; плагины могут изменить её через `returnedValues['cost']`.

```mermaid
sequenceDiagram
    participant MS3 as MiniShop3
    participant Modx as MODX Events
    participant Ftb as Плагин ftb_discount
    participant Svc as FtbDiscountService

    MS3->>Modx: invokeEvent(msOnGetCartCost, scriptProperties)
    Modx->>Ftb: плагин вызывается
    Ftb->>Ftb: require bootstrap
    Ftb->>Svc: services->get('ms3ftb_discount')
    Ftb->>Svc: apply(scriptProperties)
    alt eligible, discount > 0
        Svc-->>Ftb: newCost (float)
        Ftb->>Modx: event->returnedValues['cost'] = newCost
        Ftb->>Modx: log INFO
    else not eligible или discount <= 0
        Svc-->>Ftb: null
        Ftb->>Ftb: return (не трогаем cost)
    end
    Modx-->>MS3: scriptProperties + returnedValues
    MS3->>MS3: использует cost из returnedValues если задан
```

## Поток принятия решения о скидке (FtbDiscountService::apply)

Внутри `apply()` выполняются проверка прав, событие «до», расчёт и событие «после».

```mermaid
sequenceDiagram
    participant Caller
    participant Apply as apply()
    participant IsEligible
    participant Before as ftbOnBeforeApply
    participant Calc as calculateDiscount
    participant After as ftbOnApply

    Caller->>Apply: apply(scriptProperties)
    Apply->>Apply: resolveUserId(draft | user)
    Apply->>IsEligible: isEligible(userId, draft)
    alt !isEligible
        IsEligible-->>Apply: false
        Apply-->>Caller: null
    end
    IsEligible-->>Apply: true
    Apply->>Apply: getSettings()
    Apply->>Before: invokeEvent(ftbOnBeforeApply, params)
    Note over Before: плагины могут вернуть apply=false или cost
    alt returnedValues['apply'] === false
        Apply-->>Caller: null
    end
    Apply->>Apply: cost = returnedValues['cost'] ?? cost
    Apply->>Calc: calculateDiscount(cost, type, value)
    Calc-->>Apply: newCost
    Apply->>Apply: discountAmount = cost - newCost
    alt discountAmount <= 0
        Apply-->>Caller: null
    end
    Apply->>After: invokeEvent(ftbOnApply, ...)
    Apply-->>Caller: newCost
```

## Проверка права на скидку (isEligible)

Для авторизованных (`userId > 0`) считается число оплаченных заказов по `user_id`. Для гостей (`userId === 0`) — по email/телефону из `draft` (Address), если передан.

```mermaid
flowchart TD
    Start([isEligible userId, draft])
    CheckUser{userId > 0?}
    CheckEnabled{ftb_enabled?}
    Count{getPaidOrdersCount === 0?}
    Yes([true])
    No([false])
    Start --> CheckUser
    CheckUser -->|нет| No
    CheckUser -->|да| CheckEnabled
    CheckEnabled -->|нет| No
    CheckEnabled -->|да| Count
    Count -->|да| Yes
    Count -->|нет| No
```

## Расчёт скидки (calculateDiscount)

- **percent**: `cost * (1 - value/100)`, value ограничен 0–100.
- **fixed**: `max(0, cost - value)`.

```mermaid
flowchart LR
    Input[cost, type, value]
    Input --> Zero{cost <= 0?}
    Zero -->|да| OutZero[return 0]
    Zero -->|нет| Type{type}
    Type -->|fixed| Fixed["max(0, cost - value)"]
    Type -->|percent| Percent["cost * (1 - clamp(value)/100)"]
    Fixed --> Out[return newCost]
    Percent --> Out
```
