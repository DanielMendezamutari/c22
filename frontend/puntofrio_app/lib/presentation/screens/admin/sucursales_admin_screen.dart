import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../providers/sucursales_admin_provider.dart';

class SucursalesAdminScreen extends ConsumerStatefulWidget {
  const SucursalesAdminScreen({super.key});

  @override
  ConsumerState<SucursalesAdminScreen> createState() => _SucursalesAdminScreenState();
}

class _SucursalesAdminScreenState extends ConsumerState<SucursalesAdminScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() {
      ref.read(sucursalesAdminProvider.notifier).cargarSucursales();
    });
  }

  void _abrirModalCrearEditar({SucursalItem? sucursal}) {
    final isEditing = sucursal != null;
    final nombreCtrl = TextEditingController(text: sucursal?.nombre ?? '');
    final codigoCtrl = TextEditingController(text: sucursal?.codigo ?? '');
    final direccionCtrl = TextEditingController(text: sucursal?.direccion ?? '');
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
          builder: (modalContext, setModalState) {
            return Padding(
              padding: EdgeInsets.only(
                left: 24,
                right: 24,
                top: 24,
                bottom: MediaQuery.of(modalContext).viewInsets.bottom + 24,
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
                              color: const Color(0xFF0284C7).withOpacity(0.2),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: const Icon(
                              Icons.storefront,
                              color: Color(0xFF38BDF8),
                              size: 24,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  isEditing ? 'EDITAR SUCURSAL' : 'NUEVA SUCURSAL',
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 18,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                Text(
                                  isEditing
                                      ? 'Modifique los datos de la sede'
                                      : 'Agregue un nuevo punto de venta',
                                  style: const TextStyle(
                                    color: Colors.white70,
                                    fontSize: 12,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          IconButton(
                            icon: const Icon(Icons.close, color: Colors.white54),
                            onPressed: () => Navigator.of(modalContext).pop(),
                          ),
                        ],
                      ),
                      const SizedBox(height: 20),

                      // Nombre
                      TextFormField(
                        controller: nombreCtrl,
                        style: const TextStyle(color: Colors.white),
                        decoration: InputDecoration(
                          labelText: 'Nombre de Sucursal *',
                          labelStyle: const TextStyle(color: Colors.white70),
                          hintText: 'Ej. Casa Corona, Sucursal Norte',
                          hintStyle: const TextStyle(color: Colors.white30),
                          prefixIcon: const Icon(Icons.business, color: Color(0xFF38BDF8)),
                          filled: true,
                          fillColor: const Color(0xFF0F172A),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: BorderSide.none,
                          ),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: Color(0xFF334155)),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: Color(0xFF38BDF8)),
                          ),
                        ),
                        validator: (val) {
                          if (val == null || val.trim().isEmpty) {
                            return 'El nombre es obligatorio';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 16),

                      // Código
                      TextFormField(
                        controller: codigoCtrl,
                        style: const TextStyle(color: Colors.white),
                        textCapitalization: TextCapitalization.characters,
                        inputFormatters: [
                          FilteringTextInputFormatter.allow(RegExp(r'[a-zA-Z0-9_]')),
                        ],
                        decoration: InputDecoration(
                          labelText: 'Código Único (Acrónimo) *',
                          labelStyle: const TextStyle(color: Colors.white70),
                          hintText: 'Ej. CCORON, C22, MDN, NORTE',
                          hintStyle: const TextStyle(color: Colors.white30),
                          prefixIcon: const Icon(Icons.tag, color: Color(0xFF38BDF8)),
                          filled: true,
                          fillColor: const Color(0xFF0F172A),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: BorderSide.none,
                          ),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: Color(0xFF334155)),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: Color(0xFF38BDF8)),
                          ),
                        ),
                        validator: (val) {
                          if (val == null || val.trim().isEmpty) {
                            return 'El código es obligatorio';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 16),

                      // Dirección
                      TextFormField(
                        controller: direccionCtrl,
                        style: const TextStyle(color: Colors.white),
                        maxLines: 2,
                        decoration: InputDecoration(
                          labelText: 'Dirección o Ubicación',
                          labelStyle: const TextStyle(color: Colors.white70),
                          hintText: 'Ej. Av. Montenegro #450, San Miguel',
                          hintStyle: const TextStyle(color: Colors.white30),
                          prefixIcon: const Icon(Icons.location_on_outlined, color: Color(0xFF38BDF8)),
                          filled: true,
                          fillColor: const Color(0xFF0F172A),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: BorderSide.none,
                          ),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: Color(0xFF334155)),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: Color(0xFF38BDF8)),
                          ),
                        ),
                      ),
                      const SizedBox(height: 24),

                      // Botón Guardar
                      SizedBox(
                        height: 50,
                        child: ElevatedButton(
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFF0284C7),
                            foregroundColor: Colors.white,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14),
                            ),
                          ),
                          onPressed: isSaving
                              ? null
                              : () async {
                                  if (!formKey.currentState!.validate()) return;
                                  setModalState(() => isSaving = true);

                                  final notifier = ref.read(sucursalesAdminProvider.notifier);
                                  bool ok = false;

                                  final messenger = ScaffoldMessenger.of(context);
                                  final nav = Navigator.of(modalContext);

                                  if (isEditing) {
                                    ok = await notifier.actualizarSucursal(
                                      id: sucursal.id,
                                      nombre: nombreCtrl.text,
                                      codigo: codigoCtrl.text,
                                      direccion: direccionCtrl.text,
                                    );
                                  } else {
                                    ok = await notifier.crearSucursal(
                                      nombre: nombreCtrl.text,
                                      codigo: codigoCtrl.text,
                                      direccion: direccionCtrl.text,
                                    );
                                  }

                                  if (!mounted) return;
                                  nav.pop();
                                  messenger.showSnackBar(
                                    SnackBar(
                                      backgroundColor: ok ? Colors.green : Colors.red,
                                      content: Text(
                                        ok
                                            ? (isEditing
                                                ? 'Sucursal actualizada exitosamente'
                                                : 'Sucursal creada exitosamente')
                                            : (ref.read(sucursalesAdminProvider).error ??
                                                'Error al procesar la solicitud'),
                                      ),
                                    ),
                                  );
                                },
                          child: isSaving
                              ? const SizedBox(
                                  width: 24,
                                  height: 24,
                                  child: CircularProgressIndicator(
                                    color: Colors.white,
                                    strokeWidth: 2,
                                  ),
                                )
                              : Text(
                                  isEditing ? 'ACTUALIZAR SUCURSAL' : 'CREAR SUCURSAL',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.bold,
                                    fontSize: 15,
                                    letterSpacing: 0.5,
                                  ),
                                ),
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

  void _confirmarToggleActivo(SucursalItem sucursal) {
    final accion = sucursal.activo ? 'suspender' : 'reactivar';
    final advertencia = sucursal.activo
        ? 'Al suspender esta sucursal, dejará de aparecer en el inicio de turnos, compras y traspasos.'
        : 'Al reactivar esta sucursal, estará disponible de inmediato para operaciones de inventario y turnos.';

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: const Color(0xFF1E293B),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Row(
          children: [
            Icon(
              sucursal.activo ? Icons.warning_amber_rounded : Icons.check_circle_outline,
              color: sucursal.activo ? Colors.amber : Colors.greenAccent,
            ),
            const SizedBox(width: 8),
            Text(
              '¿Desea $accion la sucursal?',
              style: const TextStyle(color: Colors.white, fontSize: 16),
            ),
          ],
        ),
        content: Text(
          'Sucursal: "${sucursal.nombre}" (${sucursal.codigo})\n\n$advertencia',
          style: const TextStyle(color: Colors.white70, fontSize: 14),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('CANCELAR', style: TextStyle(color: Colors.white54)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: sucursal.activo ? Colors.redAccent : Colors.green,
              foregroundColor: Colors.white,
            ),
            onPressed: () async {
              Navigator.of(ctx).pop();
              final ok = await ref.read(sucursalesAdminProvider.notifier).toggleActivo(sucursal.id);
              if (mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    backgroundColor: ok ? Colors.teal : Colors.red,
                    content: Text(
                      ok
                          ? 'Sucursal ${sucursal.nombre} ${sucursal.activo ? 'suspendida' : 'reactivada'} correctamente'
                          : 'Error al cambiar estado de la sucursal',
                    ),
                  ),
                );
              }
            },
            child: Text(
              sucursal.activo ? 'SUSPENDER' : 'REACTIVAR',
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(sucursalesAdminProvider);
    final notifier = ref.read(sucursalesAdminProvider.notifier);

    final totalTodas = state.sucursales.length;
    final totalActivas = state.sucursales.where((s) => s.activo).length;
    final totalSuspendidas = state.sucursales.where((s) => !s.activo).length;

    return Scaffold(
      backgroundColor: const Color(0xFF0F172A),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1E293B),
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new, color: Colors.white),
          onPressed: () => Navigator.of(context).pop(),
        ),
        title: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'GESTIÓN DE SUCURSALES',
              style: TextStyle(
                color: Colors.white,
                fontSize: 16,
                fontWeight: FontWeight.bold,
                letterSpacing: 0.5,
              ),
            ),
            Text(
              'Red de puntos de venta y locales',
              style: TextStyle(color: Colors.white70, fontSize: 11),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh, color: Colors.white70),
            onPressed: () => notifier.cargarSucursales(),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: const Color(0xFF0284C7),
        foregroundColor: Colors.white,
        icon: const Icon(Icons.add_business),
        label: const Text(
          'NUEVA SUCURSAL',
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
        onPressed: () => _abrirModalCrearEditar(),
      ),
      body: Column(
        children: [
          // Filtros
          Container(
            color: const Color(0xFF1E293B),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  _buildFiltroChip(
                    label: 'Todas ($totalTodas)',
                    filtro: 'todas',
                    seleccionado: state.filtroActivo == 'todas',
                    onSelected: () => notifier.setFiltro('todas'),
                  ),
                  const SizedBox(width: 8),
                  _buildFiltroChip(
                    label: 'Activas ($totalActivas)',
                    filtro: 'activas',
                    color: Colors.greenAccent,
                    seleccionado: state.filtroActivo == 'activas',
                    onSelected: () => notifier.setFiltro('activas'),
                  ),
                  const SizedBox(width: 8),
                  _buildFiltroChip(
                    label: 'Suspendidas ($totalSuspendidas)',
                    filtro: 'suspendidas',
                    color: Colors.redAccent,
                    seleccionado: state.filtroActivo == 'suspendidas',
                    onSelected: () => notifier.setFiltro('suspendidas'),
                  ),
                ],
              ),
            ),
          ),

          // Lista
          Expanded(
            child: state.isLoading && state.sucursales.isEmpty
                ? const Center(
                    child: CircularProgressIndicator(color: Color(0xFF38BDF8)),
                  )
                : state.sucursalesFiltradas.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(
                              Icons.storefront_outlined,
                              size: 64,
                              color: Colors.white.withOpacity(0.3),
                            ),
                            const SizedBox(height: 16),
                            const Text(
                              'No se encontraron sucursales',
                              style: TextStyle(color: Colors.white54, fontSize: 16),
                            ),
                            const SizedBox(height: 8),
                            TextButton.icon(
                              icon: const Icon(Icons.add, color: Color(0xFF38BDF8)),
                              label: const Text(
                                'Registrar la primera sucursal',
                                style: TextStyle(color: Color(0xFF38BDF8)),
                              ),
                              onPressed: () => _abrirModalCrearEditar(),
                            ),
                          ],
                        ),
                      )
                    : RefreshIndicator(
                        color: const Color(0xFF38BDF8),
                        onRefresh: () => notifier.cargarSucursales(),
                        child: ListView.separated(
                          padding: const EdgeInsets.fromLTRB(16, 16, 16, 80),
                          itemCount: state.sucursalesFiltradas.length,
                          separatorBuilder: (_, __) => const SizedBox(height: 12),
                          itemBuilder: (context, index) {
                            final sucursal = state.sucursalesFiltradas[index];
                            return _buildSucursalCard(sucursal);
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }

  Widget _buildFiltroChip({
    required String label,
    required String filtro,
    Color? color,
    required bool seleccionado,
    required VoidCallback onSelected,
  }) {
    return ChoiceChip(
      label: Text(
        label,
        style: TextStyle(
          color: seleccionado ? Colors.white : Colors.white70,
          fontWeight: seleccionado ? FontWeight.bold : FontWeight.normal,
          fontSize: 12,
        ),
      ),
      selected: seleccionado,
      selectedColor: color != null
          ? color.withOpacity(0.3)
          : const Color(0xFF0284C7).withOpacity(0.4),
      backgroundColor: const Color(0xFF0F172A),
      side: BorderSide(
        color: seleccionado
            ? (color ?? const Color(0xFF38BDF8))
            : const Color(0xFF334155),
      ),
      onSelected: (_) => onSelected(),
    );
  }

  Widget _buildSucursalCard(SucursalItem sucursal) {
    return Container(
      decoration: BoxDecoration(
        color: const Color(0xFF1E293B),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: sucursal.activo
              ? const Color(0xFF334155)
              : Colors.redAccent.withOpacity(0.4),
          width: 1.2,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.2),
            blurRadius: 8,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Fila cabecera
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: sucursal.activo
                        ? const Color(0xFF0284C7).withOpacity(0.2)
                        : Colors.redAccent.withOpacity(0.15),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(
                    Icons.storefront,
                    color: sucursal.activo ? const Color(0xFF38BDF8) : Colors.redAccent,
                    size: 24,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Flexible(
                            child: Text(
                              sucursal.nombre,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 16,
                                fontWeight: FontWeight.bold,
                              ),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                            decoration: BoxDecoration(
                              color: const Color(0xFF0F172A),
                              borderRadius: BorderRadius.circular(6),
                              border: Border.all(color: const Color(0xFF475569)),
                            ),
                            child: Text(
                              sucursal.codigo,
                              style: const TextStyle(
                                color: Color(0xFF94A3B8),
                                fontSize: 11,
                                fontWeight: FontWeight.bold,
                                fontFamily: 'monospace',
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Row(
                        children: [
                          Icon(
                            Icons.location_on_outlined,
                            size: 14,
                            color: Colors.white.withOpacity(0.5),
                          ),
                          const SizedBox(width: 4),
                          Expanded(
                            child: Text(
                              sucursal.direccion != null && sucursal.direccion!.isNotEmpty
                                  ? sucursal.direccion!
                                  : 'Sin dirección registrada',
                              style: TextStyle(
                                color: Colors.white.withOpacity(0.6),
                                fontSize: 12,
                              ),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                // Badge Activa / Suspendida
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: sucursal.activo
                        ? Colors.green.withOpacity(0.15)
                        : Colors.redAccent.withOpacity(0.15),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: sucursal.activo
                          ? Colors.greenAccent.withOpacity(0.6)
                          : Colors.redAccent.withOpacity(0.6),
                    ),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Container(
                        width: 6,
                        height: 6,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: sucursal.activo ? Colors.greenAccent : Colors.redAccent,
                        ),
                      ),
                      const SizedBox(width: 6),
                      Text(
                        sucursal.activo ? 'ACTIVA' : 'SUSPENDIDA',
                        style: TextStyle(
                          color: sucursal.activo ? Colors.greenAccent : Colors.redAccent,
                          fontSize: 10,
                          fontWeight: FontWeight.bold,
                          letterSpacing: 0.5,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),

            const SizedBox(height: 14),
            const Divider(color: Color(0xFF334155), height: 1),
            const SizedBox(height: 12),

            // Métricas y Acciones
            Row(
              children: [
                // Info de personal
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                  decoration: BoxDecoration(
                    color: const Color(0xFF0F172A),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.people_outline, size: 14, color: Colors.white70),
                      const SizedBox(width: 6),
                      Text(
                        '${sucursal.usuariosCount} empleados',
                        style: const TextStyle(color: Colors.white70, fontSize: 11),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                  decoration: BoxDecoration(
                    color: const Color(0xFF0F172A),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.schedule, size: 14, color: Colors.white70),
                      const SizedBox(width: 6),
                      Text(
                        '${sucursal.turnosCount} turnos',
                        style: const TextStyle(color: Colors.white70, fontSize: 11),
                      ),
                    ],
                  ),
                ),

                const Spacer(),

                // Botón Editar
                IconButton(
                  icon: const Icon(Icons.edit_outlined, color: Color(0xFF38BDF8), size: 20),
                  tooltip: 'Editar datos',
                  onPressed: () => _abrirModalCrearEditar(sucursal: sucursal),
                ),

                // Botón Suspender / Reactivar
                IconButton(
                  icon: Icon(
                    sucursal.activo ? Icons.block : Icons.check_circle_outline,
                    color: sucursal.activo ? Colors.redAccent : Colors.greenAccent,
                    size: 20,
                  ),
                  tooltip: sucursal.activo ? 'Suspender sucursal' : 'Reactivar sucursal',
                  onPressed: () => _confirmarToggleActivo(sucursal),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
