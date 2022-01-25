<?php

namespace Marvel\Database\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manufacturer extends Model
{
    use Sluggable;

    protected $table = 'manufacturers';

    public $guarded = [];

    public $appends = ['products_count'];

    protected $casts = [
        'image'   => 'json',
        'cover_image' => 'json',
        'socials' => 'json',
    ];

    /**
     * Return the sluggable configuration array for this model.
     *
     * @return array
     */
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name'
            ]
        ];
    }

    public function getProductsCountAttribute()
    {
        return $this->products()->count();
    }


    /**
     * @return HasMany
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'manufacturer_id');
    }

    /**
     * @return BelongsTo
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(Type::class, 'type_id');
    }
}
