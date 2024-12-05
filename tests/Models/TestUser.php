<?php

namespace MWGuerra\EmailSecurityManager\Tests\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use MWGuerra\EmailSecurityManager\Tests\Database\Factories\TestUserFactory;
use MWGuerra\EmailSecurityManager\Traits\HasEmailSecurity;

class TestUser extends Authenticatable
{
    use HasFactory, HasEmailSecurity, Notifiable;

    protected $guarded = [];

    protected static function newFactory(): TestUserFactory
    {
        return TestUserFactory::new();
    }
}