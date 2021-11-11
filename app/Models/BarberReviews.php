<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BarberReviews extends Model
{
    use HasFactory;

    protected $table = 'barberreviews';
    public $timestamps = false;
}
