<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Osiset\ShopifyApp\Util;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(Util::getShopsTable(), function (Blueprint $table) {
            $table->id(); // INT primary key
            $table->boolean('shopify_grandfathered')->default(false);
            $table->string('shopify_namespace')->nullable(true)->default(null);
            $table->boolean('shopify_freemium')->default(false);
            $table->integer('plan_id')->unsigned()->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password', 100)->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on(Util::getShopifyConfig('table_names.plans', 'plans'));
            
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Util::getShopsTable());
    }
};
