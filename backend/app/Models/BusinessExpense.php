<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessExpense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['shop_id', 'created_by', 'incurred_on', 'category', 'description', 'amount', 'currency'];

    protected function casts(): array
    {
        return ['incurred_on' => 'date', 'amount' => 'decimal:2'];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class)->withTrashed();
    }
}
