<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kanban_order_columns', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Полиморфный владелец
            $table->string('ownerable_type');          // App\Models\User | App\Models\Shop
            $table->string('ownerable_id');
            $table->index(['ownerable_type', 'ownerable_id'], 'kanban_order_columns_owner_idx');

            // Настройки колонки
            $table->string('code');                    // slug (например: in-progress)
            $table->string('name');
            $table->text('desc')->nullable();
            $table->char('hex', 7)->default('#0284C7');
            $table->unsignedSmallInteger('position');
            $table->json('meta')->nullable();

            $table->boolean('is_system')->default(false);
            $table->softDeletes();
            $table->timestamps();

            // Уникальность кода в пределах владельца
            $table->unique(
                ['ownerable_type','ownerable_id','code'],
                'kanban_order_columns_owner_code_unique'
            );

            // (опционально) быстрые выборки по сортировке
            $table->index(
                ['ownerable_type','ownerable_id','position'],
                'kanban_order_columns_owner_position_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_order_columns');
    }
};
