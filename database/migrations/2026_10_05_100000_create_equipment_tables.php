<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('sectors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('sector_id')->nullable()->constrained('sectors')->nullOnDelete();
            $table->string('type')->default('Outros')->index();
            $table->string('name');
            $table->string('asset_tag')->nullable()->unique();   // patrimônio
            $table->string('serial_number')->nullable();
            $table->string('hostname')->nullable();
            $table->string('anydesk_id')->nullable();
            $table->string('user_name')->nullable();              // quem usa o equipamento
            $table->string('status')->default('em_uso')->index();
            $table->text('specs')->nullable();                    // especificações técnicas
            $table->text('notes')->nullable();                    // observações
            $table->unsignedBigInteger('legacy_id')->nullable()->unique(); // ID na planilha original
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
        Schema::dropIfExists('sectors');
        Schema::dropIfExists('stores');
    }
};
