import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/api_client.dart';
import '../../providers/auth_provider.dart';
import 'auditoria_pdf_service.dart';

class AuditoriaTicketZScreen extends ConsumerStatefulWidget {
  const AuditoriaTicketZScreen({super.key});

  @override
  ConsumerState<AuditoriaTicketZScreen> createState() => _AuditoriaTicketZScreenState();
}

class _AuditoriaTicketZScreenState extends ConsumerState<AuditoriaTicketZScreen> {
  int _currentStep = 0; // 0: Elegir Turno, 1: Cargar Ticket Z, 2: Resultado y PDF

  // Datos Paso 1: Turnos pendientes
  bool _isLoadingTurnos = false;
  List<Map<String, dynamic>> _turnosPendientes = [];
  Map<String, dynamic>? _turnoSeleccionado;

  // Datos Paso 2: Ticket Z
  final _individualesController = TextEditingController(text: '10');
  final _baldes6Controller = TextEditingController(text: '5');
  final _precioSancionController = TextEditingController(text: '15.00');
  final _observacionesController = TextEditingController();
  bool _isAuditing = false;

  // Datos Paso 3: Resultado
  Map<String, dynamic>? _resultado;
  String? _error;

  @override
  void initState() {
    super.initState();
    _cargarTurnosPendientes();
  }

  @override
  void dispose() {
    _individualesController.dispose();
    _baldes6Controller.dispose();
    _precioSancionController.dispose();
    _observacionesController.dispose();
    super.dispose();
  }

  Future<void> _cargarTurnosPendientes() async {
    setState(() => _isLoadingTurnos = true);
    final client = ref.read(apiClientProvider);

    try {
      final res = await client.get('/auditoria/turnos-pendientes');
      if (res.statusCode == 200 && res.data['success'] == true) {
        final List list = res.data['data'] ?? [];
        setState(() {
          _turnosPendientes = list.map((t) => Map<String, dynamic>.from(t)).toList();
        });
      }
    } catch (_) {
      // Si la API falla o está vacía, no bloquea
    } finally {
      if (mounted) setState(() => _isLoadingTurnos = false);
    }
  }

  void _seleccionarTurno(Map<String, dynamic> turno) {
    setState(() {
      _turnoSeleccionado = turno;
      _currentStep = 1;
      _error = null;
    });
  }

  Future<void> _ejecutarAuditoria() async {
    if (_turnoSeleccionado == null) return;

    setState(() {
      _isAuditing = true;
      _error = null;
    });

    final turnoId = _turnoSeleccionado!['turno_id'];
    final individuales = double.tryParse(_individualesController.text.trim()) ?? 0.0;
    final baldes = int.tryParse(_baldes6Controller.text.trim()) ?? 0;
    final precioSancion = double.tryParse(_precioSancionController.text.trim()) ?? 15.00;

    final payload = {
      'turno_id': turnoId,
      'producto_id': 2, // Corona en Botella
      'precio_unitario_sancion': precioSancion,
      'observaciones': _observacionesController.text.trim(),
      'ventas_ticket_z': {
        'productos_individuales': [
          {'producto_id': 2, 'cantidad_vendida': individuales}
        ],
        'combos': [
          {'combo_id': 1, 'cantidad_vendida': baldes}
        ]
      }
    };

    try {
      final client = ref.read(apiClientProvider);
      final res = await client.post('/auditoria/calcular', data: payload);

      if (res.statusCode == 200 && res.data['success'] == true) {
        final data = res.data['data'] as Map<String, dynamic>;
        // Completar metadatos para el PDF
        data['turno_id'] = turnoId;
        data['sucursal_nombre'] = _turnoSeleccionado!['sucursal_nombre'];
        data['barman_nombre'] = _turnoSeleccionado!['barman_nombre'];
        data['observaciones'] = _observacionesController.text.trim();

        setState(() {
          _isAuditing = false;
          _resultado = data;
          _currentStep = 2; // Avanzar a pantalla de resultado y PDF
        });
      } else {
        setState(() {
          _isAuditing = false;
          _error = res.data['error'] ?? 'Error al procesar conciliación';
        });
      }
    } catch (e) {
      setState(() {
        _isAuditing = false;
        _error = 'Error de conexión: $e';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0F141C),
      appBar: AppBar(
        backgroundColor: const Color(0xFF18202C),
        elevation: 0,
        title: const Text('Auditoría de Turnos (Ticket Z)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 17)),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh, color: Colors.white70),
            tooltip: 'Recargar turnos',
            onPressed: _cargarTurnosPendientes,
          ),
        ],
      ),
      body: Column(
        children: [
          // Barra de Progreso del Asistente en 3 Pasos
          _buildStepBar(),

          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: _buildCurrentStepContent(),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStepBar() {
    final steps = ['1. Elegir Turno', '2. Ticket Z', '3. Resultado y PDF'];
    return Container(
      color: const Color(0xFF18202C),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Row(
        children: List.generate(steps.length, (idx) {
          final isCurrent = _currentStep == idx;
          final isPast = _currentStep > idx;
          return Expanded(
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    children: [
                      Container(
                        width: 24,
                        height: 24,
                        decoration: BoxDecoration(
                          color: isPast
                              ? const Color(0xFF10B981)
                              : (isCurrent ? const Color(0xFF2563EB) : const Color(0xFF334155)),
                          shape: BoxShape.circle,
                        ),
                        alignment: Alignment.center,
                        child: isPast
                            ? const Icon(Icons.check, size: 14, color: Colors.white)
                            : Text('${idx + 1}', style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold)),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        steps[idx],
                        style: TextStyle(
                          color: isCurrent ? Colors.white : Colors.white54,
                          fontSize: 10,
                          fontWeight: isCurrent ? FontWeight.bold : FontWeight.normal,
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ),
                ),
                if (idx < steps.length - 1)
                  Container(
                    width: 20,
                    height: 1,
                    color: isPast ? const Color(0xFF10B981) : Colors.white24,
                    margin: const EdgeInsets.symmetric(horizontal: 4),
                  ),
              ],
            ),
          );
        }),
      ),
    );
  }

  Widget _buildCurrentStepContent() {
    switch (_currentStep) {
      case 0:
        return _buildStep1ElegirTurno();
      case 1:
        return _buildStep2CargarTicketZ();
      case 2:
        return _buildStep3ResultadoYPdf();
      default:
        return const SizedBox.shrink();
    }
  }

  // ==========================================
  // PASO 1: SELECCIONAR TURNO CERRADO
  // ==========================================
  Widget _buildStep1ElegirTurno() {
    if (_isLoadingTurnos) {
      return const Center(
        child: Padding(
          padding: EdgeInsets.all(40),
          child: CircularProgressIndicator(color: Color(0xFF38BDF8)),
        ),
      );
    }

    if (_turnosPendientes.isEmpty) {
      return Center(
        child: Container(
          padding: const EdgeInsets.all(24),
          decoration: BoxDecoration(
            color: const Color(0xFF161F2E),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: Colors.white12),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.event_available, color: Color(0xFF10B981), size: 48),
              const SizedBox(height: 12),
              const Text(
                'No hay turnos cerrados pendientes',
                style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 6),
              const Text(
                'Cuando un barman cierre su turno de 12 horas, aparecerá aquí automáticamente para su fiscalización.',
                style: TextStyle(color: Colors.white70, fontSize: 13),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 20),
              ElevatedButton.icon(
                onPressed: () {
                  // Permitir auditar turno manual o de prueba
                  _seleccionarTurno({
                    'turno_id': 1,
                    'sucursal_nombre': 'Casa22',
                    'barman_nombre': 'Carlos Mendoza',
                    'tipo_turno': 'noche',
                    'fecha_cierre': 'Reciente',
                  });
                },
                icon: const Icon(Icons.edit_note, color: Colors.white),
                label: const Text('AUDITAR TURNO #1 MANUALMENTE', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF2563EB)),
              ),
            ],
          ),
        ),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'SELECCIONA EL TURNO A FISCALIZAR',
          style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13, letterSpacing: 0.5),
        ),
        const SizedBox(height: 10),
        ..._turnosPendientes.map((turno) {
          final yaAuditado = turno['ya_auditado'] == true;
          return Container(
            margin: const EdgeInsets.only(bottom: 12),
            decoration: BoxDecoration(
              color: const Color(0xFF161F2E),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: yaAuditado ? Colors.white12 : const Color(0xFF38BDF8).withOpacity(0.5)),
            ),
            child: ListTile(
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              leading: CircleAvatar(
                backgroundColor: yaAuditado ? const Color(0xFF334155) : const Color(0xFF2563EB),
                child: Text('#${turno['turno_id']}', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
              ),
              title: Text(
                '${turno['sucursal_nombre']} — ${turno['barman_nombre']}',
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
              ),
              subtitle: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const SizedBox(height: 4),
                  Text(
                    'Turno ${turno['tipo_turno'].toString().toUpperCase()} • Cerrado: ${turno['fecha_cierre'] ?? 'N/A'}',
                    style: const TextStyle(color: Colors.white54, fontSize: 12),
                  ),
                  if (turno['total_comision_neta_pagada'] != null)
                    Text(
                      'Comisión pagada: ${turno['total_comision_neta_pagada']} Bs',
                      style: const TextStyle(color: Color(0xFF10B981), fontSize: 12, fontWeight: FontWeight.bold),
                    ),
                ],
              ),
              trailing: ElevatedButton(
                onPressed: () => _seleccionarTurno(turno),
                style: ElevatedButton.styleFrom(
                  backgroundColor: yaAuditado ? const Color(0xFF334155) : const Color(0xFF10B981),
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                ),
                child: Text(
                  yaAuditado ? 'RE-AUDITAR' : 'AUDITAR',
                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12),
                ),
              ),
            ),
          );
        }),
      ],
    );
  }

  // ==========================================
  // PASO 2: INGRESAR VENTAS DEL TICKET Z
  // ==========================================
  Widget _buildStep2CargarTicketZ() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Resumen del turno seleccionado
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: const Color(0xFF1E293B),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: const Color(0xFF38BDF8).withOpacity(0.3)),
          ),
          child: Row(
            children: [
              const Icon(Icons.assignment_turned_in, color: Color(0xFF38BDF8), size: 28),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Turno #${_turnoSeleccionado!['turno_id']} • ${_turnoSeleccionado!['sucursal_nombre']}',
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15),
                    ),
                    Text(
                      'Barman: ${_turnoSeleccionado!['barman_nombre']}',
                      style: const TextStyle(color: Colors.white70, fontSize: 12),
                    ),
                  ],
                ),
              ),
              TextButton(
                onPressed: () => setState(() => _currentStep = 0),
                child: const Text('CAMBIAR', style: TextStyle(color: Color(0xFF38BDF8), fontSize: 11, fontWeight: FontWeight.bold)),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),

        // Tarjeta de Transcripción del Ticket Z
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: const Color(0xFF161F2E),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: Colors.white12),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Row(
                children: [
                  Icon(Icons.point_of_sale, color: Color(0xFF10B981), size: 20),
                  SizedBox(width: 8),
                  Text(
                    'VENTAS SEGÚN TICKET Z DE CAJA',
                    style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              const Text(
                'Transcribe los totales del cierre de caja. El sistema descompone automáticamente los baldes a unidades físicas.',
                style: TextStyle(color: Colors.white60, fontSize: 12),
              ),
              const SizedBox(height: 16),

              // Campo 1: Coronas individuales
              TextField(
                controller: _individualesController,
                keyboardType: TextInputType.number,
                style: const TextStyle(color: Colors.white, fontSize: 15),
                decoration: _inputDecoration('Coronas en Botella (Venta individual)'),
              ),
              const SizedBox(height: 12),

              // Campo 2: Baldes de 6
              TextField(
                controller: _baldes6Controller,
                keyboardType: TextInputType.number,
                style: const TextStyle(color: Colors.white, fontSize: 15),
                decoration: _inputDecoration('Baldes de 6 Coronas (Combos)'),
              ),
              const SizedBox(height: 12),

              // Campo 3: Precio de sanción
              TextField(
                controller: _precioSancionController,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                style: const TextStyle(color: Colors.white, fontSize: 15),
                decoration: _inputDecoration('Precio por Faltante en Bs (ej. 15.00)'),
              ),
              const SizedBox(height: 12),

              // Campo 4: Observaciones
              TextField(
                controller: _observacionesController,
                style: const TextStyle(color: Colors.white, fontSize: 14),
                decoration: _inputDecoration('Observaciones del Auditor (Opcional)'),
              ),
            ],
          ),
        ),
        const SizedBox(height: 20),

        if (_error != null)
          Container(
            padding: const EdgeInsets.all(12),
            margin: const EdgeInsets.only(bottom: 16),
            decoration: BoxDecoration(color: Colors.red.withOpacity(0.15), borderRadius: BorderRadius.circular(8)),
            child: Text(_error!, style: const TextStyle(color: Colors.redAccent, fontSize: 13)),
          ),

        // Botón Conciliar
        SizedBox(
          width: double.infinity,
          height: 50,
          child: ElevatedButton.icon(
            onPressed: _isAuditing ? null : _ejecutarAuditoria,
            icon: _isAuditing
                ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                : const Icon(Icons.calculate, color: Colors.white),
            label: Text(
              _isAuditing ? 'CALCULANDO AUDITORÍA...' : 'CONCILIAR Y VER RESULTADO',
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15),
            ),
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF2563EB),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
          ),
        ),
      ],
    );
  }

  // ==========================================
  // PASO 3: RESULTADO COMPARATIVO Y PDF
  // ==========================================
  Widget _buildStep3ResultadoYPdf() {
    if (_resultado == null) {
      return const SizedBox.shrink();
    }

    final dif = (_resultado!['diferencia'] ?? 0.0).toDouble();
    final res = _resultado!['resultado'] ?? (dif == 0 ? 'cuadrado' : (dif < 0 ? 'faltante' : 'sobrante'));

    Color bannerColor = const Color(0xFF10B981);
    IconData bannerIcon = Icons.check_circle;
    String bannerTitle = 'INVENTARIO CUADRADO';
    String bannerDesc = 'El consumo físico coincide exactamente con las ventas del Ticket Z.';

    if (res == 'faltante' || dif < 0) {
      bannerColor = const Color(0xFFEF4444);
      bannerIcon = Icons.warning_amber_rounded;
      bannerTitle = 'FALTANTE DE INVENTARIO';
      bannerDesc = 'Consumo físico superó a las ventas. Se genera sanción económica imputada al empleado.';
    } else if (res == 'sobrante' || dif > 0) {
      bannerColor = const Color(0xFF38BDF8);
      bannerIcon = Icons.info_outline;
      bannerTitle = 'SOBRANTE DE INVENTARIO';
      bannerDesc = 'Las ventas de caja superaron la salida física. No se aplica sanción al empleado.';
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Banner de Estado
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: bannerColor.withOpacity(0.15),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: bannerColor),
          ),
          child: Row(
            children: [
              Icon(bannerIcon, color: bannerColor, size: 36),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(bannerTitle, style: TextStyle(color: bannerColor, fontWeight: FontWeight.bold, fontSize: 16)),
                    const SizedBox(height: 4),
                    Text(bannerDesc, style: const TextStyle(color: Colors.white70, fontSize: 12)),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),

        // Tarjeta Desglose Numérico
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: const Color(0xFF161F2E),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: Colors.white12),
          ),
          child: Column(
            children: [
              _buildFilaComparativa('Stock Inicial', '${_resultado!['stock_inicial'] ?? 0} u'),
              _buildFilaComparativa('Rellenos Producidos (+)', '+${_resultado!['transformaciones_producidas'] ?? 0} u', color: const Color(0xFF10B981)),
              _buildFilaComparativa('Bajas / Roturas (-)', '-${_resultado!['bajas_roturas'] ?? 0} u', color: Colors.orangeAccent),
              _buildFilaComparativa('Stock Final de Cierre', '${_resultado!['stock_final'] ?? 0} u'),
              const Divider(color: Colors.white24),
              _buildFilaComparativa('CONSUMO FÍSICO REAL', '${_resultado!['consumo_fisico_calculado'] ?? 0} u', isBold: true),
              _buildFilaComparativa('VENTAS TICKET Z (DESGLOSE)', '${_resultado!['ventas_ticket_z_desglosadas'] ?? 0} u', isBold: true),
              const Divider(color: Colors.white24),
              _buildFilaComparativa(
                'DIFERENCIA RESULTANTE',
                '${dif > 0 ? "+$dif" : "$dif"} u',
                isBold: true,
                color: bannerColor,
              ),
              if (_resultado!['sancion_generada'] != null && (_resultado!['sancion_generada'] as num) > 0)
                _buildFilaComparativa(
                  'SANCIÓN IMPUTADA',
                  '${_resultado!['sancion_generada']} Bs',
                  isBold: true,
                  color: Colors.redAccent,
                ),
            ],
          ),
        ),
        const SizedBox(height: 20),

        // Botón 1: Descargar / Imprimir Reporte PDF
        SizedBox(
          width: double.infinity,
          height: 50,
          child: ElevatedButton.icon(
            onPressed: () {
              AuditoriaPdfService.generarEImprimirPdf(
                context: context,
                datos: _resultado!,
              );
            },
            icon: const Icon(Icons.picture_as_pdf, color: Colors.white),
            label: const Text(
              'DESCARGAR / IMPRIMIR REPORTE PDF',
              style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15),
            ),
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFDC2626),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
          ),
        ),
        const SizedBox(height: 12),

        // Botón 2: Auditar otro turno
        SizedBox(
          width: double.infinity,
          height: 48,
          child: OutlinedButton.icon(
            onPressed: () {
              setState(() {
                _currentStep = 0;
                _resultado = null;
                _turnoSeleccionado = null;
              });
              _cargarTurnosPendientes();
            },
            icon: const Icon(Icons.arrow_back, color: Color(0xFF38BDF8)),
            label: const Text('AUDITAR OTRO TURNO', style: TextStyle(color: Color(0xFF38BDF8), fontWeight: FontWeight.bold)),
            style: OutlinedButton.styleFrom(
              side: const BorderSide(color: Color(0xFF38BDF8)),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildFilaComparativa(String label, String valor, {bool isBold = false, Color? color}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: TextStyle(
              color: isBold ? Colors.white : Colors.white70,
              fontSize: isBold ? 13 : 12,
              fontWeight: isBold ? FontWeight.bold : FontWeight.normal,
            ),
          ),
          Text(
            valor,
            style: TextStyle(
              color: color ?? (isBold ? Colors.white : Colors.white70),
              fontSize: isBold ? 14 : 12,
              fontWeight: isBold ? FontWeight.bold : FontWeight.normal,
            ),
          ),
        ],
      ),
    );
  }

  InputDecoration _inputDecoration(String label) {
    return InputDecoration(
      labelText: label,
      labelStyle: const TextStyle(color: Colors.white60, fontSize: 13),
      filled: true,
      fillColor: const Color(0xFF0F172A),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
    );
  }
}
