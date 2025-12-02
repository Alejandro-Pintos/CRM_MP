<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Pago;
use App\Models\Cheque;

class MigrarChequesExistentes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cheques:migrar 
                            {--dry-run : Ejecutar sin modificar la base de datos}
                            {--force : Sobrescribir cheques existentes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrar cheques existentes desde la tabla pagos a la nueva tabla cheques';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('=== MIGRACIÓN DE CHEQUES ===');
        
        if ($dryRun) {
            $this->warn('⚠️  MODO DRY-RUN: No se modificará la base de datos');
        }

        // Obtener pagos que tienen datos de cheque
        $pagosConCheque = Pago::whereNotNull('numero_cheque')
            ->with(['venta.cliente'])
            ->get();

        $this->info("📋 Encontrados {$pagosConCheque->count()} pagos con datos de cheque");

        if ($pagosConCheque->isEmpty()) {
            $this->info('✅ No hay datos para migrar');
            return 0;
        }

        $migrados = 0;
        $omitidos = 0;
        $errores = 0;

        $progressBar = $this->output->createProgressBar($pagosConCheque->count());
        $progressBar->start();

        foreach ($pagosConCheque as $pago) {
            try {
                // Verificar si ya existe un cheque para este pago
                $existente = Cheque::where('pago_id', $pago->id)->first();
                
                if ($existente && !$force) {
                    $omitidos++;
                    $progressBar->advance();
                    continue;
                }

                // Validar que tenga venta asociada
                if (!$pago->venta) {
                    $this->newLine();
                    $this->warn("⚠️  Pago ID {$pago->id}: Sin venta asociada, omitiendo...");
                    $omitidos++;
                    $progressBar->advance();
                    continue;
                }

                if (!$dryRun) {
                    DB::transaction(function () use ($pago, $existente, $force) {
                        if ($existente && $force) {
                            $existente->delete();
                        }

                        Cheque::create([
                            'venta_id' => $pago->venta_id,
                            'cliente_id' => $pago->venta->cliente_id,
                            'pago_id' => $pago->id,
                            'numero' => $pago->numero_cheque,
                            'monto' => $pago->monto,
                            'fecha_emision' => $pago->fecha_cheque ?? $pago->venta->fecha,
                            'fecha_vencimiento' => $pago->fecha_vencimiento ?? null,
                            'estado' => $this->mapearEstadoCheque($pago->estado_cheque),
                            'fecha_cobro' => ($pago->estado_cheque === 'cobrado') ? $pago->updated_at : null,
                            'fecha_rechazo' => ($pago->estado_cheque === 'rechazado') ? $pago->updated_at : null,
                            'motivo_rechazo' => null,
                            'observaciones' => $pago->observaciones,
                        ]);
                    });
                }

                $migrados++;
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("❌ Error en Pago ID {$pago->id}: {$e->getMessage()}");
                $errores++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Resumen
        $this->info('=== RESUMEN DE MIGRACIÓN ===');
        $this->line("✅ Migrados exitosamente: {$migrados}");
        $this->line("⏭️  Omitidos (ya existen): {$omitidos}");
        $this->line("❌ Errores: {$errores}");

        if ($dryRun) {
            $this->newLine();
            $this->warn('⚠️  Esto fue una simulación. Ejecuta sin --dry-run para aplicar cambios.');
        } else {
            $this->newLine();
            $this->info('✅ Migración completada exitosamente');
            
            // Verificar resultado
            $totalCheques = Cheque::count();
            $this->info("📊 Total de cheques en la nueva tabla: {$totalCheques}");
        }

        return 0;
    }

    /**
     * Mapear estados del sistema antiguo al nuevo
     */
    private function mapearEstadoCheque(?string $estadoAntiguo): string
    {
        return match($estadoAntiguo) {
            'cobrado' => 'cobrado',
            'rechazado' => 'rechazado',
            'pendiente' => 'pendiente',
            default => 'pendiente'
        };
    }
}

