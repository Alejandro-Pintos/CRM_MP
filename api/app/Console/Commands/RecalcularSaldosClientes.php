<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cliente;

class RecalcularSaldosClientes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cc:recalcular-saldos
                            {--cliente= : ID del cliente específico (opcional)}
                            {--dry-run : Solo mostrar cambios sin aplicarlos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalcula los saldos de cuenta corriente de todos los clientes';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $clienteId = $this->option('cliente');
        $dryRun = $this->option('dry-run');

        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║  RECÁLCULO DE SALDOS - CUENTA CORRIENTE                     ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();

        if ($dryRun) {
            $this->warn('⚠️  Modo DRY-RUN: No se guardarán cambios');
            $this->newLine();
        }

        // Determinar clientes a procesar
        $clientes = $clienteId 
            ? Cliente::where('id', $clienteId)->get()
            : Cliente::all();

        if ($clientes->isEmpty()) {
            $this->error('❌ No se encontraron clientes');
            return 1;
        }

        $this->info("📋 Procesando {$clientes->count()} cliente(s)...");
        $this->newLine();

        $cambios = 0;
        $sinCambios = 0;
        $errores = 0;

        $this->output->progressStart($clientes->count());

        foreach ($clientes as $cliente) {
            try {
                $saldoAntes = (float)$cliente->saldo_actual;
                $saldoCalculado = $cliente->calcularSaldoReal();
                $diferencia = abs($saldoAntes - $saldoCalculado);

                if ($diferencia > 0.01) {
                    // Hay diferencia significativa
                    $cambios++;
                    
                    $this->output->progressAdvance();
                    $this->newLine();
                    
                    $this->line(sprintf(
                        "  Cliente #%d: %s %s",
                        $cliente->id,
                        $cliente->nombre,
                        $cliente->apellido
                    ));
                    
                    $this->line(sprintf(
                        "    Antes:      $%s",
                        number_format($saldoAntes, 2, ',', '.')
                    ));
                    
                    $this->line(sprintf(
                        "    Calculado:  $%s",
                        number_format($saldoCalculado, 2, ',', '.')
                    ));
                    
                    $this->line(sprintf(
                        "    Diferencia: $%s",
                        number_format($diferencia, 2, ',', '.')
                    ));

                    if (!$dryRun) {
                        $cliente->recalcularSaldo();
                        $this->info("    ✅ Actualizado");
                    } else {
                        $this->warn("    ⏭️  Omitido (dry-run)");
                    }
                    
                    $this->newLine();
                } else {
                    // Sin cambios
                    $sinCambios++;
                    $this->output->progressAdvance();
                }

            } catch (\Exception $e) {
                $errores++;
                $this->output->progressAdvance();
                $this->newLine();
                $this->error(sprintf(
                    "  ❌ Error en cliente #%d: %s",
                    $cliente->id,
                    $e->getMessage()
                ));
                $this->newLine();
            }
        }

        $this->output->progressFinish();
        $this->newLine();

        // Resumen
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║  RESUMEN                                                     ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $this->line(sprintf("  Total clientes procesados: %d", $clientes->count()));
        $this->line(sprintf("  Clientes con cambios:      %d", $cambios));
        $this->line(sprintf("  Clientes sin cambios:      %d", $sinCambios));
        
        if ($errores > 0) {
            $this->line(sprintf("  Errores:                   %d", $errores));
        }

        $this->newLine();

        if ($cambios > 0 && !$dryRun) {
            $this->info('✅ Saldos recalculados exitosamente');
        } elseif ($cambios > 0 && $dryRun) {
            $this->warn('⚠️  Ejecuta sin --dry-run para aplicar cambios');
        } else {
            $this->info('✅ Todos los saldos están correctos');
        }

        return 0;
    }
}
