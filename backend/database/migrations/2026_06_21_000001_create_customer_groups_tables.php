<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('customer_groups.table_names');
        $columnNames = config('customer_groups.column_names');
        $pivotCustomerGroup = $columnNames['customer_group_pivot_key'] ?? 'customer_group_id';

        throw_if(empty($tableNames), 'Error: config/customer_groups.php not loaded. Run [php artisan config:clear] and try again.');

        Schema::create($tableNames['customer_groups'], static function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create($tableNames['model_has_customer_groups'], static function (Blueprint $table) use ($tableNames, $columnNames, $pivotCustomerGroup) {
            $table->unsignedBigInteger($pivotCustomerGroup);

            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_customer_groups_model_id_model_type_index');

            $table->foreign($pivotCustomerGroup)
                ->references('id')
                ->on($tableNames['customer_groups'])
                ->cascadeOnDelete();

            $table->primary([$pivotCustomerGroup, $columnNames['model_morph_key'], 'model_type'],
                'model_has_customer_groups_customer_group_model_type_primary');
        });

        app('cache')
            ->store(config('customer_groups.cache.store') != 'default' ? config('customer_groups.cache.store') : null)
            ->forget(config('customer_groups.cache.key'));
    }

    public function down(): void
    {
        $tableNames = config('customer_groups.table_names');

        if (empty($tableNames)) {
            $tableNames = [
                'model_has_customer_groups' => 'model_has_customer_groups',
                'customer_groups' => 'customer_groups',
            ];
        }

        Schema::dropIfExists($tableNames['model_has_customer_groups']);
        Schema::dropIfExists($tableNames['customer_groups']);
    }
};
