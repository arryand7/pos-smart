<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Record a purchase/stock-in movement.
     */
    public function recordPurchase(Product $product, int $quantity, float $unitCost, ?string $description = null): InventoryMovement
    {
        return DB::transaction(function () use ($product, $quantity, $unitCost, $description) {
            $movement = InventoryMovement::create([
                'product_id' => $product->id,
                'location_id' => $product->location_id,
                'type' => 'in',
                'quantity_change' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $quantity * $unitCost,
                'description' => $description ?? 'Stock purchase',
                'recorded_at' => now(),
            ]);

            $product->increment('stock', $quantity);
            
            // Update cost price based on weighted average
            $totalValue = ($product->cost_price * ($product->stock - $quantity)) + ($unitCost * $quantity);
            $product->update(['cost_price' => $totalValue / $product->stock]);

            return $movement;
        });
    }

    /**
     * Record a sale/stock-out movement using FIFO costing.
     * Returns the COGS for this sale.
     */
    public function recordSale(Product $product, int $quantity, ?string $referenceType = null, ?int $referenceId = null): array
    {
        return DB::transaction(function () use ($product, $quantity, $referenceType, $referenceId) {
            $cogs = $this->calculateFifoCogs($product, $quantity);

            $movement = InventoryMovement::create([
                'product_id' => $product->id,
                'location_id' => $product->location_id,
                'type' => 'out',
                'quantity_change' => -$quantity,
                'unit_cost' => $cogs['unit_cost'],
                'total_cost' => $cogs['total_cost'],
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => 'Sale (FIFO)',
                'metadata' => $cogs['layers'],
                'recorded_at' => now(),
            ]);

            $product->decrement('stock', $quantity);

            return [
                'movement' => $movement,
                'cogs' => $cogs['total_cost'],
                'unit_cost' => $cogs['unit_cost'],
            ];
        });
    }

    /**
     * Calculate COGS using FIFO method.
     * Iterates through oldest inventory layers first.
     */
    public function calculateFifoCogs(Product $product, int $quantityNeeded): array
    {
        // Get all "in" movements that still have remaining stock, ordered by date (oldest first)
        $inMovements = InventoryMovement::where('product_id', $product->id)
            ->where('type', 'in')
            ->where('quantity_change', '>', 0)
            ->orderBy('recorded_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $totalCost = 0;
        $remainingQty = $quantityNeeded;
        $layers = [];

        // Calculate consumed quantities from previous "out" movements
        $consumedByLayer = InventoryMovement::where('product_id', $product->id)
            ->where('type', 'out')
            ->whereNotNull('metadata')
            ->get()
            ->flatMap(fn ($m) => $m->metadata ?? [])
            ->groupBy('layer_id')
            ->map(fn ($items) => $items->sum('quantity'));

        foreach ($inMovements as $layer) {
            if ($remainingQty <= 0) break;

            $consumed = $consumedByLayer->get($layer->id, 0);
            $available = $layer->quantity_change - $consumed;

            if ($available <= 0) continue;

            $useQty = min($remainingQty, $available);
            $layerCost = $useQty * $layer->unit_cost;

            $totalCost += $layerCost;
            $remainingQty -= $useQty;

            $layers[] = [
                'layer_id' => $layer->id,
                'quantity' => $useQty,
                'unit_cost' => $layer->unit_cost,
                'cost' => $layerCost,
            ];
        }

        // If we still need more quantity than available layers, use current cost price
        if ($remainingQty > 0) {
            $fallbackCost = $remainingQty * $product->cost_price;
            $totalCost += $fallbackCost;
            $layers[] = [
                'layer_id' => null,
                'quantity' => $remainingQty,
                'unit_cost' => $product->cost_price,
                'cost' => $fallbackCost,
                'note' => 'fallback_to_current_cost',
            ];
        }

        return [
            'total_cost' => $totalCost,
            'unit_cost' => $quantityNeeded > 0 ? $totalCost / $quantityNeeded : 0,
            'layers' => $layers,
        ];
    }

    /**
     * Record a stock adjustment (correction).
     */
    public function recordAdjustment(Product $product, int $quantityChange, ?string $reason = null): InventoryMovement
    {
        return DB::transaction(function () use ($product, $quantityChange, $reason) {
            $type = $quantityChange >= 0 ? 'adjustment_in' : 'adjustment_out';

            $movement = InventoryMovement::create([
                'product_id' => $product->id,
                'location_id' => $product->location_id,
                'type' => $type,
                'quantity_change' => $quantityChange,
                'unit_cost' => $product->cost_price,
                'total_cost' => abs($quantityChange) * $product->cost_price,
                'description' => $reason ?? 'Stock adjustment',
                'recorded_at' => now(),
            ]);

            $product->increment('stock', $quantityChange);

            return $movement;
        });
    }
}
