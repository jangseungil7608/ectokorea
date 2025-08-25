<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'user_id',
    ];

    /**
     * 상품을 소유한 사용자
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
