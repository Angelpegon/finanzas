<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas_contables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->string('codigo', 64);
            $table->string('nombre');
            $table->string('naturaleza', 20);
            $table->timestamps();
            $table->unique(['usuario_id', 'codigo']);
            $table->index(['usuario_id', 'naturaleza']);
        });

        Schema::create('cuentas_liquidas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cuenta_contable_id')->constrained('cuentas_contables')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('tipo', 20);
            $table->boolean('activa')->default(true);
            $table->timestamps();
            $table->index('usuario_id');
        });

        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cuenta_contable_id')->constrained('cuentas_contables')->cascadeOnDelete();
            $table->foreignId('padre_id')->nullable()->constrained('categorias')->nullOnDelete();
            $table->string('nombre');
            $table->string('tipo', 20);
            $table->timestamps();
            $table->index(['usuario_id', 'tipo']);
        });

        Schema::create('asientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('descripcion');
            $table->string('origen_tipo', 64);
            $table->unsignedBigInteger('origen_id');
            $table->boolean('es_reverso')->default(false);
            $table->unsignedBigInteger('asiento_reversado_id')->nullable();
            $table->string('hash_integridad', 64)->nullable();
            $table->timestamps();
            $table->index(['usuario_id', 'fecha']);
            $table->index(['origen_tipo', 'origen_id']);
        });

        Schema::create('movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('asiento_id')->constrained('asientos')->cascadeOnDelete();
            $table->foreignId('cuenta_contable_id')->constrained('cuentas_contables')->cascadeOnDelete();
            $table->bigInteger('debe_centavos')->default(0);
            $table->bigInteger('haber_centavos')->default(0);
            $table->timestamps();
            $table->index(['usuario_id', 'cuenta_contable_id']);
        });

        Schema::create('hechos_tesoreria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->string('tipo', 32);
            $table->foreignId('cuenta_liquida_id')->nullable()->constrained('cuentas_liquidas')->nullOnDelete();
            $table->foreignId('cuenta_destino_id')->nullable()->constrained('cuentas_liquidas')->nullOnDelete();
            $table->foreignId('categoria_id')->nullable()->constrained('categorias')->nullOnDelete();
            $table->foreignId('meta_ahorro_id')->nullable();
            $table->date('fecha');
            $table->bigInteger('monto_centavos');
            $table->string('descripcion')->nullable();
            $table->string('hash_fila', 64)->nullable();
            $table->timestamps();
            $table->index(['usuario_id', 'fecha']);
            $table->index(['usuario_id', 'hash_fila']);
        });

        Schema::create('prestamos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cuenta_contable_id')->constrained('cuentas_contables')->cascadeOnDelete();
            $table->foreignId('cuenta_liquida_id')->constrained('cuentas_liquidas')->cascadeOnDelete();
            $table->string('nombre');
            $table->bigInteger('principal_centavos');
            $table->decimal('ea_porcentaje', 8, 4);
            $table->unsignedSmallInteger('plazo_meses');
            $table->date('fecha_desembolso');
            $table->unsignedTinyInteger('dia_pago');
            $table->string('estado', 20)->default('vigente');
            $table->bigInteger('cuota_centavos');
            $table->timestamps();
            $table->index('usuario_id');
        });

        Schema::create('cuotas_prestamo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('prestamo_id')->constrained('prestamos')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->date('fecha_vencimiento');
            $table->bigInteger('capital_centavos');
            $table->bigInteger('interes_centavos');
            $table->bigInteger('saldo_capital_centavos');
            $table->boolean('pagada')->default(false);
            $table->timestamp('pagada_en')->nullable();
            $table->timestamps();
            $table->index(['usuario_id', 'fecha_vencimiento']);
            $table->unique(['prestamo_id', 'numero']);
        });

        Schema::create('tarjetas_credito', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cuenta_contable_id')->constrained('cuentas_contables')->cascadeOnDelete();
            $table->string('nombre');
            $table->bigInteger('cupo_centavos');
            $table->unsignedTinyInteger('dia_corte');
            $table->unsignedTinyInteger('dia_pago');
            $table->decimal('ea_porcentaje', 8, 4);
            $table->boolean('activa')->default(true);
            $table->timestamps();
            $table->index('usuario_id');
        });

        Schema::create('compras_tarjeta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tarjeta_credito_id')->constrained('tarjetas_credito')->cascadeOnDelete();
            $table->foreignId('categoria_id')->nullable()->constrained('categorias')->nullOnDelete();
            $table->date('fecha');
            $table->bigInteger('monto_centavos');
            $table->unsignedSmallInteger('cuotas');
            $table->string('descripcion')->nullable();
            $table->timestamps();
            $table->index('usuario_id');
        });

        Schema::create('cuotas_tarjeta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('compra_tarjeta_id')->constrained('compras_tarjeta')->cascadeOnDelete();
            $table->foreignId('tarjeta_credito_id')->constrained('tarjetas_credito')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->date('fecha_vencimiento');
            $table->bigInteger('capital_centavos');
            $table->bigInteger('interes_centavos');
            $table->boolean('pagada')->default(false);
            $table->timestamp('pagada_en')->nullable();
            $table->timestamps();
            $table->index(['usuario_id', 'fecha_vencimiento']);
        });

        Schema::create('ciclos_facturacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tarjeta_credito_id')->constrained('tarjetas_credito')->cascadeOnDelete();
            $table->date('fecha_corte');
            $table->date('fecha_pago');
            $table->bigInteger('saldo_corte_centavos')->default(0);
            $table->bigInteger('pago_minimo_centavos')->default(0);
            $table->bigInteger('interes_centavos')->default(0);
            $table->timestamps();
        });

        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->foreignId('prestamo_id')->nullable()->constrained('prestamos')->nullOnDelete();
            $table->foreignId('tarjeta_credito_id')->nullable()->constrained('tarjetas_credito')->nullOnDelete();
            $table->foreignId('cuenta_liquida_id')->constrained('cuentas_liquidas')->cascadeOnDelete();
            $table->date('fecha');
            $table->bigInteger('monto_centavos');
            $table->bigInteger('capital_centavos')->default(0);
            $table->bigInteger('interes_centavos')->default(0);
            $table->boolean('extraordinario')->default(false);
            $table->string('descripcion')->nullable();
            $table->timestamps();
            $table->index(['usuario_id', 'fecha']);
        });

        Schema::create('presupuestos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->timestamps();
            $table->unique(['usuario_id', 'anio', 'mes']);
        });

        Schema::create('presupuesto_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('presupuesto_id')->constrained('presupuestos')->cascadeOnDelete();
            $table->foreignId('categoria_id')->constrained('categorias')->cascadeOnDelete();
            $table->bigInteger('tope_centavos');
            $table->timestamps();
            $table->unique(['presupuesto_id', 'categoria_id']);
        });

        Schema::create('metas_ahorro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cuenta_liquida_id')->nullable()->constrained('cuentas_liquidas')->nullOnDelete();
            $table->string('nombre');
            $table->bigInteger('objetivo_centavos');
            $table->date('fecha_objetivo')->nullable();
            $table->bigInteger('aporte_mensual_centavos')->default(0);
            $table->timestamps();
            $table->index('usuario_id');
        });

        Schema::create('recurrencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->foreignId('categoria_id')->nullable()->constrained('categorias')->nullOnDelete();
            $table->foreignId('cuenta_liquida_id')->nullable()->constrained('cuentas_liquidas')->nullOnDelete();
            $table->string('nombre');
            $table->bigInteger('monto_centavos');
            $table->unsignedTinyInteger('dia_del_mes');
            $table->boolean('activa')->default(true);
            $table->timestamps();
            $table->index('usuario_id');
        });

        Schema::create('importaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->string('nombre_archivo');
            $table->string('estado', 20)->default('procesada');
            $table->unsignedInteger('filas_ok')->default(0);
            $table->unsignedInteger('filas_omitidas')->default(0);
            $table->unsignedInteger('filas_error')->default(0);
            $table->json('errores')->nullable();
            $table->timestamps();
        });

        Schema::create('seguridad_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion', 64);
            $table->string('descripcion');
            $table->string('registro_afectado')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['usuario_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seguridad_logs');
        Schema::dropIfExists('importaciones');
        Schema::dropIfExists('recurrencias');
        Schema::dropIfExists('metas_ahorro');
        Schema::dropIfExists('presupuesto_lineas');
        Schema::dropIfExists('presupuestos');
        Schema::dropIfExists('pagos');
        Schema::dropIfExists('ciclos_facturacion');
        Schema::dropIfExists('cuotas_tarjeta');
        Schema::dropIfExists('compras_tarjeta');
        Schema::dropIfExists('tarjetas_credito');
        Schema::dropIfExists('cuotas_prestamo');
        Schema::dropIfExists('prestamos');
        Schema::dropIfExists('hechos_tesoreria');
        Schema::dropIfExists('movimientos');
        Schema::dropIfExists('asientos');
        Schema::dropIfExists('categorias');
        Schema::dropIfExists('cuentas_liquidas');
        Schema::dropIfExists('cuentas_contables');
    }
};
