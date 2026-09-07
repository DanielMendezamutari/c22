import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/api_client.dart';
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
  bool _isLoading = false;
  bool _isSubmitting = false;

  List<Map<String, dynamic>> _sucursalesReales = [];
  List<Map<String, dynamic>> _productosReales = [];
  Map<int, double> _stockDisponible = {};

  final List<TraspasoItemLinea> _lineas = [];

  @override
  void initState() {
    super.initState();
    _cargarDatosIniciales();
  }

  @override
  void dispose() {
    for (var l in _lineas) {
      l.cantidadCtrl.dispose();
    }
    _obsCtrl.dispose();
    super.dispose();
  }

  Future<void> _cargarDatosIniciales() async {
    setState(() => _isLoading = true);
    final auth = ref.read(authProvider);
    final miSucursalId = auth.sucursalId ?? 1;

    try {
      final client = ref.read(apiClientProvider);

      // 1. Cargar sucursales reales de la base de datos
      try {
        final resSuc = await client.get('/sucursales?activo=1');
        if (resSuc.statusCode == 200 && resSuc.data['success'] == true) {
          final List list = resSuc.data['data'] ?? [];
          _sucursalesReales = List<Map<String, dynamic>>.from(list);
        }
      } catch (_) {
        if (_sucursalesReales.isEmpty) {
          _sucursalesReales = [
            {'id': 1, 'nombre': 'Casa22'},
            {'id': 2, 'nombre': 'Casa Coron'},
            {'id': 3, 'nombre': 'Madan'},
          ];
        }
      }

      // 2. Cargar productos reales de la base de datos
      try {
        final resProd = await client.get('/productos');
        if (resProd.statusCode == 200 && resProd.data['success'] == true) {
          final List list = resProd.data['data'] ?? [];
          _productosReales = List<Map<String, dynamic>>.from(list);
        }
      } catch (_) {
        if (_productosReales.isEmpty) {
          _productosReales = [
            {'id': 1, 'nombre': 'Corona en Lata 355ml (Insumo)'},
            {'id': 2, 'nombre': 'Corona en Botella 355ml (Terminado)'},
            {'id': 3, 'nombre': 'Ron Flor de Caña 750ml'},
          ];
        }
      }

      // 3. Consultar stock disponible en la barra de origen
      try {
        final resStock = await client.get('/traspasos/stock-disponible?sucursal_id=$miSucursalId');
        if (resStock.statusCode == 200 && resStock.data['success'] == true) {
          final List list = resStock.data['data'] ?? [];
          final map = <int, double>{};
          for (var item in list) {
            final pId = item['producto_id'] as int;
            final stock = (item['stock_disponible'] as num?)?.toDouble() ?? 0.0;
            map[pId] = stock;
          }
          _stockDisponible = map;
        }
      } catch (_) {}

      // Configurar sucursal destino inicial (excluyendo la propia)
      final destinos = _sucursalesReales.where((s) => s['id'] != miSucursalId).toList();
      if (destinos.isNotEmpty && _sucursalDestinoId == null) {
        _sucursalDestinoId = destinos.first['id'] as int;
      }

      // Inicializar con la primera línea de producto si está vacía
      if (_lineas.isEmpty && _productosReales.isNotEmpty) {
        _lineas.add(TraspasoItemLinea(
          productoId: _productosReales.first['id'] as int,
          cantidadCtrl: TextEditingController(text: '1.00'),
        ));
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  void _agregarLinea() {
    if (_productosReales.isEmpty) return;
    setState(() {
      _lineas.add(TraspasoItemLinea(
        productoId: _productosReales.first['id'] as int,
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

  bool get _haySobregiroDeStock {
    for (var l in _lineas) {
      final cant = double.tryParse(l.cantidadCtrl.text.trim()) ?? 0.0;
      final stockDisp = _stockDisponible[l.productoId] ?? 0.0;
      if (cant > stockDisp) {
        return true;
      }
    }
    return false;
  }

  Future<void> _despacharTraspaso() async {
    if (!_formKey.currentState!.validate()) return;
    if (_sucursalDestinoId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Seleccione una sucursal destino.'), backgroundColor: Colors.orange),
      );
      return;
    }

    if (_haySobregiroDeStock) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('No puede despachar: la cantidad solicitada supera el stock disponible en barra.'),
          backgroundColor: Color(0xFFC0392B),
        ),
      );
      return;
    }

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
        if (res.statusCode == 201 || res.data['success'] == true) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Traspaso despachado exitosamente. Stock descontado en barra y puesto "En Tránsito".'),
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
    final destinos = _sucursalesReales.where((s) => s['id'] != miSucursalId).toList();
    final bool bloqueadoPorStock = _haySobregiroDeStock;

    return Scaffold(
      backgroundColor: const Color(0xFF121620),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1B2332),
        title: const Text('Despachar Traspaso', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        iconTheme: const IconThemeData(color: Colors.white),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: 'Actualizar stock',
            onPressed: _cargarDatosIniciales,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Color(0xFF1ABC9C)))
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16.0),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Banner de Origen y Destino
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
                              'Origen: ${auth.sucursalNombre ?? "Mi Barra"} ➔ Destino Seleccionado',
                              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),

                    // Selector Sucursal Destino Real
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
                          hint: const Text('Seleccione sucursal destino', style: TextStyle(color: Colors.white38)),
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
                      final stockDisp = _stockDisponible[linea.productoId] ?? 0.0;
                      final cantActual = double.tryParse(linea.cantidadCtrl.text.trim()) ?? 0.0;
                      final bool sobregiroLinea = cantActual > stockDisp;

                      return Card(
                        color: const Color(0xFF1B2332),
                        margin: const EdgeInsets.only(bottom: 12),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                          side: BorderSide(
                            color: sobregiroLinea ? Colors.redAccent : Colors.white12,
                            width: sobregiroLinea ? 1.5 : 1.0,
                          ),
                        ),
                        child: Padding(
                          padding: const EdgeInsets.all(12),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  CircleAvatar(
                                    radius: 12,
                                    backgroundColor: sobregiroLinea ? Colors.redAccent : const Color(0xFF16A085),
                                    child: Text('${idx + 1}', style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold)),
                                  ),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: DropdownButtonHideUnderline(
                                      child: DropdownButton<int>(
                                        dropdownColor: const Color(0xFF1E293B),
                                        value: linea.productoId,
                                        isExpanded: true,
                                        style: const TextStyle(color: Colors.white, fontSize: 14),
                                        items: _productosReales.map((p) {
                                          return DropdownMenuItem<int>(
                                            value: p['id'] as int,
                                            child: Text(p['nombre'] as String, overflow: TextOverflow.ellipsis),
                                          );
                                        }).toList(),
                                        onChanged: (val) {
                                          if (val != null) {
                                            setState(() => linea.productoId = val);
                                          }
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
                              const SizedBox(height: 6),

                              // Badge de Saldo Disponible en Barra
                              Row(
                                children: [
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                    decoration: BoxDecoration(
                                      color: stockDisp > 0
                                          ? const Color(0xFF10B981).withOpacity(0.15)
                                          : Colors.redAccent.withOpacity(0.15),
                                      borderRadius: BorderRadius.circular(6),
                                      border: Border.all(
                                        color: stockDisp > 0 ? const Color(0xFF10B981) : Colors.redAccent,
                                        width: 0.8,
                                      ),
                                    ),
                                    child: Row(
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        Icon(
                                          Icons.inventory_2_outlined,
                                          size: 12,
                                          color: stockDisp > 0 ? const Color(0xFF10B981) : Colors.redAccent,
                                        ),
                                        const SizedBox(width: 4),
                                        Text(
                                          'Saldo físico en barra: ${stockDisp.toStringAsFixed(stockDisp % 1 == 0 ? 0 : 2)}',
                                          style: TextStyle(
                                            color: stockDisp > 0 ? const Color(0xFF10B981) : Colors.redAccent,
                                            fontSize: 11,
                                            fontWeight: FontWeight.bold,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                  if (sobregiroLinea) ...[
                                    const SizedBox(width: 8),
                                    const Expanded(
                                      child: Text(
                                        '⚠️ Excede saldo',
                                        style: TextStyle(color: Colors.redAccent, fontSize: 11, fontWeight: FontWeight.bold),
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                              const SizedBox(height: 10),

                              TextFormField(
                                controller: linea.cantidadCtrl,
                                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                                style: TextStyle(
                                  color: sobregiroLinea ? Colors.redAccent : Colors.white,
                                  fontSize: 16,
                                  fontWeight: FontWeight.bold,
                                ),
                                onChanged: (_) => setState(() {}),
                                decoration: InputDecoration(
                                  filled: true,
                                  fillColor: const Color(0xFF121620),
                                  labelText: 'Cantidad a Despachar',
                                  labelStyle: TextStyle(
                                    color: sobregiroLinea ? Colors.redAccent : Colors.white60,
                                    fontSize: 12,
                                  ),
                                  border: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(8),
                                    borderSide: BorderSide(
                                      color: sobregiroLinea ? Colors.redAccent : Colors.transparent,
                                    ),
                                  ),
                                  prefixIcon: Icon(
                                    Icons.format_list_numbered,
                                    color: sobregiroLinea ? Colors.redAccent : const Color(0xFF1ABC9C),
                                    size: 18,
                                  ),
                                ),
                                validator: (val) {
                                  if (val == null || val.trim().isEmpty) return 'Ingrese cantidad';
                                  final n = double.tryParse(val.trim());
                                  if (n == null || n <= 0) return 'Mayor a 0';
                                  if (n > stockDisp) return 'Excede stock disponible ($stockDisp)';
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

                    // Alerta Roja en caso de sobregiro
                    if (bloqueadoPorStock) ...[
                      Container(
                        padding: const EdgeInsets.all(12),
                        margin: const EdgeInsets.only(bottom: 16),
                        decoration: BoxDecoration(
                          color: Colors.redAccent.withOpacity(0.15),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: Colors.redAccent),
                        ),
                        child: const Row(
                          children: [
                            Icon(Icons.warning_amber_rounded, color: Colors.redAccent, size: 24),
                            SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                'DESPACHO BLOQUEADO: Una o más líneas superan el saldo físico disponible en la barra de origen.',
                                style: TextStyle(color: Colors.redAccent, fontSize: 12, fontWeight: FontWeight.bold),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],

                    ElevatedButton(
                      onPressed: (_isSubmitting || bloqueadoPorStock) ? null : _despacharTraspaso,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: bloqueadoPorStock ? Colors.grey.shade800 : const Color(0xFF16A085),
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        elevation: bloqueadoPorStock ? 0 : 4,
                      ),
                      child: _isSubmitting
                          ? const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                          : Text(
                              bloqueadoPorStock ? 'BLOQUEADO: STOCK INSUFICIENTE' : 'DESPACHAR TRASPASO',
                              style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold, letterSpacing: 0.5),
                            ),
                    ),
                  ],
                ),
              ),
            ),
    );
  }
}
