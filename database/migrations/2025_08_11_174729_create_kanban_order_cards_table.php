<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kanban_order_cards', function (Blueprint $table) {
            $table->id();

            // polymorphic владелец (User или Shop)
            // string — самый безопасный тип: подойдёт и для ULID/UUID, и для int
            $table->string('ownerable_type');
            $table->string('ownerable_id')->index();

            // ссылка на заказ из вашей таблицы orders (FK можно не ставить, если разные БД/коннекты)
            $table->unsignedBigInteger('shop_order_id')->index();
            $table->string('shop_order_number', 64)->nullable();

            // колонка канбана (первая — "new"), позиция 0/10/20,...
            $table->string('column_code', 64)->default('new')->index();
            $table->integer('position')->default(0);

            // примечание
            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['ownerable_type','ownerable_id'], 'koc_owner_idx');
            $table->index(['ownerable_type','ownerable_id','column_code','position'], 'koc_owner_col_pos_idx');

            // один владелец — одна карточка на заказ
            $table->unique(
                ['ownerable_type','ownerable_id','shop_order_id'],
                'kanban_owner_order_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_order_cards');
    }
};

