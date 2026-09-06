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
  List<Map<String, dynamic>> _catalogoProductos = [];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _tabController.addListener(() {
      if (mounted) setState(() {});
    });
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

    // Cargar catálogo de productos reales para los selectores
    try {
      final resProd = await apiClient.get('/productos');
      if (resProd.data['success'] == true) {
        _catalogoProductos = List<Map<String, dynamic>>.from(resProd.data['data'] ?? []);
      }
    } catch (_) {}

    // Cargar recetas de transformación
    try {
      final resTrans = await apiClient.get('/recetas/transformacion');
      if (resTrans.statusCode == 200 && resTrans.data['success'] == true) {
        _transformaciones = List<Map<String, dynamic>>.from(resTrans.data['data']);
      }
    } catch (_) {
      _transformaciones = [];
    }

    // Cargar combos POS
    try {
      final resCombos = await apiClient.get('/recetas/combos');
      if (resCombos.statusCode == 200 && resCombos.data['success'] == true) {
        _combos = List<Map<String, dynamic>>.from(resCombos.data['data']);
      }
    } catch (_) {
      _combos = [];
    }

    if (mounted) setState(() => _isLoading = false);
  }

  void _mostrarDialogoNuevaTransformacion([Map<String, dynamic>? recetaExistente]) {
    if (_catalogoProductos.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('No hay productos en el catálogo. Registre productos primero.'),
          backgroundColor: Colors.orange,
        ),
      );
      return;
    }

    final esEdicion = recetaExistente != null;
    final nombreCtrl = TextEditingController(text: recetaExistente?['nombre'] ?? '');
    final tarifaCtrl = TextEditingController(
      text: (recetaExistente?['tarifa_comision_unidad'] ?? recetaExistente?['tarifa_comision'] ?? 1.00).toString(),
    );
    final ratioCtrl = TextEditingController(
      text: (recetaExistente?['ratio_referencia_esperado'] ?? recetaExistente?['ratio_teorico'] ?? 1.00).toString(),
    );

    int insumoOrigenId = recetaExistente?['insumo_origen_id'] ?? _catalogoProductos.first['id'];
    int? insumoSecundarioId = recetaExistente?['insumo_secundario_id'];
    int destinoId = recetaExistente?['producto_destino_id'] ??
        (_catalogoProductos.length > 1 ? _catalogoProductos[1]['id'] : _catalogoProductos.first['id']);

    bool esCompuesta = insumoSecundarioId != null;

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setModalState) {
          return AlertDialog(
            backgroundColor: const Color(0xFF1B2332),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
            title: Row(
              children: [
                Icon(esEdicion ? Icons.edit : Icons.add_circle, color: const Color(0xFFE67E22)),
                const SizedBox(width: 8),
                Text(
                  esEdicion ? 'Editar Transformación' : 'Nueva Receta de Relleno',
                  style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
                ),
              ],
            ),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  TextField(
                    controller: nombreCtrl,
                    style: const TextStyle(color: Colors.white),
                    decoration: const InputDecoration(
                      labelText: 'Nombre / Etiqueta de la Receta (Opcional)',
                      hintText: 'ej. Relleno Corona, Mezcla Red Label',
                      hintStyle: TextStyle(color: Colors.white30, fontSize: 12),
                      labelStyle: TextStyle(color: Colors.white70),
                      enabledBorder: UnderlineInputBorder(borderSide: BorderSide(color: Colors.white24)),
                    ),
                  ),
                  const SizedBox(height: 14),

                  // Selector de modalidad: Simple vs Compuesta (2 insumos)
                  const Text('Fórmula:', style: TextStyle(color: Colors.white70, fontSize: 12, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      ChoiceChip(
                        label: const Text('Simple (1 Insumo)'),
                        selected: !esCompuesta,
                        selectedColor: const Color(0xFFE67E22),
                        labelStyle: const TextStyle(color: Colors.white, fontSize: 12),
                        onSelected: (val) {
                          if (val) {
                            setModalState(() {
                              esCompuesta = false;
                              insumoSecundarioId = null;
                            });
                          }
                        },
                      ),
                      const SizedBox(width: 8),
                      ChoiceChip(
                        label: const Text('Compuesta (2 Insumos)'),
                        selected: esCompuesta,
                        selectedColor: Colors.purpleAccent,
                        labelStyle: const TextStyle(color: Colors.white, fontSize: 12),
                        onSelected: (val) {
                          if (val) {
                            setModalState(() {
                              esCompuesta = true;
                              if (insumoSecundarioId == null && _catalogoProductos.length > 1) {
                                insumoSecundarioId = _catalogoProductos[1]['id'];
                              }
                            });
                          }
                        },
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),

                  // Insumo 1 (Principal)
                  Text(
                    esCompuesta ? 'Insumo 1 (Principal):' : 'Insumo de Origen:',
                    style: const TextStyle(color: Colors.white70, fontSize: 12, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 4),
                  DropdownButtonFormField<int>(
                    value: insumoOrigenId,
                    dropdownColor: const Color(0xFF141923),
                    isExpanded: true,
                    style: const TextStyle(color: Colors.white, fontSize: 13),
                    items: _catalogoProductos.map((p) {
                      return DropdownMenuItem<int>(
                        value: p['id'] as int,
                        child: Text(p['nombre'].toString(), overflow: TextOverflow.ellipsis),
                      );
                    }).toList(),
                    onChanged: (val) {
                      if (val != null) setModalState(() => insumoOrigenId = val);
                    },
                    decoration: const InputDecoration(
                      filled: true,
                      fillColor: Color(0xFF242E42),
                      contentPadding: EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                      border: OutlineInputBorder(borderRadius: BorderRadius.all(Radius.circular(8)), borderSide: BorderSide.none),
                    ),
                  ),
                  const SizedBox(height: 12),

                  // Insumo 2 (Secundario) - Solo si es compuesta
                  if (esCompuesta) ...[
                    const Text('Insumo 2 (Secundario / Mezcla):', style: TextStyle(color: Colors.purpleAccent, fontSize: 12, fontWeight: FontWeight.bold)),
                    const SizedBox(height: 4),
                    DropdownButtonFormField<int>(
                      value: insumoSecundarioId,
                      dropdownColor: const Color(0xFF141923),
                      isExpanded: true,
                      style: const TextStyle(color: Colors.white, fontSize: 13),
                      items: _catalogoProductos.map((p) {
                        return DropdownMenuItem<int>(
                          value: p['id'] as int,
                          child: Text(p['nombre'].toString(), overflow: TextOverflow.ellipsis),
                        );
                      }).toList(),
                      onChanged: (val) {
                        if (val != null) setModalState(() => insumoSecundarioId = val);
                      },
                      decoration: const InputDecoration(
                        filled: true,
                        fillColor: Color(0xFF242E42),
                        contentPadding: EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                        border: OutlineInputBorder(borderRadius: BorderRadius.all(Radius.circular(8)), borderSide: BorderSide.none),
                      ),
                    ),
                    const SizedBox(height: 12),
                  ],

                  // Producto Destino
                  const Text('Producto Terminado Resultante (Destino):', style: TextStyle(color: Color(0xFF2ECC71), fontSize: 12, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 4),
                  DropdownButtonFormField<int>(
                    value: destinoId,
                    dropdownColor: const Color(0xFF141923),
                    isExpanded: true,
                    style: const TextStyle(color: Colors.white, fontSize: 13),
                    items: _catalogoProductos.map((p) {
                      return DropdownMenuItem<int>(
                        value: p['id'] as int,
                        child: Text(p['nombre'].toString(), overflow: TextOverflow.ellipsis),
                      );
                    }).toList(),
                    onChanged: (val) {
                      if (val != null) setModalState(() => destinoId = val);
                    },
                    decoration: const InputDecoration(
                      filled: true,
                      fillColor: Color(0xFF242E42),
                      contentPadding: EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                      border: OutlineInputBorder(borderRadius: BorderRadius.all(Radius.circular(8)), borderSide: BorderSide.none),
                    ),
                  ),
                  const SizedBox(height: 14),

                  // Tarifa y Ratio
                  Row(
                    children: [
                      Expanded(
                        child: TextField(
                          controller: tarifaCtrl,
                          keyboardType: const TextInputType.numberWithOptions(decimal: true),
                          style: const TextStyle(color: Colors.white),
                          decoration: const InputDecoration(
                            labelText: 'Tarifa Comisión (Bs/u)',
                            hintText: '1.00',
                            labelStyle: TextStyle(color: Colors.white70, fontSize: 12),
                            enabledBorder: UnderlineInputBorder(borderSide: BorderSide(color: Colors.white24)),
                          ),
                        ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: TextField(
                          controller: ratioCtrl,
                          keyboardType: const TextInputType.numberWithOptions(decimal: true),
                          style: const TextStyle(color: Colors.white),
                          decoration: const InputDecoration(
                            labelText: 'Ratio Teórico',
                            hintText: '1.00',
                            labelStyle: TextStyle(color: Colors.white70, fontSize: 12),
                            enabledBorder: UnderlineInputBorder(borderSide: BorderSide(color: Colors.white24)),
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(ctx).pop(),
                child: const Text('Cancelar', style: TextStyle(color: Colors.white60)),
              ),
              ElevatedButton(
                style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFFE67E22)),
                onPressed: () async {
                  final tarifa = double.tryParse(tarifaCtrl.text.trim()) ?? 1.00;
                  final ratio = double.tryParse(ratioCtrl.text.trim()) ?? 1.00;
                  final nombre = nombreCtrl.text.trim();

                  Navigator.of(ctx).pop();
                  setState(() => _isLoading = true);

                  try {
                    final apiClient = ref.read(apiClientProvider);
                    final payload = {
                      'nombre': nombre.isNotEmpty ? nombre : null,
                      'insumo_origen_id': insumoOrigenId,
                      'insumo_secundario_id': esCompuesta ? insumoSecundarioId : null,
                      'producto_destino_id': destinoId,
                      'tarifa_comision': tarifa,
                      'tarifa_comision_unidad': tarifa,
                      'ratio_teorico': ratio,
                      'ratio_referencia_esperado': ratio,
                      'activo': true,
                    };

                    if (esEdicion) {
                      await apiClient.put('/recetas/transformacion/${recetaExistente['id']}', data: payload);
                    } else {
                      await apiClient.post('/recetas/transformacion', data: payload);
                    }

                    _cargarDatos();
                    if (mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(
                          content: Text(esEdicion ? 'Receta actualizada exitosamente.' : 'Receta creada exitosamente.'),
                          backgroundColor: const Color(0xFF27AE60),
                        ),
                      );
                    }
                  } catch (e) {
                    _cargarDatos();
                    if (mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(content: Text('Error al guardar receta: $e'), backgroundColor: Colors.redAccent),
                      );
                    }
                  }
                },
                child: const Text('Guardar Receta', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
              ),
            ],
          );
        },
      ),
    );
  }

  void _eliminarTransformacion(int id) async {
    final confirmar = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: const Color(0xFF1B2332),
        title: const Text('¿Eliminar Receta?', style: TextStyle(color: Colors.white)),
        content: const Text('Esta receta dejará de estar disponible para nuevos turnos.', style: TextStyle(color: Colors.white70)),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Cancelar', style: TextStyle(color: Colors.white60))),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.redAccent),
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('Eliminar', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );

    if (confirmar == true) {
      setState(() => _isLoading = true);
      try {
        final apiClient = ref.read(apiClientProvider);
        await apiClient.delete('/recetas/transformacion/$id');
        _cargarDatos();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Receta eliminada.'), backgroundColor: Colors.orange),
          );
        }
      } catch (e) {
        _cargarDatos();
      }
    }
  }

  void _mostrarDialogoNuevoCombo([Map<String, dynamic>? comboExistente]) {
    final nombreCtrl = TextEditingController(text: comboExistente?['nombre_combo'] ?? '');
    final unidadesCtrl = TextEditingController(
      text: (comboExistente?['unidades_producto'] ?? comboExistente?['unidades_equivalentes'] ?? 6).toString(),
    );
    final precioCtrl = TextEditingController(
      text: (comboExistente?['precio_combo'] ?? 0.0).toString(),
    );

    int productoTerminadoId = comboExistente?['producto_terminado_id'] ??
        (_catalogoProductos.isNotEmpty ? _catalogoProductos.first['id'] : 2);

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setModalState) {
          return AlertDialog(
            backgroundColor: const Color(0xFF1B2332),
            title: Text(
              comboExistente != null ? 'Editar Combo POS' : 'Nuevo Combo POS',
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
            ),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  TextField(
                    controller: nombreCtrl,
                    style: const TextStyle(color: Colors.white),
                    decoration: const InputDecoration(
                      labelText: 'Nombre del Combo (como aparece en Ticket Z)',
                      hintText: 'ej. Balde 6 Coronas, Promo Fernet',
                      hintStyle: TextStyle(color: Colors.white30, fontSize: 12),
                      labelStyle: TextStyle(color: Colors.white70),
                      enabledBorder: UnderlineInputBorder(borderSide: BorderSide(color: Colors.white24)),
                    ),
                  ),
                  const SizedBox(height: 12),
                  const Text('Producto Terminado a Desglosar:', style: TextStyle(color: Colors.white70, fontSize: 12)),
                  const SizedBox(height: 4),
                  DropdownButtonFormField<int>(
                    value: productoTerminadoId,
                    dropdownColor: const Color(0xFF141923),
                    isExpanded: true,
                    style: const TextStyle(color: Colors.white, fontSize: 13),
                    items: _catalogoProductos.map((p) {
                      return DropdownMenuItem<int>(
                        value: p['id'] as int,
                        child: Text(p['nombre'].toString(), overflow: TextOverflow.ellipsis),
                      );
                    }).toList(),
                    onChanged: (val) {
                      if (val != null) setModalState(() => productoTerminadoId = val);
                    },
                    decoration: const InputDecoration(
                      filled: true,
                      fillColor: Color(0xFF242E42),
                      contentPadding: EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                      border: OutlineInputBorder(borderRadius: BorderRadius.all(Radius.circular(8)), borderSide: BorderSide.none),
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: unidadesCtrl,
                    keyboardType: TextInputType.number,
                    style: const TextStyle(color: Colors.white),
                    decoration: const InputDecoration(
                      labelText: 'Unidades individuales que Desglosa (ej. 6 o 10)',
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
                      labelText: 'Precio de Venta en POS (Bs)',
                      labelStyle: TextStyle(color: Colors.white70),
                      enabledBorder: UnderlineInputBorder(borderSide: BorderSide(color: Colors.white24)),
                    ),
                  ),
                ],
              ),
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
                  setState(() => _isLoading = true);
                  try {
                    final apiClient = ref.read(apiClientProvider);
                    final payload = {
                      'nombre_combo': nombre,
                      'producto_terminado_id': productoTerminadoId,
                      'unidades_producto': unidades,
                      'unidades_equivalentes': unidades,
                      'precio_combo': precio,
                      'activo': true,
                    };

                    if (comboExistente != null) {
                      await apiClient.put('/recetas/combos/${comboExistente['id']}', data: payload);
                    } else {
                      await apiClient.post('/recetas/combos', data: payload);
                    }
                    _cargarDatos();
                  } catch (_) {
                    _cargarDatos();
                  }
                },
                child: const Text('Guardar Combo', style: TextStyle(color: Colors.white)),
              ),
            ],
          );
        },
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
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: _tabController.index == 0 ? const Color(0xFFE67E22) : const Color(0xFF27AE60),
        icon: const Icon(Icons.add, color: Colors.white),
        label: Text(
          _tabController.index == 0 ? 'NUEVA TRANSFORMACIÓN' : 'NUEVO COMBO',
          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
        ),
        onPressed: () {
          if (_tabController.index == 0) {
            _mostrarDialogoNuevaTransformacion();
          } else {
            _mostrarDialogoNuevoCombo();
          }
        },
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Colors.amberAccent))
          : TabBarView(
              controller: _tabController,
              children: [
                // Pestaña 1: Transformaciones
                _transformaciones.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Icon(Icons.transform, size: 64, color: Colors.white24),
                            const SizedBox(height: 12),
                            const Text('No hay recetas de transformación registradas.', style: TextStyle(color: Colors.white60)),
                            const SizedBox(height: 8),
                            ElevatedButton.icon(
                              style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFFE67E22)),
                              icon: const Icon(Icons.add, color: Colors.white),
                              label: const Text('Crear Primera Receta', style: TextStyle(color: Colors.white)),
                              onPressed: () => _mostrarDialogoNuevaTransformacion(),
                            ),
                          ],
                        ),
                      )
                    : ListView.builder(
                        padding: const EdgeInsets.only(left: 16, right: 16, top: 16, bottom: 80),
                        itemCount: _transformaciones.length,
                        itemBuilder: (context, index) {
                          final r = _transformaciones[index];
                          final insumoNombre = r['insumo_origen']?['nombre'] ?? 'Insumo 1';
                          final insumoSecNombre = r['insumo_secundario']?['nombre'];
                          final destinoNombre = r['producto_destino']?['nombre'] ?? 'Producto Destino';
                          final esCompuesta = insumoSecNombre != null;
                          final nombreReceta = r['nombre'] ?? '$insumoNombre ➔ $destinoNombre';

                          final tarifa = r['tarifa_comision_unidad'] ?? r['tarifa_comision'] ?? 1.00;
                          final ratio = r['ratio_referencia_esperado'] ?? r['ratio_teorico'] ?? 1.00;

                          return Card(
                            color: const Color(0xFF1B2332),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                            margin: const EdgeInsets.symmetric(vertical: 8),
                            child: Padding(
                              padding: const EdgeInsets.all(14),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      CircleAvatar(
                                        backgroundColor: esCompuesta ? Colors.purpleAccent : const Color(0xFFE67E22),
                                        child: Icon(esCompuesta ? Icons.blender : Icons.sports_bar, color: Colors.white),
                                      ),
                                      const SizedBox(width: 12),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Row(
                                              children: [
                                                Expanded(
                                                  child: Text(
                                                    nombreReceta,
                                                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                                                  ),
                                                ),
                                                Container(
                                                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                                  decoration: BoxDecoration(
                                                    color: esCompuesta ? Colors.purple.withOpacity(0.3) : Colors.orange.withOpacity(0.3),
                                                    borderRadius: BorderRadius.circular(6),
                                                    border: Border.all(color: esCompuesta ? Colors.purpleAccent : Colors.orange),
                                                  ),
                                                  child: Text(
                                                    esCompuesta ? 'COMPUESTA' : 'SIMPLE',
                                                    style: TextStyle(
                                                      color: esCompuesta ? Colors.purpleAccent : Colors.orange,
                                                      fontSize: 10,
                                                      fontWeight: FontWeight.bold,
                                                    ),
                                                  ),
                                                ),
                                              ],
                                            ),
                                            const SizedBox(height: 4),
                                            Text(
                                              esCompuesta
                                                  ? '$insumoNombre + $insumoSecNombre ➔ $destinoNombre'
                                                  : '$insumoNombre ➔ $destinoNombre',
                                              style: const TextStyle(color: Colors.white70, fontSize: 12),
                                            ),
                                            const SizedBox(height: 4),
                                            Text(
                                              'Comisión Barman: $tarifa Bs/u | Ratio: $ratio',
                                              style: const TextStyle(color: Colors.amberAccent, fontSize: 12, fontWeight: FontWeight.w600),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ],
                                  ),
                                  const Divider(color: Colors.white12, height: 20),
                                  Row(
                                    mainAxisAlignment: MainAxisAlignment.end,
                                    children: [
                                      TextButton.icon(
                                        style: TextButton.styleFrom(foregroundColor: Colors.redAccent),
                                        icon: const Icon(Icons.delete_outline, size: 18),
                                        label: const Text('Eliminar', style: TextStyle(fontSize: 12)),
                                        onPressed: () => _eliminarTransformacion(r['id']),
                                      ),
                                      const SizedBox(width: 8),
                                      OutlinedButton.icon(
                                        style: OutlinedButton.styleFrom(
                                          foregroundColor: Colors.amberAccent,
                                          side: const BorderSide(color: Colors.amberAccent),
                                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                        ),
                                        icon: const Icon(Icons.edit, size: 16),
                                        label: const Text('Editar', style: TextStyle(fontSize: 12)),
                                        onPressed: () => _mostrarDialogoNuevaTransformacion(r),
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
                _combos.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Icon(Icons.fastfood, size: 64, color: Colors.white24),
                            const SizedBox(height: 12),
                            const Text('No hay combos POS registrados.', style: TextStyle(color: Colors.white60)),
                            const SizedBox(height: 8),
                            ElevatedButton.icon(
                              style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF27AE60)),
                              icon: const Icon(Icons.add, color: Colors.white),
                              label: const Text('Crear Primer Combo', style: TextStyle(color: Colors.white)),
                              onPressed: () => _mostrarDialogoNuevoCombo(),
                            ),
                          ],
                        ),
                      )
                    : ListView.builder(
                        padding: const EdgeInsets.only(left: 16, right: 16, top: 16, bottom: 80),
                        itemCount: _combos.length,
                        itemBuilder: (context, index) {
                          final c = _combos[index];
                          final prodNombre = c['producto_terminado']?['nombre'] ?? 'Producto Base';
                          final unidades = c['unidades_producto'] ?? c['unidades_equivalentes'] ?? 6;
                          final precio = c['precio_combo'] ?? 0.0;

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
                                'Desglosa: $unidades botellas ($prodNombre) | Precio POS: $precio Bs',
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
              ],
            ),
    );
  }
}
