<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Src\Services\SnapshotDiffer;

class SnapshotDifferTest extends TestCase
{
    private function payload(array $items, array $header = []): array
    {
        return $header + [
            'client_po_number' => 'PO-1',
            'date' => '2026-10-01',
            'job_number' => 'JOB-1',
            'order_type' => 'sales_order',
            'is_bundle' => false,
            'bundle_meta' => ['is_bundle_main' => false, 'bundle_group' => null],
            'customer_code' => 'C1',
            'customer_name' => 'PT Maju',
            'items' => $items,
        ];
    }

    private function item(int $id, array $overrides = []): array
    {
        return $overrides + [
            'source_product_id' => $id,
            'quantity' => 10,
            'unit_price' => 100,
            'item_name' => "Item {$id}",
            'product_name' => "Item {$id}",
            'description' => '',
            'product_code' => "P{$id}",
            'uom_code' => 'PCS',
            'tax_code' => 'PPN',
            'project_type' => 'gimmick',
            'is_production' => true,
            'qty_per_set' => null,
            'is_group_main' => false,
            // not part of the comparable state
            'total' => 1000,
            'tax_amount' => 110,
        ];
    }

    public function test_identical_payloads_have_no_diff(): void
    {
        $a = SnapshotDiffer::stateFromPayload($this->payload([$this->item(1), $this->item(2)]));
        $b = SnapshotDiffer::stateFromPayload($this->payload([$this->item(2), $this->item(1)]));

        $this->assertNull(SnapshotDiffer::diff($a, $b));
    }

    public function test_numeric_noise_is_not_a_change(): void
    {
        $a = SnapshotDiffer::stateFromPayload($this->payload([$this->item(1, ['quantity' => '10.00', 'unit_price' => 100])]));
        $b = SnapshotDiffer::stateFromPayload($this->payload([$this->item(1, ['quantity' => 10, 'unit_price' => '100.0'])]));

        $this->assertNull(SnapshotDiffer::diff($a, $b));
    }

    public function test_only_changed_item_fields_are_sent(): void
    {
        $old = SnapshotDiffer::stateFromPayload($this->payload([$this->item(1)]));
        $new = SnapshotDiffer::stateFromPayload($this->payload([$this->item(1, ['quantity' => 12])]));

        $this->assertSame([
            'header' => [],
            'items' => [['op' => 'update', 'source_product_id' => 1, 'changes' => ['quantity' => 12.0]]],
        ], SnapshotDiffer::diff($old, $new));
    }

    public function test_added_and_removed_items(): void
    {
        $old = SnapshotDiffer::stateFromPayload($this->payload([$this->item(1), $this->item(2)]));
        $new = SnapshotDiffer::stateFromPayload($this->payload([$this->item(1), $this->item(3)]));

        $diff = SnapshotDiffer::diff($old, $new);

        $this->assertSame(['create', 'delete'], array_column($diff['items'], 'op'));
        $this->assertSame([3, 2], array_column($diff['items'], 'source_product_id'));
        $this->assertSame('P3', $diff['items'][0]['data']['product_code']);
    }

    public function test_header_change_is_isolated(): void
    {
        $old = SnapshotDiffer::stateFromPayload($this->payload([$this->item(1)]));
        $new = SnapshotDiffer::stateFromPayload($this->payload([$this->item(1)], ['client_po_number' => 'PO-2']));

        $this->assertSame(['client_po_number' => 'PO-2'], SnapshotDiffer::diff($old, $new)['header']);
    }

    public function test_required_header_fields_are_never_nulled(): void
    {
        $old = SnapshotDiffer::stateFromPayload($this->payload([$this->item(1)]));
        $new = SnapshotDiffer::stateFromPayload($this->payload([$this->item(1)], ['customer_name' => null]));

        $this->assertNull(SnapshotDiffer::diff($old, $new));
    }

    public function test_clearing_an_optional_header_field_is_sent(): void
    {
        $old = SnapshotDiffer::stateFromPayload($this->payload([$this->item(1)]));
        $new = SnapshotDiffer::stateFromPayload($this->payload([$this->item(1)], ['client_po_number' => null]));

        $this->assertSame(['client_po_number' => null], SnapshotDiffer::diff($old, $new)['header']);
    }

    public function test_state_survives_a_json_roundtrip(): void
    {
        $state = SnapshotDiffer::stateFromPayload($this->payload([$this->item(7, ['qty_per_set' => 2.5])]));
        $restored = json_decode(json_encode($state), true);

        $this->assertNull(SnapshotDiffer::diff($restored, $state));
    }
}
