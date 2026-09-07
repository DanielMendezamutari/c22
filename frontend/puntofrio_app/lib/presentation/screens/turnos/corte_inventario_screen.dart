import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/bottle_fraction_selector.dart';
import 'cierre_turno_pdf_service.dart';
import 'conteo_pdf_service.dart';

enum TipoOperacionCorte { apertura, cierre }

class CorteInventarioScreen extends ConsumerStatefulWidget {
  final TipoOperacionCorte tipoOperacion;
  final int? turnoId;

  const CorteInventarioScreen({
    super.key,
    required this.tipoOperacion,
    this.turnoId,
  });

  @override
  ConsumerState<CorteInventarioScreen> createState() => _CorteInventarioScreenState();
}

class _CorteInventarioScreenState extends ConsumerState<CorteInventarioScreen> {
  bool _isLoading = false;
  bool _isSubmitting = false;
  String _tipoTurnoSeleccionado = 'dia';

  // Lista de productos con sus cantidades seleccionadas
  final List<Map<String, dynamic>> _items = [
    {
      'producto_id': 1,
      'nombre': 'Corona en Lata 355ml (Insumo)',
      'es_licor': false,
      'cantidad': 48.0,
    },
    {
      'producto_id': 2,
      'nombre': 'Corona en Botella 355ml (Terminado)',
      'es_licor': false,
      'cantidad': 24.0,
    },
    {
      'producto_id': 3,
      'nombre': 'Ron Flor de Caña 750ml',
      'es_licor': true,
      'cantidad': 3.75,
    },
    {
      'producto_id': 4,
      'nombre': 'Vodka Absolut 750ml',
      'es_licor': true,
      'cantidad': 2.50,
    },
    {
      'producto_id': 5,
      'nombre': 'Whisky Red Label 750ml',
      'es_licor': true,
      'cantidad': 1.25,
    },
  ];

  @override
  void initState() {
    super.initState();
    _cargarProductosRemotos();
  }

  Future<void> _cargarProductosRemotos() async {
    setState(() => _isLoading = true);
    try {
      final apiClient = ref.read(apiClientProvider);
      final res = await apiClient.get('/productos/corte');
      if (res.statusCode == 200 && res.data['data'] != null) {
        final List list = res.data['data'];
        if (list.isNotEmpty) {
          setState(() {
            _items.clear();
            for (var p in list) {
              final rawTipo = (p['tipo_producto'] ?? p['tipo'] ?? '').toString();
              final tipoBadge = rawTipo.isNotEmpty && rawTipo != 'null' ? ' (${rawTipo.toUpperCase()})' : '';
              final esLicor = rawTipo.toLowerCase().contains('terminado') &&
                  (p['nombre'].toString().toLowerCase().contains('ron') ||
                      p['nombre'].toString().toLowerCase().contains('vodka') ||
                      p['nombre'].toString().toLowerCase().contains('whisky') ||
                      p['nombre'].toString().toLowerCase().contains('tequila') ||
                      p['nombre'].toString().toLowerCase().contains('gin'));
              _items.add({
                'producto_id': p['id'],
                'nombre': '${p['nombre']}$tipoBadge',
                'es_licor': esLicor,
                'cantidad': 0.0,
              });
            }
          });
        }
      }
    } catch (_) {
      // Fallback a los datos por defecto si está offline
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _confirmarOperacion() async {
    final auth = ref.read(authProvider);
    final sucursalId = auth.sucursalId ?? 1;
    final sucursalNombre = auth.sucursalNombre ?? 'Sucursal $sucursalId';
    final barmanNombre = auth.nombre ?? 'Barman Turno';

    final cortesArray = _items
        .where((i) => (i['cantidad'] as double) >= 0)
        .map((i) => {
              'producto_id': i['producto_id'],
              'cantidad': i['cantidad'],
            })
        .toList();

    if (widget.tipoOperacion == TipoOperacionCorte.cierre) {
      final confirmar = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          backgroundColor: const Color(0xFF1B2332),
          title: const Text('¿Confirmar Cierre de Turno?', style: TextStyle(color: Colors.white)),
          content: const Text(
            '¡ATENCIÓN! Por regla constitucional de inmutabilidad, una vez cerrado el corte de inventario final, este no podrá modificarse.',
            style: TextStyle(color: Colors.white70),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(ctx).pop(false),
              child: const Text('Revisar', style: TextStyle(color: Colors.white60)),
            ),
            ElevatedButton(
              style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFFC0392B)),
              onPressed: () => Navigator.of(ctx).pop(true),
              child: const Text('Confirmar e Inmutabilizar', style: TextStyle(color: Colors.white)),
            ),
          ],
        ),
      );

      if (confirmar != true) return;
    }

    setState(() => _isSubmitting = true);

    try {
      final apiClient = ref.read(apiClientProvider);

      if (widget.tipoOperacion == TipoOperacionCorte.apertura) {
        final payload = {
          'sucursal_id': sucursalId,
          if (auth.usuarioId != null) 'barman_id': auth.usuarioId,
          'tipo_turno': _tipoTurnoSeleccionado,
          'corte_inicial': cortesArray,
        };

        final res = await apiClient.post('/turnos/abrir', data: payload);
        if (res.statusCode == 201) {
          final data = res.data['data'] as Map<String, dynamic>;
          final nuevoTurnoId = (data['turno_id'] ?? data['id']) as int;
          final tieneDiscrepancias = data['tiene_discrepancias'] == true;
          final discrepancias = (data['discrepancias'] as List?) ?? [];

          ref.read(authProvider.notifier).actualizarTurnoActivo(nuevoTurnoId);

          if (mounted) {
            // Si hay discrepancias con el turno saliente, mostrar alerta con WhatsApp
            if (tieneDiscrepancias && discrepancias.isNotEmpty) {
              await _mostrarAlertaDiscrepanciaApertura(
                context: context,
                turnoId: nuevoTurnoId,
                sucursal: sucursalNombre,
                barman: barmanNombre,
                discrepancias: discrepancias,
              );
            }

            // Mostrar Diálogo con Acciones de PDF para WhatsApp
            await showDialog(
              context: context,
              barrierDismissible: false,
              builder: (ctx) => AlertDialog(
                backgroundColor: const Color(0xFF1B2332),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                title: Row(
                  children: const [
                    Icon(Icons.check_circle, color: Color(0xFF27AE60), size: 28),
                    SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        '¡Turno de Barra Iniciado!',
                        style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
                      ),
                    ),
                  ],
                ),
                content: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Turno #$nuevoTurnoId aperturado con éxito.',
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'El conteo físico ha sido asentado inmutablemente. Puede compartir el acta oficial en PDF directamente al grupo de WhatsApp de supervisores.',
                      style: TextStyle(color: Colors.white70, fontSize: 13),
                    ),
                    const SizedBox(height: 18),
                    // Botón Compartir WhatsApp
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton.icon(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFF25D366),
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                        icon: const Icon(Icons.share, size: 20),
                        label: const Text('COMPARTIR EN WHATSAPP', style: TextStyle(fontWeight: FontWeight.bold)),
                        onPressed: () async {
                          await ConteoPdfService.compartirEnWhatsApp(
                            context: ctx,
                            turnoId: nuevoTurnoId,
                            sucursal: sucursalNombre,
                            barman: barmanNombre,
                            tipoTurno: _tipoTurnoSeleccionado,
                            items: _items,
                          );
                        },
                      ),
                    ),
                    const SizedBox(height: 10),
                    // Botón Ver / Imprimir PDF
                    SizedBox(
                      width: double.infinity,
                      child: OutlinedButton.icon(
                        style: OutlinedButton.styleFrom(
                          foregroundColor: const Color(0xFF3498DB),
                          side: const BorderSide(color: Color(0xFF3498DB)),
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                        icon: const Icon(Icons.picture_as_pdf, size: 20),
                        label: const Text('VER / IMPRIMIR PDF', style: TextStyle(fontWeight: FontWeight.bold)),
                        onPressed: () async {
                          await ConteoPdfService.previsualizarOImprimir(
                            context: ctx,
                            turnoId: nuevoTurnoId,
                            sucursal: sucursalNombre,
                            barman: barmanNombre,
                            tipoTurno: _tipoTurnoSeleccionado,
                            items: _items,
                          );
                        },
                      ),
                    ),
                  ],
                ),
                actions: [
                  TextButton(
                    onPressed: () {
                      Navigator.of(ctx).pop();
                      Navigator.of(context).pop();
                    },
                    child: const Text('Continuar a Barra', style: TextStyle(color: Colors.white60, fontSize: 13)),
                  ),
                ],
              ),
            );
            return;
          }
        }
      } else {
        // Cierre de Turno
        final turnoId = widget.turnoId ?? auth.turnoActivoId ?? 1;
        final payload = {
          'corte_final': cortesArray,
        };

        await apiClient.post('/turnos/$turnoId/cerrar', data: payload);
        ref.read(authProvider.notifier).actualizarTurnoActivo(null);

        if (mounted) {
          // Mostrar Acta Oficial de Cierre en PDF y botón de WhatsApp
          await showDialog(
            context: context,
            barrierDismissible: false,
            builder: (ctx) => AlertDialog(
              backgroundColor: const Color(0xFF1B2332),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              title: Row(
                children: const [
                  Icon(Icons.lock_clock, color: Color(0xFFE74C3C), size: 28),
                  SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      '¡Turno Cerrado con Éxito!',
                      style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
                    ),
                  ),
                ],
              ),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Turno #$turnoId cerrado y balance asentado.',
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Se ha generado el Acta Oficial de Cierre y Balance. Compártala por WhatsApp para respaldar la entrega de barra.',
                    style: TextStyle(color: Colors.white70, fontSize: 13),
                  ),
                  const SizedBox(height: 18),
                  // Botón Compartir WhatsApp
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF25D366),
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      ),
                      icon: const Icon(Icons.share, size: 20),
                      label: const Text('COMPARTIR EN WHATSAPP', style: TextStyle(fontWeight: FontWeight.bold)),
                      onPressed: () async {
                        await CierreTurnoPdfService.compartirEnWhatsApp(
                          context: ctx,
                          turnoId: turnoId,
                          sucursal: sucursalNombre,
                          barman: barmanNombre,
                          tipoTurno: _tipoTurnoSeleccionado,
                          items: _items,
                        );
                      },
                    ),
                  ),
                  const SizedBox(height: 10),
                  // Botón Ver / Imprimir PDF
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton.icon(
                      style: OutlinedButton.styleFrom(
                        foregroundColor: const Color(0xFF3498DB),
                        side: const BorderSide(color: Color(0xFF3498DB)),
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      ),
                      icon: const Icon(Icons.picture_as_pdf, size: 20),
                      label: const Text('VER / IMPRIMIR PDF', style: TextStyle(fontWeight: FontWeight.bold)),
                      onPressed: () async {
                        await CierreTurnoPdfService.previsualizarOImprimir(
                          context: ctx,
                          turnoId: turnoId,
                          sucursal: sucursalNombre,
                          barman: barmanNombre,
                          tipoTurno: _tipoTurnoSeleccionado,
                          items: _items,
                        );
                      },
                    ),
                  ),
                ],
              ),
              actions: [
                TextButton(
                  onPressed: () {
                    Navigator.of(ctx).pop();
                    Navigator.of(context).pop();
                  },
                  child: const Text('Finalizar', style: TextStyle(color: Colors.white60, fontSize: 13)),
                ),
              ],
            ),
          );
          return;
        }
      }
    } catch (e) {
      String mensaje = e.toString();
      if (e is DioException) {
        final data = e.response?.data;
        if (data is Map && data['error'] != null) {
          mensaje = data['error'].toString();
        } else if (data is Map && data['message'] != null) {
          mensaje = data['message'].toString();
        }
      }

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error: $mensaje'),
            backgroundColor: Colors.redAccent,
            duration: const Duration(seconds: 5),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  Future<void> _mostrarAlertaDiscrepanciaApertura({
    required BuildContext context,
    required int turnoId,
    required String sucursal,
    required String barman,
    required List discrepancias,
  }) async {
    // Redactar mensaje pre-llenado de WhatsApp para Daniel (67369293)
    final buffer = StringBuffer();
    buffer.writeln('🚨 *ALERTA PUNTO FRÍO - DISCREPANCIA EN APERTURA*');
    buffer.writeln('📍 *Sucursal:* $sucursal');
    buffer.writeln('👤 *Barman Entrante:* $barman');
    buffer.writeln('🕒 *Turno Entrante:* #$turnoId');
    buffer.writeln('⚠️ *Detalle de Faltantes respecto al Cierre Anterior:*');
    for (final d in discrepancias) {
      final prod = d['producto_nombre'] ?? 'Producto #${d['producto_id']}';
      final esp = d['stock_esperado'];
      final dec = d['stock_declarado'];
      final dif = d['diferencia'];
      buffer.writeln('• $prod: Esperado $esp | Declarado $dec (Diferencia: $dif)');
    }
    buffer.writeln('');
    buffer.writeln('Favor verificar de inmediato con el turno saliente.');

    final encodedText = Uri.encodeComponent(buffer.toString());
    final urlWhatsApp = Uri.parse('https://wa.me/59167369293?text=$encodedText');

    await showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        backgroundColor: const Color(0xFF1E1B28),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: const BorderSide(color: Color(0xFFE74C3C), width: 2),
        ),
        title: Row(
          children: const [
            Icon(Icons.warning_amber_rounded, color: Color(0xFFE74C3C), size: 30),
            SizedBox(width: 10),
            Expanded(
              child: Text(
                '¡DISCREPANCIA EN APERTURA!',
                style: TextStyle(color: Color(0xFFE74C3C), fontSize: 16, fontWeight: FontWeight.bold),
              ),
            ),
          ],
        ),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'El conteo físico de apertura no coincide con el corte final registrado por el turno anterior en esta sucursal:',
                style: TextStyle(color: Colors.white70, fontSize: 13),
              ),
              const SizedBox(height: 12),
              ...discrepancias.map((d) {
                final prod = d['producto_nombre'] ?? 'Producto #${d['producto_id']}';
                final esp = d['stock_esperado'];
                final dec = d['stock_declarado'];
                final dif = d['diferencia'];
                return Container(
                  margin: const EdgeInsets.only(bottom: 8),
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: const Color(0xFF2C1E2B),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: Colors.redAccent.withOpacity(0.4)),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Text(
                          prod.toString(),
                          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13),
                        ),
                      ),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text('Esperado: $esp | Entrante: $dec',
                              style: const TextStyle(color: Colors.white60, fontSize: 11)),
                          Text(
                            'Dif: $dif botellas',
                            style: const TextStyle(color: Colors.redAccent, fontWeight: FontWeight.bold, fontSize: 12),
                          ),
                        ],
                      ),
                    ],
                  ),
                );
              }),
              const SizedBox(height: 16),
              // Botón Verde Directo a WhatsApp de Daniel (67369293)
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF25D366),
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  icon: const Icon(Icons.chat, size: 20),
                  label: const Text(
                    '📱 NOTIFICAR A DANIEL POR WHATSAPP',
                    style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold),
                  ),
                  onPressed: () async {
                    if (await canLaunchUrl(urlWhatsApp)) {
                      await launchUrl(urlWhatsApp, mode: LaunchMode.externalApplication);
                    }
                  },
                ),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('Entendido / Continuar', style: TextStyle(color: Colors.white60)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final esApertura = widget.tipoOperacion == TipoOperacionCorte.apertura;

    return Scaffold(
      backgroundColor: const Color(0xFF121620),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1B2332),
        title: Text(
          esApertura ? 'Corte de Apertura' : 'Corte de Cierre',
          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
        ),
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Colors.amberAccent))
          : Column(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                  color: esApertura
                      ? const Color(0xFF2980B9).withOpacity(0.2)
                      : const Color(0xFFC0392B).withOpacity(0.2),
                  child: Row(
                    children: [
                      Icon(
                        esApertura ? Icons.login : Icons.lock_clock,
                        color: esApertura ? const Color(0xFF5DADE2) : const Color(0xFFE74C3C),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          esApertura
                              ? 'Ingrese el conteo inicial físico al recibir la barra.'
                              : 'Conteo final al entregar el turno. Medición estricta en múltiplos de 1/4.',
                          style: const TextStyle(color: Colors.white70, fontSize: 13),
                        ),
                      ),
                    ],
                  ),
                ),
                if (esApertura)
                  Padding(
                    padding: const EdgeInsets.all(12.0),
                    child: Wrap(
                      spacing: 8,
                      runSpacing: 4,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        const Text('Jornada:', style: TextStyle(color: Colors.white70, fontWeight: FontWeight.bold)),
                        ChoiceChip(
                          label: const Text('Día (12h - Cobro diario)'),
                          selected: _tipoTurnoSeleccionado == 'dia',
                          selectedColor: const Color(0xFFE67E22),
                          labelStyle: const TextStyle(color: Colors.white),
                          onSelected: (val) {
                            if (val) setState(() => _tipoTurnoSeleccionado = 'dia');
                          },
                        ),
                        ChoiceChip(
                          label: const Text('Noche (12h - Semanal)'),
                          selected: _tipoTurnoSeleccionado == 'noche',
                          selectedColor: const Color(0xFF8E44AD),
                          labelStyle: const TextStyle(color: Colors.white),
                          onSelected: (val) {
                            if (val) setState(() => _tipoTurnoSeleccionado = 'noche');
                          },
                        ),
                      ],
                    ),
                  ),
                Expanded(
                  child: ListView.builder(
                    padding: const EdgeInsets.all(12),
                    itemCount: _items.length,
                    itemBuilder: (context, index) {
                      final item = _items[index];
                      return BottleFractionSelector(
                        productName: item['nombre'],
                        value: item['cantidad'] as double,
                        onChanged: (newVal) {
                          setState(() {
                            item['cantidad'] = newVal;
                          });
                        },
                      );
                    },
                  ),
                ),
                Container(
                  padding: const EdgeInsets.all(16),
                  color: const Color(0xFF1B2332),
                  child: SafeArea(
                    child: ElevatedButton(
                      onPressed: _isSubmitting ? null : _confirmarOperacion,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: esApertura ? const Color(0xFF2980B9) : const Color(0xFFC0392B),
                        minimumSize: const Size.fromHeight(52),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      child: _isSubmitting
                          ? const CircularProgressIndicator(color: Colors.white)
                          : Text(
                              esApertura ? 'CONFIRMAR Y ABRIR TURNO' : 'CONFIRMAR Y CERRAR TURNO',
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 16,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                    ),
                  ),
                ),
              ],
            ),
    );
  }
}
