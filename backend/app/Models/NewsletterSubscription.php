<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterSubscription extends Model
{
    protected $guarded = [];

    protected $hidden = ['token_hash'];
}
