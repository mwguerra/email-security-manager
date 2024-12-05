<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('email_security_audits', function (Blueprint $table) {
            $table->id();
            $table->morphs('authenticatable');
            $table->string('email');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->string('triggered_by')->nullable(); // user, system, etc.
            $table->nullableMorphs('triggered_by_authenticatable');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['authenticatable_id', 'authenticatable_type', 'verified_at']);
            $table->index(['authenticatable_id', 'authenticatable_type', 'password_changed_at']);
        });
    }

    /**
     * Reverse the migrations for Gamification Implementation.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('email_security_audits');
    }
};
