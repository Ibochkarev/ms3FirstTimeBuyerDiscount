<?php

namespace Ms3FirstTimeBuyerDiscount\Services;

use MiniShop3\Model\msOrder;
use MODX\Revolution\modX;

/**
 * Сервис скидки для первого заказа (First-Time Buyer).
 * Расширяемый слой: переопределяйте isEligible, getPaidOrdersCount, calculateDiscount в наследнике.
 */
class FtbDiscountService
{
    private const DISCOUNT_TYPE_PERCENT = 'percent';
    private const DISCOUNT_TYPE_FIXED = 'fixed';
    private const SETTING_ENABLED = 'ftb_enabled';
    private const SETTING_DISCOUNT_TYPE = 'ftb_discount_type';
    private const SETTING_DISCOUNT_VALUE = 'ftb_discount_value';

    protected modX $modx;
    protected string $namespace = 'ms3firsttimebuyerdiscount';

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Можно ли применить скидку (включена настройка, пользователь задан, 0 оплаченных заказов).
     */
    public function isEligible(int $userId, ?object $draft = null): bool
    {
        if ($userId <= 0) {
            return false;
        }
        if (!$this->getSetting(self::SETTING_ENABLED, true)) {
            return false;
        }
        return $this->getPaidOrdersCount($userId) === 0;
    }

    /**
     * Количество оплаченных заказов пользователя (по ms3_status_for_stat).
     */
    public function getPaidOrdersCount(int $userId): int
    {
        $statusIds = $this->getPaidStatusIds();
        if ($statusIds === []) {
            return 0;
        }
        return (int) $this->modx->getCount(msOrder::class, [
            'user_id' => $userId,
            'status_id:IN' => $statusIds,
        ]);
    }

    /**
     * Расчёт новой стоимости после скидки (percent или fixed).
     */
    public function calculateDiscount(float $cost, string $type, float $value): float
    {
        if ($cost <= 0.0) {
            return 0.0;
        }
        if ($type === self::DISCOUNT_TYPE_FIXED) {
            return max(0.0, $cost - $value);
        }
        $percent = min(100.0, max(0.0, $value));
        return $cost * (1.0 - $percent / 100.0);
    }

    /**
     * Применить скидку: проверка eligible → ftbOnBeforeApply → расчёт → ftbOnApply. Возврат нового cost или null.
     */
    public function apply(array $scriptProperties): ?float
    {
        $userId = $this->resolveUserId($scriptProperties);
        if (!$this->isEligible($userId, $scriptProperties['draft'] ?? null)) {
            return null;
        }

        $cost = (float) ($scriptProperties['cost'] ?? 0);
        $settings = $this->getSettings();
        $costAfterBefore = $this->resolveCostAfterBeforeEvent($scriptProperties, $userId, $cost, $settings);
        if ($costAfterBefore === null) {
            return null;
        }
        $cost = $costAfterBefore;
        $newCost = $this->calculateDiscount($cost, $settings[self::SETTING_DISCOUNT_TYPE], $settings[self::SETTING_DISCOUNT_VALUE]);
        $discountAmount = $cost - $newCost;

        if ($discountAmount <= 0.0) {
            return null;
        }

        $this->invokeApplyEvent($userId, $cost, $newCost, $discountAmount, $scriptProperties['draft'] ?? null);
        return $newCost;
    }

    private function getSetting(string $name, mixed $default = null): mixed
    {
        return $this->modx->getOption($this->namespace . '_' . $name, null, $default);
    }

    /**
     * @return list<int>
     */
    private function getPaidStatusIds(): array
    {
        $raw = $this->modx->getOption('ms3_status_for_stat', null, '2,3');
        $ids = array_filter(array_map('intval', explode(',', $raw)));
        return array_values($ids);
    }

    private function getSettings(): array
    {
        return [
            self::SETTING_ENABLED => (bool) $this->getSetting(self::SETTING_ENABLED, true),
            self::SETTING_DISCOUNT_TYPE => (string) $this->getSetting(self::SETTING_DISCOUNT_TYPE, self::DISCOUNT_TYPE_PERCENT),
            self::SETTING_DISCOUNT_VALUE => (float) $this->getSetting(self::SETTING_DISCOUNT_VALUE, 10),
        ];
    }

    private function resolveUserId(array $scriptProperties): int
    {
        $draft = $scriptProperties['draft'] ?? null;
        if ($draft !== null && is_object($draft) && method_exists($draft, 'get')) {
            return (int) $draft->get('user_id');
        }
        return (int) $this->modx->user->get('id');
    }

    private function resolveCostAfterBeforeEvent(array $scriptProperties, int $userId, float $cost, array $settings): ?float
    {
        $eventParams = [
            'user_id' => $userId,
            'cost' => $cost,
            'draft' => $scriptProperties['draft'] ?? null,
            'cart' => $scriptProperties['cart'] ?? null,
            'settings' => $settings,
        ];
        $this->modx->invokeEvent('ftbOnBeforeApply', $eventParams);
        $returned = $this->modx->event->returnedValues ?? null;
        if (is_array($returned) && isset($returned['apply']) && $returned['apply'] === false) {
            return null;
        }
        if (is_array($returned) && isset($returned['cost']) && is_numeric($returned['cost'])) {
            return (float) $returned['cost'];
        }
        return $cost;
    }

    private function invokeApplyEvent(int $userId, float $costBefore, float $costAfter, float $discountAmount, ?object $draft): void
    {
        $this->modx->invokeEvent('ftbOnApply', [
            'user_id' => $userId,
            'cost_before' => $costBefore,
            'cost_after' => $costAfter,
            'discount_amount' => $discountAmount,
            'draft' => $draft,
        ]);
    }
}
