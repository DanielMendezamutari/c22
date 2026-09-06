import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../providers/auth_provider.dart';

class RecibirTraspasoScreen extends ConsumerStatefulWidget {
  const RecibirTraspasoScreen({super.key});

  @override
  ConsumerState<RecibirTraspasoScreen> createState() => _RecibirTraspasoScreenState();
}

class _RecibirTraspasoScreenState extends ConsumerState<RecibirTraspasoScreen> {
  bool _isLoading = false;
  List<Map<String, dynamic>> _traspasosEnTransito = [];

  @override
  void initState() {
    super.initState();
    _cargarPendientes();
  }

  Future<void> _cargarPendientes() async {
    setState(() => _isLoading = true);
    final auth = ref.read(authProvider);
    final miSucursalId = auth.sucursalId ?? 1;

    try {
      final apiClient = ref.read(apiClientProvider);
      final res = await apiClient.get('/traspasos/pendientes', queryParameters: {
        'sucursal_destino_id': miSucursalId,
      });

      if (res.statusCode == 200 && res.data['success'] == true) {
        setState(() {
          _traspasosEnTransito = List<Map<String, dynamic>>.from(res.data['data']);
        });
      }
    } catch (_) {
      // Datos de prueba offline
      setState(() {
        _traspasosEnTransito = [
          {
            'id': 1,
            'sucursal_origen': {'nombre': 'Casa22'},
            'producto': {'nombre': 'Corona en Botella 355ml'},
            'cantidad_despachada': 24.0,
            'usuario_emisor': {'nombre': 'Carlos', 'apellido': 'Mendoza'},
            'fecha_envio': '2026-09-05 16:00:00',
            'observaciones': 'Envío multi-producto',
            'detalles': [
              {
                'id': 1,
                'producto': {'nombre': 'Corona en Lata 355ml'},
                'cantidad_despachada': 12.0,
              },
              {
                'id': 2,
                'producto': {'nombre': 'Corona en Botella 355ml'},
                'cantidad_despachada': 6.0,
              }
            ]
          }
        ];
      });
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  void _abrirModalRecepcion(Map<String, dynamic> traspaso) {
    final List<dynamic> detalles = traspaso['detalles'] ?? [];
    final bool esMultiItem = detalles.isNotEmpty;

    // Controladores para cada detalle
    final List<Map<String, dynamic>> lineasControles = [];

    if (esMultiItem) {
      for (var d in detalles) {
        final cantDesp = double.tryParse(d['cantidad_despachada']?.toString() ?? '0') ?? 0.0;
        lineasControles.add({
          'detalle_id': d['id'],
          'producto_nombre': d['producto']?['nombre'] ?? 'Producto',
          'despachada': cantDesp,
          'conformeCtrl': TextEditingController(text: cantDesp.toStringAsFixed(2)),
          'mermaCtrl': TextEditingController(text: '0.00'),
        });
      }
    } else {
      final despachada = (traspaso['cantidad_despachada'] as num?)?.toDouble() ?? 0.0;
      lineasControles.add({
        'detalle_id': null,
        'producto_nombre': traspaso['producto']?['nombre'] ?? 'Producto',
        'despachada': despachada,
        'conformeCtrl': TextEditingController(text: despachada.toStringAsFixed(2)),
        'mermaCtrl': TextEditingController(text: '0.00'),
      });
    }

    final obsCtrl = TextEditingController();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: const Color(0xFF1B2332),
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) => Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.of(ctx).viewInsets.bottom + 20,
          left: 20,
          right: 20,
          top: 20,
        ),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'Recepción de Traspaso',
                    style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close, color: Colors.white60),
                    onPressed: () => Navigator.of(ctx).pop(),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                'Origen: ${traspaso['sucursal_origen']?['nombre'] ?? 'Sucursal'} | Traspaso #${traspaso['id']}',
                style: const TextStyle(color: Colors.amberAccent, fontWeight: FontWeight.bold, fontSize: 14),
              ),
              const Divider(color: Colors.white12, height: 20),

              // Lista de productos a verificar
              ...lineasControles.map((lc) {
                return Container(
                  margin: const EdgeInsets.only(bottom: 14),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: const Color(0xFF121620),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: Colors.white12),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          const Icon(Icons.inventory_2, color: Color(0xFF1ABC9C), size: 16),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              lc['producto_nombre'],
                              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                            ),
                          ),
                          Text(
                            'Despachado: ${lc['despachada']} u',
                            style: const TextStyle(color: Colors.white70, fontSize: 12),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text('Conforme (Bueno):', style: TextStyle(color: Color(0xFF2ECC71), fontSize: 11, fontWeight: FontWeight.bold)),
                                const SizedBox(height: 4),
                                TextField(
                                  controller: lc['conformeCtrl'],
                                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                                  decoration: InputDecoration(
                                    filled: true,
                                    fillColor: const Color(0xFF1B2332),
                                    contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                                    prefixIcon: const Icon(Icons.check_circle, color: Color(0xFF2ECC71), size: 16),
                                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: BorderSide.none),
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text('Merma / Roto:', style: TextStyle(color: Color(0xFFE74C3C), fontSize: 11, fontWeight: FontWeight.bold)),
                                const SizedBox(height: 4),
                                TextField(
                                  controller: lc['mermaCtrl'],
                                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                                  decoration: InputDecoration(
                                    filled: true,
                                    fillColor: const Color(0xFF1B2332),
                                    contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                                    prefixIcon: const Icon(Icons.broken_image, color: Color(0xFFE74C3C), size: 16),
                                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: BorderSide.none),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                );
              }).toList(),

              // Observaciones
              const Text('Observaciones de Recepción:', style: TextStyle(color: Colors.white70, fontSize: 13)),
              const SizedBox(height: 6),
              TextField(
                controller: obsCtrl,
                style: const TextStyle(color: Colors.white),
                decoration: InputDecoration(
                  filled: true,
                  fillColor: const Color(0xFF121620),
                  hintText: 'Ej. Recepción conforme de todos los bultos',
                  hintStyle: const TextStyle(color: Colors.white38),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
                ),
              ),
              const SizedBox(height: 20),

              ElevatedButton(
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF16A085),
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                onPressed: () async {
                  final id = traspaso['id'];

                  Navigator.of(ctx).pop();
                  try {
                    final apiClient = ref.read(apiClientProvider);

                    Map<String, dynamic> payload;
                    double totalMermas = 0.0;

                    if (esMultiItem) {
                      final itemsPayload = lineasControles.map((lc) {
                        final conf = double.tryParse(lc['conformeCtrl'].text) ?? 0.0;
                        final merm = double.tryParse(lc['mermaCtrl'].text) ?? 0.0;
                        totalMermas += merm;
                        return {
                          'detalle_id': lc['detalle_id'],
                          'cantidad_recibida_conforme': conf,
                          'cantidad_merma_transito': merm,
                        };
                      }).toList();

                      payload = {
                        'items': itemsPayload,
                        'observaciones': obsCtrl.text.trim(),
                      };
                    } else {
                      final lc = lineasControles.first;
                      final conf = double.tryParse(lc['conformeCtrl'].text) ?? 0.0;
                      final merm = double.tryParse(lc['mermaCtrl'].text) ?? 0.0;
                      totalMermas = merm;
                      payload = {
                        'cantidad_recibida_conforme': conf,
                        'cantidad_merma_transito': merm,
                        'observaciones': obsCtrl.text.trim(),
                      };
                    }

                    await apiClient.post('/traspasos/$id/recibir', data: payload);

                    if (mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(
                          content: Text(totalMermas > 0
                              ? 'Recepción asentada con $totalMermas u registradas como Merma en Tránsito.'
                              : 'Traspaso recibido conforme y stock acreditado en destino.'),
                          backgroundColor: const Color(0xFF27AE60),
                        ),
                      );
                      _cargarPendientes();
                    }
                  } catch (e) {
                    if (mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(content: Text('Error al recibir: $e'), backgroundColor: Colors.redAccent),
                      );
                    }
                  }
                },
                child: const Text('CONFIRMAR RECEPCIÓN EN DESTINO', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF121620),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1B2332),
        title: const Text('Recepcionar Traspasos', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        iconTheme: const IconThemeData(color: Colors.white),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _cargarPendientes),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Colors.amberAccent))
          : _traspasosEnTransito.isEmpty
              ? const Center(
                  child: Text(
                    'No hay traspasos pendientes de recepción.',
                    style: TextStyle(color: Colors.white54, fontSize: 15),
                  ),
                )
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: _traspasosEnTransito.length,
                  itemBuilder: (context, index) {
                    final t = _traspasosEnTransito[index];
                    final List<dynamic> detalles = t['detalles'] ?? [];
                    final bool tieneDetallesMulti = detalles.isNotEmpty;

                    return Card(
                      color: const Color(0xFF1B2332),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14), side: const BorderSide(color: Color(0xFF1ABC9C))),
                      margin: const EdgeInsets.symmetric(vertical: 8),
                      child: Padding(
                        padding: const EdgeInsets.all(16.0),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                const CircleAvatar(
                                  backgroundColor: Color(0xFF16A085),
                                  child: Icon(Icons.local_shipping, color: Colors.white),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        'Traspaso #${t['id']} (En Tránsito)',
                                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15),
                                      ),
                                      Text(
                                        'De: ${t['sucursal_origen']?['nombre'] ?? 'Origen'}',
                                        style: const TextStyle(color: Colors.white60, fontSize: 12),
                                      ),
                                    ],
                                  ),
                                ),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFF39C12).withOpacity(0.2),
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: const Text(
                                    'EN TRÁNSITO',
                                    style: TextStyle(color: Color(0xFFF39C12), fontSize: 10, fontWeight: FontWeight.bold),
                                  ),
                                ),
                              ],
                            ),
                            const Divider(color: Colors.white12, height: 20),
                            if (tieneDetallesMulti) ...[
                              const Text('Lote con múltiples productos:', style: TextStyle(color: Colors.amberAccent, fontWeight: FontWeight.bold, fontSize: 13)),
                              const SizedBox(height: 6),
                              ...detalles.map((d) {
                                return Padding(
                                  padding: const EdgeInsets.symmetric(vertical: 2),
                                  child: Row(
                                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                    children: [
                                      Text('• ${d['producto']?['nombre'] ?? 'Producto'}', style: const TextStyle(color: Colors.white, fontSize: 13)),
                                      Text('${d['cantidad_despachada']} u', style: const TextStyle(color: Colors.white70, fontWeight: FontWeight.bold, fontSize: 13)),
                                    ],
                                  ),
                                );
                              }).toList(),
                            ] else ...[
                              Text(
                                'Producto: ${t['producto']?['nombre'] ?? 'Producto'}',
                                style: const TextStyle(color: Colors.white, fontSize: 14),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                'Cantidad Despachada: ${t['cantidad_despachada']} u',
                                style: const TextStyle(color: Colors.amberAccent, fontWeight: FontWeight.bold, fontSize: 14),
                              ),
                            ],
                            if (t['observaciones'] != null && t['observaciones'].toString().isNotEmpty) ...[
                              const SizedBox(height: 6),
                              Text(
                                'Obs: ${t['observaciones']}',
                                style: const TextStyle(color: Colors.white54, fontSize: 12),
                              ),
                            ],
                            const SizedBox(height: 14),
                            ElevatedButton(
                              style: ElevatedButton.styleFrom(
                                backgroundColor: const Color(0xFF16A085),
                                minimumSize: const Size.fromHeight(40),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                              ),
                              onPressed: () => _abrirModalRecepcion(t),
                              child: const Text('CONTAR Y RECIBIR EN BARRA', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                ),
    );
  }
}
