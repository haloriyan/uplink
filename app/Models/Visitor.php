<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id','name','email','phone','address','token'
    ];
    public function transactions() {
        return $this->hasMany('App\Models\VisitorOrder', 'visitor_id');
    }
}
