<?php

namespace Ms3FirstTimeBuyerDiscount\Services;

use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderAddress;
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
    private const SETTING_ALLOW_COMBINATION = 'ftb_allow_combination';

    protected modX $modx;
    protected string $namespace = 'ms3firsttimebuyerdiscount';

    /** @var array<int, int> кэш количества оплаченных заказов по user_id (в рамках одного запроса) */
    private array $paidOrdersCache = [];
    /** @var array<string, int> кэш количества оплаченных заказов по ключу "email|phone" для гостей */
    private array $paidOrdersByContactCache = [];
    /** @var array<string, mixed>|null кэш настроек на время одного apply() */
    private ?array $settingsCache = null;
    /** @var bool предупреждение о пустом ms3_status_for_stat выведено один раз за запрос */
    private bool $emptyStatusIdsWarned = false;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Можно ли применить скидку (включена настройка, 0 оплаченных заказов).
     * Для авторизованных — по user_id; для гостей (user_id = 0) — по email или телефону из draft.
     */
    public function isEligible(int $userId, ?object $draft = null): bool
    {
        $excludeOrderId = $this->resolveDraftOrderId($draft);
        if (!$this->getSetting(self::SETTING_ENABLED, true)) {
            return false;
        }
        if ($userId > 0) {
            return $this->getPaidOrdersCount($userId, $excludeOrderId) === 0;
        }
        $contact = $this->getGuestContactFromDraft($draft);
        if ($contact['email'] === '' && $contact['phone'] === '') {
            return false;
        }
        return $this->getPaidOrdersCountByContact($contact['email'], $contact['phone'], $excludeOrderId) === 0;
    }

    /**
     * Количество оплаченных заказов пользователя (по ms3_status_for_stat).
     */
    public function getPaidOrdersCount(int $userId, int $excludeOrderId = 0): int
    {
        if ($excludeOrderId <= 0 && isset($this->paidOrdersCache[$userId])) {
            return $this->paidOrdersCache[$userId];
        }
        $statusIds = $this->getPaidStatusIds();
        if ($statusIds === []) {
            if (!$this->emptyStatusIdsWarned) {
                $this->modx->log(modX::LOG_LEVEL_WARN, '[FTB] ms3_status_for_stat is empty; all users are treated as first-time buyers', '', 'ftb', __FILE__, __LINE__);
                $this->emptyStatusIdsWarned = true;
            }
            $this->paidOrdersCache[$userId] = 0;
            return 0;
        }
        $criteria = [
            'user_id' => $userId,
            'status_id:IN' => $statusIds,
        ];
        if ($excludeOrderId > 0) {
            $criteria['id:!='] = $excludeOrderId;
        }
        $count = (int) $this->modx->getCount(msOrder::class, $criteria);
        if ($excludeOrderId <= 0) {
            $this->paidOrdersCache[$userId] = $count;
        }
        return $count;
    }

    /**
     * Извлечь email и телефон гостя из черновика заказа (Address).
     *
     * @return array{email: string, phone: string} нормализованные непустые строки
     */
    public function getGuestContactFromDraft(?object $draft): array
    {
        $email = '';
        $phone = '';
        if ($draft === null || !is_object($draft) || !method_exists($draft, 'getOne')) {
            return ['email' => $email, 'phone' => $phone];
        }
        $address = $draft->getOne('Address');
        if ($address !== null && is_object($address) && method_exists($address, 'get')) {
            $email = $this->normalizeEmail((string) $address->get('email'));
            $phone = $this->normalizePhone((string) $address->get('phone'));
        }
        return ['email' => $email, 'phone' => $phone];
    }

    /**
     * Количество оплаченных заказов по email или телефону (для гостей).
     * Учитываются заказы, у которых в Address совпадает email или phone.
     */
    public function getPaidOrdersCountByContact(string $email, string $phone, int $excludeOrderId = 0): int
    {
        $email = $this->normalizeEmail($email);
        $phone = $this->normalizePhone($phone);
        if ($email === '' && $phone === '') {
            return 0;
        }
        $cacheKey = $email . '|' . $phone;
        if ($excludeOrderId <= 0 && isset($this->paidOrdersByContactCache[$cacheKey])) {
            return $this->paidOrdersByContactCache[$cacheKey];
        }
        $statusIds = $this->getPaidStatusIds();
        if ($statusIds === []) {
            if (!$this->emptyStatusIdsWarned) {
                $this->modx->log(modX::LOG_LEVEL_WARN, '[FTB] ms3_status_for_stat is empty; all users are treated as first-time buyers', '', 'ftb', __FILE__, __LINE__);
                $this->emptyStatusIdsWarned = true;
            }
            $this->paidOrdersByContactCache[$cacheKey] = 0;
            return 0;
        }
        $q = $this->modx->newQuery(msOrder::class);
        $q->innerJoin(msOrderAddress::class, 'Address', 'msOrder.id = Address.order_id');
        $q->where(['msOrder.status_id:IN' => $statusIds]);
        if ($excludeOrderId > 0) {
            $q->where(['msOrder.id:!=' => $excludeOrderId]);
        }
        $orConditions = [];
        if ($email !== '') {
            $orConditions[] = ['Address.email' => $email];
        }
        if ($phone !== '') {
            $phoneTail10 = $this->getPhoneTail10($phone);
            if ($phoneTail10 !== '') {
                $orConditions[] = ['Address.phone:LIKE' => '%' . $phoneTail10];
            } else {
                $orConditions[] = ['Address.phone' => $phone];
            }
        }
        $q->where($orConditions, 'OR');
        $count = (int) $this->modx->getCount(msOrder::class, $q);
        if ($excludeOrderId <= 0) {
            $this->paidOrdersByContactCache[$cacheKey] = $count;
        }
        return $count;
    }

    /**
     * Расчёт новой стоимости после скидки (percent или fixed).
     */
    public function calculateDiscount(float $cost, string $type, float $value): float
    {
        if ($cost <= 0.0) {
            return 0.0;
        }
        $type = $this->normalizeDiscountType($type);
        if ($type === self::DISCOUNT_TYPE_FIXED) {
            $value = max(0.0, $value);
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
        if (!$settings[self::SETTING_ALLOW_COMBINATION] && $this->hasExistingDiscountInCart($scriptProperties)) {
            return null;
        }
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
        $raw = is_scalar($raw) ? (string) $raw : '2,3';
        $ids = array_filter(array_map('intval', explode(',', $raw)));

        // Also count "new order" status as a first-order marker to prevent repeated discount
        // before payment status is reached.
        $statusNew = (int) $this->modx->getOption('ms3_status_new', null, 2);
        if ($statusNew > 0 && !in_array($statusNew, $ids, true)) {
            $ids[] = $statusNew;
        }

        return array_values($ids);
    }

    private function getSettings(): array
    {
        if ($this->settingsCache !== null) {
            return $this->settingsCache;
        }
        $type = $this->normalizeDiscountType((string) $this->getSetting(self::SETTING_DISCOUNT_TYPE, self::DISCOUNT_TYPE_PERCENT));
        $this->settingsCache = [
            self::SETTING_ENABLED => (bool) $this->getSetting(self::SETTING_ENABLED, true),
            self::SETTING_DISCOUNT_TYPE => $type,
            self::SETTING_DISCOUNT_VALUE => (float) $this->getSetting(self::SETTING_DISCOUNT_VALUE, 10),
            self::SETTING_ALLOW_COMBINATION => (bool) $this->getSetting(self::SETTING_ALLOW_COMBINATION, true),
        ];
        return $this->settingsCache;
    }

    private function normalizeDiscountType(string $type): string
    {
        $type = strtolower(trim($type));
        return $type === self::DISCOUNT_TYPE_FIXED ? self::DISCOUNT_TYPE_FIXED : self::DISCOUNT_TYPE_PERCENT;
    }

    private function resolveDraftOrderId(?object $draft): int
    {
        if ($draft !== null && is_object($draft) && method_exists($draft, 'get')) {
            return (int) $draft->get('id');
        }
        return 0;
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) === 10) {
            return '7' . $digits;
        }
        if (strlen($digits) === 11 && $digits[0] === '8') {
            return '7' . substr($digits, 1);
        }
        return $digits;
    }

    private function getPhoneTail10(string $phone): string
    {
        $normalized = $this->normalizePhone($phone);
        if ($normalized === '') {
            return '';
        }
        return strlen($normalized) >= 10 ? substr($normalized, -10) : $normalized;
    }

    private function hasExistingDiscountInCart(array $scriptProperties): bool
    {
        $cart = $scriptProperties['cart'] ?? null;
        if ($cart === null || !is_object($cart) || !method_exists($cart, 'status')) {
            return false;
        }

        try {
            $status = $cart->status();
        } catch (\Throwable) {
            return false;
        }

        if (!is_array($status) || empty($status['success']) || !is_array($status['data'] ?? null)) {
            return false;
        }

        $totalDiscount = (float) ($status['data']['total_discount'] ?? 0.0);
        return $totalDiscount > 0.0;
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
