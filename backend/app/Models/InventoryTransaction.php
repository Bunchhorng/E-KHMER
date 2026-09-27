<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['inventory_id', 'shop_id', 'created_by', 'type', 'quantity', 'balance_after', 'reference', 'note'])]
class InventoryTransaction extends Model
{
    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}