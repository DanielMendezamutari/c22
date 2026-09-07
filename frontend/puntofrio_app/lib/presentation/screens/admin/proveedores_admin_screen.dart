import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/network/api_client.dart';
import '../../providers/auth_provider.dart';

class ProveedoresAdminScreen extends ConsumerStatefulWidget {
  const ProveedoresAdminScreen({super.key});

  @override
  ConsumerState<ProveedoresAdminScreen> createState() => _ProveedoresAdminScreenState();
}

class _ProveedoresAdminScreenState extends ConsumerState<ProveedoresAdminScreen> {
  List<Map<String, dynamic>> _proveedores = [];
  List<Map<String, dynamic>> _proveedoresFiltrados = [];
  bool _isLoading = false;
  final TextEditingController _searchCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    _cargarProveedores();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _cargarProveedores() async {
    setState(() => _isLoading = true);
    try {
      final client = ref.read(apiClientProvider);
      final res = await client.get('/proveedores');
      if (res.statusCode == 200 && res.data['success'] == true) {
        final List list = res.data['data'] ?? [];
        setState(() {
          _proveedores = List<Map<String, dynamic>>.from(list);
          _aplicarFiltro(_searchCtrl.text);
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error al cargar proveedores: $e'),
            backgroundColor: Colors.redAccent,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  void _aplicarFiltro(String q) {
    final query = q.trim().toLowerCase();
    if (query.isEmpty) {
      setState(() => _proveedoresFiltrados = List.from(_proveedores));
    } else {
      setState(() {
        _proveedoresFiltrados = _proveedores.where((p) {
          final nombre = (p['nombre'] ?? '').toString().toLowerCase();
          final contacto = (p['contacto_nombre'] ?? '').toString().toLowerCase();
          final nit = (p['nit_o_ci'] ?? '').toString().toLowerCase();
          final telefono = (p['telefono'] ?? '').toString().toLowerCase();
          return nombre.contains(query) ||
              contacto.contains(query) ||
              nit.contains(query) ||
              telefono.contains(query);
        }).toList();
      });
    }
  }

  Future<void> _toggleActivo(int id, bool estadoActual) async {
    try {
      final client = ref.read(apiClientProvider);
      final res = await client.patch('/proveedores/$id/toggle-activo');
      if (res.statusCode == 200 && res.data['success'] == true) {
        _cargarProveedores();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(
                estadoActual
                    ? 'Proveedor suspendido de la lista operativa'
                    : 'Proveedor reactivado exitosamente',
              ),
              backgroundColor: estadoActual ? Colors.orange : const Color(0xFF27AE60),
              duration: const Duration(seconds: 2),
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error al cambiar estado: $e'), backgroundColor: Colors.redAccent),
        );
      }
    }
  }

  Future<void> _eliminarProveedor(Map<String, dynamic> p) async {
    final bool confirmar = await showDialog(
          context: context,
          builder: (ctx) => AlertDialog(
            backgroundColor: const Color(0xFF1E293B),
            title: const Text('¿Eliminar Proveedor?', style: TextStyle(color: Colors.white)),
            content: Text(
              '¿Está seguro de eliminar "${p['nombre']}"? Si ya tiene compras registradas se desactivará para resguardar la auditoría.',
              style: const TextStyle(color: Colors.white70),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(ctx).pop(false),
                child: const Text('CANCELAR', style: TextStyle(color: Colors.white54)),
              ),
              ElevatedButton(
                style: ElevatedButton.styleFrom(backgroundColor: Colors.redAccent),
                onPressed: () => Navigator.of(ctx).pop(true),
                child: const Text('ELIMINAR', style: TextStyle(color: Colors.white)),
              ),
            ],
          ),
        ) ??
        false;

    if (!confirmar) return;

    try {
      final client = ref.read(apiClientProvider);
      final res = await client.delete('/proveedores/${p['id']}');
      if (res.statusCode == 200 && res.data['success'] == true) {
        _cargarProveedores();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(res.data['message'] ?? 'Proveedor procesado'),
              backgroundColor: const Color(0xFF27AE60),
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error al eliminar: $e'), backgroundColor: Colors.redAccent),
        );
      }
    }
  }

  void _abrirModalCrearEditar({Map<String, dynamic>? proveedor}) {
    final isEditing = proveedor != null;
    final nombreCtrl = TextEditingController(text: proveedor?['nombre'] ?? '');
    final contactoCtrl = TextEditingController(text: proveedor?['contacto_nombre'] ?? '');
    final telefonoCtrl = TextEditingController(text: proveedor?['telefono'] ?? '');
    final nitCtrl = TextEditingController(text: proveedor?['nit_o_ci'] ?? '');
    final direccionCtrl = TextEditingController(text: proveedor?['direccion'] ?? '');
    final formKey = GlobalKey<FormState>();
    bool isSaving = false;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: const Color(0xFF1E293B),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) {
        return StatefulBuilder(
          builder: (modalCtx, setModalState) {
            return Padding(
              padding: EdgeInsets.only(
                left: 20,
                right: 20,
                top: 20,
                bottom: MediaQuery.of(modalCtx).viewInsets.bottom + 24,
              ),
              child: Form(
                key: formKey,
                child: SingleChildScrollView(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(10),
                            decoration: BoxDecoration(
                              color: const Color(0xFF0D9488).withOpacity(0.2),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: const Icon(Icons.local_shipping, color: Color(0xFF2DD4BF), size: 24),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  isEditing ? 'EDITAR PROVEEDOR' : 'NUEVO PROVEEDOR',
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 18,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                Text(
                                  isEditing
                                      ? 'Actualice la información comercial'
                                      : 'Registre un proveedor de insumos/bebidas',
                                  style: const TextStyle(color: Colors.white70, fontSize: 12),
                                ),
                              ],
                            ),
                          ),
                          IconButton(
                            icon: const Icon(Icons.close, color: Colors.white54),
                            onPressed: () => Navigator.of(modalCtx).pop(),
                          ),
                        ],
                      ),
                      const SizedBox(height: 20),
                      TextFormField(
                        controller: nombreCtrl,
                        style: const TextStyle(color: Colors.white),
                        decoration: InputDecoration(
                          labelText: 'Razón Social / Nombre Comercial *',
                          labelStyle: const TextStyle(color: Colors.white70),
                          hintText: 'Ej. Cervecería Boliviana Nacional, Embol...',
                          hintStyle: const TextStyle(color: Colors.white30),
                          prefixIcon: const Icon(Icons.business, color: Color(0xFF2DD4BF)),
                          filled: true,
                          fillColor: const Color(0xFF0F172A),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: BorderSide.none,
                          ),
                        ),
                        validator: (v) => (v == null || v.trim().isEmpty)
                            ? 'El nombre del proveedor es obligatorio'
                            : null,
                      ),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: TextFormField(
                              controller: contactoCtrl,
                              style: const TextStyle(color: Colors.white),
                              decoration: InputDecoration(
                                labelText: 'Nombre de Contacto',
                                labelStyle: const TextStyle(color: Colors.white70),
                                hintText: 'Ej. Juan Pérez (Preventa)',
                                hintStyle: const TextStyle(color: Colors.white30),
                                prefixIcon: const Icon(Icons.person_outline, color: Colors.white54),
                                filled: true,
                                fillColor: const Color(0xFF0F172A),
                                border: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(12),
                                  borderSide: BorderSide.none,
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: TextFormField(
                              controller: telefonoCtrl,
                              keyboardType: TextInputType.phone,
                              style: const TextStyle(color: Colors.white),
                              decoration: InputDecoration(
                                labelText: 'Teléfono / Celular',
                                labelStyle: const TextStyle(color: Colors.white70),
                                hintText: 'Ej. 77712345',
                                hintStyle: const TextStyle(color: Colors.white30),
                                prefixIcon: const Icon(Icons.phone_outlined, color: Colors.white54),
                                filled: true,
                                fillColor: const Color(0xFF0F172A),
                                border: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(12),
                                  borderSide: BorderSide.none,
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: TextFormField(
                              controller: nitCtrl,
                              style: const TextStyle(color: Colors.white),
                              decoration: InputDecoration(
                                labelText: 'NIT / CI (Opcional)',
                                labelStyle: const TextStyle(color: Colors.white70),
                                hintText: 'Ej. 1029384756',
                                hintStyle: const TextStyle(color: Colors.white30),
                                prefixIcon: const Icon(Icons.badge_outlined, color: Colors.white54),
                                filled: true,
                                fillColor: const Color(0xFF0F172A),
                                border: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(12),
                                  borderSide: BorderSide.none,
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: direccionCtrl,
                        style: const TextStyle(color: Colors.white),
                        decoration: InputDecoration(
                          labelText: 'Dirección / Zona / Distribuidora',
                          labelStyle: const TextStyle(color: Colors.white70),
                          hintText: 'Ej. Parque Industrial, Zona Central...',
                          hintStyle: const TextStyle(color: Colors.white30),
                          prefixIcon: const Icon(Icons.location_on_outlined, color: Colors.white54),
                          filled: true,
                          fillColor: const Color(0xFF0F172A),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: BorderSide.none,
                          ),
                        ),
                      ),
                      const SizedBox(height: 24),
                      ElevatedButton(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFF0D9488),
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                          elevation: 4,
                        ),
                        onPressed: isSaving
                            ? null
                            : () async {
                                if (!formKey.currentState!.validate()) return;
                                setModalState(() => isSaving = true);
                                try {
                                  final client = ref.read(apiClientProvider);
                                  final data = {
                                    'nombre': nombreCtrl.text.trim(),
                                    'contacto_nombre': contactoCtrl.text.trim().isEmpty
                                        ? null
                                        : contactoCtrl.text.trim(),
                                    'telefono': telefonoCtrl.text.trim().isEmpty
                                        ? null
                                        : telefonoCtrl.text.trim(),
                                    'nit_o_ci':
                                        nitCtrl.text.trim().isEmpty ? null : nitCtrl.text.trim(),
                                    'direccion': direccionCtrl.text.trim().isEmpty
                                        ? null
                                        : direccionCtrl.text.trim(),
                                    if (!isEditing) 'activo': true,
                                  };

                                  final res = isEditing
                                      ? await client.put('/proveedores/${proveedor!['id']}', data: data)
                                      : await client.post('/proveedores', data: data);

                                  if (res.statusCode == 200 || res.statusCode == 201) {
                                    Navigator.of(modalCtx).pop();
                                    _cargarProveedores();
                                    if (mounted) {
                                      ScaffoldMessenger.of(context).showSnackBar(
                                        SnackBar(
                                          content: Text(
                                            isEditing
                                                ? 'Proveedor actualizado correctamente'
                                                : 'Proveedor registrado exitosamente',
                                          ),
                                          backgroundColor: const Color(0xFF27AE60),
                                        ),
                                      );
                                    }
                                  }
                                } catch (e) {
                                  setModalState(() => isSaving = false);
                                  if (mounted) {
                                    ScaffoldMessenger.of(context).showSnackBar(
                                      SnackBar(
                                        content: Text('Error al guardar: $e'),
                                        backgroundColor: Colors.redAccent,
                                      ),
                                    );
                                  }
                                }
                              },
                        child: isSaving
                            ? const SizedBox(
                                height: 20,
                                width: 20,
                                child: CircularProgressIndicator(
                                  color: Colors.white,
                                  strokeWidth: 2,
                                ),
                              )
                            : Text(
                                isEditing ? 'ACTUALIZAR PROVEEDOR' : 'REGISTRAR PROVEEDOR',
                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                              ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0F172A),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1E293B),
        elevation: 0,
        title: const Row(
          children: [
            Icon(Icons.local_shipping_outlined, color: Color(0xFF2DD4BF)),
            SizedBox(width: 8),
            Text(
              'GESTIÓN DE PROVEEDORES',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, letterSpacing: 0.5),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: 'Actualizar lista',
            onPressed: _cargarProveedores,
          ),
        ],
      ),
      body: Column(
        children: [
          // Barra de Búsqueda y Estadísticas
          Container(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
            color: const Color(0xFF1E293B),
            child: Column(
              children: [
                TextField(
                  controller: _searchCtrl,
                  style: const TextStyle(color: Colors.white),
                  onChanged: _aplicarFiltro,
                  decoration: InputDecoration(
                    hintText: 'Buscar por nombre, contacto o NIT...',
                    hintStyle: const TextStyle(color: Colors.white38),
                    prefixIcon: const Icon(Icons.search, color: Color(0xFF2DD4BF)),
                    suffixIcon: _searchCtrl.text.isNotEmpty
                        ? IconButton(
                            icon: const Icon(Icons.clear, color: Colors.white38),
                            onPressed: () {
                              _searchCtrl.clear();
                              _aplicarFiltro('');
                            },
                          )
                        : null,
                    filled: true,
                    fillColor: const Color(0xFF0F172A),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: BorderSide.none,
                    ),
                  ),
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    const Icon(Icons.verified_outlined, size: 14, color: Color(0xFF2DD4BF)),
                    const SizedBox(width: 6),
                    Text(
                      '${_proveedoresFiltrados.length} proveedores registrados',
                      style: const TextStyle(color: Colors.white70, fontSize: 12),
                    ),
                    const Spacer(),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                      decoration: BoxDecoration(
                        color: const Color(0xFF0D9488).withOpacity(0.2),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: const Text(
                        'Punto Frío Grupo',
                        style: TextStyle(color: Color(0xFF2DD4BF), fontSize: 11, fontWeight: FontWeight.bold),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),

          // Lista de Proveedores
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: Color(0xFF2DD4BF)))
                : _proveedoresFiltrados.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.store_mall_directory_outlined, size: 64, color: Colors.white.withOpacity(0.2)),
                            const SizedBox(height: 12),
                            Text(
                              _searchCtrl.text.isEmpty
                                  ? 'No hay proveedores registrados'
                                  : 'No se encontraron proveedores con "${_searchCtrl.text}"',
                              style: const TextStyle(color: Colors.white60, fontSize: 14),
                            ),
                          ],
                        ),
                      )
                    : RefreshIndicator(
                        color: const Color(0xFF2DD4BF),
                        onRefresh: _cargarProveedores,
                        child: ListView.builder(
                          padding: const EdgeInsets.fromLTRB(16, 12, 16, 90),
                          itemCount: _proveedoresFiltrados.length,
                          itemBuilder: (context, index) {
                            final p = _proveedoresFiltrados[index];
                            final bool activo = p['activo'] == true || p['activo'] == 1;
                            final int comprasCount = (p['compras_count'] as num?)?.toInt() ?? 0;

                            return Container(
                              margin: const EdgeInsets.only(bottom: 12),
                              decoration: BoxDecoration(
                                color: const Color(0xFF1E293B),
                                borderRadius: BorderRadius.circular(16),
                                border: Border.all(
                                  color: activo
                                      ? const Color(0xFF0D9488).withOpacity(0.3)
                                      : Colors.redAccent.withOpacity(0.2),
                                  width: 1.2,
                                ),
                              ),
                              child: Padding(
                                padding: const EdgeInsets.all(16),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Row(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        CircleAvatar(
                                          backgroundColor: activo
                                              ? const Color(0xFF0D9488).withOpacity(0.2)
                                              : Colors.red.withOpacity(0.2),
                                          radius: 20,
                                          child: Icon(
                                            Icons.local_shipping,
                                            color: activo ? const Color(0xFF2DD4BF) : Colors.redAccent,
                                            size: 20,
                                          ),
                                        ),
                                        const SizedBox(width: 12),
                                        Expanded(
                                          child: Column(
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            children: [
                                              Text(
                                                p['nombre'] ?? 'Sin Nombre',
                                                style: TextStyle(
                                                  color: activo ? Colors.white : Colors.white60,
                                                  fontWeight: FontWeight.bold,
                                                  fontSize: 16,
                                                  decoration: activo ? null : TextDecoration.lineThrough,
                                                ),
                                              ),
                                              if (p['nit_o_ci'] != null && p['nit_o_ci'].toString().isNotEmpty)
                                                Text(
                                                  'NIT / CI: ${p['nit_o_ci']}',
                                                  style: const TextStyle(color: Colors.white54, fontSize: 12),
                                                ),
                                            ],
                                          ),
                                        ),
                                        Switch(
                                          value: activo,
                                          activeColor: const Color(0xFF2DD4BF),
                                          activeTrackColor: const Color(0xFF0D9488).withOpacity(0.5),
                                          inactiveThumbColor: Colors.white38,
                                          inactiveTrackColor: Colors.white12,
                                          onChanged: (val) => _toggleActivo(p['id'], activo),
                                        ),
                                      ],
                                    ),
                                    const SizedBox(height: 10),
                                    if (p['contacto_nombre'] != null && p['contacto_nombre'].toString().isNotEmpty) ...[
                                      Row(
                                        children: [
                                          const Icon(Icons.person, size: 14, color: Colors.white38),
                                          const SizedBox(width: 6),
                                          Text(
                                            'Contacto: ${p['contacto_nombre']}',
                                            style: const TextStyle(color: Colors.white70, fontSize: 13),
                                          ),
                                        ],
                                      ),
                                      const SizedBox(height: 4),
                                    ],
                                    if (p['telefono'] != null && p['telefono'].toString().isNotEmpty) ...[
                                      Row(
                                        children: [
                                          const Icon(Icons.phone, size: 14, color: Colors.white38),
                                          const SizedBox(width: 6),
                                          Text(
                                            p['telefono'],
                                            style: const TextStyle(color: Color(0xFF38BDF8), fontSize: 13),
                                          ),
                                        ],
                                      ),
                                      const SizedBox(height: 4),
                                    ],
                                    if (p['direccion'] != null && p['direccion'].toString().isNotEmpty) ...[
                                      Row(
                                        children: [
                                          const Icon(Icons.location_on, size: 14, color: Colors.white38),
                                          const SizedBox(width: 6),
                                          Expanded(
                                            child: Text(
                                              p['direccion'],
                                              style: const TextStyle(color: Colors.white54, fontSize: 12),
                                              maxLines: 1,
                                              overflow: TextOverflow.ellipsis,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ],
                                    const Divider(color: Colors.white10, height: 20),
                                    Row(
                                      children: [
                                        Container(
                                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                          decoration: BoxDecoration(
                                            color: Colors.white.withOpacity(0.05),
                                            borderRadius: BorderRadius.circular(6),
                                          ),
                                          child: Text(
                                            '$comprasCount recepciones registradas',
                                            style: const TextStyle(color: Colors.white54, fontSize: 11),
                                          ),
                                        ),
                                        const Spacer(),
                                        IconButton(
                                          icon: const Icon(Icons.edit_outlined, size: 20, color: Color(0xFF38BDF8)),
                                          tooltip: 'Editar',
                                          onPressed: () => _abrirModalCrearEditar(proveedor: p),
                                        ),
                                        IconButton(
                                          icon: const Icon(Icons.delete_outline, size: 20, color: Colors.redAccent),
                                          tooltip: 'Eliminar o desactivar',
                                          onPressed: () => _eliminarProveedor(p),
                                        ),
                                      ],
                                    ),
                                  ],
                                ),
                              ),
                            );
                          },
                        ),
                      ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: const Color(0xFF0D9488),
        icon: const Icon(Icons.add_business, color: Colors.white),
        label: const Text(
          'NUEVO PROVEEDOR',
          style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, letterSpacing: 0.5),
        ),
        onPressed: () => _abrirModalCrearEditar(),
      ),
    );
  }
}
