import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import '../../../core/network/api_client.dart';
import '../../providers/auth_provider.dart';

class CompraItemDraft {
  int? productoId;
  String productoNombre;
  double cantidad;
  double costoUnitario;

  CompraItemDraft({
    this.productoId,
    this.productoNombre = '',
    this.cantidad = 1.0,
    this.costoUnitario = 0.0,
  });
}

class RegistrarCompraScreen extends ConsumerStatefulWidget {
  const RegistrarCompraScreen({super.key});

  @override
  ConsumerState<RegistrarCompraScreen> createState() => _RegistrarCompraScreenState();
}

class _RegistrarCompraScreenState extends ConsumerState<RegistrarCompraScreen> {
  final _proveedorController = TextEditingController();
  final _notaFacturaController = TextEditingController();
  final _obsController = TextEditingController();
  final ImagePicker _picker = ImagePicker();

  int _sucursalSeleccionadaId = 1;
  XFile? _fotoComprobante;
  bool _isLoading = false;
  bool _isSubmitting = false;

  List<Map<String, dynamic>> _sucursales = [
    {'id': 1, 'nombre': 'Casa22'},
    {'id': 2, 'nombre': 'Casa Coron'},
    {'id': 3, 'nombre': 'Madan'},
  ];

  List<Map<String, dynamic>> _catalogoProductos = [];
  final List<CompraItemDraft> _items = [];

  @override
  void initState() {
    super.initState();
    _cargarDatosIniciales();
  }

  @override
  void dispose() {
    _proveedorController.dispose();
    _notaFacturaController.dispose();
    _obsController.dispose();
    super.dispose();
  }

  Future<void> _cargarDatosIniciales() async {
    setState(() => _isLoading = true);
    final client = ApiClient(ref.read(apiConfigProvider));

    try {
      // 1. Cargar productos
      final respProd = await client.get('/productos');
      if (respProd.data['success'] == true && respProd.data['data'] != null) {
        final List prods = respProd.data['data'];
        setState(() {
          _catalogoProductos = prods.map((p) => {
            'id': p['id'],
            'nombre': p['nombre'],
            'tipo': p['tipo_producto'] ?? p['tipo'] ?? 'insumo',
          }).toList();
        });
      }

      // 2. Cargar sucursales
      final respSuc = await client.get('/sucursales');
      if (respSuc.data['success'] == true && respSuc.data['data'] != null) {
        final List sucs = respSuc.data['data'];
        setState(() {
          _sucursales = sucs.map((s) => {'id': s['id'], 'nombre': s['nombre']}).toList();
        });
      }
    } catch (_) {
      // Fallback a productos base
      if (_catalogoProductos.isEmpty) {
        _catalogoProductos = [
          {'id': 1, 'nombre': 'Corona en Lata 355ml (Materia Prima)'},
          {'id': 2, 'nombre': 'Corona en Botella 355ml (Terminado)'},
          {'id': 3, 'nombre': 'Ron Flor de Caña 750ml'},
          {'id': 4, 'nombre': 'Vodka Absolut 750ml'},
          {'id': 5, 'nombre': 'Fernet Branca 750ml'},
        ];
      }
    } finally {
      if (_items.isEmpty && _catalogoProductos.isNotEmpty) {
        _items.add(CompraItemDraft(
          productoId: _catalogoProductos.first['id'],
          productoNombre: _catalogoProductos.first['nombre'],
          cantidad: 24.0,
          costoUnitario: 0.0,
        ));
      }
      setState(() => _isLoading = false);
    }
  }

  Future<void> _tomarFoto(ImageSource source) async {
    try {
      final XFile? foto = await _picker.pickImage(
        source: source,
        imageQuality: 75,
        maxWidth: 1280,
      );
      if (foto != null) {
        setState(() {
          _fotoComprobante = foto;
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error al capturar foto: $e'), backgroundColor: Colors.redAccent),
        );
      }
    }
  }

  void _agregarProducto() {
    if (_catalogoProductos.isEmpty) return;
    setState(() {
      _items.add(CompraItemDraft(
        productoId: _catalogoProductos.first['id'],
        productoNombre: _catalogoProductos.first['nombre'],
        cantidad: 12.0,
        costoUnitario: 0.0,
      ));
    });
  }

  void _eliminarProducto(int index) {
    if (_items.length <= 1) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('La compra debe contener al menos 1 producto.'), backgroundColor: Colors.orange),
      );
      return;
    }
    setState(() {
      _items.removeAt(index);
    });
  }

  double get _totalUnidades => _items.fold(0.0, (acc, item) => acc + item.cantidad);
  double get _totalCosto => _items.fold(0.0, (acc, item) => acc + (item.cantidad * item.costoUnitario));

  Future<void> _guardarCompra() async {
    final proveedor = _proveedorController.text.trim();
    if (proveedor.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Ingrese el nombre del proveedor o distribuidora.'), backgroundColor: Colors.redAccent),
      );
      return;
    }

    if (_fotoComprobante == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('¡EVIDENCIA OBLIGATORIA! Debe capturar la foto de la factura o remisión física.'),
          backgroundColor: Colors.redAccent,
        ),
      );
      return;
    }

    for (final it in _items) {
      if (it.productoId == null || it.cantidad <= 0) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Verifique que todos los productos tengan cantidad mayor a cero.'), backgroundColor: Colors.redAccent),
        );
        return;
      }
    }

    setState(() => _isSubmitting = true);

    try {
      final bytes = await File(_fotoComprobante!.path).readAsBytes();
      final base64Image = 'data:image/jpeg;base64,${base64Encode(bytes)}';

      final client = ApiClient(ref.read(apiConfigProvider));
      final auth = ref.read(authProvider);

      final payload = {
        'sucursal_id': _sucursalSeleccionadaId,
        'usuario_id': auth.usuarioId ?? 1,
        'proveedor': proveedor,
        'numero_nota_factura': _notaFacturaController.text.trim(),
        'foto_comprobante': base64Image,
        'total_costo_estimado': _totalCosto,
        'observaciones': _obsController.text.trim(),
        'items': _items.map((it) => {
          'producto_id': it.productoId,
          'cantidad': it.cantidad,
          'costo_unitario': it.costoUnitario,
        }).toList(),
      };

      final resp = await client.post('/inventario/compras', data: payload);

      if (resp.data['success'] == true) {
        if (mounted) {
          showDialog(
            context: context,
            barrierDismissible: false,
            builder: (ctx) => AlertDialog(
              backgroundColor: const Color(0xFF1E293B),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              title: const Row(
                children: [
                  Icon(Icons.check_circle, color: Color(0xFF10B981), size: 28),
                  SizedBox(width: 10),
                  Text('Compra Registrada', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                ],
              ),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Proveedor: $proveedor', style: const TextStyle(color: Colors.white70, fontSize: 14)),
                  const SizedBox(height: 6),
                  Text('Ítems: ${_items.length} productos ingresados', style: const TextStyle(color: Colors.white70, fontSize: 14)),
                  const SizedBox(height: 6),
                  Text('Total unidades: ${_totalUnidades.toStringAsFixed(0)} u.', style: const TextStyle(color: Color(0xFF38BDF8), fontWeight: FontWeight.bold, fontSize: 15)),
                  if (_totalCosto > 0) ...[
                    const SizedBox(height: 6),
                    Text('Total costo: ${_totalCosto.toStringAsFixed(2)} Bs', style: const TextStyle(color: Color(0xFF10B981), fontWeight: FontWeight.bold, fontSize: 15)),
                  ],
                  const SizedBox(height: 12),
                  const Text('El inventario físico ha sido actualizado en la sucursal de forma atómica.', style: TextStyle(color: Colors.white54, fontSize: 12)),
                ],
              ),
              actions: [
                TextButton(
                  onPressed: () {
                    Navigator.of(ctx).pop();
                    Navigator.of(context).pop();
                  },
                  child: const Text('ACEPTAR', style: TextStyle(color: Color(0xFF38BDF8), fontWeight: FontWeight.bold)),
                ),
              ],
            ),
          );
        }
      } else {
        throw Exception(resp.data['error'] ?? 'Error desconocido');
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error al registrar compra: $e'), backgroundColor: Colors.redAccent),
        );
      }
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0F172A),
      appBar: AppBar(
        title: const Text('Nueva Compra de Mercadería', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: const Color(0xFF1E293B),
        elevation: 0,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Color(0xFF38BDF8)))
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // 1. Selector de Sucursal y Proveedor
                  _buildCard(
                    title: 'DATOS DE LA COMPRA',
                    icon: Icons.storefront,
                    child: Column(
                      children: [
                        DropdownButtonFormField<int>(
                          value: _sucursalSeleccionadaId,
                          decoration: _inputDecoration('Sucursal de Destino'),
                          dropdownColor: const Color(0xFF1E293B),
                          style: const TextStyle(color: Colors.white, fontSize: 14),
                          items: _sucursales.map((s) {
                            return DropdownMenuItem<int>(
                              value: s['id'] as int,
                              child: Text(s['nombre'] as String),
                            );
                          }).toList(),
                          onChanged: (v) => setState(() => _sucursalSeleccionadaId = v ?? 1),
                        ),
                        const SizedBox(height: 12),
                        TextField(
                          controller: _proveedorController,
                          style: const TextStyle(color: Colors.white),
                          decoration: _inputDecoration('Proveedor / Distribuidora (ej. CBN, Embol)'),
                        ),
                        const SizedBox(height: 12),
                        TextField(
                          controller: _notaFacturaController,
                          style: const TextStyle(color: Colors.white),
                          decoration: _inputDecoration('N° de Factura o Nota de Remisión (Opcional)'),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),

                  // 2. Foto Obligatoria de Respaldo
                  _buildCard(
                    title: 'EVIDENCIA FOTOGRÁFICA OBLIGATORIA',
                    icon: Icons.camera_alt,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (_fotoComprobante == null) ...[
                          const Text(
                            'Capture la nota de remisión o factura física para respaldar el ingreso de mercadería.',
                            style: TextStyle(color: Colors.white70, fontSize: 13),
                          ),
                          const SizedBox(height: 12),
                          Row(
                            children: [
                              Expanded(
                                child: ElevatedButton.icon(
                                  onPressed: () => _tomarFoto(ImageSource.camera),
                                  icon: const Icon(Icons.camera_alt, color: Colors.white),
                                  label: const Text('ABRIR CÁMARA', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: const Color(0xFF2563EB),
                                    padding: const EdgeInsets.symmetric(vertical: 14),
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                  ),
                                ),
                              ),
                              const SizedBox(width: 10),
                              IconButton(
                                onPressed: () => _tomarFoto(ImageSource.gallery),
                                icon: const Icon(Icons.photo_library, color: Color(0xFF38BDF8)),
                                tooltip: 'Subir desde galería',
                              ),
                            ],
                          ),
                        ] else ...[
                          ClipRRect(
                            borderRadius: BorderRadius.circular(10),
                            child: Image.file(
                              File(_fotoComprobante!.path),
                              height: 180,
                              width: double.infinity,
                              fit: BoxFit.cover,
                            ),
                          ),
                          const SizedBox(height: 10),
                          Row(
                            children: [
                              const Icon(Icons.verified, color: Color(0xFF10B981), size: 18),
                              const SizedBox(width: 6),
                              const Text('Foto adjunta correctamente', style: TextStyle(color: Color(0xFF10B981), fontSize: 13, fontWeight: FontWeight.bold)),
                              const Spacer(),
                              TextButton.icon(
                                onPressed: () => setState(() => _fotoComprobante = null),
                                icon: const Icon(Icons.refresh, color: Colors.redAccent, size: 16),
                                label: const Text('CAMBIAR FOTO', style: TextStyle(color: Colors.redAccent, fontSize: 12)),
                              ),
                            ],
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),

                  // 3. Detalle Multi-Producto
                  _buildCard(
                    title: 'PRODUCTOS INGRESADOS (${_items.length})',
                    icon: Icons.list_alt,
                    child: Column(
                      children: [
                        ..._items.asMap().entries.map((entry) {
                          final idx = entry.key;
                          final item = entry.value;
                          return Container(
                            margin: const EdgeInsets.only(bottom: 12),
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: const Color(0xFF0F172A),
                              borderRadius: BorderRadius.circular(10),
                              border: Border.all(color: Colors.white12),
                            ),
                            child: Column(
                              children: [
                                Row(
                                  children: [
                                    CircleAvatar(
                                      radius: 12,
                                      backgroundColor: const Color(0xFF38BDF8).withOpacity(0.2),
                                      child: Text('${idx + 1}', style: const TextStyle(color: Color(0xFF38BDF8), fontSize: 12, fontWeight: FontWeight.bold)),
                                    ),
                                    const SizedBox(width: 8),
                                    Expanded(
                                      child: DropdownButtonHideUnderline(
                                        child: DropdownButton<int>(
                                          value: item.productoId,
                                          isExpanded: true,
                                          dropdownColor: const Color(0xFF1E293B),
                                          style: const TextStyle(color: Colors.white, fontSize: 14),
                                          items: _catalogoProductos.map((p) {
                                            return DropdownMenuItem<int>(
                                              value: p['id'] as int,
                                              child: Text(p['nombre'] as String, overflow: TextOverflow.ellipsis),
                                            );
                                          }).toList(),
                                          onChanged: (v) {
                                            final sel = _catalogoProductos.firstWhere((p) => p['id'] == v);
                                            setState(() {
                                              item.productoId = v;
                                              item.productoNombre = sel['nombre'];
                                            });
                                          },
                                        ),
                                      ),
                                    ),
                                    IconButton(
                                      icon: const Icon(Icons.delete_outline, color: Colors.redAccent, size: 20),
                                      onPressed: () => _eliminarProducto(idx),
                                    ),
                                  ],
                                ),
                                const Divider(color: Colors.white12),
                                Row(
                                  children: [
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          const Text('Cantidad', style: TextStyle(color: Colors.white54, fontSize: 11)),
                                          const SizedBox(height: 4),
                                          Row(
                                            children: [
                                              _btnMini('-', () {
                                                if (item.cantidad > 1) {
                                                  setState(() => item.cantidad -= 1);
                                                }
                                              }),
                                              Expanded(
                                                child: Center(
                                                  child: Text(
                                                    item.cantidad.toStringAsFixed(0),
                                                    style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
                                                  ),
                                                ),
                                              ),
                                              _btnMini('+', () {
                                                setState(() => item.cantidad += 1);
                                              }),
                                            ],
                                          ),
                                        ],
                                      ),
                                    ),
                                    const SizedBox(width: 16),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          const Text('Costo Unit. (Bs)', style: TextStyle(color: Colors.white54, fontSize: 11)),
                                          const SizedBox(height: 4),
                                          TextFormField(
                                            initialValue: item.costoUnitario > 0 ? item.costoUnitario.toStringAsFixed(2) : '',
                                            keyboardType: const TextInputType.numberWithOptions(decimal: true),
                                            style: const TextStyle(color: Colors.white, fontSize: 14),
                                            decoration: InputDecoration(
                                              hintText: '0.00',
                                              hintStyle: const TextStyle(color: Colors.white30),
                                              contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                                              filled: true,
                                              fillColor: const Color(0xFF1E293B),
                                              border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: BorderSide.none),
                                            ),
                                            onChanged: (v) {
                                              item.costoUnitario = double.tryParse(v.trim()) ?? 0.0;
                                              setState(() {});
                                            },
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          );
                        }),
                        const SizedBox(height: 8),
                        OutlinedButton.icon(
                          onPressed: _agregarProducto,
                          icon: const Icon(Icons.add, color: Color(0xFF38BDF8)),
                          label: const Text('+ AÑADIR OTRO PRODUCTO', style: TextStyle(color: Color(0xFF38BDF8), fontWeight: FontWeight.bold)),
                          style: OutlinedButton.styleFrom(
                            side: const BorderSide(color: Color(0xFF38BDF8)),
                            padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 20),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),

                  // 4. Resumen Total
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [Color(0xFF1E293B), Color(0xFF334155)],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: Colors.white24),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceAround,
                      children: [
                        Column(
                          children: [
                            const Text('TOTAL ÍTEMS', style: TextStyle(color: Colors.white54, fontSize: 11)),
                            const SizedBox(height: 4),
                            Text('${_items.length}', style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold)),
                          ],
                        ),
                        Container(width: 1, height: 35, color: Colors.white12),
                        Column(
                          children: [
                            const Text('UNIDADES', style: TextStyle(color: Colors.white54, fontSize: 11)),
                            const SizedBox(height: 4),
                            Text(_totalUnidades.toStringAsFixed(0), style: const TextStyle(color: Color(0xFF38BDF8), fontSize: 18, fontWeight: FontWeight.bold)),
                          ],
                        ),
                        Container(width: 1, height: 35, color: Colors.white12),
                        Column(
                          children: [
                            const Text('TOTAL BS', style: TextStyle(color: Colors.white54, fontSize: 11)),
                            const SizedBox(height: 4),
                            Text('${_totalCosto.toStringAsFixed(2)} Bs', style: const TextStyle(color: Color(0xFF10B981), fontSize: 18, fontWeight: FontWeight.bold)),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 24),

                  // 5. Botón de Envío
                  SizedBox(
                    width: double.infinity,
                    height: 52,
                    child: ElevatedButton.icon(
                      onPressed: _isSubmitting ? null : _guardarCompra,
                      icon: _isSubmitting
                          ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                          : const Icon(Icons.check_circle_outline, color: Colors.white),
                      label: Text(
                        _isSubmitting ? 'PROCESANDO INGRESO...' : 'CONFIRMAR Y REGISTRAR COMPRA',
                        style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Colors.white),
                      ),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF10B981),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                    ),
                  ),
                  const SizedBox(height: 30),
                ],
              ),
            ),
    );
  }

  Widget _btnMini(String txt, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(6),
      child: Container(
        width: 32,
        height: 32,
        decoration: BoxDecoration(
          color: const Color(0xFF1E293B),
          borderRadius: BorderRadius.circular(6),
          border: Border.all(color: Colors.white24),
        ),
        alignment: Alignment.center,
        child: Text(txt, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold)),
      ),
    );
  }

  Widget _buildCard({required String title, required IconData icon, required Widget child}) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFF1E293B),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Colors.white10),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: const Color(0xFF38BDF8), size: 18),
              const SizedBox(width: 8),
              Text(title, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13, letterSpacing: 0.5)),
            ],
          ),
          const SizedBox(height: 14),
          child,
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
