import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../providers/productos_admin_provider.dart';

class CatalogoProductosScreen extends ConsumerStatefulWidget {
  const CatalogoProductosScreen({super.key});

  @override
  ConsumerState<CatalogoProductosScreen> createState() => _CatalogoProductosScreenState();
}

class _CatalogoProductosScreenState extends ConsumerState<CatalogoProductosScreen> {
  final TextEditingController _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(productosAdminProvider.notifier).cargarProductos();
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _abrirDialogoProducto({ProductoItem? producto}) {
    final bool esEdicion = producto != null;
    final nombreCtrl = TextEditingController(text: producto?.nombre ?? '');
    final codigoCtrl = TextEditingController(text: producto?.codigoBarra ?? '');
    String tipoSeleccionado = producto?.tipo ?? 'insumo';
    String unidadSeleccionada = producto?.unidadMedida ?? 'unidad';
    bool esTransformable = producto?.esTransformable ?? false;
    bool activo = producto?.activo ?? true;

    final formKey = GlobalKey<FormState>();

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (dialogCtx) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return AlertDialog(
              backgroundColor: const Color(0xFF1A2232),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(16),
                side: const BorderSide(color: Color(0xFF2E3D52)),
              ),
              title: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: esEdicion ? Colors.amber.withOpacity(0.15) : const Color(0xFF00C897).withOpacity(0.15),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Icon(
                      esEdicion ? Icons.edit : Icons.add_box_outlined,
                      color: esEdicion ? Colors.amber : const Color(0xFF00C897),
                      size: 20,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      esEdicion ? 'Editar Producto' : 'Nuevo Producto Maestro',
                      style: const TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.bold),
                    ),
                  ),
                ],
              ),
              content: SizedBox(
                width: MediaQuery.of(context).size.width * 0.9,
                child: SingleChildScrollView(
                  child: Form(
                    key: formKey,
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Nombre del Producto *', style: TextStyle(color: Color(0xFF90A3BF), fontSize: 12, fontWeight: FontWeight.w600)),
                        const SizedBox(height: 6),
                        TextFormField(
                          controller: nombreCtrl,
                          style: const TextStyle(color: Colors.white, fontSize: 14),
                          decoration: InputDecoration(
                            hintText: 'Ej. Cerveza Paceña Lata 355ml',
                            hintStyle: const TextStyle(color: Colors.white24, fontSize: 13),
                            filled: true,
                            fillColor: const Color(0xFF131A26),
                            contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFF2E3D52))),
                            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFF2E3D52))),
                            focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFF00C897))),
                          ),
                          validator: (val) {
                            if (val == null || val.trim().isEmpty) return 'El nombre es obligatorio';
                            return null;
                          },
                        ),
                        const SizedBox(height: 14),
                        const Text('Código de Barras / SKU (Opcional)', style: TextStyle(color: Color(0xFF90A3BF), fontSize: 12, fontWeight: FontWeight.w600)),
                        const SizedBox(height: 6),
                        TextFormField(
                          controller: codigoCtrl,
                          style: const TextStyle(color: Colors.white, fontSize: 14),
                          decoration: InputDecoration(
                            hintText: 'Ej. PAC-LAT-355',
                            hintStyle: const TextStyle(color: Colors.white24, fontSize: 13),
                            prefixIcon: const Icon(Icons.qr_code_2, color: Color(0xFF90A3BF), size: 18),
                            filled: true,
                            fillColor: const Color(0xFF131A26),
                            contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFF2E3D52))),
                            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFF2E3D52))),
                            focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFF00C897))),
                          ),
                        ),
                        const SizedBox(height: 14),
                        const Text('Tipo de Catálogo *', style: TextStyle(color: Color(0xFF90A3BF), fontSize: 12, fontWeight: FontWeight.w600)),
                        const SizedBox(height: 6),
                        DropdownButtonFormField<String>(
                          value: tipoSeleccionado,
                          isExpanded: true,
                          dropdownColor: const Color(0xFF1A2232),
                          style: const TextStyle(color: Colors.white, fontSize: 13),
                          decoration: InputDecoration(
                            filled: true,
                            fillColor: const Color(0xFF131A26),
                            contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFF2E3D52))),
                            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFF2E3D52))),
                          ),
                          items: const [
                            DropdownMenuItem(
                              value: 'insumo',
                              child: Text('Insumo (Materia prima relleno)', overflow: TextOverflow.ellipsis),
                            ),
                            DropdownMenuItem(
                              value: 'terminado',
                              child: Text('Terminado (Venta directa / POS)', overflow: TextOverflow.ellipsis),
                            ),
                            DropdownMenuItem(
                              value: 'ambos',
                              child: Text('Ambos (Insumo y terminado)', overflow: TextOverflow.ellipsis),
                            ),
                          ],
                          onChanged: (val) {
                            if (val != null) setDialogState(() => tipoSeleccionado = val);
                          },
                        ),
                        const SizedBox(height: 14),
                        const Text('Unidad de Medida *', style: TextStyle(color: Color(0xFF90A3BF), fontSize: 12, fontWeight: FontWeight.w600)),
                        const SizedBox(height: 6),
                        DropdownButtonFormField<String>(
                          value: unidadSeleccionada,
                          isExpanded: true,
                          dropdownColor: const Color(0xFF1A2232),
                          style: const TextStyle(color: Colors.white, fontSize: 13),
                          decoration: InputDecoration(
                            filled: true,
                            fillColor: const Color(0xFF131A26),
                            contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFF2E3D52))),
                            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFF2E3D52))),
                          ),
                          items: const [
                            DropdownMenuItem(
                              value: 'unidad',
                              child: Text('Unidad Entera (Latas, botellas)', overflow: TextOverflow.ellipsis),
                            ),
                            DropdownMenuItem(
                              value: 'fraccion_cuartos',
                              child: Text('Fracción en Cuartos (1/4 - Licores)', overflow: TextOverflow.ellipsis),
                            ),
                          ],
                          onChanged: (val) {
                            if (val != null) setDialogState(() => unidadSeleccionada = val);
                          },
                        ),
                        const SizedBox(height: 14),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                          decoration: BoxDecoration(
                            color: const Color(0xFF131A26),
                            borderRadius: BorderRadius.circular(10),
                            border: Border.all(color: const Color(0xFF2E3D52)),
                          ),
                          child: SwitchListTile.adaptive(
                            contentPadding: EdgeInsets.zero,
                            title: const Text('¿Apto para Transformación?', style: TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w500)),
                            subtitle: const Text('Permite usar este producto en recetas de relleno de latas a botellas', style: TextStyle(color: Color(0xFF90A3BF), fontSize: 11)),
                            value: esTransformable,
                            activeColor: const Color(0xFF00C897),
                            onChanged: (v) => setDialogState(() => esTransformable = v),
                          ),
                        ),
                        if (esEdicion) ...[
                          const SizedBox(height: 10),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                            decoration: BoxDecoration(
                              color: const Color(0xFF131A26),
                              borderRadius: BorderRadius.circular(10),
                              border: Border.all(color: const Color(0xFF2E3D52)),
                            ),
                            child: SwitchListTile.adaptive(
                              contentPadding: EdgeInsets.zero,
                              title: const Text('Estado Activo', style: TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w500)),
                              subtitle: Text(
                                activo ? 'Visible para barmen en turnos y operaciones' : 'Oculto para operaciones del personal',
                                style: const TextStyle(color: Color(0xFF90A3BF), fontSize: 11),
                              ),
                              value: activo,
                              activeColor: const Color(0xFF00C897),
                              onChanged: (v) => setDialogState(() => activo = v),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(dialogCtx),
                  child: const Text('Cancelar', style: TextStyle(color: Color(0xFF90A3BF))),
                ),
                ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF00C897),
                    foregroundColor: const Color(0xFF0A121D),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
                  ),
                  onPressed: () async {
                    if (!formKey.currentState!.validate()) return;

                    final notifier = ref.read(productosAdminProvider.notifier);
                    bool ok = false;

                    final messenger = ScaffoldMessenger.of(context);
                    final navigator = Navigator.of(dialogCtx);

                    if (esEdicion) {
                      ok = await notifier.actualizarProducto(
                        producto.id,
                        nombre: nombreCtrl.text,
                        codigoBarra: codigoCtrl.text,
                        tipo: tipoSeleccionado,
                        unidadMedida: unidadSeleccionada,
                        esTransformable: esTransformable,
                        activo: activo,
                      );
                    } else {
                      ok = await notifier.crearProducto(
                        nombre: nombreCtrl.text,
                        codigoBarra: codigoCtrl.text,
                        tipo: tipoSeleccionado,
                        unidadMedida: unidadSeleccionada,
                        esTransformable: esTransformable,
                        activo: activo,
                      );
                    }

                    if (mounted) {
                      navigator.pop();
                      final state = ref.read(productosAdminProvider);
                      messenger.showSnackBar(
                        SnackBar(
                          backgroundColor: ok ? const Color(0xFF1B4D3E) : const Color(0xFF5C1D24),
                          content: Text(
                            ok
                                ? (state.successMessage ?? 'Operación exitosa')
                                : (state.errorMessage ?? 'Ocurrió un error al procesar la solicitud'),
                            style: const TextStyle(color: Colors.white),
                          ),
                        ),
                      );
                    }
                  },
                  child: Text(
                    esEdicion ? 'Actualizar' : 'Guardar Producto',
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
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
    final state = ref.watch(productosAdminProvider);
    final productos = state.productosFiltrados;

    return Scaffold(
      backgroundColor: const Color(0xFF0F141C),
      appBar: AppBar(
        backgroundColor: const Color(0xFF151C28),
        elevation: 0,
        title: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Catálogo de Productos', style: TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.bold)),
            Text('Administración de productos e insumos', style: TextStyle(color: Color(0xFF90A3BF), fontSize: 11)),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh, color: Color(0xFF00C897)),
            tooltip: 'Recargar',
            onPressed: () => ref.read(productosAdminProvider.notifier).cargarProductos(),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: const Color(0xFF00C897),
        foregroundColor: const Color(0xFF0A121D),
        elevation: 4,
        icon: const Icon(Icons.add, size: 20),
        label: const Text('Nuevo Producto', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
        onPressed: () => _abrirDialogoProducto(),
      ),
      body: Column(
        children: [
          // Barra de búsqueda y contador
          Container(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 8),
            color: const Color(0xFF151C28),
            child: Column(
              children: [
                TextField(
                  controller: _searchController,
                  style: const TextStyle(color: Colors.white, fontSize: 14),
                  onChanged: (val) => ref.read(productosAdminProvider.notifier).setBusqueda(val),
                  decoration: InputDecoration(
                    hintText: 'Buscar por nombre o código de barra...',
                    hintStyle: const TextStyle(color: Colors.white30, fontSize: 13),
                    prefixIcon: const Icon(Icons.search, color: Color(0xFF90A3BF), size: 20),
                    suffixIcon: _searchController.text.isNotEmpty
                        ? IconButton(
                            icon: const Icon(Icons.clear, color: Colors.white54, size: 18),
                            onPressed: () {
                              _searchController.clear();
                              ref.read(productosAdminProvider.notifier).setBusqueda('');
                            },
                          )
                        : null,
                    filled: true,
                    fillColor: const Color(0xFF0F141C),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFF253043))),
                    enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFF253043))),
                    focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFF00C897))),
                  ),
                ),
                const SizedBox(height: 10),
                // Chips de filtro horizontal usando Wrap
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: [
                      _buildFilterChip('todos', 'Todos (${state.productos.length})'),
                      const SizedBox(width: 8),
                      _buildFilterChip('insumo', 'Insumos'),
                      const SizedBox(width: 8),
                      _buildFilterChip('terminado', 'Terminados'),
                      const SizedBox(width: 8),
                      _buildFilterChip('fraccion_cuartos', 'Licores (1/4)'),
                    ],
                  ),
                ),
              ],
            ),
          ),

          // Lista de productos
          Expanded(
            child: state.isLoading
                ? const Center(
                    child: CircularProgressIndicator(color: Color(0xFF00C897)),
                  )
                : productos.isEmpty
                    ? Center(
                        child: Padding(
                          padding: const EdgeInsets.all(24.0),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.inventory_2_outlined, size: 56, color: Colors.white.withOpacity(0.2)),
                              const SizedBox(height: 12),
                              const Text(
                                'No se encontraron productos',
                                style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
                              ),
                              const SizedBox(height: 6),
                              Text(
                                state.busqueda.isNotEmpty || state.filtroTipo != 'todos'
                                    ? 'Prueba modificando la búsqueda o el filtro seleccionado.'
                                    : 'Aún no hay productos registrados en el catálogo.',
                                textAlign: TextAlign.center,
                                style: const TextStyle(color: Color(0xFF90A3BF), fontSize: 12),
                              ),
                              const SizedBox(height: 16),
                              if (state.busqueda.isNotEmpty || state.filtroTipo != 'todos')
                                OutlinedButton.icon(
                                  style: OutlinedButton.styleFrom(
                                    foregroundColor: const Color(0xFF00C897),
                                    side: const BorderSide(color: Color(0xFF00C897)),
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                  ),
                                  icon: const Icon(Icons.filter_alt_off, size: 16),
                                  label: const Text('Limpiar Filtros'),
                                  onPressed: () {
                                    _searchController.clear();
                                    ref.read(productosAdminProvider.notifier).setBusqueda('');
                                    ref.read(productosAdminProvider.notifier).setFiltroTipo('todos');
                                  },
                                ),
                            ],
                          ),
                        ),
                      )
                    : ListView.separated(
                        padding: const EdgeInsets.fromLTRB(16, 12, 16, 80),
                        itemCount: productos.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 8),
                        itemBuilder: (ctx, index) {
                          final item = productos[index];
                          return _buildProductoCard(item);
                        },
                      ),
          ),
        ],
      ),
    );
  }

  Widget _buildFilterChip(String key, String label) {
    final currentFilter = ref.watch(productosAdminProvider).filtroTipo;
    final isSelected = currentFilter == key;

    return ChoiceChip(
      label: Text(
        label,
        style: TextStyle(
          fontSize: 12,
          fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
          color: isSelected ? const Color(0xFF0A121D) : const Color(0xFF90A3BF),
        ),
      ),
      selected: isSelected,
      selectedColor: const Color(0xFF00C897),
      backgroundColor: const Color(0xFF131A26),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: BorderSide(
          color: isSelected ? const Color(0xFF00C897) : const Color(0xFF253043),
        ),
      ),
      onSelected: (selected) {
        if (selected) {
          ref.read(productosAdminProvider.notifier).setFiltroTipo(key);
        }
      },
    );
  }

  Widget _buildProductoCard(ProductoItem item) {
    final Color badgeTipoColor = item.tipo == 'insumo'
        ? const Color(0xFF00C897)
        : item.tipo == 'terminado'
            ? const Color(0xFF38BDF8)
            : const Color(0xFFA78BFA);

    return Container(
      decoration: BoxDecoration(
        color: const Color(0xFF17202E),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: item.activo ? const Color(0xFF233044) : const Color(0xFF3C2426),
        ),
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: () => _abrirDialogoProducto(producto: item),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                // Ícono indicador
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: item.activo ? badgeTipoColor.withOpacity(0.12) : Colors.white.withOpacity(0.05),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Icon(
                    item.unidadMedida == 'fraccion_cuartos'
                        ? Icons.wine_bar
                        : item.tipo == 'insumo'
                            ? Icons.local_drink
                            : Icons.sports_bar,
                    color: item.activo ? badgeTipoColor : Colors.white24,
                    size: 22,
                  ),
                ),
                const SizedBox(width: 12),

                // Información central
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              item.nombre,
                              style: TextStyle(
                                color: item.activo ? Colors.white : Colors.white38,
                                fontSize: 14,
                                fontWeight: FontWeight.bold,
                                decoration: item.activo ? null : TextDecoration.lineThrough,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 4),

                      // Badges
                      Wrap(
                        spacing: 6,
                        runSpacing: 4,
                        children: [
                          // Badge Tipo
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: badgeTipoColor.withOpacity(0.15),
                              borderRadius: BorderRadius.circular(4),
                              border: Border.all(color: badgeTipoColor.withOpacity(0.4), width: 0.8),
                            ),
                            child: Text(
                              item.tipoLabel,
                              style: TextStyle(color: badgeTipoColor, fontSize: 10, fontWeight: FontWeight.bold),
                            ),
                          ),

                          // Badge Unidad
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: Colors.amber.withOpacity(0.12),
                              borderRadius: BorderRadius.circular(4),
                              border: Border.all(color: Colors.amber.withOpacity(0.3), width: 0.8),
                            ),
                            child: Text(
                              item.unidadMedidaLabel,
                              style: const TextStyle(color: Colors.amber, fontSize: 10, fontWeight: FontWeight.bold),
                            ),
                          ),

                          // Badge Transformable
                          if (item.esTransformable)
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(
                                color: const Color(0xFF6366F1).withOpacity(0.15),
                                borderRadius: BorderRadius.circular(4),
                                border: Border.all(color: const Color(0xFF6366F1).withOpacity(0.4), width: 0.8),
                              ),
                              child: const Text(
                                'Transformable',
                                style: TextStyle(color: Color(0xFF818CF8), fontSize: 10, fontWeight: FontWeight.bold),
                              ),
                            ),

                          // Badge Código
                          if (item.codigoBarra != null && item.codigoBarra!.isNotEmpty)
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(
                                color: Colors.white.withOpacity(0.06),
                                borderRadius: BorderRadius.circular(4),
                              ),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  const Icon(Icons.qr_code_2, size: 10, color: Color(0xFF90A3BF)),
                                  const SizedBox(width: 3),
                                  Text(
                                    item.codigoBarra!,
                                    style: const TextStyle(color: Color(0xFF90A3BF), fontSize: 10),
                                  ),
                                ],
                              ),
                            ),
                        ],
                      ),
                    ],
                  ),
                ),

                const SizedBox(width: 8),

                // Switch de estado Activo/Inactivo
                Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Switch.adaptive(
                      value: item.activo,
                      activeColor: const Color(0xFF00C897),
                      inactiveTrackColor: const Color(0xFF331518),
                      onChanged: (newVal) async {
                        await ref.read(productosAdminProvider.notifier).toggleActivo(item.id);
                      },
                    ),
                    Text(
                      item.activo ? 'Activo' : 'Inactivo',
                      style: TextStyle(
                        color: item.activo ? const Color(0xFF00C897) : const Color(0xFFEF4444),
                        fontSize: 10,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
