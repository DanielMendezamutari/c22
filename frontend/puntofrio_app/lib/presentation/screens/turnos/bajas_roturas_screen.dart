import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../providers/auth_provider.dart';

class BajasRoturasScreen extends ConsumerStatefulWidget {
  const BajasRoturasScreen({super.key});

  @override
  ConsumerState<BajasRoturasScreen> createState() => _BajasRoturasScreenState();
}

class _BajasRoturasScreenState extends ConsumerState<BajasRoturasScreen> {
  bool _isLoading = true;
  bool _isSubmitting = false;
  List<Map<String, dynamic>> _productos = [];
  int? _productoSeleccionadoId;
  final TextEditingController _cantidadCtrl = TextEditingController(text: '1');
  final TextEditingController _obsCtrl = TextEditingController();
  String _motivoSeleccionado = 'Rotura Accidental en Barra';

  List<String> _motivos = [
    'Rotura Accidental en Barra',
    'Botella Quebrada en Descorche',
    'Defecto de Fábrica / Sin Gas',
    'Vencimiento de Producto',
    'Derrame Accidental',
  ];

  final List<Map<String, dynamic>> _bajasRecientes = [];

  @override
  void initState() {
    super.initState();
    _cargarDatos();
  }

  @override
  void dispose() {
    _cantidadCtrl.dispose();
    _obsCtrl.dispose();
    super.dispose();
  }

  Future<void> _cargarDatos() async {
    final client = ref.read(apiClientProvider);

    // 1. Cargar motivos dinámicos desde API
    try {
      final resMotivos = await client.get('/motivos-baja');
      if (resMotivos.data['success'] == true && resMotivos.data['data'] != null) {
        final List<dynamic> lista = resMotivos.data['data'];
        final descs = lista.map((m) => m['descripcion'].toString()).toList();
        if (descs.isNotEmpty) {
          setState(() {
            _motivos = descs;
            _motivoSeleccionado = _motivos.first;
          });
        }
      }
    } catch (_) {}

    // 2. Cargar catálogo de productos
    try {
      final resp = await client.get('/productos');
      final List<dynamic> items = resp.data['data'] ?? [];

      setState(() {
        _productos = items.map((e) => {
          'id': e['id'],
          'nombre': e['nombre'],
          'tipo': e['tipo'],
          'unidad': e['unidad_medida'],
        }).toList();

        if (_productos.isNotEmpty) {
          _productoSeleccionadoId = _productos.first['id'] as int;
        }
        _isLoading = false;
      });
    } catch (e) {
      // Fallback a productos estándar si falla
      setState(() {
        _productos = [
          {'id': 1, 'nombre': 'Corona en Lata 355ml', 'tipo': 'insumo', 'unidad': 'unidad'},
          {'id': 2, 'nombre': 'Corona en Botella 355ml', 'tipo': 'terminado', 'unidad': 'unidad'},
          {'id': 3, 'nombre': 'Ron Flor de Caña 750ml', 'tipo': 'insumo', 'unidad': 'fraccion_cuartos'},
          {'id': 4, 'nombre': 'Vodka Absolut 750ml', 'tipo': 'insumo', 'unidad': 'fraccion_cuartos'},
          {'id': 5, 'nombre': 'Whisky Red Label 750ml', 'tipo': 'insumo', 'unidad': 'fraccion_cuartos'},
        ];
        _productoSeleccionadoId = 1;
        _isLoading = false;
      });
    }
  }

  Future<void> _registrarBaja() async {
    final cantidad = double.tryParse(_cantidadCtrl.text.trim());
    if (cantidad == null || cantidad <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Ingrese una cantidad válida mayor a 0'), backgroundColor: Colors.orange),
      );
      return;
    }

    if (_productoSeleccionadoId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Seleccione un producto'), backgroundColor: Colors.orange),
      );
      return;
    }

    final auth = ref.read(authProvider);
    final prod = _productos.firstWhere((p) => p['id'] == _productoSeleccionadoId, orElse: () => {'nombre': 'Producto'});

    final bool? confirmar = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: const Color(0xFF1E293B),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Row(
          children: const [
            Icon(Icons.warning_amber_rounded, color: Colors.redAccent),
            SizedBox(width: 8),
            Text('Confirmar Baja', style: TextStyle(color: Colors.white, fontSize: 18)),
          ],
        ),
        content: Text(
          '¿Está seguro de registrar la baja de $cantidad x "${prod['nombre']}" por concepto de "$_motivoSeleccionado"?\n\nEsta acción descontará el stock de la sucursal.',
          style: const TextStyle(color: Colors.white70, fontSize: 14),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('CANCELAR', style: TextStyle(color: Colors.white60)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.redAccent),
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('CONFIRMAR BAJA', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );

    if (confirmar != true) return;

    setState(() => _isSubmitting = true);

    try {
      final client = ref.read(apiClientProvider);
      final resp = await client.post('/inventario/bajas', data: {
        'producto_id': _productoSeleccionadoId,
        'cantidad': cantidad,
        'motivo': _motivoSeleccionado,
        'observaciones': _obsCtrl.text.trim().isEmpty ? _motivoSeleccionado : '$_motivoSeleccionado - ${_obsCtrl.text.trim()}',
        'tipo_baja': 'rotura',
        'sucursal_id': auth.sucursalId ?? 1,
        'turno_id': auth.turnoActivoId,
        'usuario_id': auth.usuarioId,
      });

      if (resp.data['success'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Baja registrada correctamente: $cantidad x ${prod['nombre']}'),
            backgroundColor: Colors.green,
          ),
        );

        setState(() {
          _bajasRecientes.insert(0, {
            'producto': prod['nombre'],
            'cantidad': cantidad,
            'motivo': _motivoSeleccionado,
            'hora': '${DateTime.now().hour.toString().padLeft(2, '0')}:${DateTime.now().minute.toString().padLeft(2, '0')}',
          });
          _cantidadCtrl.text = '1';
          _obsCtrl.clear();
        });
      } else {
        throw Exception(resp.data['error'] ?? 'Error desconocido');
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error al registrar baja: $e'), backgroundColor: Colors.red),
      );
    } finally {
      setState(() => _isSubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0F172A),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1E293B),
        title: const Text('BAJAS / ROTURAS EN BARRA', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Colors.redAccent))
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Banner explicativo
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: const Color(0xFF7F1D1D).withOpacity(0.4),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: Colors.redAccent.withOpacity(0.5)),
                    ),
                    child: Row(
                      children: const [
                        Icon(Icons.broken_image_outlined, color: Colors.redAccent, size: 28),
                        SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            'Declare aquí botellas quebradas, mermas o productos en mal estado ocurridos durante su turno para balance exacto del inventario.',
                            style: TextStyle(color: Colors.white, fontSize: 12),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),

                  // Formulario de Baja
                  Card(
                    color: const Color(0xFF1E293B),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Detalle del Producto Afectado',
                            style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
                          ),
                          const SizedBox(height: 16),

                          // Selector de Producto
                          DropdownButtonFormField<int>(
                            value: _productoSeleccionadoId,
                            dropdownColor: const Color(0xFF1E293B),
                            style: const TextStyle(color: Colors.white),
                            decoration: const InputDecoration(
                              labelText: 'Producto *',
                              labelStyle: TextStyle(color: Colors.white70),
                              prefixIcon: Icon(Icons.liquor, color: Colors.redAccent),
                            ),
                            items: _productos.map((p) {
                              return DropdownMenuItem<int>(
                                value: p['id'] as int,
                                child: Text('${p['nombre']} (${p['tipo']})'),
                              );
                            }).toList(),
                            onChanged: (val) => setState(() => _productoSeleccionadoId = val),
                          ),
                          const SizedBox(height: 16),

                          // Cantidad y Stepper
                          Row(
                            children: [
                              Expanded(
                                flex: 2,
                                child: TextFormField(
                                  controller: _cantidadCtrl,
                                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                                  style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
                                  decoration: const InputDecoration(
                                    labelText: 'Cantidad *',
                                    labelStyle: TextStyle(color: Colors.white70),
                                    prefixIcon: Icon(Icons.pin, color: Colors.redAccent),
                                  ),
                                ),
                              ),
                              const SizedBox(width: 12),
                              IconButton(
                                icon: const Icon(Icons.remove_circle, color: Colors.redAccent, size: 32),
                                onPressed: () {
                                  final val = double.tryParse(_cantidadCtrl.text.trim()) ?? 1;
                                  if (val > 1) {
                                    _cantidadCtrl.text = (val - 1).toString();
                                  }
                                },
                              ),
                              IconButton(
                                icon: const Icon(Icons.add_circle, color: Colors.greenAccent, size: 32),
                                onPressed: () {
                                  final val = double.tryParse(_cantidadCtrl.text.trim()) ?? 0;
                                  _cantidadCtrl.text = (val + 1).toString();
                                },
                              ),
                            ],
                          ),
                          const SizedBox(height: 16),

                          // Motivo
                          DropdownButtonFormField<String>(
                            value: _motivoSeleccionado,
                            dropdownColor: const Color(0xFF1E293B),
                            style: const TextStyle(color: Colors.white),
                            decoration: const InputDecoration(
                              labelText: 'Causa / Motivo *',
                              labelStyle: TextStyle(color: Colors.white70),
                              prefixIcon: Icon(Icons.report_problem, color: Colors.amberAccent),
                            ),
                            items: _motivos.map((m) {
                              return DropdownMenuItem<String>(value: m, child: Text(m));
                            }).toList(),
                            onChanged: (val) {
                              if (val != null) setState(() => _motivoSeleccionado = val);
                            },
                          ),
                          const SizedBox(height: 16),

                          // Observaciones
                          TextFormField(
                            controller: _obsCtrl,
                            style: const TextStyle(color: Colors.white),
                            decoration: const InputDecoration(
                              labelText: 'Observaciones Adicionales (Opcional)',
                              labelStyle: TextStyle(color: Colors.white70),
                              hintText: 'Ej. Se cayó al sacar de la hielera',
                              hintStyle: TextStyle(color: Colors.white30),
                              prefixIcon: Icon(Icons.notes, color: Colors.white54),
                            ),
                          ),
                          const SizedBox(height: 24),

                          // Botón de Enviar
                          SizedBox(
                            width: double.infinity,
                            height: 48,
                            child: ElevatedButton.icon(
                              icon: _isSubmitting
                                  ? const SizedBox(
                                      width: 20,
                                      height: 20,
                                      child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                                    )
                                  : const Icon(Icons.delete_forever, color: Colors.white),
                              label: Text(
                                _isSubmitting ? 'REGISTRANDO...' : 'REGISTRAR BAJA DE INVENTARIO',
                                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                              ),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: const Color(0xFFDC2626),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                              ),
                              onPressed: _isSubmitting ? null : _registrarBaja,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 20),

                  // Historial de Bajas Registradas en la Sesión
                  if (_bajasRecientes.isNotEmpty) ...[
                    const Text(
                      'Bajas Registradas en Esta Sesión',
                      style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 8),
                    ...List.generate(_bajasRecientes.length, (idx) {
                      final item = _bajasRecientes[idx];
                      return Card(
                        color: const Color(0xFF1E293B).withOpacity(0.6),
                        margin: const EdgeInsets.only(bottom: 8),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        child: ListTile(
                          leading: const CircleAvatar(
                            backgroundColor: Color(0xFF7F1D1D),
                            child: Icon(Icons.check, color: Colors.redAccent, size: 20),
                          ),
                          title: Text(item['producto'], style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                          subtitle: Text('${item['motivo']} • ${item['hora']}', style: const TextStyle(color: Colors.white60, fontSize: 12)),
                          trailing: Text(
                            '-${item['cantidad']}',
                            style: const TextStyle(color: Colors.redAccent, fontWeight: FontWeight.bold, fontSize: 16),
                          ),
                        ),
                      );
                    }),
                  ],
                ],
              ),
            ),
    );
  }
}
