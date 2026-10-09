<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteItem extends Model
{
    protected $fillable = ['quote_id', 'service_id', 'description', 'teeth', 'quantity', 'unit_price', 'line_total', 'price_options', 'position', 'done_at', 'done_by', 'evolution_id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'line_total' => 'integer',
            'done_at' => 'datetime',
        ];
    }

    /** Opciones de precio ("Uniradicular $400.000, Biradicular $450.000"), una por línea. */
    public function priceOptionLines(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\n;,]+/', (string) $this->price_options))));
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
