<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meal extends Model
{
    protected $guarded = ['id'];

    public function mealItems()
    {
        return $this->hasMany(MealItem::class);
    }
}
