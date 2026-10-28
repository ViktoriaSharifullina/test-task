<?php

declare(strict_types=1);

const DEFAULT_ORDERS_JSON = '[
    {"id": 101, "customer": "Иван", "amount": 1500, "status": "paid"},
    {"id": 102, "customer": "Мария", "amount": 2500, "status": "pending"},
    {"id": 103, "customer": "Пётр", "amount": 3000, "status": "paid"},
    {"id": 104, "customer": "Анна", "amount": 0, "status": "paid"}
]';

/**
 * @return array<int, array<string, mixed>>
 */
function decodeOrders(string $json): array
{
    try {
        $orders = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new InvalidArgumentException('Не удалось декодировать JSON: ' . $exception->getMessage(), 0, $exception);
    }

    if (!is_array($orders) || !array_is_list($orders) || $orders === []) {
        throw new InvalidArgumentException('JSON должен содержать непустой массив заказов.');
    }

    foreach ($orders as $index => $order) {
        validateOrder($order, $index);
    }

    return $orders;
}

/**
 * @param mixed $order
 */
function validateOrder($order, int $index): void
{
    if (!is_array($order)) {
        throw new InvalidArgumentException('Заказ #' . ($index + 1) . ' должен быть объектом.');
    }

    $requiredFields = ['id', 'customer', 'amount', 'status'];
    foreach ($requiredFields as $field) {
        if (!array_key_exists($field, $order)) {
            throw new InvalidArgumentException('У заказа #' . ($index + 1) . ' отсутствует поле "' . $field . '".');
        }
    }

    if (!is_int($order['id']) || $order['id'] < 1) {
        throw new InvalidArgumentException('У заказа #' . ($index + 1) . ' некорректный id.');
    }

    if (!is_string($order['customer']) || trim($order['customer']) === '') {
        throw new InvalidArgumentException('У заказа #' . ($index + 1) . ' некорректный customer.');
    }

    if ((!is_int($order['amount']) && !is_float($order['amount']))
        || !is_finite((float) $order['amount'])
        || $order['amount'] < 0
    ) {
        throw new InvalidArgumentException('У заказа #' . ($index + 1) . ' некорректная сумма.');
    }

    $knownStatuses = ['paid', 'pending'];
    if (!is_string($order['status']) || !in_array($order['status'], $knownStatuses, true)) {
        throw new InvalidArgumentException('У заказа #' . ($index + 1) . ' неизвестный статус.');
    }
}

/**
 * @param array<int, array<string, mixed>> $orders
 * @return array<int, array<string, mixed>>
 */
function getPaidOrders(array $orders): array
{
    return array_values(array_filter($orders, static function (array $order): bool {
        return $order['status'] === 'paid';
    }));
}

/**
 * @param array<int, array<string, mixed>> $orders
 */
function getTotalAmount(array $orders): int|float
{
    return array_reduce($orders, static function (int|float $total, array $order): int|float {
        return $total + $order['amount'];
    }, 0);
}

/**
 * @param array<int, array<string, mixed>> $orders
 */
function getMostExpensiveOrder(array $orders): ?array
{
    if ($orders === []) {
        return null;
    }

    $mostExpensive = $orders[0];
    foreach ($orders as $order) {
        if ($order['amount'] > $mostExpensive['amount']) {
            $mostExpensive = $order;
        }
    }

    return $mostExpensive;
}

/**
 * @param array<int, array<string, mixed>> $orders
 * @return array<string, int>
 */
function groupOrdersByStatus(array $orders): array
{
    $groups = [];
    foreach ($orders as $order) {
        $status = $order['status'];
        if (!isset($groups[$status])) {
            $groups[$status] = 0;
        }
        $groups[$status]++;
    }

    return $groups;
}

function readInputJson(array $arguments): string
{
    if (!isset($arguments[1])) {
        return DEFAULT_ORDERS_JSON;
    }

    if ($arguments[1] === '-') {
        $json = stream_get_contents(STDIN);
        if ($json === false) {
            throw new RuntimeException('Не удалось прочитать JSON из stdin.');
        }

        return $json;
    }

    if (!is_readable($arguments[1])) {
        throw new InvalidArgumentException('Файл не найден или недоступен для чтения: ' . $arguments[1]);
    }

    $json = file_get_contents($arguments[1]);
    if ($json === false) {
        throw new RuntimeException('Не удалось прочитать файл: ' . $arguments[1]);
    }

    return $json;
}

try {
    $orders = decodeOrders(readInputJson($argv));
    $paidOrders = getPaidOrders($orders);
    $mostExpensiveOrder = getMostExpensiveOrder($paidOrders);

    $result = [
        'paid_count' => count($paidOrders),
        'paid_total_amount' => getTotalAmount($paidOrders),
        'most_expensive_paid_order' => $mostExpensiveOrder === null
            ? null
            : [
                'id' => $mostExpensiveOrder['id'],
                'amount' => $mostExpensiveOrder['amount'],
            ],
        'orders_by_status' => groupOrdersByStatus($orders),
    ];

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Ошибка: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
