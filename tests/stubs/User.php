<?php

use Laraplus\Data\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Model
{
    use SoftDeletes, Translatable;

    protected $translatable = ['bio'];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
