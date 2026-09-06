import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/api_client.dart';
import '../../providers/auth_provider.dart';
import '../../providers/transformacion_provider.dart';

class TransformacionScreen extends ConsumerStatefulWidget {
  const TransformacionScreen({super.key});

  @override
  ConsumerState<TransformacionScreen> createState() => _TransformacionScreenState();
}

class _TransformacionScreenState extends ConsumerState<TransformacionScreen> {
  bool _isLoading = true;
  List<Map<String, dynamic>> _recetas = [];
  List<Map<String, dynamic>> _productos = [];
  Map<String, dynamic>? _recetaSeleccionada;

  // --- Campos para Receta Simple ---
  final _insumoSimpleCtrl = TextEditingController(text: '24');
  final _producidasSimpleCtrl = TextEditingController(text: '20');
  final _roturasSimpleCtrl = TextEditingController(text: '0');
  int? _insumoFisicoSimpleId;

  // --- Campos para Receta Compuesta ---
  double _cantInsumo1 = 0.50;
  double _cantInsumo2 = 0.50;
  final _insumo1Ctrl = TextEditingController(text: '0.50');
  final _insumo2Ctrl = TextEditingController(text: '0.50');
  final _producidasCompuestaCtrl = TextEditingController(text: '1');
  final _roturasCompuestaCtrl = TextEditingController(text: '0');

  final _obsCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    _cargarDatos();
  }

  @override
  void dispose() {
    _insumoSimpleCtrl.dispose();
    _producidasSimpleCtrl.dispose();
    _roturasSimpleCtrl.dispose();
    _insumo1Ctrl.dispose();
    _insumo2Ctrl.dispose();
    _producidasCompuestaCtrl.dispose();
    _roturasCompuestaCtrl.dispose();
    _obsCtrl.dispose();
    super.dispose();
  }

  Future<void> _cargarDatos() async {
    setState(() => _isLoading = true);
    final client = ref.read(apiClientProvider);

    try {
      // 1. Cargar productos reales del catálogo
      final resProd = await client.get('/productos');
      if (resProd.data['success'] == true && resProd.data['data'] != null) {
        _productos = List<Map<String, dynamic>>.from(resProd.data['data']);
      }
    } catch (_) {}

    try {
      // 2. Cargar recetas de transformación de la base de datos
      final resRecetas = await client.get('/recetas/transformacion');
      if (resRecetas.data['success'] == true && resRecetas.data['data'] != null) {
        final todas = List<Map<String, dynamic>>.from(resRecetas.data['data']);
        _recetas = todas.where((r) => r['activo'] == 1 || r['activo'] == true).toList();
      }
    } catch (_) {}

    if (_recetas.isNotEmpty) {
      _seleccionarReceta(_recetas.first);
    }

    if (mounted) setState(() => _isLoading = false);
  }

  void _seleccionarReceta(Map<String, dynamic> receta) {
    setState(() {
      _recetaSeleccionada = receta;
      final esCompuesta = receta['insumo_secundario_id'] != null;

      if (!esCompuesta) {
        _insumoFisicoSimpleId = receta['insumo_origen_id'];
        final tarifa = (receta['tarifa_comision_unidad'] ?? receta['tarifa_comision'] ?? 1.0).toDouble();
        ref.read(transformacionProvider.notifier).setTarifa(tarifa);
        ref.read(transformacionProvider.notifier).setCantidadInsumo(double.tryParse(_insumoSimpleCtrl.text) ?? 24.0);
        ref.read(transformacionProvider.notifier).setCantidadProducida(int.tryParse(_producidasSimpleCtrl.text) ?? 20);
        ref.read(transformacionProvider.notifier).setCantidadRoturas(int.tryParse(_roturasSimpleCtrl.text) ?? 0);
      } else {
        _cantInsumo1 = 0.50;
        _cantInsumo2 = 0.50;
        _insumo1Ctrl.text = '0.50';
        _insumo2Ctrl.text = '0.50';
        _producidasCompuestaCtrl.text = '1';
        _roturasCompuestaCtrl.text = '0';
      }
    });
  }

  void _incrementarInsumoSimple(double delta) {
    final actual = double.tryParse(_insumoSimpleCtrl.text) ?? 0.0;
    final nuevo = (actual + delta).clamp(0.0, 9999.0);
    _insumoSimpleCtrl.text = nuevo.toStringAsFixed(0);
    ref.read(transformacionProvider.notifier).setCantidadInsumo(nuevo);
  }

  void _incrementarProducidasSimple(int delta) {
    final actual = int.tryParse(_producidasSimpleCtrl.text) ?? 0;
    final nuevo = (actual + delta).clamp(0, 9999);
    _producidasSimpleCtrl.text = nuevo.toString();
    ref.read(transformacionProvider.notifier).setCantidadProducida(nuevo);
  }

  String _obtenerNombreProducto(int? id) {
    if (id == null) return 'Desconocido';
    final p = _productos.firstWhere((item) => item['id'] == id, orElse: () => {});
    return p['nombre'] ?? 'Producto #$id';
  }

  Future<void> _guardarTransformacion() async {
    if (_recetaSeleccionada == null) return;

    final authState = ref.read(authProvider);
    final turnoId = authState.turnoActivoId ?? 1;
    final esCompuesta = _recetaSeleccionada!['insumo_secundario_id'] != null;
    final recetaId = _recetaSeleccionada!['id'] as int;

    if (!esCompuesta) {
      final cantInsumo = double.tryParse(_insumoSimpleCtrl.text) ?? 0.0;
      final cantProducida = int.tryParse(_producidasSimpleCtrl.text) ?? 0;
      final cantRoturas = int.tryParse(_roturasSimpleCtrl.text) ?? 0;

      if (cantInsumo <= 0 || cantProducida <= 0) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            backgroundColor: Colors.redAccent,
            content: Text('Debe ingresar cantidad de insumos y unidades producidas mayores a 0.'),
          ),
        );
        return;
      }

      final ok = await ref.read(transformacionProvider.notifier).registrarRelleno(
            turnoId: turnoId,
            recetaId: recetaId,
            insumoOrigenId: _insumoFisicoSimpleId ?? _recetaSeleccionada!['insumo_origen_id'],
            cantidadInsumo: cantInsumo,
            cantidadProducida: cantProducida,
            cantidadRoturas: cantRoturas,
            observaciones: _obsCtrl.text.trim().isNotEmpty
                ? _obsCtrl.text.trim()
                : 'Transformación Simple: ${_recetaSeleccionada!['nombre']}',
          );

      if (ok && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            backgroundColor: const Color(0xFF27AE60),
            content: Text('¡Transformación "${_recetaSeleccionada!['nombre']}" registrada con éxito!'),
          ),
        );
        _insumoSimpleCtrl.text = '24';
        _producidasSimpleCtrl.text = '20';
        _roturasSimpleCtrl.text = '0';
        _obsCtrl.clear();
      }
    } else {
      // Receta Compuesta
      final cant1 = double.tryParse(_insumo1Ctrl.text) ?? _cantInsumo1;
      final cant2 = double.tryParse(_insumo2Ctrl.text) ?? _cantInsumo2;
      final cantProducida = int.tryParse(_producidasCompuestaCtrl.text) ?? 0;
      final cantRoturas = int.tryParse(_roturasCompuestaCtrl.text) ?? 0;

      if (cant1 <= 0 && cant2 <= 0) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            backgroundColor: Colors.redAccent,
            content: Text('Debe ingresar al menos una fracción de alguno de los insumos.'),
          ),
        );
        return;
      }

      if (cantProducida <= 0) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            backgroundColor: Colors.redAccent,
            content: Text('Debe producir al menos 1 unidad de producto terminado.'),
          ),
        );
        return;
      }

      final insumos = <Map<String, dynamic>>[];
      final insumo1Id = _recetaSeleccionada!['insumo_origen_id'] as int;
      final insumo2Id = _recetaSeleccionada!['insumo_secundario_id'] as int;

      if (cant1 > 0) insumos.add({'insumo_id': insumo1Id, 'cantidad': cant1});
      if (cant2 > 0) insumos.add({'insumo_id': insumo2Id, 'cantidad': cant2});

      final totalInsumo = cant1 + cant2;

      final ok = await ref.read(transformacionProvider.notifier).registrarRelleno(
            turnoId: turnoId,
            recetaId: recetaId,
            insumoOrigenId: insumo1Id,
            insumosOrigen: insumos,
            cantidadInsumo: totalInsumo,
            cantidadProducida: cantProducida,
            cantidadRoturas: cantRoturas,
            observaciones: _obsCtrl.text.trim().isNotEmpty
                ? _obsCtrl.text.trim()
                : 'Mezcla Compuesta: ${_recetaSeleccionada!['nombre']} ($cant1 btl + $cant2 btl)',
          );

      if (ok && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            backgroundColor: const Color(0xFF27AE60),
            content: Text('¡Mezcla compuesta "${_recetaSeleccionada!['nombre']}" registrada con éxito!'),
          ),
        );
        _insumo1Ctrl.text = '0.50';
        _insumo2Ctrl.text = '0.50';
        _cantInsumo1 = 0.50;
        _cantInsumo2 = 0.50;
        _producidasCompuestaCtrl.text = '1';
        _roturasCompuestaCtrl.text = '0';
        _obsCtrl.clear();
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(transformacionProvider);

    return Scaffold(
      backgroundColor: const Color(0xFF121620),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1B2332),
        title: const Text('Transformaciones de Barra', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.white)),
        iconTheme: const IconThemeData(color: Colors.white),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh, color: Colors.white70),
            tooltip: 'Recargar recetas y productos',
            onPressed: _cargarDatos,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Colors.amberAccent))
          : _recetas.isEmpty
              ? _buildEstadoSinRecetas()
              : SingleChildScrollView(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      // Selector Dinámico de Receta
                      _buildSelectorReceta(),
                      const SizedBox(height: 16),

                      if (_recetaSeleccionada != null) ...[
                        // Banner de Indicadores en Vivo
                        _buildBannerMetricas(),
                        const SizedBox(height: 20),

                        // Formulario según tipo de Receta (Simple o Compuesta)
                        if (_recetaSeleccionada!['insumo_secundario_id'] == null)
                          _buildFormularioSimple(state)
                        else
                          _buildFormularioCompuesto(state),

                        const SizedBox(height: 24),

                        // Observaciones opcionales
                        TextField(
                          controller: _obsCtrl,
                          style: const TextStyle(color: Colors.white, fontSize: 14),
                          decoration: InputDecoration(
                            labelText: 'Observaciones (opcional)',
                            labelStyle: const TextStyle(color: Colors.white60),
                            hintText: 'Ej. Relleno antes del turno pico...',
                            hintStyle: const TextStyle(color: Colors.white24),
                            filled: true,
                            fillColor: const Color(0xFF1B2332),
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                          ),
                        ),
                        const SizedBox(height: 24),

                        // Botón de Confirmación y Guardado
                        SizedBox(
                          height: 52,
                          child: ElevatedButton.icon(
                            style: ElevatedButton.styleFrom(
                              backgroundColor: const Color(0xFF27AE60),
                              foregroundColor: Colors.white,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                              elevation: 4,
                            ),
                            icon: state.isSubmitting
                                ? const SizedBox(
                                    width: 20,
                                    height: 20,
                                    child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                                  )
                                : const Icon(Icons.check_circle, size: 22),
                            label: Text(
                              state.isSubmitting
                                  ? 'PROCESANDO REGISTRO...'
                                  : 'REGISTRAR TRANSFORMACIÓN',
                              style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold, letterSpacing: 0.5),
                            ),
                            onPressed: state.isSubmitting ? null : _guardarTransformacion,
                          ),
                        ),
                        const SizedBox(height: 20),
                      ],
                    ],
                  ),
                ),
    );
  }

  Widget _buildEstadoSinRecetas() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24.0),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.menu_book, color: Colors.white30, size: 64),
            const SizedBox(height: 16),
            const Text(
              'No hay recetas de transformación activas',
              style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 8),
            const Text(
              'El Administrador puede configurar nuevas recetas simples y compuestas desde el panel de Recetas.',
              style: TextStyle(color: Colors.white60, fontSize: 13),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 20),
            ElevatedButton.icon(
              style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF3498DB)),
              icon: const Icon(Icons.refresh),
              label: const Text('Verificar Nuevamente'),
              onPressed: _cargarDatos,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSelectorReceta() {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFF1B2332),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFF3498DB).withOpacity(0.5)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: const [
              Icon(Icons.auto_awesome, color: Color(0xFF3498DB), size: 20),
              SizedBox(width: 8),
              Text(
                'Seleccione la Fórmula / Receta a Registrar:',
                style: TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold),
              ),
            ],
          ),
          const SizedBox(height: 10),
          DropdownButtonHideUnderline(
            child: DropdownButton<int>(
              value: _recetaSeleccionada?['id'],
              dropdownColor: const Color(0xFF1B2332),
              isExpanded: true,
              style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.bold),
              items: _recetas.map((r) {
                final esCompuesta = r['insumo_secundario_id'] != null;
                final badge = esCompuesta ? ' [Compuesta]' : ' [Simple]';
                return DropdownMenuItem<int>(
                  value: r['id'] as int,
                  child: Text(
                    '${r['nombre']}$badge',
                    overflow: TextOverflow.ellipsis,
                  ),
                );
              }).toList(),
              onChanged: (id) {
                if (id != null) {
                  final seleccionada = _recetas.firstWhere((r) => r['id'] == id);
                  _seleccionarReceta(seleccionada);
                }
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBannerMetricas() {
    final esCompuesta = _recetaSeleccionada!['insumo_secundario_id'] != null;
    final tarifa = (_recetaSeleccionada!['tarifa_comision_unidad'] ?? _recetaSeleccionada!['tarifa_comision'] ?? 1.0).toDouble();
    final nombreDestino = _recetaSeleccionada!['producto_destino']?['nombre'] ??
        _obtenerNombreProducto(_recetaSeleccionada!['producto_destino_id']);

    int produc = 0;
    int roturas = 0;
    double totalInsumo = 0.0;

    if (!esCompuesta) {
      produc = int.tryParse(_producidasSimpleCtrl.text) ?? 0;
      roturas = int.tryParse(_roturasSimpleCtrl.text) ?? 0;
      totalInsumo = double.tryParse(_insumoSimpleCtrl.text) ?? 0.0;
    } else {
      produc = int.tryParse(_producidasCompuestaCtrl.text) ?? 0;
      roturas = int.tryParse(_roturasCompuestaCtrl.text) ?? 0;
      totalInsumo = (double.tryParse(_insumo1Ctrl.text) ?? _cantInsumo1) +
          (double.tryParse(_insumo2Ctrl.text) ?? _cantInsumo2);
    }

    final netas = (produc - roturas).clamp(0, 9999);
    final comision = double.parse((netas * tarifa).toStringAsFixed(2));
    final ratio = produc > 0 ? double.parse((totalInsumo / produc).toStringAsFixed(2)) : 0.0;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: esCompuesta
              ? [const Color(0xFF4A148C), const Color(0xFF8E24AA)]
              : [const Color(0xFF1E3C72), const Color(0xFF2A5298)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(14),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.3),
            blurRadius: 8,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Producto Resultante:', style: TextStyle(color: Colors.white70, fontSize: 13)),
              Flexible(
                child: Text(
                  nombreDestino,
                  style: const TextStyle(color: Colors.amberAccent, fontSize: 15, fontWeight: FontWeight.bold),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ],
          ),
          const Divider(color: Colors.white24, height: 16),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Comisión Ganada:', style: TextStyle(color: Colors.white70, fontSize: 12)),
                  const SizedBox(height: 2),
                  Text(
                    '$comision Bs',
                    style: const TextStyle(color: Colors.amberAccent, fontSize: 24, fontWeight: FontWeight.bold),
                  ),
                ],
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text('Tarifa: $tarifa Bs / un.', style: const TextStyle(color: Colors.white, fontSize: 12)),
                  const SizedBox(height: 4),
                  Text('Netas: $netas un.', style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 2),
                  Text('Ratio: $ratio', style: const TextStyle(color: Colors.lightGreenAccent, fontSize: 12, fontWeight: FontWeight.bold)),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildFormularioSimple(TransformacionState state) {
    final insumoOrigenId = _recetaSeleccionada!['insumo_origen_id'] as int?;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Selector de Insumo Físico Intercambiable
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          decoration: BoxDecoration(
            color: const Color(0xFF1E293B),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: Colors.amberAccent.withOpacity(0.4)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: const [
                  Icon(Icons.swap_horiz, color: Colors.amberAccent, size: 18),
                  SizedBox(width: 8),
                  Text(
                    'Materia Prima Utilizada (Insumo Físico Real):',
                    style: TextStyle(color: Colors.amberAccent, fontSize: 12, fontWeight: FontWeight.bold),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              DropdownButtonHideUnderline(
                child: DropdownButton<int>(
                  value: _insumoFisicoSimpleId ?? insumoOrigenId,
                  isExpanded: true,
                  dropdownColor: const Color(0xFF1E293B),
                  style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.bold),
                  items: _productos.map((p) {
                    return DropdownMenuItem<int>(
                      value: p['id'] as int,
                      child: Text(p['nombre'].toString(), overflow: TextOverflow.ellipsis),
                    );
                  }).toList(),
                  onChanged: (val) {
                    if (val != null) setState(() => _insumoFisicoSimpleId = val);
                  },
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),

        // 1. Cantidad Insumo Consumido
        const Text(
          '1. Materia Prima Consumida',
          style: TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w600),
        ),
        const SizedBox(height: 8),
        TextField(
          controller: _insumoSimpleCtrl,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold),
          decoration: InputDecoration(
            filled: true,
            fillColor: const Color(0xFF1B2332),
            hintText: 'Ej. 24',
            hintStyle: const TextStyle(color: Colors.white38),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
            suffixText: 'unidades',
            suffixStyle: const TextStyle(color: Colors.white60),
          ),
          onChanged: (val) {
            setState(() {});
            final d = double.tryParse(val) ?? 0.0;
            ref.read(transformacionProvider.notifier).setCantidadInsumo(d);
          },
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            _buildBotonAtajo('+6', () => _incrementarInsumoSimple(6)),
            const SizedBox(width: 8),
            _buildBotonAtajo('+12', () => _incrementarInsumoSimple(12)),
            const SizedBox(width: 8),
            _buildBotonAtajo('+24', () => _incrementarInsumoSimple(24)),
            const SizedBox(width: 8),
            _buildBotonAtajo('+48', () => _incrementarInsumoSimple(48)),
          ],
        ),
        const SizedBox(height: 20),

        // 2. Unidades Producidas
        const Text(
          '2. Unidades Terminadas Obtenidas',
          style: TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w600),
        ),
        const SizedBox(height: 8),
        TextField(
          controller: _producidasSimpleCtrl,
          keyboardType: TextInputType.number,
          style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold),
          decoration: InputDecoration(
            filled: true,
            fillColor: const Color(0xFF1B2332),
            hintText: 'Ej. 20',
            hintStyle: const TextStyle(color: Colors.white38),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
            suffixText: 'unidades',
            suffixStyle: const TextStyle(color: Colors.white60),
          ),
          onChanged: (val) {
            setState(() {});
            final i = int.tryParse(val) ?? 0;
            ref.read(transformacionProvider.notifier).setCantidadProducida(i);
          },
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            _buildBotonAtajo('+6', () => _incrementarProducidasSimple(6)),
            const SizedBox(width: 8),
            _buildBotonAtajo('+12', () => _incrementarProducidasSimple(12)),
            const SizedBox(width: 8),
            _buildBotonAtajo('+20', () => _incrementarProducidasSimple(20)),
            const SizedBox(width: 8),
            _buildBotonAtajo('+24', () => _incrementarProducidasSimple(24)),
          ],
        ),
        const SizedBox(height: 20),

        // 3. Roturas
        const Text(
          '3. Botellas Rotas / Dañadas (0% Comisión)',
          style: TextStyle(color: Colors.white70, fontSize: 13),
        ),
        const SizedBox(height: 6),
        TextField(
          controller: _roturasSimpleCtrl,
          keyboardType: TextInputType.number,
          style: const TextStyle(color: Colors.white, fontSize: 18),
          decoration: InputDecoration(
            filled: true,
            fillColor: const Color(0xFF1B2332),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
          ),
          onChanged: (val) {
            setState(() {});
            final i = int.tryParse(val) ?? 0;
            ref.read(transformacionProvider.notifier).setCantidadRoturas(i);
          },
        ),
      ],
    );
  }

  Widget _buildFormularioCompuesto(TransformacionState state) {
    final insumo1Nombre = _recetaSeleccionada!['insumo_origen']?['nombre'] ??
        _obtenerNombreProducto(_recetaSeleccionada!['insumo_origen_id']);
    final insumo2Nombre = _recetaSeleccionada!['insumo_secundario']?['nombre'] ??
        _obtenerNombreProducto(_recetaSeleccionada!['insumo_secundario_id']);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Insumo 1 Card
        Card(
          color: const Color(0xFF1B2332),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Expanded(
                      child: Text(
                        'Insumo 1: $insumo1Nombre',
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    Text(
                      '${_cantInsumo1.toStringAsFixed(2)} btl',
                      style: const TextStyle(color: Colors.amberAccent, fontWeight: FontWeight.bold, fontSize: 15),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    _buildFraccionBoton('0.25 btl', () {
                      setState(() {
                        _cantInsumo1 = 0.25;
                        _insumo1Ctrl.text = '0.25';
                      });
                    }),
                    const SizedBox(width: 6),
                    _buildFraccionBoton('0.50 btl', () {
                      setState(() {
                        _cantInsumo1 = 0.50;
                        _insumo1Ctrl.text = '0.50';
                      });
                    }),
                    const SizedBox(width: 6),
                    _buildFraccionBoton('0.75 btl', () {
                      setState(() {
                        _cantInsumo1 = 0.75;
                        _insumo1Ctrl.text = '0.75';
                      });
                    }),
                    const SizedBox(width: 6),
                    _buildFraccionBoton('1.00 btl', () {
                      setState(() {
                        _cantInsumo1 = 1.00;
                        _insumo1Ctrl.text = '1.00';
                      });
                    }),
                  ],
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: _insumo1Ctrl,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  style: const TextStyle(color: Colors.white, fontSize: 15),
                  decoration: const InputDecoration(
                    labelText: 'Cantidad manual (btl)',
                    labelStyle: TextStyle(color: Colors.white60, fontSize: 12),
                    isDense: true,
                  ),
                  onChanged: (val) {
                    final d = double.tryParse(val);
                    if (d != null) setState(() => _cantInsumo1 = d);
                  },
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 14),

        // Insumo 2 Card
        Card(
          color: const Color(0xFF1B2332),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Expanded(
                      child: Text(
                        'Insumo 2: $insumo2Nombre',
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    Text(
                      '${_cantInsumo2.toStringAsFixed(2)} btl',
                      style: const TextStyle(color: Colors.amberAccent, fontWeight: FontWeight.bold, fontSize: 15),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    _buildFraccionBoton('0.25 btl', () {
                      setState(() {
                        _cantInsumo2 = 0.25;
                        _insumo2Ctrl.text = '0.25';
                      });
                    }),
                    const SizedBox(width: 6),
                    _buildFraccionBoton('0.50 btl', () {
                      setState(() {
                        _cantInsumo2 = 0.50;
                        _insumo2Ctrl.text = '0.50';
                      });
                    }),
                    const SizedBox(width: 6),
                    _buildFraccionBoton('0.75 btl', () {
                      setState(() {
                        _cantInsumo2 = 0.75;
                        _insumo2Ctrl.text = '0.75';
                      });
                    }),
                    const SizedBox(width: 6),
                    _buildFraccionBoton('1.00 btl', () {
                      setState(() {
                        _cantInsumo2 = 1.00;
                        _insumo2Ctrl.text = '1.00';
                      });
                    }),
                  ],
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: _insumo2Ctrl,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  style: const TextStyle(color: Colors.white, fontSize: 15),
                  decoration: const InputDecoration(
                    labelText: 'Cantidad manual (btl)',
                    labelStyle: TextStyle(color: Colors.white60, fontSize: 12),
                    isDense: true,
                  ),
                  onChanged: (val) {
                    final d = double.tryParse(val);
                    if (d != null) setState(() => _cantInsumo2 = d);
                  },
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 18),

        // Unidades Producidas
        const Text('Botellas Producidas Terminadas',
            style: TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w600)),
        const SizedBox(height: 8),
        TextField(
          controller: _producidasCompuestaCtrl,
          keyboardType: TextInputType.number,
          style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold),
          decoration: InputDecoration(
            filled: true,
            fillColor: const Color(0xFF1B2332),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
            suffixText: 'botellas',
          ),
          onChanged: (val) => setState(() {}),
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            _buildBotonAtajo('+1', () {
              final a = int.tryParse(_producidasCompuestaCtrl.text) ?? 0;
              _producidasCompuestaCtrl.text = (a + 1).toString();
              setState(() {});
            }),
            const SizedBox(width: 8),
            _buildBotonAtajo('+2', () {
              final a = int.tryParse(_producidasCompuestaCtrl.text) ?? 0;
              _producidasCompuestaCtrl.text = (a + 2).toString();
              setState(() {});
            }),
            const SizedBox(width: 8),
            _buildBotonAtajo('+5', () {
              final a = int.tryParse(_producidasCompuestaCtrl.text) ?? 0;
              _producidasCompuestaCtrl.text = (a + 5).toString();
              setState(() {});
            }),
          ],
        ),
        const SizedBox(height: 16),

        // Roturas
        const Text('Botellas Rotas / Dañadas', style: TextStyle(color: Colors.white70, fontSize: 13)),
        const SizedBox(height: 6),
        TextField(
          controller: _roturasCompuestaCtrl,
          keyboardType: TextInputType.number,
          style: const TextStyle(color: Colors.white, fontSize: 16),
          decoration: InputDecoration(
            filled: true,
            fillColor: const Color(0xFF1B2332),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
          ),
          onChanged: (val) => setState(() {}),
        ),
      ],
    );
  }

  Widget _buildBotonAtajo(String texto, VoidCallback onPressed) {
    return Expanded(
      child: OutlinedButton(
        style: OutlinedButton.styleFrom(
          foregroundColor: const Color(0xFF3498DB),
          side: const BorderSide(color: Color(0xFF3498DB)),
          padding: const EdgeInsets.symmetric(vertical: 8),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        ),
        onPressed: onPressed,
        child: Text(texto, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
      ),
    );
  }

  Widget _buildFraccionBoton(String texto, VoidCallback onPressed) {
    return Expanded(
      child: OutlinedButton(
        style: OutlinedButton.styleFrom(
          foregroundColor: Colors.amberAccent,
          side: const BorderSide(color: Colors.amberAccent),
          padding: const EdgeInsets.symmetric(vertical: 6),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
        ),
        onPressed: onPressed,
        child: Text(texto, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
      ),
    );
  }
}
