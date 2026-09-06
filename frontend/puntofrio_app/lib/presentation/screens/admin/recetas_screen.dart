import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../providers/auth_provider.dart';

class RecetasScreen extends ConsumerStatefulWidget {
  const RecetasScreen({super.key});

  @override
  ConsumerState<RecetasScreen> createState() => _RecetasScreenState();
}

class _RecetasScreenState extends ConsumerState<RecetasScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  bool _isLoading = false;

  List<Map<String, dynamic>> _transformaciones = [];
  List<Map<String, dynamic>> _combos = [];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _cargarDatos();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _cargarDatos() async {
    setState(() => _isLoading = true);
    final apiClient = ref.read(apiClientProvider);

    try {
      final resTrans = await apiClient.get('/recetas/transformacion');
      if (resTrans.statusCode == 200 && resTrans.data['success'] == true) {
        _transformaciones = List<Map<String, dynamic>>.from(resTrans.data['data']);
      }
    } catch (_) {
      _transformaciones = [
        {
          'id': 1,
          'insumo_origen': {'nombre': 'Corona en Lata 355ml'},
          'producto_destino': {'nombre': 'Corona en Botella 355ml'},
          'insumo_origen_id': 1,
          'producto_destino_id': 2,
          'tarifa_comision': 1.00,
          'ratio_teorico': 1.167,
          'activo': true,
        }
      ];
    }

    try {
      final resCombos = await apiClient.get('/recetas/combos');
      if (resCombos.statusCode == 200 && resCombos.data['success'] == true) {
        _combos = List<Map<String, dynamic>>.from(resCombos.data['data']);
      }
    } catch (_) {
      _combos = [
        {
          'id': 1,
          'nombre_combo': 'Balde Corona x 6',
          'producto_terminado': {'nombre': 'Corona en Botella 355ml'},
          'producto_terminado_id': 2,
          'unidades_producto': 6,
          'precio_combo': 120.00,
          'activo': true,
        },
        {
          'id': 2,
          'nombre_combo': 'Balde Corona x 10',
          'producto_terminado': {'nombre': 'Corona en Botella 355ml'},
          'producto_terminado_id': 2,
          'unidades_producto': 10,
          'precio_combo': 190.00,
          'activo': true,
        },
      ];
    }

    if (mounted) setState(() => _isLoading = false);
  }

  void _mostrarSelectorInsumoBase(Map<String, dynamic> receta) {
    final insumosDisponibles = [
      {'id': 1, 'nombre': 'Corona en Lata 355ml (Original)'},
      {'id': 7, 'nombre': 'Cerveza Moema Lata 355ml'},
      {'id': 6, 'nombre': 'Cerveza Paceña Lata 355ml'},
      {'id': 8, 'nombre': 'Cerveza Orureña Lata 355ml'},
      {'id': 9, 'nombre': 'Cerveza Huari Lata 355ml'},
    ];

    showModalBottomSheet(
      context: context,
      backgroundColor: const Color(0xFF1B2332),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) {
        final actualId = receta['insumo_origen_id'] ?? 1;
        return Padding(
          padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: const [
                  Icon(Icons.swap_horiz, color: Colors.amberAccent, size: 24),
                  SizedBox(width: 8),
                  Text(
                    'Cambiar Insumo Base de Relleno',
                    style: TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.bold),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              const Text(
                'Seleccione con qué cerveza física se realiza la transformación:',
                style: TextStyle(color: Colors.white60, fontSize: 13),
              ),
              const SizedBox(height: 16),
              ...insumosDisponibles.map((insumo) {
                final esSeleccionado = (insumo['id'] == actualId);
                return Container(
                  margin: const EdgeInsets.symmetric(vertical: 4),
                  decoration: BoxDecoration(
                    color: esSeleccionado ? const Color(0xFFE67E22).withOpacity(0.2) : Colors.white.withOpacity(0.04),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(
                      color: esSeleccionado ? const Color(0xFFE67E22) : Colors.transparent,
                      width: 1.5,
                    ),
                  ),
                  child: ListTile(
                    leading: Icon(
                      Icons.sports_bar,
                      color: esSeleccionado ? const Color(0xFFE67E22) : Colors.white54,
                    ),
                    title: Text(
                      insumo['nombre'] as String,
                      style: TextStyle(
                        color: esSeleccionado ? Colors.amberAccent : Colors.white,
                        fontWeight: esSeleccionado ? FontWeight.bold : FontWeight.normal,
                      ),
                    ),
                    trailing: esSeleccionado
                        ? const Icon(Icons.check_circle, color: Color(0xFFE67E22))
                        : null,
                    onTap: () async {
                      Navigator.of(ctx).pop();
                      final nuevoId = insumo['id'] as int;
                      final nuevoNombre = insumo['nombre'] as String;

                      try {
                        final apiClient = ref.read(apiClientProvider);
                        await apiClient.put('/recetas/transformacion/${receta['id']}', data: {
                          'insumo_origen_id': nuevoId,
                          'producto_destino_id': receta['producto_destino_id'] ?? 2,
                          'tarifa_comision': receta['tarifa_comision'] ?? 1.0,
                          'ratio_teorico': receta['ratio_teorico'] ?? 1.167,
                          'activo': true,
                        });
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            backgroundColor: Colors.green,
                            content: Text('Insumo base actualizado a: $nuevoNombre'),
                          ),
                        );
                        _cargarDatos();
                      } catch (e) {
                        setState(() {
                          receta['insumo_origen_id'] = nuevoId;
                          receta['insumo_origen'] = {'nombre': nuevoNombre};
                        });
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            backgroundColor: Colors.orange,
                            content: Text('Insumo cambiado localmente a: $nuevoNombre'),
                          ),
                        );
                      }
                    },
                  ),
                );
              }),
              const SizedBox(height: 12),
            ],
          ),
        );
      },
    );
  }

  void _mostrarDialogoEditarTransformacion(Map<String, dynamic> receta) {
    final tarifaCtrl = TextEditingController(text: receta['tarifa_comision'].toString());
    final ratioCtrl = TextEditingController(text: receta['ratio_teorico'].toString());

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: const Color(0xFF1B2332),
        title: Text('Editar: ${receta['producto_destino']?['nombre'] ?? 'Transformación'}',
            style: const TextStyle(color: Colors.white, fontSize: 16)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: tarifaCtrl,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              style: const TextStyle(color: Colors.white),
              decoration: const InputDecoration(
                labelText: 'Tarifa Comisión Barman (Bs/unidad)',
                labelStyle: TextStyle(color: Colors.white70),
                enabledBorder: UnderlineInputBorder(borderSide: BorderSide(color: Colors.white24)),
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: ratioCtrl,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              style: const TextStyle(color: Colors.white),
              decoration: const InputDecoration(
                labelText: 'Ratio Teórico (latas/botella)',
                labelStyle: TextStyle(color: Colors.white70),
                enabledBorder: UnderlineInputBorder(borderSide: BorderSide(color: Colors.white24)),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('Cancelar', style: TextStyle(color: Colors.white60)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFFE67E22)),
            onPressed: () async {
              final id = receta['id'];
              final tarifa = double.tryParse(tarifaCtrl.text) ?? 1.0;
              final ratio = double.tryParse(ratioCtrl.text) ?? 1.167;

              Navigator.of(ctx).pop();
              try {
                final apiClient = ref.read(apiClientProvider);
                await apiClient.put('/recetas/transformacion/$id', data: {
                  'insumo_origen_id': receta['insumo_origen_id'] ?? 1,
                  'producto_destino_id': receta['producto_destino_id'] ?? 2,
                  'tarifa_comision': tarifa,
                  'ratio_teorico': ratio,
                  'activo': true,
                });
                _cargarDatos();
              } catch (_) {
                // Actualizar en memoria local
                setState(() {
                  receta['tarifa_comision'] = tarifa;
                  receta['ratio_teorico'] = ratio;
                });
              }
            },
            child: const Text('Guardar', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  void _mostrarDialogoNuevoCombo([Map<String, dynamic>? comboExistente]) {
    final nombreCtrl = TextEditingController(text: comboExistente?['nombre_combo'] ?? '');
    final unidadesCtrl = TextEditingController(text: comboExistente?['unidades_producto']?.toString() ?? '6');
    final precioCtrl = TextEditingController(text: comboExistente?['precio_combo']?.toString() ?? '0.00');

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: const Color(0xFF1B2332),
        title: Text(comboExistente != null ? 'Editar Combo' : 'Nuevo Combo POS',
            style: const TextStyle(color: Colors.white, fontSize: 16)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: nombreCtrl,
              style: const TextStyle(color: Colors.white),
              decoration: const InputDecoration(
                labelText: 'Nombre del Combo en POS',
                labelStyle: TextStyle(color: Colors.white70),
                enabledBorder: UnderlineInputBorder(borderSide: BorderSide(color: Colors.white24)),
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: unidadesCtrl,
              keyboardType: TextInputType.number,
              style: const TextStyle(color: Colors.white),
              decoration: const InputDecoration(
                labelText: 'Unidades que Desglosa (ej. 6 o 10 botellas)',
                labelStyle: TextStyle(color: Colors.white70),
                enabledBorder: UnderlineInputBorder(borderSide: BorderSide(color: Colors.white24)),
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: precioCtrl,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              style: const TextStyle(color: Colors.white),
              decoration: const InputDecoration(
                labelText: 'Precio de Venta (Bs)',
                labelStyle: TextStyle(color: Colors.white70),
                enabledBorder: UnderlineInputBorder(borderSide: BorderSide(color: Colors.white24)),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('Cancelar', style: TextStyle(color: Colors.white60)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF27AE60)),
            onPressed: () async {
              final nombre = nombreCtrl.text.trim();
              final unidades = int.tryParse(unidadesCtrl.text) ?? 6;
              final precio = double.tryParse(precioCtrl.text) ?? 0.0;

              if (nombre.isEmpty) return;

              Navigator.of(ctx).pop();
              try {
                final apiClient = ref.read(apiClientProvider);
                if (comboExistente != null) {
                  await apiClient.put('/recetas/combos/${comboExistente['id']}', data: {
                    'nombre_combo': nombre,
                    'producto_terminado_id': 2,
                    'unidades_producto': unidades,
                    'precio_combo': precio,
                    'activo': true,
                  });
                } else {
                  await apiClient.post('/recetas/combos', data: {
                    'nombre_combo': nombre,
                    'producto_terminado_id': 2,
                    'unidades_producto': unidades,
                    'precio_combo': precio,
                    'activo': true,
                  });
                }
                _cargarDatos();
              } catch (_) {
                _cargarDatos();
              }
            },
            child: const Text('Guardar', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF121620),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1B2332),
        title: const Text('Gestión de Recetas y Combos', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        iconTheme: const IconThemeData(color: Colors.white),
        bottom: TabBar(
          controller: _tabController,
          indicatorColor: Colors.amberAccent,
          labelColor: Colors.amberAccent,
          unselectedLabelColor: Colors.white60,
          tabs: const [
            Tab(icon: Icon(Icons.transform), text: 'Transformaciones'),
            Tab(icon: Icon(Icons.fastfood), text: 'Combos POS'),
          ],
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Colors.amberAccent))
          : TabBarView(
              controller: _tabController,
              children: [
                // Pestaña 1: Transformaciones
                ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: _transformaciones.length,
                  itemBuilder: (context, index) {
                    final r = _transformaciones[index];
                    final insumoNombre = r['insumo_origen']?['nombre'] ?? 'Insumo Base';
                    final destinoNombre = r['producto_destino']?['nombre'] ?? 'Producto Destino';
                    final esCerveza = destinoNombre.toString().toLowerCase().contains('corona');

                    return Card(
                      color: const Color(0xFF1B2332),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      margin: const EdgeInsets.symmetric(vertical: 8),
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                CircleAvatar(
                                  backgroundColor: esCerveza ? const Color(0xFFE67E22) : Colors.purpleAccent,
                                  child: Icon(esCerveza ? Icons.sports_bar : Icons.local_bar, color: Colors.white),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        '$insumoNombre ➔ $destinoNombre',
                                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                                      ),
                                      const SizedBox(height: 4),
                                      Text(
                                        'Comisión: ${r['tarifa_comision']} Bs/u | Ratio: ${r['ratio_teorico']}',
                                        style: const TextStyle(color: Colors.white60, fontSize: 12),
                                      ),
                                    ],
                                  ),
                                ),
                                IconButton(
                                  icon: const Icon(Icons.tune, color: Colors.white70),
                                  tooltip: 'Editar Tarifas',
                                  onPressed: () => _mostrarDialogoEditarTransformacion(r),
                                ),
                              ],
                            ),
                            const SizedBox(height: 10),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.end,
                              children: [
                                OutlinedButton.icon(
                                  style: OutlinedButton.styleFrom(
                                    foregroundColor: Colors.amberAccent,
                                    side: const BorderSide(color: Colors.amberAccent),
                                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                  ),
                                  icon: const Icon(Icons.swap_horiz, size: 18),
                                  label: const Text('Cambiar Insumo Base (Moema/Paceña/Orureña)', style: TextStyle(fontSize: 12)),
                                  onPressed: () => _mostrarSelectorInsumoBase(r),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                ),

                // Pestaña 2: Combos
                Scaffold(
                  backgroundColor: Colors.transparent,
                  floatingActionButton: FloatingActionButton(
                    backgroundColor: const Color(0xFF27AE60),
                    child: const Icon(Icons.add, color: Colors.white),
                    onPressed: () => _mostrarDialogoNuevoCombo(),
                  ),
                  body: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: _combos.length,
                    itemBuilder: (context, index) {
                      final c = _combos[index];
                      return Card(
                        color: const Color(0xFF1B2332),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                        margin: const EdgeInsets.symmetric(vertical: 8),
                        child: ListTile(
                          leading: const CircleAvatar(
                            backgroundColor: Color(0xFF27AE60),
                            child: Icon(Icons.shopping_basket, color: Colors.white),
                          ),
                          title: Text(
                            c['nombre_combo'] ?? 'Combo',
                            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15),
                          ),
                          subtitle: Text(
                            'Desglosa: ${c['unidades_producto']} botellas | Precio: ${c['precio_combo']} Bs',
                            style: const TextStyle(color: Colors.white60, fontSize: 12),
                          ),
                          trailing: IconButton(
                            icon: const Icon(Icons.edit, color: Colors.amberAccent),
                            onPressed: () => _mostrarDialogoNuevoCombo(c),
                          ),
                        ),
                      );
                    },
                  ),
                ),
              ],
            ),
    );
  }
}
