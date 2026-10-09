<?php

namespace Src\Services;

/**
 * Builds the comparable "state" of a Sales Order payload and diffs two states
 * into the delta that Accounting's `mode=delta` endpoint accepts.
 *
 * State shape: ['header' => [field => value], 'items' => [source_product_id => [field => value]]]
 */
class SnapshotDiffer
{
    public const HEADER_FIELDS = [
        'client_po_number', 'date', 'job_number', 'order_type', 'is_bundle', 'bundle_meta',
        'parent_deposit_so_id', 'customer_code', 'customer_name',
    ];

    public const ITEM_FIELDS = [
        'quantity', 'unit_price', 'item_name', 'product_name', 'description', 'product_code',
        'uom_code', 'tax_code', 'project_type', 'is_production', 'qty_per_set', 'is_group_main',
    ];

    /** Header fields that Accounting cannot store as null. */
    private const REQUIRED_HEADER_FIELDS = ['date', 'job_number', 'order_type', 'customer_code', 'customer_name'];

    private const DECIMAL_FIELDS = ['quantity', 'unit_price', 'qty_per_set'];

    private const BOOL_FIELDS = ['is_production', 'is_group_main', 'is_bundle'];

    /**
     * Normalise a full sync payload (as sent to Accounting) into a state.
     *
     * @param  array<string, mixed>  $payload
     * @return array{header: array<string, mixed>, items: array<int, array<string, mixed>>}
     */
    public static function stateFromPayload(array $payload): array
    {
        $header = [];
        foreach (self::HEADER_FIELDS as $field) {
            $header[$field] = self::normalise($field, $payload[$field] ?? null);
        }

        $items = [];
        foreach ($payload['items'] ?? [] as $item) {
            $sourceId = $item['source_product_id'] ?? null;
            if ($sourceId === null) {
                continue;
            }

            $row = [];
            foreach (self::ITEM_FIELDS as $field) {
                $row[$field] = self::normalise($field, $item[$field] ?? null);
            }
            $items[(int) $sourceId] = $row;
        }

        return ['header' => $header, 'items' => $items];
    }

    /**
     * @param  array{header: array<string, mixed>, items: array<int|string, array<string, mixed>>}  $old
     * @param  array{header: array<string, mixed>, items: array<int, array<string, mixed>>}  $new
     * @return array{header: array<string, mixed>, items: list<array<string, mixed>>}|null null when nothing changed
     */
    public static function diff(array $old, array $new): ?array
    {
        $header = [];
        foreach (self::HEADER_FIELDS as $field) {
            $before = $old['header'][$field] ?? null;
            $after = $new['header'][$field] ?? null;

            if (self::same($before, $after)) {
                continue;
            }
            if ($after === null && in_array($field, self::REQUIRED_HEADER_FIELDS, true)) {
                continue;
            }
            $header[$field] = $after;
        }

        $oldItems = [];
        foreach ($old['items'] ?? [] as $id => $row) {
            $oldItems[(int) $id] = $row;
        }

        $ops = [];
        foreach ($new['items'] as $id => $row) {
            if (! isset($oldItems[$id])) {
                $ops[] = ['op' => 'create', 'source_product_id' => $id, 'data' => $row];
                continue;
            }

            $changes = [];
            foreach (self::ITEM_FIELDS as $field) {
                if (! self::same($oldItems[$id][$field] ?? null, $row[$field] ?? null)) {
                    $changes[$field] = $row[$field] ?? null;
                }
            }
            if ($changes !== []) {
                $ops[] = ['op' => 'update', 'source_product_id' => $id, 'changes' => $changes];
            }
        }

        foreach (array_diff_key($oldItems, $new['items']) as $id => $row) {
            $ops[] = ['op' => 'delete', 'source_product_id' => $id];
        }

        if ($header === [] && $ops === []) {
            return null;
        }

        return ['header' => $header, 'items' => $ops];
    }

    /**
     * Strict comparison, except that 10 and 10.0 are equal: JSON storage does
     * not keep the int/float distinction.
     */
    private static function same(mixed $a, mixed $b): bool
    {
        if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
            return abs($a - $b) < 0.005;
        }

        return $a === $b;
    }

    private static function normalise(string $field, mixed $value): mixed
    {
        if (in_array($field, self::BOOL_FIELDS, true)) {
            return (bool) $value;
        }

        if (in_array($field, self::DECIMAL_FIELDS, true)) {
            return $value === null || $value === '' ? null : round((float) $value, 2);
        }

        if ($field === 'bundle_meta') {
            return is_array($value) ? self::sortKeys($value) : null;
        }

        if ($field === 'parent_deposit_so_id') {
            return $value === null || $value === '' ? null : (int) $value;
        }

        if ($field === 'description') {
            return (string) ($value ?? '');
        }

        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    private static function sortKeys(array $value): array
    {
        ksort($value);
        foreach ($value as $key => $inner) {
            if (is_array($inner)) {
                $value[$key] = self::sortKeys($inner);
            }
        }

        return $value;
    }
}
