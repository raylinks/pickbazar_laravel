<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Variation extends Model
{
  protected $table = 'variation_options';

  public $guarded = [];

  protected $casts = [
    'options'   => 'json',
    'image'   => 'json',
  ];

  public function digital_file()
  {
    return $this->morphOne(DigitalFile::class, 'fileable');
  }

  public function product()
  {
    return $this->belongsTo(Product::class, 'product_id');
  }
}
