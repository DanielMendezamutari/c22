import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import '../../../core/network/api_client.dart';
import '../../providers/auth_provider.dart';

class RecepcionItemDraft {
  int? productoId;
  String productoNombre;
  double cantidad;
  double costoUnitario;

  RecepcionItemDraft({
    this.productoId,
    this.productoNombre = '',
    this.cantidad = 24.0,
    this.costoUnitario = 0.0,
  });
}

class IngresoMercaderiaScreen extends ConsumerStatefulWidget {
  const IngresoMercaderiaScreen({super.key});

  @override
  ConsumerState<IngresoMercaderiaScreen> createState() => _IngresoMercaderiaScreenState();
}

class _IngresoMercaderiaScreenState extends ConsumerState<IngresoMercaderiaScreen> {
  final _proveedorController = TextEditingController();
  final _notaGuiaController = TextEditingController();
  final _obsController = TextEditingController();
  final ImagePicker _picker = ImagePicker();

  XFile? _fotoComprobante;
  bool _isLoading = false;
  bool _isSubmitting = false;

  List<Map<String, dynamic>> _catalogoProductos = [];
  List<Map<String, dynamic>> _proveedores = [];
  int? _selectedProveedorId;
  bool _esProveedorNoListado = false;
  final List<RecepcionItemDraft> _items = [];

  @override
  void initState() {
    super.initState();
    _cargarDatosIniciales();
  }

  @override
  void dispose() {
    _proveedorController.dispose();
    _notaGuiaController.dispose();
    _obsController.dispose();
    super.dispose();
  }

  Future<void> _cargarDatosIniciales() async {
    setState(() => _isLoading = true);
    await Future.wait([
      _cargarCatalogo(),
      _cargarProveedores(),
    ]);
    if (mounted) setState(() => _isLoading = false);
  }

  Future<void> _cargarProveedores() async {
    try {
      final client = ref.read(apiClientProvider);
      final resp = await client.get('/proveedores?activo=1');
      if (resp.data['success'] == true && resp.data['data'] != null) {
        final List list = resp.data['data'];
        setState(() {
          _proveedores = List<Map<String, dynamic>>.from(list);
          if (_proveedores.isNotEmpty && _selectedProveedorId == null && !_esProveedorNoListado) {
            // Pre-seleccionar Licorería Punto Frío si existe, o el primero
            final puntoFrio = _proveedores.firstWhere(
              (p) => (p['nombre'] ?? '').toString().toLowerCase().contains('punto frío') ||
                     (p['nombre'] ?? '').toString().toLowerCase().contains('punto frio'),
              orElse: () => _proveedores.first,
            );
            _selectedProveedorId = puntoFrio['id'];
            _proveedorController.text = puntoFrio['nombre'];
          }
        });
      }
    } catch (_) {
      // Fallback si la red falla
      if (_proveedores.isEmpty) {
        _proveedores = [
          {'id': 1, 'nombre': 'Cervecería Boliviana Nacional (CBN)'},
          {'id': 2, 'nombre': 'Embol S.A. (Coca-Cola / Bebidas)'},
          {'id': 3, 'nombre': 'Licorería Punto Frío Central'},
          {'id': 4, 'nombre': 'Distribuidora San Juan'},
        ];
        _selectedProveedorId = 3;
        _proveedorController.text = 'Licorería Punto Frío Central';
      }
    }
  }

  Future<void> _cargarCatalogo() async {
    final client = ref.read(apiClientProvider);

    try {
      final resp = await client.get('/productos');
      if (resp.data['success'] == true && resp.data['data'] != null) {
        final List prods = resp.data['data'];
        setState(() {
          _catalogoProductos = prods.map((p) => {
            'id': p['id'],
            'nombre': p['nombre'].toString(),
            'tipo': (p['tipo_producto'] ?? p['tipo'] ?? 'insumo').toString(),
          }).toList();
        });
      }
    } catch (_) {
      if (_catalogoProductos.isEmpty) {
        _catalogoProductos = [
          {'id': 1, 'nombre': 'Corona en Lata 355ml (Materia Prima)', 'tipo': 'insumo'},
          {'id': 2, 'nombre': 'Corona en Botella 355ml (Terminado)', 'tipo': 'terminado'},
          {'id': 6, 'nombre': 'Cerveza Pacena Lata 355ml', 'tipo': 'insumo'},
          {'id': 3, 'nombre': 'Ron Flor de Caña 750ml', 'tipo': 'terminado'},
          {'id': 4, 'nombre': 'Vodka Absolut 750ml', 'tipo': 'terminado'},
          {'id': 5, 'nombre': 'Whisky Red Label 750ml', 'tipo': 'terminado'},
        ];
      }
    } finally {
      if (_items.isEmpty && _catalogoProductos.isNotEmpty) {
        _items.add(RecepcionItemDraft(
          productoId: _catalogoProductos.first['id'],
          productoNombre: _catalogoProductos.first['nombre'],
          cantidad: 24.0,
        ));
      }
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _capturarFoto(ImageSource source) async {
    try {
      final XFile? foto = await _picker.pickImage(
        source: source,
        imageQuality: 75,
        maxWidth: 1280,
      );
      if (foto != null) {
        setState(() => _fotoComprobante = foto);
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
      _items.add(RecepcionItemDraft(
        productoId: _catalogoProductos.first['id'],
        productoNombre: _catalogoProductos.first['nombre'],
        cantidad: 12.0,
      ));
    });
  }

  void _eliminarProducto(int index) {
    if (_items.length <= 1) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Debe registrar al menos 1 producto recibido.'), backgroundColor: Colors.orange),
      );
      return;
    }
    setState(() => _items.removeAt(index));
  }

  Future<void> _guardarRecepcion() async {
    if (_items.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Agregue al menos un producto a la recepción.'), backgroundColor: Colors.orange),
      );
      return;
    }

    if (_fotoComprobante == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Evidencia fotográfica obligatoria. Capture la nota de remisión.'),
          backgroundColor: Color(0xFFC0392B),
        ),
      );
      return;
    }

    final auth = ref.read(authProvider);
    final sucursalId = auth.sucursalId ?? 1;

    setState(() => _isSubmitting = true);

    try {
      final bytes = await File(_fotoComprobante!.path).readAsBytes();
      final base64Image = 'data:image/jpeg;base64,${base64Encode(bytes)}';

      final itemsPayload = _items.map((i) => {
        'producto_id': i.productoId,
        'cantidad': i.cantidad,
        'costo_unitario': i.costoUnitario,
      }).toList();

      final String proveedorFinal = _proveedorController.text.trim().isNotEmpty
          ? _proveedorController.text.trim()
          : 'Licorería Punto Frío Central';

      final payload = {
        'sucursal_id': sucursalId,
        'proveedor_id': _esProveedorNoListado ? null : _selectedProveedorId,
        'proveedor': proveedorFinal,
        'numero_nota_factura': _notaGuiaController.text.trim().isNotEmpty ? _notaGuiaController.text.trim() : 'S/N',
        'numero_factura_nota': _notaGuiaController.text.trim().isNotEmpty ? _notaGuiaController.text.trim() : 'S/N',
        'observaciones': _obsController.text.trim(),
        'foto_comprobante': base64Image,
        'foto_factura': base64Image,
        'items': itemsPayload,
      };

      final client = ApiClient(ref.read(apiConfigProvider));
      final res = await client.post('/inventario/compras', data: payload);

      if (res.data['success'] == true) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Recepción de ${_items.length} productos registrada y stock acreditado.'),
              backgroundColor: const Color(0xFF27AE60),
            ),
          );
          Navigator.of(context).pop();
        }
      } else {
        throw Exception(res.data['error'] ?? 'Error al registrar mercadería');
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e'), backgroundColor: Colors.redAccent),
        );
      }
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.read(authProvider);
    final sucursalNombre = auth.sucursalNombre ?? 'Casa22';

    final totalUnidades = _items.fold<double>(0.0, (acc, i) => acc + i.cantidad);

    return Scaffold(
      backgroundColor: const Color(0xFF121620),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1B2332),
        title: const Text(
          'Recepción de Mercadería',
          style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 18),
        ),
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Color(0xFF3498DB)))
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // 1. Origen y Documento
                  _buildCard(
                    title: 'DATOS DE LA NOTA / REMISIÓN',
                    icon: Icons.local_shipping,
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        decoration: BoxDecoration(
                          color: const Color(0xFF242E42),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: Colors.white12),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.store, color: Color(0xFF3498DB), size: 18),
                            const SizedBox(width: 8),
                            const Text('Destino en Barra: ', style: TextStyle(color: Colors.white70, fontSize: 13)),
                            Text(sucursalNombre, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14)),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      // Selector Dinámico de Proveedores
                      if (!_esProveedorNoListado) ...[
                        DropdownButtonFormField<int>(
                          value: _selectedProveedorId,
                          dropdownColor: const Color(0xFF1E293B),
                          style: const TextStyle(color: Colors.white, fontSize: 14),
                          decoration: InputDecoration(
                            labelText: 'Proveedor Comercial Autorizado *',
                            labelStyle: const TextStyle(color: Color(0xFF2DD4BF)),
                            prefixIcon: const Icon(Icons.business, color: Color(0xFF2DD4BF)),
                            filled: true,
                            fillColor: const Color(0xFF242E42),
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: BorderSide.none),
                          ),
                          items: [
                            ..._proveedores.map((p) {
                              return DropdownMenuItem<int>(
                                value: p['id'] as int,
                                child: Text(
                                  p['nombre'] ?? '',
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(color: Colors.white, fontSize: 13),
                                ),
                              );
                            }),
                            const DropdownMenuItem<int>(
                              value: -1,
                              child: Row(
                                children: [
                                  Icon(Icons.add_circle_outline, color: Color(0xFFF59E0B), size: 16),
                                  SizedBox(width: 6),
                                  Text(
                                    '+ Proveedor no listado...',
                                    style: TextStyle(color: Color(0xFFF59E0B), fontWeight: FontWeight.bold, fontSize: 13),
                                  ),
                                ],
                              ),
                            ),
                          ],
                          onChanged: (val) {
                            if (val == -1) {
                              setState(() {
                                _esProveedorNoListado = true;
                                _selectedProveedorId = null;
                                _proveedorController.clear();
                              });
                            } else {
                              setState(() {
                                _selectedProveedorId = val;
                                final prov = _proveedores.firstWhere((p) => p['id'] == val, orElse: () => {});
                                _proveedorController.text = prov['nombre'] ?? '';
                              });
                            }
                          },
                        ),
                      ] else ...[
                        Row(
                          children: [
                            Expanded(
                              child: TextField(
                                controller: _proveedorController,
                                style: const TextStyle(color: Colors.white, fontSize: 14),
                                decoration: InputDecoration(
                                  labelText: 'Nombre Comercial del Proveedor *',
                                  labelStyle: const TextStyle(color: Color(0xFFF59E0B)),
                                  hintText: 'Ej. Distribuidora Santa Cruz, Don Pepe...',
                                  hintStyle: const TextStyle(color: Colors.white30),
                                  prefixIcon: const Icon(Icons.edit_note, color: Color(0xFFF59E0B)),
                                  filled: true,
                                  fillColor: const Color(0xFF242E42),
                                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: BorderSide.none),
                                ),
                              ),
                            ),
                            const SizedBox(width: 8),
                            IconButton(
                              icon: const Icon(Icons.list_alt, color: Color(0xFF2DD4BF)),
                              tooltip: 'Volver a lista autorizada',
                              onPressed: () {
                                setState(() {
                                  _esProveedorNoListado = false;
                                  if (_proveedores.isNotEmpty) {
                                    _selectedProveedorId = _proveedores.first['id'];
                                    _proveedorController.text = _proveedores.first['nombre'];
                                  }
                                });
                              },
                            ),
                          ],
                        ),
                      ],
                      const SizedBox(height: 10),
                      TextField(
                        controller: _notaGuiaController,
                        style: const TextStyle(color: Colors.white, fontSize: 14),
                        decoration: InputDecoration(
                          labelText: 'N° de Guía de Entrega o Nota de Remisión (Opcional)',
                          labelStyle: const TextStyle(color: Colors.white60),
                          prefixIcon: const Icon(Icons.receipt_long, color: Colors.white60),
                          filled: true,
                          fillColor: const Color(0xFF242E42),
                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: BorderSide.none),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),

                  // 2. Foto Obligatoria
                  _buildCard(
                    title: 'EVIDENCIA FOTOGRÁFICA OBLIGATORIA',
                    icon: Icons.camera_alt,
                    children: [
                      const Text(
                        'Fotografíe la nota física o el lote de productos para respaldar la recepción en su turno.',
                        style: TextStyle(color: Colors.white60, fontSize: 12),
                      ),
                      const SizedBox(height: 12),
                      if (_fotoComprobante == null)
                        Row(
                          children: [
                            Expanded(
                              child: ElevatedButton.icon(
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: const Color(0xFF2980B9),
                                  foregroundColor: Colors.white,
                                  padding: const EdgeInsets.symmetric(vertical: 12),
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                ),
                                icon: const Icon(Icons.camera_alt),
                                label: const Text('ABRIR CÁMARA', style: TextStyle(fontWeight: FontWeight.bold)),
                                onPressed: () => _capturarFoto(ImageSource.camera),
                              ),
                            ),
                            const SizedBox(width: 8),
                            IconButton(
                              tooltip: 'Galería',
                              icon: const Icon(Icons.photo_library, color: Colors.white70),
                              onPressed: () => _capturarFoto(ImageSource.gallery),
                            ),
                          ],
                        )
                      else
                        Stack(
                          alignment: Alignment.topRight,
                          children: [
                            Container(
                              height: 160,
                              width: double.infinity,
                              decoration: BoxDecoration(
                                borderRadius: BorderRadius.circular(8),
                                image: DecorationImage(
                                  image: FileImage(File(_fotoComprobante!.path)),
                                  fit: BoxFit.cover,
                                ),
                              ),
                            ),
                            IconButton(
                              icon: const CircleAvatar(
                                backgroundColor: Colors.redAccent,
                                child: Icon(Icons.close, color: Colors.white, size: 18),
                              ),
                              onPressed: () => setState(() => _fotoComprobante = null),
                            ),
                          ],
                        ),
                    ],
                  ),
                  const SizedBox(height: 16),

                  // 3. Lista Multi-Producto
                  _buildCard(
                    title: 'PRODUCTOS INGRESADOS (${_items.length})',
                    icon: Icons.format_list_bulleted,
                    children: [
                      const Text(
                        'Agregue cada producto que llegó en la nota de entrega.',
                        style: TextStyle(color: Colors.white60, fontSize: 12),
                      ),
                      const SizedBox(height: 12),
                      ..._items.asMap().entries.map((entry) {
                        final idx = entry.key;
                        final item = entry.value;
                        return _buildItemRow(idx, item);
                      }),
                      const SizedBox(height: 12),
                      Center(
                        child: OutlinedButton.icon(
                          style: OutlinedButton.styleFrom(
                            foregroundColor: const Color(0xFF3498DB),
                            side: const BorderSide(color: Color(0xFF3498DB), width: 1.5),
                            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                          ),
                          icon: const Icon(Icons.add_circle_outline, size: 18),
                          label: const Text('+ AÑADIR OTRO PRODUCTO', style: TextStyle(fontWeight: FontWeight.bold)),
                          onPressed: _agregarProducto,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),

                  // Resumen
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: const Color(0xFF1B2332),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: Colors.white12),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceAround,
                      children: [
                        _buildStat('TOTAL ÍTEMS', '${_items.length}'),
                        _buildStat('TOTAL UNIDADES', '${totalUnidades.toStringAsFixed(0)} un.'),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),

                  // Botón de Confirmación
                  SizedBox(
                    width: double.infinity,
                    height: 52,
                    child: ElevatedButton.icon(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF27AE60),
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        elevation: 4,
                      ),
                      icon: _isSubmitting
                          ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                            )
                          : const Icon(Icons.check_circle_outline, size: 22),
                      label: Text(
                        _isSubmitting ? 'REGISTRANDO INGRESO...' : 'CONFIRMAR RECEPCIÓN DE MERCADERÍA',
                        style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, letterSpacing: 0.5),
                      ),
                      onPressed: _isSubmitting ? null : _guardarRecepcion,
                    ),
                  ),
                  const SizedBox(height: 30),
                ],
              ),
            ),
    );
  }

  Widget _buildItemRow(int index, RecepcionItemDraft item) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFF242E42),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: Colors.white10),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 12,
                backgroundColor: const Color(0xFF3498DB),
                child: Text('${index + 1}', style: const TextStyle(fontSize: 11, color: Colors.white, fontWeight: FontWeight.bold)),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: DropdownButtonHideUnderline(
                  child: DropdownButton<int>(
                    value: item.productoId,
                    dropdownColor: const Color(0xFF1B2332),
                    isExpanded: true,
                    style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold),
                    items: _catalogoProductos.map((p) {
                      return DropdownMenuItem<int>(
                        value: p['id'] as int,
                        child: Text(
                          p['nombre'].toString(),
                          overflow: TextOverflow.ellipsis,
                        ),
                      );
                    }).toList(),
                    onChanged: (val) {
                      if (val != null) {
                        final found = _catalogoProductos.firstWhere((p) => p['id'] == val);
                        setState(() {
                          item.productoId = val;
                          item.productoNombre = found['nombre'];
                        });
                      }
                    },
                  ),
                ),
              ),
              IconButton(
                icon: const Icon(Icons.delete_outline, color: Colors.redAccent, size: 20),
                onPressed: () => _eliminarProducto(index),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              const Text('Cantidad: ', style: TextStyle(color: Colors.white70, fontSize: 13, fontWeight: FontWeight.bold)),
              const Spacer(),
              IconButton(
                icon: const Icon(Icons.remove_circle_outline, color: Colors.white70),
                onPressed: () {
                  if (item.cantidad > 1) {
                    setState(() => item.cantidad -= 1);
                  }
                },
              ),
              InkWell(
                onTap: () => _editarCantidadItem(item),
                borderRadius: BorderRadius.circular(8),
                child: Container(
                  constraints: const BoxConstraints(minWidth: 64),
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: const Color(0xFF1B2332),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: const Color(0xFF3498DB), width: 1.2),
                  ),
                  alignment: Alignment.center,
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        item.cantidad.toStringAsFixed(0),
                        style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
                      ),
                      const SizedBox(width: 4),
                      const Icon(Icons.edit, size: 12, color: Color(0xFF5DADE2)),
                    ],
                  ),
                ),
              ),
              IconButton(
                icon: const Icon(Icons.add_circle_outline, color: Color(0xFF2ECC71)),
                onPressed: () {
                  setState(() => item.cantidad += 1);
                },
              ),
            ],
          ),
          const SizedBox(height: 6),
          Row(
            children: [
              _buildShortcutButton('+6', () => setState(() => item.cantidad += 6)),
              const SizedBox(width: 6),
              _buildShortcutButton('+12 (Caja)', () => setState(() => item.cantidad += 12)),
              const SizedBox(width: 6),
              _buildShortcutButton('+24 (2 Cajas)', () => setState(() => item.cantidad += 24)),
              const SizedBox(width: 6),
              _buildShortcutButton('+60', () => setState(() => item.cantidad += 60)),
            ],
          ),
        ],
      ),
    );
  }

  void _editarCantidadItem(RecepcionItemDraft item) {
    final ctrl = TextEditingController(text: item.cantidad.toStringAsFixed(0));
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: const Color(0xFF1B2332),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        title: Row(
          children: const [
            Icon(Icons.edit, color: Color(0xFF3498DB), size: 20),
            SizedBox(width: 8),
            Text('Digitar Cantidad', style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold)),
          ],
        ),
        content: TextField(
          controller: ctrl,
          keyboardType: TextInputType.number,
          autofocus: true,
          style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.bold),
          decoration: InputDecoration(
            filled: true,
            fillColor: const Color(0xFF141923),
            labelText: 'Cantidad recibida (unidades)',
            labelStyle: const TextStyle(color: Colors.white60),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('Cancelar', style: TextStyle(color: Colors.white60)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF27AE60)),
            onPressed: () {
              final v = double.tryParse(ctrl.text.trim());
              if (v != null && v > 0) {
                setState(() => item.cantidad = v);
              }
              Navigator.of(ctx).pop();
            },
            child: const Text('ACEPTAR', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.white)),
          ),
        ],
      ),
    );
  }

  Widget _buildShortcutButton(String label, VoidCallback onTap) {
    return Expanded(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(6),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 5),
          decoration: BoxDecoration(
            color: const Color(0xFF1B2332),
            borderRadius: BorderRadius.circular(6),
            border: Border.all(color: const Color(0xFF3498DB).withOpacity(0.5)),
          ),
          child: Center(
            child: Text(
              label,
              style: const TextStyle(color: Color(0xFF5DADE2), fontSize: 10, fontWeight: FontWeight.bold),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildCard({required String title, required IconData icon, required List<Widget> children}) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFF1B2332),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.white10),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: const Color(0xFF3498DB), size: 18),
              const SizedBox(width: 8),
              Text(title, style: const TextStyle(color: Colors.white70, fontSize: 12, fontWeight: FontWeight.bold, letterSpacing: 0.5)),
            ],
          ),
          const SizedBox(height: 12),
          ...children,
        ],
      ),
    );
  }

  Widget _buildStat(String label, String value) {
    return Column(
      children: [
        Text(label, style: const TextStyle(color: Colors.white60, fontSize: 11)),
        const SizedBox(height: 4),
        Text(value, style: const TextStyle(color: Color(0xFF3498DB), fontSize: 17, fontWeight: FontWeight.bold)),
      ],
    );
  }
}
