import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../providers/auth_provider.dart';
import '../../providers/corte_garzon_provider.dart';

class LiquidacionJornalGarzonScreen extends ConsumerStatefulWidget {
  final int turnoId;
  final double faltanteBotellas;
  final double jornalBase;
  final double costoBotella;

  const LiquidacionJornalGarzonScreen({
    super.key,
    required this.turnoId,
    this.faltanteBotellas = 0.0,
    this.jornalBase = 120.0,
    this.costoBotella = 15.0,
  });

  @override
  ConsumerState<LiquidacionJornalGarzonScreen> createState() => _LiquidacionJornalGarzonScreenState();
}

class _LiquidacionJornalGarzonScreenState extends ConsumerState<LiquidacionJornalGarzonScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(corteGarzonProvider.notifier).calcularLiquidacion(
        jornalBase: widget.jornalBase,
        faltanteBotellas: widget.faltanteBotellas,
        costoUnitario: widget.costoBotella,
      );
    });
  }

  Future<void> _procesarCobro() async {
    final auth = ref.read(authProvider);
    final notifier = ref.read(corteGarzonProvider.notifier);

    final ok = await notifier.liquidarJornal(
      turnoId: widget.turnoId,
      usuarioId: auth.usuarioId ?? 1,
    );

    if (!ok && mounted) {
      final err = ref.read(corteGarzonProvider).errorMessage;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(err ?? 'Error al procesar liquidación'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(corteGarzonProvider);
    final auth = ref.watch(authProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Liquidación de Jornal'),
        backgroundColor: Colors.indigo.shade800,
        foregroundColor: Colors.white,
      ),
      body: state.yaLiquidado
          ? _buildComprobanteSellado(state, auth)
          : _buildLiquidacionDetalle(state, auth),
    );
  }

  Widget _buildLiquidacionDetalle(CorteGarzonState state, AuthState auth) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Tarjeta de Personal y Turno
          Card(
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
            elevation: 2,
            child: Padding(
              padding: const EdgeInsets.all(16.0),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 28,
                    backgroundColor: Colors.indigo.shade100,
                    child: Icon(Icons.person, size: 32, color: Colors.indigo.shade800),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          auth.nombre != null && auth.nombre!.isNotEmpty ? auth.nombre! : 'Garzón de Barra',
                          style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                        ),
                        Text(
                          'Turno Día # ${widget.turnoId} | ${auth.sucursalNombre ?? "Casa"}',
                          style: TextStyle(color: Colors.grey.shade600, fontSize: 13),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 20),

          // Desglose de Cálculo Estricto
          Card(
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
            elevation: 3,
            child: Padding(
              padding: const EdgeInsets.all(20.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'CÁLCULO DEL JORNAL DIARIO',
                    style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold, letterSpacing: 0.8),
                  ),
                  const Divider(height: 24),

                  // 1. Jornal Base
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Jornal Base Convenido:', style: TextStyle(fontSize: 16)),
                      Text(
                        'Bs. ${state.jornalBaseBs.toStringAsFixed(2)}',
                        style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),

                  // 2. Descuento por Faltante de Botellas
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('(-) Descuento Faltante Botellas:', style: TextStyle(fontSize: 16, color: Colors.red)),
                            Text(
                              '${state.faltanteBotellas.toStringAsFixed(1)} btls × Bs. ${state.costoBotellaBs.toStringAsFixed(2)} (al costo)',
                              style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                            ),
                          ],
                        ),
                      ),
                      Text(
                        '- Bs. ${state.descuentoFaltanteBs.toStringAsFixed(2)}',
                        style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.red),
                      ),
                    ],
                  ),
                  const Divider(height: 28),

                  // 3. Total Neto a Cobrar
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.green.shade50,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: Colors.green.shade300),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'TOTAL A COBRAR EN CAJA:',
                              style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.green),
                            ),
                            Text(
                              'Jornal Neto a Liquidar',
                              style: TextStyle(fontSize: 11, color: Colors.black54),
                            ),
                          ],
                        ),
                        Text(
                          'Bs. ${state.totalACobrarBs.toStringAsFixed(2)}',
                          style: TextStyle(
                            fontSize: 26,
                            fontWeight: FontWeight.bold,
                            color: Colors.green.shade800,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 32),

          // Botón de Liquidación
          ElevatedButton.icon(
            onPressed: state.isLoading ? null : _procesarCobro,
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.indigo.shade700,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(vertical: 18),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              elevation: 4,
            ),
            icon: state.isLoading
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                  )
                : const Icon(Icons.receipt_long, size: 24),
            label: Text(
              state.isLoading ? 'PROCESANDO...' : 'GENERAR COMPROBANTE DE COBRO',
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, letterSpacing: 0.5),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildComprobanteSellado(CorteGarzonState state, AuthState auth) {
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(24.0),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            // Icono de Éxito y Candado de Seguridad
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.green.shade100,
              ),
              child: Icon(Icons.verified, size: 64, color: Colors.green.shade700),
            ),
            const SizedBox(height: 16),
            const Text(
              'COMPROBANTE SELLADO ANTI-FRAUDE',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, letterSpacing: 0.8),
            ),
            const SizedBox(height: 6),
            Text(
              'Presenta esta pantalla a la cajera para recibir tu jornal',
              style: TextStyle(color: Colors.grey.shade600, fontSize: 13),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 24),

            // Tarjeta de Recibo Sellado
            Card(
              elevation: 6,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
              color: Colors.white,
              child: Padding(
                padding: const EdgeInsets.all(24.0),
                child: Column(
                  children: [
                    Text(
                      state.codigoCobro ?? 'JRN-VALIDO',
                      style: const TextStyle(
                        fontFamily: 'monospace',
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                        letterSpacing: 2.0,
                        color: Colors.indigo,
                      ),
                    ),
                    const Divider(height: 32),
                    Text(
                      'Bs. ${state.totalACobrarBs.toStringAsFixed(2)}',
                      style: TextStyle(
                        fontSize: 42,
                        fontWeight: FontWeight.bold,
                        color: Colors.green.shade800,
                      ),
                    ),
                    const Text('TOTAL LIQUIDADO', style: TextStyle(color: Colors.black54, fontSize: 12)),
                    const SizedBox(height: 24),

                    // Reloj Temporizador de 60 segundos
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                      decoration: BoxDecoration(
                        color: state.segundosRestantes > 10 ? Colors.orange.shade50 : Colors.red.shade50,
                        borderRadius: BorderRadius.circular(30),
                        border: Border.all(
                          color: state.segundosRestantes > 10 ? Colors.orange : Colors.red,
                        ),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            Icons.timer,
                            size: 18,
                            color: state.segundosRestantes > 10 ? Colors.orange.shade800 : Colors.red,
                          ),
                          const SizedBox(width: 8),
                          Text(
                            'Expira en: ${state.segundosRestantes}s',
                            style: TextStyle(
                              fontWeight: FontWeight.bold,
                              color: state.segundosRestantes > 10 ? Colors.orange.shade800 : Colors.red,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 32),

            ElevatedButton(
              onPressed: () => Navigator.of(context).pop(),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.grey.shade800,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 36, vertical: 14),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: const Text('VOLVER AL DASHBOARD'),
            ),
          ],
        ),
      ),
    );
  }
}
