import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../providers/auth_provider.dart';

enum PeriodoFiltro { estaSemana, semanaPasada, personalizado }

class LiquidacionSemanalScreen extends ConsumerStatefulWidget {
  const LiquidacionSemanalScreen({super.key});

  @override
  ConsumerState<LiquidacionSemanalScreen> createState() => _LiquidacionSemanalScreenState();
}

class _LiquidacionSemanalScreenState extends ConsumerState<LiquidacionSemanalScreen> {
  PeriodoFiltro _filtro = PeriodoFiltro.estaSemana;
  DateTime? _fechaInicioCustom;
  DateTime? _fechaFinCustom;

  bool _isLoading = false;
  Map<String, dynamic>? _reporte;
  String? _error;
  final Set<int> _pagadosEnSesion = {};

  @override
  void initState() {
    super.initState();
    _cargarLiquidacion();
  }

  Future<void> _cargarLiquidacion() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final client = ref.read(apiClientProvider);

      Map<String, dynamic> queryParams = {};

      final now = DateTime.now();
      if (_filtro == PeriodoFiltro.estaSemana) {
        // Lunes de esta semana a domingo
        final diaSemana = now.weekday; // 1 = Lunes
        final lunes = now.subtract(Duration(days: diaSemana - 1));
        final domingo = lunes.add(const Duration(days: 6));
        queryParams['fecha_inicio'] = lunes.toIso8601String().substring(0, 10);
        queryParams['fecha_fin'] = domingo.toIso8601String().substring(0, 10);
      } else if (_filtro == PeriodoFiltro.semanaPasada) {
        final diaSemana = now.weekday;
        final lunesPasado = now.subtract(Duration(days: diaSemana + 6));
        final domingoPasado = lunesPasado.add(const Duration(days: 6));
        queryParams['fecha_inicio'] = lunesPasado.toIso8601String().substring(0, 10);
        queryParams['fecha_fin'] = domingoPasado.toIso8601String().substring(0, 10);
      } else {
        if (_fechaInicioCustom != null) {
          queryParams['fecha_inicio'] = _fechaInicioCustom!.toIso8601String().substring(0, 10);
        }
        if (_fechaFinCustom != null) {
          queryParams['fecha_fin'] = _fechaFinCustom!.toIso8601String().substring(0, 10);
        }
      }

      final res = await client.get('/liquidaciones/semanal', queryParameters: queryParams);

      if (res.statusCode == 200 && res.data['success'] == true) {
        setState(() {
          _isLoading = false;
          _reporte = res.data['data'];
        });
      } else {
        setState(() {
          _isLoading = false;
          _error = res.data['error'] ?? 'Error al generar liquidaciones';
        });
      }
    } catch (e) {
      setState(() {
        _isLoading = false;
        _error = 'Error de conexión: $e';
      });
    }
  }

  void _seleccionarFiltro(PeriodoFiltro f) async {
    if (f == PeriodoFiltro.personalizado) {
      final picked = await showDateRangePicker(
        context: context,
        firstDate: DateTime(2025),
        lastDate: DateTime(2030),
        initialDateRange: DateTimeRange(
          start: DateTime.now().subtract(const Duration(days: 7)),
          end: DateTime.now(),
        ),
      );
      if (picked != null) {
        setState(() {
          _filtro = PeriodoFiltro.personalizado;
          _fechaInicioCustom = picked.start;
          _fechaFinCustom = picked.end;
        });
        _cargarLiquidacion();
      }
    } else {
      setState(() => _filtro = f);
      _cargarLiquidacion();
    }
  }

  void _confirmarPago(Map<String, dynamic> item) {
    final barmanId = item['barman_id'] as int;
    final barmanNombre = item['barman'] ?? 'Barman';
    final neto = (item['sueldo_neto_a_pagar'] as num?)?.toDouble() ?? 0.0;

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: const Color(0xFF1E293B),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Row(
          children: const [
            Icon(Icons.payments, color: Colors.greenAccent),
            SizedBox(width: 8),
            Text('Registrar Pago Semanal', style: TextStyle(color: Colors.white, fontSize: 18)),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Personal: $barmanNombre', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15)),
            const SizedBox(height: 6),
            Text('Período: ${_reporte?['periodo'] ?? 'Semana actual'}', style: const TextStyle(color: Colors.white70, fontSize: 13)),
            const Divider(color: Colors.white12, height: 20),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Monto Neto a Pagar:', style: TextStyle(color: Colors.white70)),
                Text(
                  'Bs. ${neto.toStringAsFixed(2)}',
                  style: const TextStyle(color: Colors.amberAccent, fontWeight: FontWeight.bold, fontSize: 20),
                ),
              ],
            ),
            const SizedBox(height: 12),
            const Text(
              'Al confirmar, este pago quedará registrado en el historial contable del establecimiento.',
              style: TextStyle(color: Colors.white54, fontSize: 12),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('CANCELAR', style: TextStyle(color: Colors.white60)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF27AE60)),
            onPressed: () async {
              Navigator.of(ctx).pop();
              try {
                final client = ref.read(apiClientProvider);
                final res = await client.post('/liquidaciones/pagar', data: {
                  'barman_id': barmanId,
                  'monto_pagado': neto,
                  'observaciones': 'Pago semanal período ${_reporte?['periodo']}',
                });

                if (res.data['success'] == true) {
                  setState(() {
                    _pagadosEnSesion.add(barmanId);
                  });
                  if (mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: Text('Pago de Bs. ${neto.toStringAsFixed(2)} registrado para $barmanNombre'),
                        backgroundColor: Colors.green,
                      ),
                    );
                  }
                }
              } catch (e) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('Error al registrar pago: $e'), backgroundColor: Colors.redAccent),
                  );
                }
              }
            },
            child: const Text('CONFIRMAR PAGO', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final liquidaciones = (_reporte?['liquidaciones'] as List<dynamic>?) ?? [];
    final periodoStr = _reporte?['periodo'] ?? '';

    return Scaffold(
      backgroundColor: const Color(0xFF0F172A),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1E293B),
        title: const Text('LIQUIDACIÓN SEMANAL DE SUELDOS', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _cargarLiquidacion,
          ),
        ],
      ),
      body: Column(
        children: [
          // Selector de período con 3 botones directos
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            color: const Color(0xFF1E293B).withOpacity(0.6),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    _buildChipPeriodo('Esta Semana', PeriodoFiltro.estaSemana),
                    const SizedBox(width: 8),
                    _buildChipPeriodo('Semana Pasada', PeriodoFiltro.semanaPasada),
                    const SizedBox(width: 8),
                    _buildChipPeriodo('Elegir Fechas', PeriodoFiltro.personalizado),
                  ],
                ),
                if (periodoStr.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      const Icon(Icons.calendar_month, size: 14, color: Colors.cyanAccent),
                      const SizedBox(width: 6),
                      Text('Período: $periodoStr', style: const TextStyle(color: Colors.cyanAccent, fontSize: 12, fontWeight: FontWeight.w600)),
                    ],
                  ),
                ],
              ],
            ),
          ),

          // Banner explicativo del modelo de negocio acordado
          Container(
            margin: const EdgeInsets.all(16),
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: const Color(0xFF1E293B),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: Colors.white10),
            ),
            child: Row(
              children: const [
                Icon(Icons.info_outline, color: Colors.amberAccent, size: 24),
                SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'Turno Noche: Los barmen tienen sueldo fijo semanal. Los faltantes de inventario se descuentan aquí al finalizar la semana. (El relleno de botella se liquida diariamente al cerrar turno).',
                    style: TextStyle(color: Colors.white70, fontSize: 11),
                  ),
                ),
              ],
            ),
          ),

          if (_error != null)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Text(_error!, style: const TextStyle(color: Colors.redAccent)),
            ),

          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: Colors.greenAccent))
                : liquidaciones.isEmpty
                    ? const Center(
                        child: Text(
                          'No hay barmen registrados para liquidar en este período.',
                          style: TextStyle(color: Colors.white54, fontSize: 14),
                        ),
                      )
                    : ListView.builder(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        itemCount: liquidaciones.length,
                        itemBuilder: (context, index) {
                          final item = liquidaciones[index];
                          final barmanId = item['barman_id'] as int;
                          final yaPagado = _pagadosEnSesion.contains(barmanId) || item['estado'] == 'pagado';
                          return _buildBarmanCard(item, yaPagado);
                        },
                      ),
          ),
        ],
      ),
    );
  }

  Widget _buildChipPeriodo(String label, PeriodoFiltro filtro) {
    final isSelected = _filtro == filtro;
    return ChoiceChip(
      label: Text(label),
      selected: isSelected,
      selectedColor: Colors.greenAccent,
      backgroundColor: const Color(0xFF334155),
      labelStyle: TextStyle(
        color: isSelected ? Colors.black : Colors.white,
        fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
        fontSize: 12,
      ),
      onSelected: (val) {
        if (val) _seleccionarFiltro(filtro);
      },
    );
  }

  Widget _buildBarmanCard(Map<String, dynamic> item, bool yaPagado) {
    final sueldoBase = (item['sueldo_base_semanal'] as num?)?.toDouble() ?? 0.0;
    final faltantes = (item['total_sanciones_faltantes'] as num?)?.toDouble() ?? 0.0;
    final neto = (item['sueldo_neto_a_pagar'] as num?)?.toDouble() ?? 0.0;
    final remanente = (item['saldo_deudor_remanente'] as num?)?.toDouble() ?? 0.0;
    final turnosNoche = item['turnos_noche_trabajados'] ?? 0;
    final turnosDia = item['turnos_dia_trabajados'] ?? 0;

    return Card(
      color: const Color(0xFF1E293B),
      margin: const EdgeInsets.only(bottom: 14),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: BorderSide(color: yaPagado ? Colors.green.withOpacity(0.4) : Colors.white12),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Cabecera: Nombre y Estado
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Row(
                  children: [
                    CircleAvatar(
                      backgroundColor: Colors.cyanAccent.withOpacity(0.15),
                      child: const Icon(Icons.person, color: Colors.cyanAccent),
                    ),
                    const SizedBox(width: 12),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          item['barman'] ?? 'Barman',
                          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16),
                        ),
                        Text(
                          'Sucursal: ${item['sucursal'] ?? 'Central'}',
                          style: const TextStyle(color: Colors.white60, fontSize: 12),
                        ),
                      ],
                    ),
                  ],
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: (yaPagado ? Colors.green : Colors.amber).withOpacity(0.15),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    yaPagado ? 'PAGADO' : 'PENDIENTE',
                    style: TextStyle(
                      color: yaPagado ? Colors.greenAccent : Colors.amberAccent,
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),

            // Turnos cumplidos
            Text(
              'Jornadas: $turnosNoche turnos de noche • $turnosDia de día',
              style: const TextStyle(color: Colors.white54, fontSize: 12),
            ),
            const Divider(color: Colors.white12, height: 20),

            // Desglose de Cálculo: Sueldo Base - Faltantes = Neto
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Sueldo Base Semanal:', style: TextStyle(color: Colors.white70, fontSize: 13)),
                Text('+ Bs. ${sueldoBase.toStringAsFixed(2)}',
                    style: const TextStyle(color: Colors.greenAccent, fontWeight: FontWeight.bold, fontSize: 13)),
              ],
            ),
            const SizedBox(height: 6),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Faltantes en Ticket Z (Descuento):', style: TextStyle(color: Colors.white70, fontSize: 13)),
                Text('- Bs. ${faltantes.toStringAsFixed(2)}',
                    style: TextStyle(
                      color: faltantes > 0 ? Colors.redAccent : Colors.white60,
                      fontWeight: FontWeight.bold,
                      fontSize: 13,
                    )),
              ],
            ),
            if (remanente > 0) ...[
              const SizedBox(height: 6),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Saldo deudor para prox. semana:', style: TextStyle(color: Colors.orangeAccent, fontSize: 12)),
                  Text('Bs. ${remanente.toStringAsFixed(2)}', style: const TextStyle(color: Colors.orangeAccent, fontSize: 12)),
                ],
              ),
            ],
            const Divider(color: Colors.white12, height: 20),

            // Fila Total Neto a Pagar
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'NETO A PAGAR:',
                  style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                ),
                Text(
                  'Bs. ${neto.toStringAsFixed(2)}',
                  style: const TextStyle(color: Colors.amberAccent, fontWeight: FontWeight.w900, fontSize: 22),
                ),
              ],
            ),
            const SizedBox(height: 14),

            // Botón Registrar Pago
            SizedBox(
              width: double.infinity,
              height: 42,
              child: ElevatedButton.icon(
                icon: Icon(yaPagado ? Icons.check_circle : Icons.payment, color: Colors.white, size: 18),
                label: Text(
                  yaPagado ? 'PAGO ASENTADO' : 'REGISTRAR PAGO (BS. ${neto.toStringAsFixed(2)})',
                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: yaPagado ? const Color(0xFF1E3A2F) : const Color(0xFF16A34A),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                onPressed: yaPagado ? null : () => _confirmarPago(item),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
