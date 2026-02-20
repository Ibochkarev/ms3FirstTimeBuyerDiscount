# Архитектура компонента ms3FirstTimeBuyerDiscount

## Обзор слоёв

Компонент состоит из трёх основных слоёв: точка входа (плагин), bootstrap/DI, бизнес-логика (сервис).

```mermaid
flowchart TB
    subgraph MS3["MiniShop3"]
        msOnGetCartCost["Событие msOnGetCartCost"]
    end
    subgraph Entry["Точка входа"]
        Plugin["Плагин ftb_discount"]
    end
    subgraph Bootstrap["Bootstrap / DI"]
        BootstrapFile["bootstrap.php"]
        ServiceReg["Регистрация сервиса ms3ftb_discount"]
    end
    subgraph Logic["Бизнес-логика"]
        FtbService["FtbDiscountService"]
    end
    msOnGetCartCost --> Plugin
    Plugin --> BootstrapFile
    BootstrapFile --> ServiceReg
    Plugin --> FtbService
```

## Структура компонента

```mermaid
flowchart LR
    subgraph Root["Корень пакета"]
        Build["_build/"]
        Core["core/"]
        Assets["assets/"]
    end
    subgraph CoreDetail["core/components/ms3firsttimebuyerdiscount"]
        Bootstrap["bootstrap.php"]
        Src["src/Services/"]
        Elements["elements/plugins/"]
        Lexicon["lexicon/"]
        DocsCore["docs/"]
    end
    Src --> FtbService["FtbDiscountService.php"]
    Elements --> FtbPlugin["ftb_discount.php"]
    Core --> CoreDetail
```

## Зависимости

- **MODX** (`modX`): конфиг, пользователь, события, лог, getOption, getCount.
- **MiniShop3**: модель `msOrder` (подсчёт заказов), настройка `ms3_status_for_stat`.
- **События MODX**: `msOnGetCartCost` (вход), `ftbOnBeforeApply`, `ftbOnApply` (внутренние).

```mermaid
flowchart LR
    subgraph Component["ms3FirstTimeBuyerDiscount"]
        Plugin["Плагин"]
        Service["FtbDiscountService"]
    end
    subgraph MODX["MODX"]
        ModX["modX"]
        Events["События"]
    end
    subgraph MS3["MiniShop3"]
        MsOrder["msOrder"]
        Ms3Settings["ms3_status_for_stat"]
    end
    Plugin --> ModX
    Plugin --> Service
    Service --> ModX
    Service --> MsOrder
    Service --> Ms3Settings
    Events --> Plugin
```

## Регистрация сервиса

Сервис регистрируется в контейнере MODX под ключом `ms3ftb_discount` и создаётся лениво (при первом обращении).

```mermaid
sequenceDiagram
    participant Plugin
    participant Modx
    participant Bootstrap
    participant Service
    Plugin->>Modx: services->get('ms3ftb_discount')
    Modx->>Modx: has('ms3ftb_discount')?
    alt нет
        Modx->>Bootstrap: (уже загружен при require bootstrap.php)
        Bootstrap->>Modx: services->add('ms3ftb_discount', factory)
    end
    Modx->>Service: factory() -> new FtbDiscountService(modx)
    Modx->>Plugin: return service
```

## Расширяемость

- **Сервис**: можно подменить реализацию через свой пакет/резолвер и регистрировать другой класс под ключом `ms3ftb_discount`.
- **События**: плагины на `ftbOnBeforeApply` могут отменить применение (`returnedValues['apply'] = false`) или подменить базовую сумму (`returnedValues['cost']`); `ftbOnApply` — уведомление после успешного применения.

См. [API и события](api.md) и [Расширение](extension.md).
