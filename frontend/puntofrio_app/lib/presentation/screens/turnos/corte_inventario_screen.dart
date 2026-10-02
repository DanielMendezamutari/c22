import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/bottle_fraction_selector.dart';
import 'cierre_turno_pdf_service.dart';
import 'conteo_pdf_service.dart';

enum TipoOperacionCorte { apertura, cierre }

class CorteInventarioScreen extends ConsumerStatefulWidget {
  final TipoOperacionCorte tipoOperacion;
  final int? turnoId;
  final bool esSuplencia;
  final int? barmanSuplidoId;
  final bool esModoReconteo;
  final String? reconteoTipo;

  const CorteInventarioScreen({
    super.key,
    TipoOperacionCorte? tipoOperacion,
    bool esCierre = false,
    this.turnoId,
    this.esSuplencia = false,
    this.barmanSuplidoId,
    this.esModoReconteo = false,
    this.reconteoTipo,
  }) : tipoOperacion = tipoOperacion ?? (esCierre ? TipoOperacionCorte.cierre : TipoOperacionCorte.apertura);

  @override
  ConsumerState<CorteInventarioScreen> createState() => _CorteInventarioScreenState();
}

class _CorteInventarioScreenState extends ConsumerState<CorteInventarioScreen> {
  bool _isLoading = false;
  bool _isSubmitting = false;
  String _tipoTurnoSeleccionado = 'dia';

  // Buscador reactivo
  final TextEditingController _busquedaController = TextEditingController();
  String _filtroBusqueda = '';

  // Lista de productos con sus cantidades seleccionadas
  final List<Map<String, dynamic>> _items = [];

  String get _draftKey {
    final sucursalId = ref.read(authProvider).sucursalId ?? 1;
    return 'corte_draft_${sucursalId}_${widget.tipoOperacion.name}';
  }

  @override
  void initState() {
    super.initState();
    _cargarProductosRemotos();
  }

  @override
  void dispose() {
    _busquedaController.dispose();
    super.dispose();
  }

  List<Map<String, dynamic>> get _itemsFiltrados {
    if (_filtroBusqueda.trim().isEmpty) {
      return _items;
    }
    final q = _filtroBusqueda.toLowerCase().trim();
    return _items.where((i) {
      final n = (i['nombre'] ?? '').toString().toLowerCase();
      final pn = (i['producto_nombre'] ?? '').toString().toLowerCase();
      final prov = (i['nombre_provisional'] ?? '').toString().toLowerCase();
      return n.contains(q) || pn.contains(q) || prov.contains(q);
    }).toList();
  }

  Future<void> _guardarBorrador() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final data = _items.map((i) => {
        'producto_id': i['producto_id'],
        'nombre': i['nombre'],
        'producto_nombre': i['producto_nombre'],
        'nombre_provisional': i['nombre_provisional'],
        'es_provisional': i['es_provisional'] ?? false,
        'es_licor': i['es_licor'] ?? false,
        'cantidad': i['cantidad'] ?? 0.0,
        'cantidad_inicial': i['cantidad_inicial'] ?? 0.0,
        'ingresos': i['ingresos'] ?? 0.0,
        'rellenos': i['rellenos'] ?? 0.0,
        'bajas': i['bajas'] ?? 0.0,
        'total_disponible': i['total_disponible'] ?? 0.0,
      }).toList();
      await prefs.setString(_draftKey, jsonEncode(data));
    } catch (_) {}
  }

  Future<void> _restaurarBorradorSiExiste() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_draftKey);
      if (raw != null && raw.isNotEmpty) {
        final List list = jsonDecode(raw);
        if (list.isNotEmpty) {
          final Map<String, double> draftMap = {};
          final List<Map<String, dynamic>> provisionalesDraft = [];

          for (var it in list) {
            if (it['es_provisional'] == true) {
              provisionalesDraft.add(Map<String, dynamic>.from(it));
            } else if (it['producto_id'] != null) {
              draftMap['p_${it['producto_id']}'] = (it['cantidad'] as num?)?.toDouble() ?? 0.0;
            }
          }

          bool huboRestauracion = false;
          for (var item in _items) {
            final pid = item['producto_id'];
            if (pid != null && draftMap.containsKey('p_$pid')) {
              final cant = draftMap['p_$pid']!;
              if (cant > 0) {
                item['cantidad'] = cant;
                item['cantidad_inicial'] = cant;
                huboRestauracion = true;
              }
            }
          }

          for (var prov in provisionalesDraft) {
            final yaExiste = _items.any((i) =>
                i['es_provisional'] == true &&
                i['nombre_provisional'] == prov['nombre_provisional']);
            if (!yaExiste) {
              _items.insert(0, prov);
              huboRestauracion = true;
            }
          }

          if (huboRestauracion && mounted) {
            setState(() {});
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(
                backgroundColor: Color(0xFF1E3A8A),
                duration: Duration(seconds: 2),
                content: Text('💾 Borrador local recuperado. Cantidades intactas.'),
              ),
            );
          }
        }
      }
    } catch (_) {}
  }

  Future<void> _purgarBorrador() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_draftKey);
    } catch (_) {}
  }

  Future<void> _cargarProductosRemotos({
    bool preservarCantidades = false,
    Map<String, double>? cantidadesRespaldo,
    List<Map<String, dynamic>>? provisionalesRespaldo,
  }) async {
    setState(() => _isLoading = true);
    try {
      final apiClient = ref.read(apiClientProvider);
      final auth = ref.read(authProvider);
      final turnoId = widget.turnoId ?? auth.turnoActivoId;

      List<Map<String, dynamic>> nuevosItems = [];

      // Si es Modo Reconteo o Cierre de Turno, cargar el corte inicial/acumulado del turno
      final bool esReconteo = widget.esModoReconteo;
      if ((widget.tipoOperacion == TipoOperacionCorte.cierre || esReconteo) && turnoId != null) {
        final resTurno = await apiClient.get('/turnos/$turnoId/corte-inicial');
        if (resTurno.statusCode == 200 && resTurno.data['data'] != null && resTurno.data['data']['items'] != null) {
          final List list = resTurno.data['data']['items'];
          for (var p in list) {
            final rawTipo = (p['tipo_producto'] ?? p['tipo'] ?? '').toString();
            final tipoBadge = rawTipo.isNotEmpty && rawTipo != 'null' ? ' (${rawTipo.toUpperCase()})' : '';
            final nombreBase = (p['nombre'] ?? p['producto_nombre'] ?? 'Producto').toString();
            final esLicor = (p['es_licor'] == true) ||
                (rawTipo.toLowerCase().contains('terminado') &&
                    (nombreBase.toLowerCase().contains('ron') ||
                        nombreBase.toLowerCase().contains('vodka') ||
                        nombreBase.toLowerCase().contains('whisky') ||
                        nombreBase.toLowerCase().contains('tequila') ||
                        nombreBase.toLowerCase().contains('gin')));

            final pid = p['producto_id'] ?? p['id'];
            final cantIni = (p['cantidad_inicial'] as num?)?.toDouble() ?? 0.0;
            final keyPid = pid != null ? 'p_$pid' : null;

            // En reconteo de apertura tomamos cantidad_inicial/cantidad; en reconteo de cierre tomamos cantidad_final/cantidad
            double cantBase = 0.0;
            if (esReconteo) {
              if (widget.reconteoTipo == 'cierre' || widget.tipoOperacion == TipoOperacionCorte.cierre) {
                cantBase = (p['cantidad_final'] as num?)?.toDouble() ?? (p['cantidad'] as num?)?.toDouble() ?? cantIni;
              } else {
                cantBase = (p['cantidad_inicial'] as num?)?.toDouble() ?? (p['cantidad'] as num?)?.toDouble() ?? 0.0;
              }
            }

            double cantPreservada = cantBase;
            if (preservarCantidades && keyPid != null && cantidadesRespaldo != null && cantidadesRespaldo.containsKey(keyPid)) {
              cantPreservada = cantidadesRespaldo[keyPid]!;
            }

            nuevosItems.add({
              'producto_id': pid,
              'nombre': '$nombreBase$tipoBadge',
              'producto_nombre': nombreBase,
              'nombre_provisional': p['nombre_provisional'],
              'es_provisional': p['es_provisional'] == true,
              'es_licor': esLicor,
              'cantidad': cantPreservada,
              'cantidad_inicial': cantIni,
              'ingresos': (p['ingresos'] as num?)?.toDouble() ?? 0.0,
              'rellenos': (p['rellenos'] as num?)?.toDouble() ?? 0.0,
              'bajas': (p['bajas'] as num?)?.toDouble() ?? 0.0,
              'total_disponible': (p['total_disponible'] as num?)?.toDouble() ?? (cantIni + ((p['ingresos'] as num?)?.toDouble() ?? 0.0)),
            });
          }
        }
      } else {
        // Apertura o fallback: cargar catálogo general de productos activos
        final res = await apiClient.get('/productos/corte');
        if (res.statusCode == 200 && res.data['data'] != null) {
          final List list = res.data['data'];
          for (var p in list) {
            final rawTipo = (p['tipo_producto'] ?? p['tipo'] ?? '').toString();
            final tipoBadge = rawTipo.isNotEmpty && rawTipo != 'null' ? ' (${rawTipo.toUpperCase()})' : '';
            final esLicor = (p['unidad_medida'] == 'fraccion_cuartos') ||
                (rawTipo.toLowerCase().contains('terminado') &&
                    (p['nombre'].toString().toLowerCase().contains('ron') ||
                        p['nombre'].toString().toLowerCase().contains('vodka') ||
                        p['nombre'].toString().toLowerCase().contains('whisky') ||
                        p['nombre'].toString().toLowerCase().contains('tequila') ||
                        p['nombre'].toString().toLowerCase().contains('gin')));

            final pid = p['id'] as int;
            final keyPid = 'p_$pid';

            double cantPreservada = 0.0;
            if (preservarCantidades && cantidadesRespaldo != null && cantidadesRespaldo.containsKey(keyPid)) {
              cantPreservada = cantidadesRespaldo[keyPid]!;
            }

            nuevosItems.add({
              'producto_id': pid,
              'nombre': '${p['nombre']}$tipoBadge',
              'producto_nombre': p['nombre'],
              'nombre_provisional': null,
              'es_provisional': false,
              'es_licor': esLicor,
              'cantidad': cantPreservada,
              'cantidad_inicial': cantPreservada,
              'ingresos': 0.0,
              'rellenos': 0.0,
              'bajas': 0.0,
              'total_disponible': cantPreservada,
            });
          }
        }
      }

      // Re-incorporar productos provisionales respaldados
      if (provisionalesRespaldo != null && provisionalesRespaldo.isNotEmpty) {
        for (var prov in provisionalesRespaldo) {
          final yaEsta = nuevosItems.any((i) =>
              i['es_provisional'] == true &&
              i['nombre_provisional'] == prov['nombre_provisional']);
          if (!yaEsta) {
            nuevosItems.insert(0, prov);
          }
        }
      }

      if (nuevosItems.isNotEmpty) {
        setState(() {
          _items.clear();
          _items.addAll(nuevosItems);
        });
      }

      if (!preservarCantidades) {
        await _restaurarBorradorSiExiste();
      }
    } catch (_) {
      // Fallback local si ocurre falla de red
      if (_items.isEmpty) {
        _items.addAll([
          {
            'producto_id': 1,
            'nombre': 'Corona en Lata 355ml (Insumo)',
            'producto_nombre': 'Corona en Lata 355ml',
            'es_licor': false,
            'cantidad': 0.0,
            'cantidad_inicial': 0.0,
            'ingresos': 0.0,
            'rellenos': 0.0,
            'bajas': 0.0,
            'total_disponible': 0.0,
          },
          {
            'producto_id': 2,
            'nombre': 'Corona en Botella 355ml (Terminado)',
            'producto_nombre': 'Corona en Botella 355ml',
            'es_licor': false,
            'cantidad': 0.0,
            'cantidad_inicial': 0.0,
            'ingresos': 0.0,
            'rellenos': 0.0,
            'bajas': 0.0,
            'total_disponible': 0.0,
          },
        ]);
        await _restaurarBorradorSiExiste();
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _refrescarCatalogo() async {
    // Respaldar cantidades actuales
    final Map<String, double> respaldo = {};
    final List<Map<String, dynamic>> provisionales = [];

    for (var it in _items) {
      if (it['es_provisional'] == true) {
        provisionales.add(Map<String, dynamic>.from(it));
      } else if (it['producto_id'] != null) {
        respaldo['p_${it['producto_id']}'] = (it['cantidad'] as num?)?.toDouble() ?? 0.0;
      }
    }

    await _cargarProductosRemotos(
      preservarCantidades: true,
      cantidadesRespaldo: respaldo,
      provisionalesRespaldo: provisionales,
    );

    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          backgroundColor: Colors.green,
          duration: Duration(seconds: 2),
          content: Text('✅ Catálogo actualizado sin perder tus cantidades contadas.'),
        ),
      );
    }
  }

  Future<void> _mostrarModalProductoNoListado() async {
    final nombreCtrl = TextEditingController();
    final cantidadCtrl = TextEditingController(text: '1.0');
    bool esLicor = false;

    final res = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (dctx) {
        return StatefulBuilder(
          builder: (context, setDState) {
            return AlertDialog(
              backgroundColor: const Color(0xFF1B2332),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              title: const Row(
                children: [
                  Icon(Icons.add_shopping_cart, color: Colors.amberAccent),
                  SizedBox(width: 8),
                  Text('Producto No Listado', style: TextStyle(color: Colors.white, fontSize: 18)),
                ],
              ),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Si llegó un producto nuevo que no figura en el catálogo, puedes contabilizarlo provisionalmente. Se emitirá una alerta al Administrador para su validación oficial.',
                      style: TextStyle(color: Colors.white70, fontSize: 12),
                    ),
                    const SizedBox(height: 14),
                    TextField(
                      controller: nombreCtrl,
                      style: const TextStyle(color: Colors.white),
                      decoration: InputDecoration(
                        labelText: 'Nombre del Producto *',
                        labelStyle: const TextStyle(color: Colors.amberAccent),
                        hintText: 'Ej. Fernet Menta 750ml, Monster Energy',
                        hintStyle: const TextStyle(color: Colors.white38),
                        filled: true,
                        fillColor: const Color(0xFF26324A),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                      ),
                    ),
                    const SizedBox(height: 14),
                    TextField(
                      controller: cantidadCtrl,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      style: const TextStyle(color: Colors.white),
                      decoration: InputDecoration(
                        labelText: 'Cantidad Física Contada *',
                        labelStyle: const TextStyle(color: Colors.amberAccent),
                        hintText: 'Ej. 1.0, 2.50, 6.0',
                        hintStyle: const TextStyle(color: Colors.white38),
                        filled: true,
                        fillColor: const Color(0xFF26324A),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                      ),
                    ),
                    const SizedBox(height: 14),
                    SwitchListTile(
                      title: const Text('¿Es licor fraccionable?', style: TextStyle(color: Colors.white, fontSize: 14)),
                      subtitle: const Text('Medición en cuartos de botella', style: TextStyle(color: Colors.white54, fontSize: 11)),
                      value: esLicor,
                      activeColor: Colors.amberAccent,
                      onChanged: (val) => setDState(() => esLicor = val),
                      contentPadding: EdgeInsets.zero,
                    ),
                  ],
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(dctx),
                  child: const Text('Cancelar', style: TextStyle(color: Colors.white54)),
                ),
                ElevatedButton(
                  style: ElevatedButton.styleFrom(backgroundColor: Colors.amberAccent, foregroundColor: Colors.black),
                  onPressed: () {
                    final nom = nombreCtrl.text.trim();
                    final cant = double.tryParse(cantidadCtrl.text.trim()) ?? 0.0;
                    if (nom.isEmpty) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(backgroundColor: Colors.orange, content: Text('El nombre del producto es obligatorio')),
                      );
                      return;
                    }
                    Navigator.pop(dctx, {
                      'nombre': nom,
                      'cantidad': cant,
                      'es_licor': esLicor,
                    });
                  },
                  child: const Text('Agregar a Conteo', style: TextStyle(fontWeight: FontWeight.bold)),
                ),
              ],
            );
          },
        );
      },
    );

    if (res != null) {
      setState(() {
        _items.insert(0, {
          'producto_id': null,
          'nombre': '${res['nombre']} (PROVISIONAL)',
          'producto_nombre': res['nombre'],
          'nombre_provisional': res['nombre'],
          'es_provisional': true,
          'es_licor': res['es_licor'],
          'cantidad': res['cantidad'],
          'cantidad_inicial': res['cantidad'],
          'ingresos': 0.0,
          'rellenos': 0.0,
          'bajas': 0.0,
          'total_disponible': res['cantidad'],
        });
      });
      _guardarBorrador();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            backgroundColor: Colors.orange,
            content: Text('⚠️ "${res['nombre']}" agregado al conteo. Se alertará al Administrador.'),
          ),
        );
      }
    }
  }

  Future<void> _confirmarOperacion() async {
    final auth = ref.read(authProvider);
    final sucursalId = auth.sucursalId ?? 1;
    final sucursalNombre = auth.sucursalNombre ?? 'Sucursal $sucursalId';
    final barmanNombre = auth.nombre ?? 'Barman Turno';

    final cortesArray = _items
        .where((i) => ((i['cantidad'] as num?)?.toDouble() ?? 0.0) >= 0)
        .map((i) {
          final esProv = i['es_provisional'] == true;
          return {
            'producto_id': esProv ? null : i['producto_id'],
            'cantidad': (i['cantidad'] as num?)?.toDouble() ?? 0.0,
            'es_provisional': esProv,
            'nombre_provisional': esProv ? i['nombre_provisional'] : null,
            'es_licor': i['es_licor'] == true,
          };
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

      if (widget.esModoReconteo) {
        final turnoId = widget.turnoId ?? auth.turnoActivoId;
        if (turnoId == null) {
          throw Exception('No se encontró el turno activo para aplicar el reconteo.');
        }

        final reconTipo = widget.reconteoTipo ?? (widget.tipoOperacion == TipoOperacionCorte.cierre ? 'cierre' : 'apertura');
        final payload = {
          'tipo': reconTipo,
          'corte_corregido': cortesArray,
        };

        final res = await apiClient.post('/turnos/$turnoId/aplicar-reconteo', data: payload);
        if (res.statusCode == 200) {
          await _purgarBorrador();

          for (var it in _items) {
            final c = (it['cantidad'] as num?)?.toDouble() ?? 0.0;
            if (reconTipo == 'apertura') {
              it['cantidad_inicial'] = c;
              final ing = (it['ingresos'] as num?)?.toDouble() ?? 0.0;
              it['total_disponible'] = c + ing;
            } else {
              it['cantidad_final'] = c;
            }
          }

          if (mounted) {
            await showDialog(
              context: context,
              barrierDismissible: false,
              builder: (ctx) => AlertDialog(
                backgroundColor: const Color(0xFF1B2332),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                title: Row(
                  children: const [
                    Icon(Icons.check_circle, color: Colors.amberAccent, size: 28),
                    SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        '¡Reconteo Sellado!',
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
                      'Se ha aplicado y re-sellado la corrección de ${reconTipo.toUpperCase()} para el Turno #$turnoId.',
                      style: const TextStyle(color: Colors.white70, fontSize: 14),
                    ),
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: Colors.amber.withOpacity(0.1),
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: Colors.amberAccent.withOpacity(0.3)),
                      ),
                      child: const Row(
                        children: [
                          Icon(Icons.lock_outline, color: Colors.amberAccent, size: 20),
                          SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              'El inventario fue recalculado y se registró en la auditoría del turno.',
                              style: TextStyle(color: Colors.amberAccent, fontSize: 12),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                actions: [
                  TextButton.icon(
                    icon: const Icon(Icons.picture_as_pdf, color: Colors.amberAccent),
                    label: const Text('Ver Acta Corregida', style: TextStyle(color: Colors.amberAccent)),
                    onPressed: () {
                      final turnoPdfData = {
                        'id': turnoId,
                        'turno_id': turnoId,
                        'sucursal': sucursalNombre,
                        'barman': barmanNombre,
                        'tipo_turno': _tipoTurnoSeleccionado,
                        'fecha_apertura': DateTime.now().toIso8601String(),
                        'fecha_cierre': DateTime.now().toIso8601String(),
                        'es_reconteo': true,
                      };
                      if (reconTipo == 'cierre') {
                        CierreTurnoPdfService.previsualizarOImprimir(
                          context: context,
                          turnoId: turnoId,
                          sucursal: sucursalNombre,
                          barman: barmanNombre,
                          tipoTurno: _tipoTurnoSeleccionado,
                          items: _items,
                          turnoData: turnoPdfData,
                        );
                      } else {
                        ConteoPdfService.previsualizarOImprimir(
                          context: context,
                          turnoId: turnoId,
                          sucursal: sucursalNombre,
                          barman: barmanNombre,
                          tipoTurno: _tipoTurnoSeleccionado,
                          items: _items,
                          turnoData: turnoPdfData,
                        );
                      }
                    },
                  ),
                  ElevatedButton(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF2980B9),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                    ),
                    onPressed: () => Navigator.of(ctx).pop(),
                    child: const Text('Continuar', style: TextStyle(color: Colors.white)),
                  ),
                ],
              ),
            );

            if (mounted) Navigator.of(context).pop(true);
          }
        }
      } else if (widget.tipoOperacion == TipoOperacionCorte.apertura) {
        final payload = {
          'sucursal_id': sucursalId,
          'barman_id': widget.barmanSuplidoId ?? auth.usuarioId,
          'tipo_turno': _tipoTurnoSeleccionado,
          'es_suplencia': widget.esSuplencia,
          if (widget.esSuplencia) 'realizado_por_usuario_id': auth.usuarioId,
          'corte_inicial': cortesArray,
        };

        final res = await apiClient.post('/turnos/abrir', data: payload);
        if (res.statusCode == 201) {
          final data = res.data['data'] as Map<String, dynamic>;
          final nuevoTurnoId = (data['turno_id'] ?? data['id']) as int;
          final tieneDiscrepancias = res.data['tiene_discrepancias'] == true;
          final discrepancias = (res.data['discrepancias'] as List?) ?? [];

          // Purgar borrador local
          await _purgarBorrador();

          if (!widget.esSuplencia) {
            ref.read(authProvider.notifier).actualizarTurnoActivo(nuevoTurnoId);
          }

          // Sincronizar _items para el PDF
          for (var it in _items) {
            final c = (it['cantidad'] as num?)?.toDouble() ?? 0.0;
            it['cantidad_inicial'] = c;
            final ing = (it['ingresos'] as num?)?.toDouble() ?? 0.0;
            it['total_disponible'] = c + ing;
          }


          if (mounted) {
            await showDialog(
              context: context,
              barrierDismissible: false,
              builder: (ctx) => AlertDialog(
                backgroundColor: const Color(0xFF1B2332),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                title: Row(
                  children: const [
                    Icon(Icons.check_circle_outline, color: Color(0xFF2ECC71), size: 28),
                    SizedBox(width: 8),
                    Text('¡Turno de Barra Iniciado!', style: TextStyle(color: Colors.white, fontSize: 18)),
                  ],
                ),
                content: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Turno #$nuevoTurnoId aperturado exitosamente en $sucursalNombre.',
                      style: const TextStyle(color: Colors.white70, fontSize: 14),
                    ),
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: const Color(0xFF26324A),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            '📄 Acta Oficial de Conteo Inicial',
                            style: TextStyle(color: Colors.amberAccent, fontWeight: FontWeight.bold, fontSize: 13),
                          ),
                          const SizedBox(height: 6),
                          const Text(
                            'Se ha generado el PDF con el balance físico recibido. Puedes imprimirlo o compartirlo de inmediato por WhatsApp:',
                            style: TextStyle(color: Colors.white60, fontSize: 12),
                          ),
                          const SizedBox(height: 12),
                          Row(
                            children: [
                              Expanded(
                                child: OutlinedButton.icon(
                                  style: OutlinedButton.styleFrom(
                                    foregroundColor: Colors.white,
                                    side: const BorderSide(color: Colors.white24),
                                    padding: const EdgeInsets.symmetric(vertical: 10),
                                  ),
                                  icon: const Icon(Icons.print, size: 16),
                                  label: const Text('VER / IMPRIMIR PDF', style: TextStyle(fontSize: 11)),
                                  onPressed: () {
                                    ConteoPdfService.previsualizarOImprimir(
                                      context: ctx,
                                      turnoId: nuevoTurnoId,
                                      sucursal: sucursalNombre,
                                      barman: barmanNombre,
                                      tipoTurno: _tipoTurnoSeleccionado,
                                      items: List<Map<String, dynamic>>.from(_items),
                                    );
                                  },
                                ),
                              ),
                              const SizedBox(width: 8),
                              Expanded(
                                child: ElevatedButton.icon(
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: const Color(0xFF25D366),
                                    foregroundColor: Colors.white,
                                    padding: const EdgeInsets.symmetric(vertical: 10),
                                  ),
                                  icon: const Icon(Icons.share, size: 16),
                                  label: const Text('COMPARTIR EN WHATSAPP', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                                  onPressed: () {
                                    ConteoPdfService.compartirEnWhatsApp(
                                      context: ctx,
                                      turnoId: nuevoTurnoId,
                                      sucursal: sucursalNombre,
                                      barman: barmanNombre,
                                      tipoTurno: _tipoTurnoSeleccionado,
                                      items: List<Map<String, dynamic>>.from(_items),
                                    );
                                  },
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                actions: [
                  ElevatedButton(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF2980B9),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                    ),
                    onPressed: () => Navigator.of(ctx).pop(),
                    child: const Text('Continuar al Panel', style: TextStyle(color: Colors.white)),
                  ),
                ],
              ),
            );

            if (tieneDiscrepancias && discrepancias.isNotEmpty && mounted) {
              await _mostrarModalDiscrepancias(
                context,
                discrepancias,
                nuevoTurnoId,
                sucursalNombre,
                barmanNombre,
              );
            }

            if (mounted) Navigator.of(context).pop(true);
          }
        }
      } else {
        // Cierre de Turno
        final turnoId = widget.turnoId ?? auth.turnoActivoId;
        if (turnoId == null) {
          throw Exception('No se encontró un turno activo para cerrar.');
        }

        final payload = {
          'corte_final': cortesArray,
          'es_suplencia': widget.esSuplencia,
          if (widget.esSuplencia) 'cerrado_por_usuario_id': auth.usuarioId,
        };

        final res = await apiClient.post('/turnos/$turnoId/cerrar', data: payload);
        if (res.statusCode == 200) {
          await _purgarBorrador();

          if (!widget.esSuplencia) {
            ref.read(authProvider.notifier).actualizarTurnoActivo(null);
          }

          final turnoData = {
            'id': turnoId,
            'turno_id': turnoId,
            'sucursal': sucursalNombre,
            'barman': barmanNombre,
            'tipo_turno': _tipoTurnoSeleccionado,
            'fecha_cierre': DateTime.now().toIso8601String(),
            'es_suplencia': widget.esSuplencia,
            'cerrado_por': widget.esSuplencia ? auth.nombre : null,
          };

          if (mounted) {
            await showDialog(
              context: context,
              barrierDismissible: false,
              builder: (ctx) => AlertDialog(
                backgroundColor: const Color(0xFF1B2332),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                title: Row(
                  children: const [
                    Icon(Icons.check_circle_outline, color: Color(0xFF2ECC71), size: 28),
                    SizedBox(width: 8),
                    Text('¡Turno Cerrado!', style: TextStyle(color: Colors.white, fontSize: 18)),
                  ],
                ),
                content: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Turno #$turnoId cerrado exitosamente en $sucursalNombre.',
                      style: const TextStyle(color: Colors.white70, fontSize: 14),
                    ),
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: const Color(0xFF26324A),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            '📜 Acta Oficial de Cierre y Balance',
                            style: TextStyle(color: Colors.amberAccent, fontWeight: FontWeight.bold, fontSize: 13),
                          ),
                          const SizedBox(height: 6),
                          const Text(
                            'Se ha generado el Acta en PDF con el inventario físico entregado.',
                            style: TextStyle(color: Colors.white60, fontSize: 12),
                          ),
                          const SizedBox(height: 12),
                          Row(
                            children: [
                              Expanded(
                                child: OutlinedButton.icon(
                                  style: OutlinedButton.styleFrom(
                                    foregroundColor: Colors.white,
                                    side: const BorderSide(color: Colors.white24),
                                    padding: const EdgeInsets.symmetric(vertical: 10),
                                  ),
                                  icon: const Icon(Icons.print, size: 16),
                                  label: const Text('VER ACTA', style: TextStyle(fontSize: 11)),
                                  onPressed: () {
                                    CierreTurnoPdfService.imprimir(
                                      context: ctx,
                                      turnoData: turnoData,
                                      items: List<Map<String, dynamic>>.from(_items),
                                    );
                                  },
                                ),
                              ),
                              const SizedBox(width: 8),
                              Expanded(
                                child: ElevatedButton.icon(
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: const Color(0xFF25D366),
                                    foregroundColor: Colors.white,
                                    padding: const EdgeInsets.symmetric(vertical: 10),
                                  ),
                                  icon: const Icon(Icons.share, size: 16),
                                  label: const Text('COMPARTIR EN WHATSAPP', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                                  onPressed: () {
                                    CierreTurnoPdfService.compartirEnWhatsApp(
                                      context: ctx,
                                      turnoData: turnoData,
                                      items: List<Map<String, dynamic>>.from(_items),
                                    );
                                  },
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                actions: [
                  ElevatedButton(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFFC0392B),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                    ),
                    onPressed: () => Navigator.of(ctx).pop(),
                    child: const Text('Finalizar', style: TextStyle(color: Colors.white)),
                  ),
                ],
              ),
            );

            if (mounted) Navigator.of(context).pop(true);
          }
        }
      }
    } catch (e) {
      if (mounted) {
        String errorMsg = e.toString();
        if (e is DioException && e.response?.data != null) {
          final resData = e.response!.data;
          if (resData is Map && resData['error'] != null) {
            errorMsg = resData['error'].toString();
          }
        }
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            backgroundColor: const Color(0xFFC0392B),
            content: Text('Error: $errorMsg'),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  Future<void> _mostrarModalDiscrepancias(
    BuildContext context,
    List discrepancias,
    int turnoId,
    String sucursalNombre,
    String barmanNombre,
  ) async {
    final StringBuffer sb = StringBuffer();
    sb.writeln('🚨 *ALERTA DE DISCREPANCIA EN APERTURA - PUNTO FRÍO*');
    sb.writeln('📍 *Sucursal:* $sucursalNombre');
    sb.writeln('👤 *Barman Entrante:* $barmanNombre');
    sb.writeln('🔢 *Turno Entrante:* #$turnoId');
    sb.writeln('────────────────────');
    sb.writeln('*Diferencias detectadas:*');

    for (var d in discrepancias) {
      final prod = d['producto_nombre'] ?? 'Producto #${d['producto_id']}';
      final esp = d['stock_esperado'];
      final dec = d['stock_declarado'];
      final dif = d['diferencia'];
      sb.writeln('• *$prod:*');
      sb.writeln('   - Cierre saliente: $esp');
      sb.writeln('   - Conteo entrante: $dec');
      sb.writeln('   - Faltante/Diferencia: *$dif botellas*');
    }

    sb.writeln('────────────────────');
    sb.writeln('Por favor verificar esta situación de inmediato.');

    final mensajeUrl = Uri.encodeComponent(sb.toString());
    final urlWhatsApp = Uri.parse('https://wa.me/59167369293?text=$mensajeUrl');

    await showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        backgroundColor: const Color(0xFF1E1B28),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: const BorderSide(color: Color(0xFFE74C3C), width: 2),
        ),
        title: const Row(
          children: [
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
    final itemsVisibles = _itemsFiltrados;

    return Scaffold(
      backgroundColor: const Color(0xFF121620),
      appBar: AppBar(
        backgroundColor: widget.esModoReconteo ? const Color(0xFF452B0E) : const Color(0xFF1B2332),
        title: Text(
          widget.esModoReconteo
              ? '🔄 Reconteo Autorizado (${(widget.reconteoTipo ?? (esApertura ? "apertura" : "cierre")).toUpperCase()})'
              : (esApertura
                  ? (widget.esSuplencia ? 'Apertura (Suplencia Cajera)' : 'Corte de Apertura')
                  : (widget.esSuplencia ? 'Cierre (Suplencia Cajera)' : 'Corte de Cierre')),
          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 17),
        ),
        iconTheme: const IconThemeData(color: Colors.white),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh, color: Colors.white70),
            tooltip: 'Actualizar catálogo',
            onPressed: _isLoading ? null : _refrescarCatalogo,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Colors.amberAccent))
          : Column(
              children: [
                // Banner superior de instrucción
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  color: widget.esModoReconteo
                      ? Colors.amber.shade900.withOpacity(0.3)
                      : (esApertura
                          ? const Color(0xFF2980B9).withOpacity(0.2)
                          : const Color(0xFFC0392B).withOpacity(0.2)),
                  child: Row(
                    children: [
                      Icon(
                        widget.esModoReconteo
                            ? Icons.history_edu
                            : (esApertura ? Icons.login : Icons.lock_clock),
                        color: widget.esModoReconteo
                            ? Colors.amberAccent
                            : (esApertura ? const Color(0xFF5DADE2) : const Color(0xFFE74C3C)),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          widget.esModoReconteo
                              ? '¡Cantidades previas precargadas en memoria! Modifique solo los productos que requieran corrección y confirme para re-sellar.'
                              : (esApertura
                                  ? 'Ingrese el conteo inicial físico al recibir la barra.'
                                  : 'Conteo final al entregar el turno. Medición en múltiplos de 1/4.'),
                          style: TextStyle(
                            color: widget.esModoReconteo ? Colors.amberAccent : Colors.white70,
                            fontSize: 12,
                            fontWeight: widget.esModoReconteo ? FontWeight.bold : FontWeight.normal,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),

                // Buscador reactivo y botón "+ Producto no listado"
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 12.0, vertical: 8.0),
                  child: Row(
                    children: [
                      Expanded(
                        child: Container(
                          height: 44,
                          decoration: BoxDecoration(
                            color: const Color(0xFF1B2332),
                            borderRadius: BorderRadius.circular(10),
                            border: Border.all(color: Colors.white12),
                          ),
                          child: TextField(
                            controller: _busquedaController,
                            style: const TextStyle(color: Colors.white, fontSize: 14),
                            onChanged: (val) {
                              setState(() {
                                _filtroBusqueda = val;
                              });
                            },
                            decoration: InputDecoration(
                              hintText: 'Buscar producto...',
                              hintStyle: const TextStyle(color: Colors.white38, fontSize: 13),
                              prefixIcon: const Icon(Icons.search, color: Colors.amberAccent, size: 20),
                              suffixIcon: _filtroBusqueda.isNotEmpty
                                  ? IconButton(
                                      icon: const Icon(Icons.close, color: Colors.white54, size: 18),
                                      onPressed: () {
                                        _busquedaController.clear();
                                        setState(() => _filtroBusqueda = '');
                                      },
                                    )
                                  : null,
                              border: InputBorder.none,
                              contentPadding: const EdgeInsets.symmetric(vertical: 10),
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      ElevatedButton.icon(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFF26324A),
                          foregroundColor: Colors.amberAccent,
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10),
                            side: const BorderSide(color: Colors.amberAccent, width: 0.8),
                          ),
                        ),
                        icon: const Icon(Icons.add, size: 16),
                        label: const Text('+ No listado', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                        onPressed: _mostrarModalProductoNoListado,
                      ),
                    ],
                  ),
                ),

                if (esApertura)
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 12.0, vertical: 4.0),
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

                // Lista de productos con buscador
                Expanded(
                  child: itemsVisibles.isEmpty
                      ? Center(
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              const Icon(Icons.search_off, color: Colors.white30, size: 48),
                              const SizedBox(height: 8),
                              Text(
                                'No se halló "$_filtroBusqueda"',
                                style: const TextStyle(color: Colors.white60, fontSize: 14),
                              ),
                              const SizedBox(height: 12),
                              ElevatedButton(
                                style: ElevatedButton.styleFrom(backgroundColor: Colors.amberAccent, foregroundColor: Colors.black),
                                onPressed: _mostrarModalProductoNoListado,
                                child: const Text('Agregar como Producto No Listado'),
                              ),
                            ],
                          ),
                        )
                      : ListView.builder(
                          padding: const EdgeInsets.all(12),
                          itemCount: itemsVisibles.length,
                          itemBuilder: (context, index) {
                            final item = itemsVisibles[index];
                            final esProv = item['es_provisional'] == true;

                            return Container(
                              margin: const EdgeInsets.only(bottom: 8),
                              decoration: esProv
                                  ? BoxDecoration(
                                      borderRadius: BorderRadius.circular(12),
                                      border: Border.all(color: Colors.orangeAccent.withValues(alpha: 0.5), width: 1.5),
                                    )
                                  : null,
                              child: BottleFractionSelector(
                                productName: item['nombre'],
                                value: (item['cantidad'] as num?)?.toDouble() ?? 0.0,
                                cantidadInicial: (item['cantidad_inicial'] as num?)?.toDouble(),
                                ingresos: (item['ingresos'] as num?)?.toDouble(),
                                totalDisponible: (item['total_disponible'] as num?)?.toDouble(),
                                esCierre: !esApertura,
                                onChanged: (newVal) {
                                  setState(() {
                                    item['cantidad'] = newVal;
                                    if (esApertura) {
                                      item['cantidad_inicial'] = newVal;
                                      final ing = (item['ingresos'] as num?)?.toDouble() ?? 0.0;
                                      item['total_disponible'] = newVal + ing;
                                    }
                                  });
                                  _guardarBorrador();
                                },
                              ),
                            );
                          },
                        ),
                ),

                // Botón de Confirmación
                Container(
                  padding: const EdgeInsets.all(16),
                  color: const Color(0xFF1B2332),
                  child: SafeArea(
                    child: ElevatedButton(
                      onPressed: _isSubmitting ? null : _confirmarOperacion,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: widget.esModoReconteo
                            ? const Color(0xFFD35400)
                            : (esApertura ? const Color(0xFF2980B9) : const Color(0xFFC0392B)),
                        minimumSize: const Size.fromHeight(52),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      child: _isSubmitting
                          ? const CircularProgressIndicator(color: Colors.white)
                          : Text(
                              widget.esModoReconteo
                                  ? 'CONFIRMAR Y RE-SELLAR RECONTEO'
                                  : (esApertura
                                      ? (widget.esSuplencia ? 'CONFIRMAR APERTURA POR SUPLENCIA' : 'CONFIRMAR Y ABRIR TURNO')
                                      : (widget.esSuplencia ? 'CONFIRMAR CIERRE POR SUPLENCIA' : 'CONFIRMAR Y CERRAR TURNO')),
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 15,
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
