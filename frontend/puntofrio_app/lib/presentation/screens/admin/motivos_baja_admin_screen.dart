import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/api_client.dart';
import '../../providers/auth_provider.dart';

class MotivosBajaAdminScreen extends ConsumerStatefulWidget {
  const MotivosBajaAdminScreen({super.key});

  @override
  ConsumerState<MotivosBajaAdminScreen> createState() => _MotivosBajaAdminScreenState();
}

class _MotivosBajaAdminScreenState extends ConsumerState<MotivosBajaAdminScreen> {
  bool _isLoading = true;
  List<Map<String, dynamic>> _motivos = [];

  @override
  void initState() {
    super.initState();
    _cargarMotivos();
  }

  Future<void> _cargarMotivos() async {
    setState(() => _isLoading = true);
    final client = ref.read(apiClientProvider);

    try {
      final res = await client.get('/motivos-baja/admin');
      if (res.data['success'] == true && res.data['data'] != null) {
        setState(() {
          _motivos = List<Map<String, dynamic>>.from(res.data['data']);
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error al cargar motivos: $e'), backgroundColor: Colors.redAccent),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  void _abrirDialogoMotivo([Map<String, dynamic>? motivoExistente]) {
    final esEdicion = motivoExistente != null;
    final ctrl = TextEditingController(text: motivoExistente?['descripcion'] ?? '');
    bool activo = motivoExistente?['activo'] == 1 || motivoExistente?['activo'] == true || !esEdicion;

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
                  esEdicion ? 'Editar Motivo' : 'Nuevo Motivo de Baja',
                  style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
                ),
              ],
            ),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                TextField(
                  controller: ctrl,
                  autofocus: true,
                  style: const TextStyle(color: Colors.white, fontSize: 15),
                  decoration: InputDecoration(
                    labelText: 'Descripción del Motivo',
                    labelStyle: const TextStyle(color: Colors.white60),
                    hintText: 'Ej. Botella Defectuosa en Fábrica',
                    hintStyle: const TextStyle(color: Colors.white24),
                    filled: true,
                    fillColor: const Color(0xFF121620),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                ),
                if (esEdicion) ...[
                  const SizedBox(height: 12),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Motivo Activo:', style: TextStyle(color: Colors.white70)),
                      Switch(
                        value: activo,
                        activeColor: const Color(0xFF27AE60),
                        onChanged: (val) => setModalState(() => activo = val),
                      ),
                    ],
                  ),
                ],
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
                  final desc = ctrl.text.trim();
                  if (desc.isEmpty) return;

                  Navigator.of(ctx).pop();
                  final client = ref.read(apiClientProvider);

                  try {
                    if (esEdicion) {
                      await client.put('/motivos-baja/${motivoExistente['id']}', data: {
                        'descripcion': desc,
                        'activo': activo ? 1 : 0,
                      });
                    } else {
                      await client.post('/motivos-baja', data: {
                        'descripcion': desc,
                      });
                    }
                    _cargarMotivos();
                  } catch (e) {
                    if (mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(content: Text('Error al guardar: $e'), backgroundColor: Colors.redAccent),
                      );
                    }
                  }
                },
                child: const Text('GUARDAR', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.white)),
              ),
            ],
          );
        },
      ),
    );
  }

  Future<void> _desactivarMotivo(Map<String, dynamic> motivo) async {
    final client = ref.read(apiClientProvider);
    final id = motivo['id'];

    try {
      await client.delete('/motivos-baja/$id');
      _cargarMotivos();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Motivo desactivado correctamente'), backgroundColor: Colors.green),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e'), backgroundColor: Colors.redAccent),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF121620),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1B2332),
        title: const Text('Motivos de Bajas y Roturas', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        iconTheme: const IconThemeData(color: Colors.white),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh, color: Colors.white70),
            onPressed: _cargarMotivos,
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        backgroundColor: const Color(0xFFE67E22),
        foregroundColor: Colors.white,
        onPressed: () => _abrirDialogoMotivo(),
        child: const Icon(Icons.add),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Colors.amberAccent))
          : _motivos.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.report_problem_outlined, size: 64, color: Colors.white24),
                      const SizedBox(height: 16),
                      const Text(
                        'No hay motivos de baja configurados',
                        style: TextStyle(color: Colors.white70, fontSize: 16),
                      ),
                      const SizedBox(height: 12),
                      ElevatedButton.icon(
                        style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFFE67E22)),
                        icon: const Icon(Icons.add),
                        label: const Text('Crear Primer Motivo'),
                        onPressed: () => _abrirDialogoMotivo(),
                      ),
                    ],
                  ),
                )
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: _motivos.length,
                  itemBuilder: (context, index) {
                    final m = _motivos[index];
                    final bool activo = m['activo'] == 1 || m['activo'] == true;

                    return Card(
                      color: const Color(0xFF1B2332),
                      margin: const EdgeInsets.only(bottom: 10),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      child: ListTile(
                        leading: CircleAvatar(
                          backgroundColor: activo
                              ? const Color(0xFF27AE60).withOpacity(0.2)
                              : const Color(0xFFC0392B).withOpacity(0.2),
                          child: Icon(
                            activo ? Icons.check_circle_outline : Icons.cancel_outlined,
                            color: activo ? const Color(0xFF2ECC71) : const Color(0xFFE74C3C),
                            size: 20,
                          ),
                        ),
                        title: Text(
                          m['descripcion'] ?? '',
                          style: TextStyle(
                            color: activo ? Colors.white : Colors.white38,
                            fontWeight: FontWeight.bold,
                            decoration: activo ? null : TextDecoration.lineThrough,
                          ),
                        ),
                        subtitle: Text(
                          activo ? 'Disponible para barmen en turno' : 'Desactivado (oculto en barra)',
                          style: TextStyle(color: activo ? Colors.white54 : Colors.redAccent.withOpacity(0.6), fontSize: 11),
                        ),
                        trailing: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            IconButton(
                              icon: const Icon(Icons.edit, color: Color(0xFF3498DB), size: 20),
                              tooltip: 'Editar descripción',
                              onPressed: () => _abrirDialogoMotivo(m),
                            ),
                            if (activo)
                              IconButton(
                                icon: const Icon(Icons.delete_outline, color: Colors.redAccent, size: 20),
                                tooltip: 'Desactivar',
                                onPressed: () => _desactivarMotivo(m),
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
