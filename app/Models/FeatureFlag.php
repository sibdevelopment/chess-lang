<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key', 'name', 'description', 'is_enabled', 'rollout_percentage', 'config',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'rollout_percentage' => 'integer',
        'config' => 'array',
    ];
}
