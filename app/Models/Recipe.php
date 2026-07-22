<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Recipe extends Model
{
    protected $fillable = ['title', 'description', 'image', 'category', 'prep_time', 'cook_time', 'servings', 'difficulty', 'status'];
    protected $appends = ['image_full_url'];

    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    public function savedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_recipes')->withPivot('created_at');
    }

    public function storage()
    {
        return $this->morphMany(\App\Models\Storage::class, 'data');
    }

    public function getImageFullUrlAttribute()
    {
        $value = $this->image;
        if (count($this->storage) > 0) {
            foreach ($this->storage as $storage) {
                if ($storage['key'] == 'image') {
                    return Helpers::get_full_url('recipe', $value, $storage['value']);
                }
            }
        }

        return Helpers::get_full_url('recipe', $value, 'public');
    }
}
