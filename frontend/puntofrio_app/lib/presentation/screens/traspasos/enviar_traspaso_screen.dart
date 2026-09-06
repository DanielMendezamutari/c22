import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../providers/auth_provider.dart';

class TraspasoItemLinea {
  int productoId;
  TextEditingController cantidadCtrl;

  TraspasoItemLinea({required this.productoId, required this.cantidadCtrl});
}

class EnviarTraspasoScreen extends ConsumerStatefulWidget {
  const EnviarTraspasoScreen({super.key});

  @override
  ConsumerState<EnviarTraspasoScreen> createState() => _EnviarTraspasoScreenState();
}

class _EnviarTraspasoScreenState extends ConsumerState<EnviarTraspasoScreen> {
  final _formKey = GlobalKey<FormState>();
  final _obsCtrl = TextEditingController();

  int? _sucursalDestinoId;
  bool _isSubmitting = false;

  final List<Map<String, dynamic>> _todasSucursales = [
    {'id': 1, 'nombre': 'Casa22'},
    {'id': 2, 'nombre': 'Casa Coron'},
    {'id': 3, 'nombre': 'Madan'},
  ];

  final List<Map<String, dynamic>> _productos = [
    {'id': 1, 'nombre': 'Corona en Lata 355ml (Insumo)'},
    {'id': 2, 'nombre': 'Corona en Botella 355ml (Terminado)'},
    {'id': 3, 'nombre': 'Ron Flor de Caña 750ml'},
    {'id': 4, 'nombre': 'Vodka Absolut 750ml'},
    {'id': 5, 'nombre': 'Whisky Red Label 750ml'},
  ];

  final List<TraspasoItemLinea> _lineas = [];

  @override
  void initState() {
    super.initState();
    final auth = ref.read(authProvider);
    final miSucursal = auth.sucursalId ?? 1;
    final destinos = _todasSucursales.where((s) => s['id'] != miSucursal).toList();
    if (destinos.isNotEmpty) {
      _sucursalDestinoId = destinos.first['id'] as int;
    }

    // Inicializar con al menos 1 producto en la lista
    _lineas.add(TraspasoItemLinea(
      productoId: 1,
      cantidadCtrl: TextEditingController(text: '12.00'),
    ));
  }

  @override
  void dispose() {
    for (var l in _lineas) {
      l.cantidadCtrl.dispose();
    }
    _obsCtrl.dispose();
    super.dispose();
  }

  void _agregarLinea() {
    setState(() {
      _lineas.add(TraspasoItemLinea(
        productoId: _productos.first['id'] as int,
        cantidadCtrl: TextEditingController(text: '1.00'),
      ));
    });
  }

  void _eliminarLinea(int index) {
    if (_lineas.length <= 1) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Debe incluir al menos un producto en el traspaso.')),
      );
      return;
    }
    setState(() {
      _lineas[index].cantidadCtrl.dispose();
      _lineas.removeAt(index);
    });
  }

  Future<void> _despacharTraspaso() async {
    if (!_formKey.currentState!.validate()) return;
    if (_sucursalDestinoId == null) return;

    final auth = ref.read(authProvider);
    final miSucursalId = auth.sucursalId ?? 1;

    final itemsPayload = <Map<String, dynamic>>[];
    for (var l in _lineas) {
      final cant = double.tryParse(l.cantidadCtrl.text.trim()) ?? 0.0;
      if (cant <= 0) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Cada producto debe tener una cantidad mayor a 0.'), backgroundColor: Colors.redAccent),
        );
        return;
      }
      itemsPayload.add({
        'producto_id': l.productoId,
        'cantidad': cant,
      });
    }

    setState(() => _isSubmitting = true);

    try {
      final apiClient = ref.read(apiClientProvider);
      final res = await apiClient.post('/traspasos/enviar', data: {
        'sucursal_origen_id': miSucursalId,
        'sucursal_destino_id': _sucursalDestinoId,
        'items': itemsPayload,
        'observaciones': _obsCtrl.text.trim().isEmpty ? 'Despacho inter-sucursal multi-producto' : _obsCtrl.text.trim(),
      });

      if (mounted) {
        if (res.statusCode == 201) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Traspaso despachado. Mercadería en custodia y estado "En Tránsito".'),
              backgroundColor: Color(0xFF27AE60),
            ),
          );
          Navigator.of(context).pop();
        } else {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Error: ${res.data['error']}'), backgroundColor: Colors.redAccent),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error al despachar: $e'), backgroundColor: Colors.redAccent),
        );
      }
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.read(authProvider);
    final miSucursalId = auth.sucursalId ?? 1;
    final destinos = _todasSucursales.where((s) => s['id'] != miSucursalId).toList();

    return Scaffold(
      backgroundColor: const Color(0xFF121620),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1B2332),
        title: const Text('Despachar Traspaso', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: const Color(0xFF16A085).withOpacity(0.15),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFF16A085).withOpacity(0.4)),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.swap_horiz, color: Color(0xFF1ABC9C), size: 28),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        'Origen: ${auth.sucursalNombre ?? 'Mi Sucursal'} ➔ Destino (Envíe múltiples productos en una sola orden)',
                        style: const TextStyle(color: Colors.white70, fontSize: 13),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),

              // Selector Sucursal Destino
              const Text('Sucursal Destino:', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14)),
              const SizedBox(height: 8),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
                decoration: BoxDecoration(
                  color: const Color(0xFF1B2332),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: Colors.white24),
                ),
                child: DropdownButtonHideUnderline(
                  child: DropdownButton<int>(
                    dropdownColor: const Color(0xFF1B2332),
                    value: _sucursalDestinoId,
                    isExpanded: true,
                    style: const TextStyle(color: Colors.white, fontSize: 15),
                    items: destinos.map((d) {
                      return DropdownMenuItem<int>(
                        value: d['id'] as int,
                        child: Text(d['nombre'] as String),
                      );
                    }).toList(),
                    onChanged: (val) {
                      if (val != null) setState(() => _sucursalDestinoId = val);
                    },
                  ),
                ),
              ),
              const SizedBox(height: 20),

              // Lista de Productos a Traspasar
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Productos a Despachar:', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15)),
                  TextButton.icon(
                    icon: const Icon(Icons.add_circle, color: Color(0xFF1ABC9C), size: 18),
                    label: const Text('AÑADIR OTRO', style: TextStyle(color: Color(0xFF1ABC9C), fontWeight: FontWeight.bold)),
                    onPressed: _agregarLinea,
                  ),
                ],
              ),
              const SizedBox(height: 8),

              ...List.generate(_lineas.length, (idx) {
                final linea = _lineas[idx];
                return Card(
                  color: const Color(0xFF1B2332),
                  margin: const EdgeInsets.only(bottom: 12),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                    side: const BorderSide(color: Colors.white12),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      children: [
                        Row(
                          children: [
                            CircleAvatar(
                              radius: 12,
                              backgroundColor: const Color(0xFF16A085),
                              child: Text('${idx + 1}', style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold)),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: DropdownButtonHideUnderline(
                                child: DropdownButton<int>(
                                  dropdownColor: const Color(0xFF1B2332),
                                  value: linea.productoId,
                                  isExpanded: true,
                                  style: const TextStyle(color: Colors.white, fontSize: 14),
                                  items: _productos.map((p) {
                                    return DropdownMenuItem<int>(
                                      value: p['id'] as int,
                                      child: Text(p['nombre'] as String),
                                    );
                                  }).toList(),
                                  onChanged: (val) {
                                    if (val != null) setState(() => linea.productoId = val);
                                  },
                                ),
                              ),
                            ),
                            if (_lineas.length > 1)
                              IconButton(
                                icon: const Icon(Icons.delete_outline, color: Colors.redAccent, size: 20),
                                onPressed: () => _eliminarLinea(idx),
                              ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        TextFormField(
                          controller: linea.cantidadCtrl,
                          keyboardType: const TextInputType.numberWithOptions(decimal: true),
                          style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
                          decoration: InputDecoration(
                            filled: true,
                            fillColor: const Color(0xFF121620),
                            labelText: 'Cantidad a Despachar',
                            labelStyle: const TextStyle(color: Colors.white60, fontSize: 12),
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: BorderSide.none),
                            prefixIcon: const Icon(Icons.format_list_numbered, color: Color(0xFF1ABC9C), size: 18),
                          ),
                          validator: (val) {
                            if (val == null || val.trim().isEmpty) return 'Ingrese cantidad';
                            final n = double.tryParse(val.trim());
                            if (n == null || n <= 0) return 'Mayor a 0';
                            return null;
                          },
                        ),
                      ],
                    ),
                  ),
                );
              }),

              const SizedBox(height: 12),

              // Observaciones
              const Text('Observaciones / Chofer de Traslado:', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14)),
              const SizedBox(height: 8),
              TextFormField(
                controller: _obsCtrl,
                style: const TextStyle(color: Colors.white),
                decoration: InputDecoration(
                  filled: true,
                  fillColor: const Color(0xFF1B2332),
                  hintText: 'Ej. Traslado con chofer Juan en taxi móvil',
                  hintStyle: const TextStyle(color: Colors.white38),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                  prefixIcon: const Icon(Icons.local_shipping, color: Colors.white70),
                ),
              ),
              const SizedBox(height: 24),

              ElevatedButton(
                onPressed: _isSubmitting ? null : _despacharTraspaso,
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF16A085),
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: _isSubmitting
                    ? const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                    : const Text(
                        'DESPACHAR TRASPASO',
                        style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
