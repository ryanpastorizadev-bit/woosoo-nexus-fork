<?php

namespace App\Services;

use App\Models\DeviceOrder;
use App\Models\DeviceOrderItems;
use App\Models\PrintEvent;
use App\Enums\PrintEventType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrintTicketService
{
    /**
     * Create or reuse a print event for initial order
     */
    public function createInitialPrintEvent(DeviceOrder $order, string $clientSubmissionId): PrintEvent
    {
        $idempotencyKey = "initial:{$order->id}:{$clientSubmissionId}";

        return DB::transaction(function () use ($order, $clientSubmissionId, $idempotencyKey) {
            // Check for existing print event with same idempotency key
            $existingEvent = PrintEvent::where('idempotency_key', $idempotencyKey)->first();
            
            if ($existingEvent) {
                Log::info('Reusing existing initial print event', [
                    'print_event_id' => $existingEvent->id,
                    'order_id' => $order->id,
                    'client_submission_id' => $clientSubmissionId
                ]);
                return $existingEvent;
            }

            // Create new print event
            $printEvent = PrintEvent::create([
                'device_order_id' => $order->id,
                'event_type' => PrintEventType::INITIAL->value,
                'idempotency_key' => $idempotencyKey,
                'client_submission_id' => $clientSubmissionId,
                'refill_number' => null,
                'meta' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'table_id' => $order->table_id,
                    'device_id' => $order->device_id,
                ]
            ]);

            // Attach all initial order items to print event
            $this->attachItemsToPrintEvent($printEvent, $order->items, 'INITIAL', $clientSubmissionId);

            Log::info('Created new initial print event', [
                'print_event_id' => $printEvent->id,
                'order_id' => $order->id,
                'client_submission_id' => $clientSubmissionId,
                'items_count' => $order->items->count()
            ]);

            return $printEvent;
        });
    }

    /**
     * Create or reuse a print event for refill
     */
    public function createRefillPrintEvent(DeviceOrder $order, array $refillItems, string $clientSubmissionId): PrintEvent
    {
        $idempotencyKey = "refill:{$order->id}:{$clientSubmissionId}";
        $refillNumber = $this->getNextRefillNumber($order);

        return DB::transaction(function () use ($order, $refillItems, $clientSubmissionId, $idempotencyKey, $refillNumber) {
            // Check for existing print event with same idempotency key
            $existingEvent = PrintEvent::where('idempotency_key', $idempotencyKey)->first();
            
            if ($existingEvent) {
                Log::info('Reusing existing refill print event', [
                    'print_event_id' => $existingEvent->id,
                    'order_id' => $order->id,
                    'client_submission_id' => $clientSubmissionId,
                    'refill_number' => $refillNumber
                ]);
                return $existingEvent;
            }

            // Create new print event
            $printEvent = PrintEvent::create([
                'device_order_id' => $order->id,
                'event_type' => PrintEventType::REFILL->value,
                'idempotency_key' => $idempotencyKey,
                'client_submission_id' => $clientSubmissionId,
                'refill_number' => $refillNumber,
                'meta' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'table_id' => $order->table_id,
                    'device_id' => $order->device_id,
                    'refill_number' => $refillNumber,
                ]
            ]);

            // Attach only refill items to print event
            $this->attachRefillItemsToPrintEvent($printEvent, $refillItems, $clientSubmissionId);

            Log::info('Created new refill print event', [
                'print_event_id' => $printEvent->id,
                'order_id' => $order->id,
                'client_submission_id' => $clientSubmissionId,
                'refill_number' => $refillNumber,
                'items_count' => count($refillItems)
            ]);

            return $printEvent;
        });
    }

    /**
     * Mark items as printed when print event is acknowledged
     */
    public function markItemsAsPrinted(PrintEvent $printEvent): void
    {
        DB::transaction(function () use ($printEvent) {
            $printEvent->printEventItems()->with('deviceOrderItem')->get()->each(function ($printEventItem) use ($printEvent) {
                $item = $printEventItem->deviceOrderItem;
                
                $item->update([
                    'is_printed' => true,
                    'printed_at' => now(),
                    'printed_by_print_event_id' => $printEvent->id,
                    'print_type' => $printEvent->event_type,
                ]);
            });

            Log::info('Marked items as printed', [
                'print_event_id' => $printEvent->id,
                'items_count' => $printEvent->printEventItems()->count()
            ]);
        });
    }

    /**
     * Attach order items to print event
     */
    private function attachItemsToPrintEvent(PrintEvent $printEvent, $items, string $printType, string $clientSubmissionId): void
    {
        foreach ($items as $item) {
            $printEvent->printEventItems()->create([
                'device_order_item_id' => $item->id,
                'quantity' => $item->quantity,
            ]);

            // Update item with client submission ID
            $item->update([
                'client_submission_id' => $clientSubmissionId,
            ]);
        }
    }

    /**
     * Attach refill items to print event
     */
    private function attachRefillItemsToPrintEvent(PrintEvent $printEvent, array $refillItems, string $clientSubmissionId): void
    {
        foreach ($refillItems as $itemData) {
            // Find the corresponding device order item
            $deviceOrderItem = DeviceOrderItems::where('order_id', $printEvent->device_order_id)
                ->where('menu_id', $itemData['menu_id'])
                ->where('quantity', $itemData['quantity'])
                ->where('is_refill', true)
                ->latest()
                ->first();

            if ($deviceOrderItem) {
                $printEvent->printEventItems()->create([
                    'device_order_item_id' => $deviceOrderItem->id,
                    'quantity' => $itemData['quantity'],
                ]);

                // Update item with client submission ID
                $deviceOrderItem->update([
                    'client_submission_id' => $clientSubmissionId,
                ]);
            } else {
                Log::warning('Could not find device order item for refill print event', [
                    'print_event_id' => $printEvent->id,
                    'menu_id' => $itemData['menu_id'],
                    'quantity' => $itemData['quantity']
                ]);
            }
        }
    }

    /**
     * Get next refill number for order
     */
    private function getNextRefillNumber(DeviceOrder $order): int
    {
        $lastRefillEvent = PrintEvent::where('device_order_id', $order->id)
            ->where('event_type', PrintEventType::REFILL->value)
            ->orderBy('refill_number', 'desc')
            ->first();

        return ($lastRefillEvent?->refill_number ?? 0) + 1;
    }
}
