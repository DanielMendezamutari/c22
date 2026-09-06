import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../providers/auth_provider.dart';

class UsuarioAdminItem {
  final int id;
  final String nombre;
  final String apellido;
  final String rol;
  final String modalidadCobro;
  final double sueldoBaseSemanal;
  final int? sucursalActualId;
  final String? sucursalNombre;
  final bool activo;

  UsuarioAdminItem({
    required this.id,
    required this.nombre,
    required this.apellido,
    required this.rol,
    required this.modalidadCobro,
    required this.sueldoBaseSemanal,
    this.sucursalActualId,
    this.sucursalNombre,
    required this.activo,
  });

  factory UsuarioAdminItem.fromJson(Map<String, dynamic> json) {
    String? sucNombre;
    if (json['sucursal_actual'] != null) {
      sucNombre = json['sucursal_actual']['nombre'];
    }

    return UsuarioAdminItem(
      id: json['id'] as int,
      nombre: json['nombre'] as String? ?? '',
      apellido: json['apellido'] as String? ?? '',
      rol: json['rol'] as String? ?? 'barman',
      modalidadCobro: json['modalidad_cobro'] as String? ?? 'diario',
      sueldoBaseSemanal: double.tryParse(json['sueldo_base_semanal']?.toString() ?? '0') ?? 0.0,
      sucursalActualId: json['sucursal_actual_id'] as int?,
      sucursalNombre: sucNombre,
      activo: json['activo'] == true || json['activo'] == 1,
    );
  }
}

class SucursalOption {
  final int id;
  final String nombre;
  final String codigo;

  SucursalOption({required this.id, required this.nombre, required this.codigo});

  factory SucursalOption.fromJson(Map<String, dynamic> json) {
    return SucursalOption(
      id: json['id'] as int,
      nombre: json['nombre'] as String? ?? '',
      codigo: json['codigo'] as String? ?? '',
    );
  }
}

class UsuariosAdminScreen extends ConsumerStatefulWidget {
  const UsuariosAdminScreen({super.key});

  @override
  ConsumerState<UsuariosAdminScreen> createState() => _UsuariosAdminScreenState();
}

class _UsuariosAdminScreenState extends ConsumerState<UsuariosAdminScreen> {
  List<UsuarioAdminItem> _usuarios = [];
  List<SucursalOption> _sucursales = [];
  bool _isLoading = true;
  String? _error;
  String _filtroRol = 'todos';

  @override
  void initState() {
    super.initState();
    _cargarDatos();
  }

  Future<void> _cargarDatos() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final client = ref.read(apiClientProvider);

      final respUsuarios = await client.get('/usuarios');
      final respSucursales = await client.get('/auth/sucursales');

      final List<dynamic> uList = respUsuarios.data['data'] ?? [];
      final List<dynamic> sList = respSucursales.data['data'] ?? [];

      setState(() {
        _usuarios = uList.map((e) => UsuarioAdminItem.fromJson(e)).toList();
        _sucursales = sList.map((e) => SucursalOption.fromJson(e)).toList();
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _error = 'Error al cargar personal: $e';
        _isLoading = false;
      });
    }
  }

  void _abrirDialogoCrearUsuario() {
    final nombreCtrl = TextEditingController();
    final apellidoCtrl = TextEditingController();
    final pinCtrl = TextEditingController();
    final sueldoCtrl = TextEditingController(text: '700.00');
    String rolSeleccionado = 'barman';
    String modalidadSeleccionada = 'semanal';
    int? sucursalSeleccionada = _sucursales.isNotEmpty ? _sucursales.first.id : null;
    final formKey = GlobalKey<FormState>();

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (dialogCtx) {
        return StatefulBuilder(
          builder: (ctx, setModalState) {
            return AlertDialog(
              backgroundColor: const Color(0xFF1E293B),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              title: Row(
                children: const [
                  Icon(Icons.person_add_alt_1, color: Colors.cyanAccent),
                  SizedBox(width: 8),
                  Text('Nuevo Usuario / Personal', style: TextStyle(color: Colors.white, fontSize: 18)),
                ],
              ),
              content: SizedBox(
                width: 400,
                child: Form(
                  key: formKey,
                  child: SingleChildScrollView(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        TextFormField(
                          controller: nombreCtrl,
                          style: const TextStyle(color: Colors.white),
                          decoration: const InputDecoration(
                            labelText: 'Nombre *',
                            labelStyle: TextStyle(color: Colors.white70),
                            prefixIcon: Icon(Icons.badge, color: Colors.cyanAccent),
                          ),
                          validator: (v) => (v == null || v.trim().isEmpty) ? 'Requerido' : null,
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: apellidoCtrl,
                          style: const TextStyle(color: Colors.white),
                          decoration: const InputDecoration(
                            labelText: 'Apellido *',
                            labelStyle: TextStyle(color: Colors.white70),
                            prefixIcon: Icon(Icons.person, color: Colors.cyanAccent),
                          ),
                          validator: (v) => (v == null || v.trim().isEmpty) ? 'Requerido' : null,
                        ),
                        const SizedBox(height: 12),
                        DropdownButtonFormField<String>(
                          value: rolSeleccionado,
                          dropdownColor: const Color(0xFF1E293B),
                          style: const TextStyle(color: Colors.white),
                          decoration: const InputDecoration(
                            labelText: 'Rol en Sistema *',
                            labelStyle: TextStyle(color: Colors.white70),
                            prefixIcon: Icon(Icons.work, color: Colors.cyanAccent),
                          ),
                          items: const [
                            DropdownMenuItem(value: 'barman', child: Text('Barman (Cierre semanal)')),
                            DropdownMenuItem(value: 'garzon', child: Text('Garzón / Día (Cobro diario)')),
                            DropdownMenuItem(value: 'admin', child: Text('Administrador / Auditor')),
                          ],
                          onChanged: (val) {
                            if (val != null) {
                              setModalState(() {
                                rolSeleccionado = val;
                                if (val == 'barman') {
                                  modalidadSeleccionada = 'semanal';
                                  sueldoCtrl.text = '700.00';
                                } else if (val == 'garzon') {
                                  modalidadSeleccionada = 'diario';
                                  sueldoCtrl.text = '0.00';
                                } else {
                                  modalidadSeleccionada = 'semanal';
                                  sueldoCtrl.text = '0.00';
                                  sucursalSeleccionada = null;
                                }
                              });
                            }
                          },
                        ),
                        const SizedBox(height: 12),
                        if (rolSeleccionado != 'admin')
                          DropdownButtonFormField<int?>(
                            value: sucursalSeleccionada,
                            dropdownColor: const Color(0xFF1E293B),
                            style: const TextStyle(color: Colors.white),
                            decoration: const InputDecoration(
                              labelText: 'Sucursal Asignada *',
                              labelStyle: TextStyle(color: Colors.white70),
                              prefixIcon: Icon(Icons.storefront, color: Colors.cyanAccent),
                            ),
                            items: _sucursales.map((s) {
                              return DropdownMenuItem<int?>(value: s.id, child: Text(s.nombre));
                            }).toList(),
                            onChanged: (val) => setModalState(() => sucursalSeleccionada = val),
                          ),
                        if (rolSeleccionado == 'barman') ...[
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: sueldoCtrl,
                            keyboardType: const TextInputType.numberWithOptions(decimal: true),
                            style: const TextStyle(color: Colors.white),
                            decoration: const InputDecoration(
                              labelText: 'Sueldo Base Semanal (Bs) *',
                              labelStyle: TextStyle(color: Colors.white70),
                              prefixIcon: Icon(Icons.attach_money, color: Colors.greenAccent),
                              helperText: 'Base semanal fija antes de descuentos por faltante',
                              helperStyle: TextStyle(color: Colors.white54, fontSize: 11),
                            ),
                            validator: (v) => (v == null || double.tryParse(v) == null) ? 'Monto inválido' : null,
                          ),
                        ],
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: pinCtrl,
                          keyboardType: TextInputType.number,
                          maxLength: 4,
                          obscureText: true,
                          inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                          style: const TextStyle(color: Colors.white, letterSpacing: 8, fontSize: 18),
                          decoration: const InputDecoration(
                            labelText: 'PIN Numérico (4 dígitos) *',
                            labelStyle: TextStyle(color: Colors.white70),
                            prefixIcon: Icon(Icons.lock, color: Colors.amberAccent),
                            counterText: '',
                          ),
                          validator: (v) => (v == null || v.length != 4) ? 'El PIN debe ser de 4 dígitos' : null,
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.of(ctx).pop(),
                  child: const Text('CANCELAR', style: TextStyle(color: Colors.white60)),
                ),
                ElevatedButton(
                  style: ElevatedButton.styleFrom(backgroundColor: Colors.cyanAccent),
                  onPressed: () async {
                    if (!formKey.currentState!.validate()) return;
                    Navigator.of(ctx).pop();
                    await _guardarNuevoUsuario(
                      nombre: nombreCtrl.text.trim(),
                      apellido: apellidoCtrl.text.trim(),
                      rol: rolSeleccionado,
                      modalidad: modalidadSeleccionada,
                      sucursalId: sucursalSeleccionada,
                      sueldoBase: double.tryParse(sueldoCtrl.text.trim()) ?? 0.0,
                      pin: pinCtrl.text.trim(),
                    );
                  },
                  child: const Text('GUARDAR', style: TextStyle(color: Colors.black, fontWeight: FontWeight.bold)),
                ),
              ],
            );
          },
        );
      },
    );
  }

  Future<void> _guardarNuevoUsuario({
    required String nombre,
    required String apellido,
    required String rol,
    required String modalidad,
    required int? sucursalId,
    required double sueldoBase,
    required String pin,
  }) async {
    try {
      final client = ref.read(apiClientProvider);
      final resp = await client.post('/usuarios', data: {
        'nombre': nombre,
        'apellido': apellido,
        'rol': rol,
        'pin': pin,
        'modalidad_cobro': modalidad,
        'sueldo_base_semanal': sueldoBase,
        'sucursal_actual_id': sucursalId,
        'activo': true,
      });

      if (resp.data['success'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Usuario $nombre creado con éxito'),
            backgroundColor: Colors.green,
          ),
        );
        _cargarDatos();
      } else {
        throw Exception(resp.data['error'] ?? 'Error desconocido');
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error al crear usuario: $e'), backgroundColor: Colors.red),
      );
    }
  }

  void _abrirDialogoCambiarPin(UsuarioAdminItem u) {
    final pinCtrl = TextEditingController();
    final formKey = GlobalKey<FormState>();

    showDialog(
      context: context,
      builder: (ctx) {
        return AlertDialog(
          backgroundColor: const Color(0xFF1E293B),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: Row(
            children: [
              const Icon(Icons.key, color: Colors.amberAccent),
              const SizedBox(width: 8),
              Expanded(
                child: Text('Cambiar PIN: ${u.nombre} ${u.apellido}',
                    style: const TextStyle(color: Colors.white, fontSize: 16)),
              ),
            ],
          ),
          content: Form(
            key: formKey,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Text(
                  'El usuario usará este nuevo PIN de 4 dígitos para identificarse en el teclado de acceso.',
                  style: TextStyle(color: Colors.white70, fontSize: 13),
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: pinCtrl,
                  keyboardType: TextInputType.number,
                  maxLength: 4,
                  obscureText: true,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Colors.amberAccent, letterSpacing: 12, fontSize: 24, fontWeight: FontWeight.bold),
                  decoration: const InputDecoration(
                    hintText: '••••',
                    counterText: '',
                    focusedBorder: UnderlineInputBorder(borderSide: BorderSide(color: Colors.amberAccent, width: 2)),
                  ),
                  validator: (v) => (v == null || v.length != 4) ? 'Ingrese exactamente 4 dígitos' : null,
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(ctx).pop(),
              child: const Text('CANCELAR', style: TextStyle(color: Colors.white60)),
            ),
            ElevatedButton(
              style: ElevatedButton.styleFrom(backgroundColor: Colors.amberAccent),
              onPressed: () async {
                if (!formKey.currentState!.validate()) return;
                Navigator.of(ctx).pop();
                try {
                  final client = ref.read(apiClientProvider);
                  final resp = await client.put('/usuarios/${u.id}/pin', data: {
                    'nuevo_pin': pinCtrl.text.trim(),
                  });
                  if (resp.data['success'] == true) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: Text('PIN de ${u.nombre} actualizado correctamente'),
                        backgroundColor: Colors.green,
                      ),
                    );
                  } else {
                    throw Exception(resp.data['error'] ?? 'Error');
                  }
                } catch (e) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('Error al cambiar PIN: $e'), backgroundColor: Colors.red),
                  );
                }
              },
              child: const Text('ACTUALIZAR PIN', style: TextStyle(color: Colors.black, fontWeight: FontWeight.bold)),
            ),
          ],
        );
      },
    );
  }

  void _abrirDialogoEditar(UsuarioAdminItem u) {
    final nombreCtrl = TextEditingController(text: u.nombre);
    final apellidoCtrl = TextEditingController(text: u.apellido);
    final sueldoCtrl = TextEditingController(text: u.sueldoBaseSemanal.toStringAsFixed(2));
    String rolSeleccionado = u.rol;
    String modalidadSeleccionada = u.modalidadCobro;
    int? sucursalSeleccionada = u.sucursalActualId;
    bool activo = u.activo;
    final formKey = GlobalKey<FormState>();

    showDialog(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            return AlertDialog(
              backgroundColor: const Color(0xFF1E293B),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              title: Text('Editar: ${u.nombre} ${u.apellido}', style: const TextStyle(color: Colors.white, fontSize: 18)),
              content: SizedBox(
                width: 400,
                child: Form(
                  key: formKey,
                  child: SingleChildScrollView(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        TextFormField(
                          controller: nombreCtrl,
                          style: const TextStyle(color: Colors.white),
                          decoration: const InputDecoration(labelText: 'Nombre *', labelStyle: TextStyle(color: Colors.white70)),
                          validator: (v) => (v == null || v.trim().isEmpty) ? 'Requerido' : null,
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: apellidoCtrl,
                          style: const TextStyle(color: Colors.white),
                          decoration: const InputDecoration(labelText: 'Apellido *', labelStyle: TextStyle(color: Colors.white70)),
                          validator: (v) => (v == null || v.trim().isEmpty) ? 'Requerido' : null,
                        ),
                        const SizedBox(height: 12),
                        DropdownButtonFormField<String>(
                          value: rolSeleccionado,
                          dropdownColor: const Color(0xFF1E293B),
                          style: const TextStyle(color: Colors.white),
                          decoration: const InputDecoration(labelText: 'Rol', labelStyle: TextStyle(color: Colors.white70)),
                          items: const [
                            DropdownMenuItem(value: 'barman', child: Text('Barman')),
                            DropdownMenuItem(value: 'garzon', child: Text('Garzón')),
                            DropdownMenuItem(value: 'admin', child: Text('Administrador')),
                          ],
                          onChanged: (val) {
                            if (val != null) {
                              setModalState(() {
                                rolSeleccionado = val;
                                if (val == 'admin') sucursalSeleccionada = null;
                              });
                            }
                          },
                        ),
                        if (rolSeleccionado != 'admin') ...[
                          const SizedBox(height: 12),
                          DropdownButtonFormField<int?>(
                            value: sucursalSeleccionada,
                            dropdownColor: const Color(0xFF1E293B),
                            style: const TextStyle(color: Colors.white),
                            decoration: const InputDecoration(labelText: 'Sucursal', labelStyle: TextStyle(color: Colors.white70)),
                            items: _sucursales.map((s) {
                              return DropdownMenuItem<int?>(value: s.id, child: Text(s.nombre));
                            }).toList(),
                            onChanged: (val) => setModalState(() => sucursalSeleccionada = val),
                          ),
                        ],
                        if (rolSeleccionado == 'barman') ...[
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: sueldoCtrl,
                            keyboardType: const TextInputType.numberWithOptions(decimal: true),
                            style: const TextStyle(color: Colors.white),
                            decoration: const InputDecoration(
                              labelText: 'Sueldo Base Semanal (Bs)',
                              labelStyle: TextStyle(color: Colors.white70),
                              prefixIcon: Icon(Icons.attach_money, color: Colors.greenAccent),
                            ),
                          ),
                        ],
                        const SizedBox(height: 12),
                        SwitchListTile(
                          contentPadding: EdgeInsets.zero,
                          title: const Text('Estado Activo', style: TextStyle(color: Colors.white)),
                          subtitle: Text(
                            activo ? 'Puede iniciar sesión con su PIN' : 'Acceso bloqueado',
                            style: const TextStyle(color: Colors.white54, fontSize: 12),
                          ),
                          value: activo,
                          activeColor: Colors.greenAccent,
                          onChanged: (val) => setModalState(() => activo = val),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.of(ctx).pop(),
                  child: const Text('CANCELAR', style: TextStyle(color: Colors.white60)),
                ),
                ElevatedButton(
                  style: ElevatedButton.styleFrom(backgroundColor: Colors.cyanAccent),
                  onPressed: () async {
                    if (!formKey.currentState!.validate()) return;
                    Navigator.of(ctx).pop();
                    try {
                      final client = ref.read(apiClientProvider);
                      final resp = await client.put('/usuarios/${u.id}', data: {
                        'nombre': nombreCtrl.text.trim(),
                        'apellido': apellidoCtrl.text.trim(),
                        'rol': rolSeleccionado,
                        'modalidad_cobro': modalidadSeleccionada,
                        'sucursal_actual_id': sucursalSeleccionada,
                        'sueldo_base_semanal': double.tryParse(sueldoCtrl.text.trim()) ?? 0.0,
                        'activo': activo,
                      });
                      if (resp.data['success'] == true) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Usuario modificado correctamente'), backgroundColor: Colors.green),
                        );
                        _cargarDatos();
                      } else {
                        throw Exception(resp.data['error'] ?? 'Error');
                      }
                    } catch (e) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(content: Text('Error al modificar: $e'), backgroundColor: Colors.red),
                      );
                    }
                  },
                  child: const Text('GUARDAR CAMBIOS', style: TextStyle(color: Colors.black, fontWeight: FontWeight.bold)),
                ),
              ],
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final usuariosFiltrados = _usuarios.where((u) {
      if (_filtroRol == 'todos') return true;
      return u.rol == _filtroRol;
    }).toList();

    return Scaffold(
      backgroundColor: const Color(0xFF0F172A),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1E293B),
        title: const Text('GESTIÓN DE PERSONAL Y PINS', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _cargarDatos,
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: Colors.cyanAccent,
        icon: const Icon(Icons.person_add, color: Colors.black),
        label: const Text('NUEVO USUARIO', style: TextStyle(color: Colors.black, fontWeight: FontWeight.bold)),
        onPressed: _abrirDialogoCrearUsuario,
      ),
      body: Column(
        children: [
          // Barra de filtros por Rol
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            color: const Color(0xFF1E293B).withOpacity(0.5),
            child: Row(
              children: [
                const Text('Filtrar:', style: TextStyle(color: Colors.white70, fontWeight: FontWeight.w600)),
                const SizedBox(width: 12),
                _buildFilterChip('Todos', 'todos'),
                const SizedBox(width: 8),
                _buildFilterChip('Barmen', 'barman'),
                const SizedBox(width: 8),
                _buildFilterChip('Garzones', 'garzon'),
                const SizedBox(width: 8),
                _buildFilterChip('Admins', 'admin'),
              ],
            ),
          ),
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: Colors.cyanAccent))
                : _error != null
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(_error!, style: const TextStyle(color: Colors.redAccent)),
                            const SizedBox(height: 12),
                            ElevatedButton(onPressed: _cargarDatos, child: const Text('Reintentar')),
                          ],
                        ),
                      )
                    : usuariosFiltrados.isEmpty
                        ? const Center(child: Text('No hay usuarios registrados', style: TextStyle(color: Colors.white54)))
                        : ListView.builder(
                            padding: const EdgeInsets.all(16),
                            itemCount: usuariosFiltrados.length,
                            itemBuilder: (context, index) {
                              final u = usuariosFiltrados[index];
                              return _buildUsuarioCard(u);
                            },
                          ),
          ),
        ],
      ),
    );
  }

  Widget _buildFilterChip(String label, String value) {
    final isSelected = _filtroRol == value;
    return ChoiceChip(
      label: Text(label),
      selected: isSelected,
      selectedColor: Colors.cyanAccent,
      backgroundColor: const Color(0xFF334155),
      labelStyle: TextStyle(
        color: isSelected ? Colors.black : Colors.white,
        fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
        fontSize: 12,
      ),
      onSelected: (selected) {
        if (selected) {
          setState(() => _filtroRol = value);
        }
      },
    );
  }

  Widget _buildUsuarioCard(UsuarioAdminItem u) {
    Color rolColor;
    switch (u.rol) {
      case 'admin':
        rolColor = Colors.purpleAccent;
        break;
      case 'barman':
        rolColor = Colors.cyanAccent;
        break;
      default:
        rolColor = Colors.amberAccent;
    }

    return Card(
      color: const Color(0xFF1E293B),
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: u.activo ? Colors.white10 : Colors.redAccent.withOpacity(0.4)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(
                  backgroundColor: rolColor.withOpacity(0.2),
                  child: Icon(
                    u.rol == 'admin' ? Icons.admin_panel_settings : Icons.person,
                    color: rolColor,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Text(
                            '${u.nombre} ${u.apellido}',
                            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16),
                          ),
                          const SizedBox(width: 8),
                          if (!u.activo)
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(color: Colors.red.withOpacity(0.2), borderRadius: BorderRadius.circular(4)),
                              child: const Text('INACTIVO', style: TextStyle(color: Colors.redAccent, fontSize: 10, fontWeight: FontWeight.bold)),
                            ),
                        ],
                      ),
                      const SizedBox(height: 2),
                      Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                            decoration: BoxDecoration(color: rolColor.withOpacity(0.15), borderRadius: BorderRadius.circular(4)),
                            child: Text(
                              u.rol.toUpperCase(),
                              style: TextStyle(color: rolColor, fontSize: 11, fontWeight: FontWeight.bold),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Icon(Icons.store, size: 14, color: Colors.white54),
                          const SizedBox(width: 4),
                          Text(
                            u.sucursalNombre ?? 'Todas las Sucursales',
                            style: const TextStyle(color: Colors.white70, fontSize: 12),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const Divider(color: Colors.white10, height: 20),
            Row(
              children: [
                if (u.rol == 'barman') ...[
                  const Icon(Icons.attach_money, size: 16, color: Colors.greenAccent),
                  Text(
                    'Sueldo Base: Bs. ${u.sueldoBaseSemanal.toStringAsFixed(2)} / sem',
                    style: const TextStyle(color: Colors.greenAccent, fontSize: 12, fontWeight: FontWeight.w600),
                  ),
                ] else if (u.rol == 'garzon') ...[
                  const Icon(Icons.today, size: 16, color: Colors.amberAccent),
                  const Text('Cobro diario por turno', style: TextStyle(color: Colors.amberAccent, fontSize: 12)),
                ] else ...[
                  const Icon(Icons.all_inclusive, size: 16, color: Colors.purpleAccent),
                  const Text('Acceso Global Admin', style: TextStyle(color: Colors.purpleAccent, fontSize: 12)),
                ],
                const Spacer(),
                OutlinedButton.icon(
                  icon: const Icon(Icons.key, size: 14, color: Colors.amberAccent),
                  label: const Text('PIN', style: TextStyle(color: Colors.amberAccent, fontSize: 11)),
                  style: OutlinedButton.styleFrom(
                    side: const BorderSide(color: Colors.amberAccent),
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  ),
                  onPressed: () => _abrirDialogoCambiarPin(u),
                ),
                const SizedBox(width: 8),
                IconButton(
                  icon: const Icon(Icons.edit, size: 18, color: Colors.cyanAccent),
                  onPressed: () => _abrirDialogoEditar(u),
                  tooltip: 'Editar Datos',
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
