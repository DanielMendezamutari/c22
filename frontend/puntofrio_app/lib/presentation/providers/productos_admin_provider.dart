import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/network/api_client.dart';
import 'auth_provider.dart';

class ProductoItem {
  final int id;
  final String nombre;
  final String? codigoBarra;
  final String tipo; // 'insumo', 'terminado', 'ambos'
  final String unidadMedida; // 'unidad', 'fraccion_cuartos'
  final bool esTransformable;
  final bool activo;

  const ProductoItem({
    required this.id,
    required this.nombre,
    this.codigoBarra,
    required this.tipo,
    required this.unidadMedida,
    required this.esTransformable,
    required this.activo,
  });

  factory ProductoItem.fromJson(Map<String, dynamic> json) {
    return ProductoItem(
      id: json['id'] as int,
      nombre: json['nombre'] as String? ?? '',
      codigoBarra: json['codigo_barra'] as String?,
      tipo: json['tipo'] as String? ?? 'insumo',
      unidadMedida: json['unidad_medida'] as String? ?? 'unidad',
      esTransformable: json['es_transformable'] == true || json['es_transformable'] == 1,
      activo: json['activo'] == true || json['activo'] == 1,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'nombre': nombre,
      'codigo_barra': codigoBarra,
      'tipo': tipo,
      'unidad_medida': unidadMedida,
      'es_transformable': esTransformable,
      'activo': activo,
    };
  }

  ProductoItem copyWith({
    int? id,
    String? nombre,
    String? codigoBarra,
    String? tipo,
    String? unidadMedida,
    bool? esTransformable,
    bool? activo,
  }) {
    return ProductoItem(
      id: id ?? this.id,
      nombre: nombre ?? this.nombre,
      codigoBarra: codigoBarra ?? this.codigoBarra,
      tipo: tipo ?? this.tipo,
      unidadMedida: unidadMedida ?? this.unidadMedida,
      esTransformable: esTransformable ?? this.esTransformable,
      activo: activo ?? this.activo,
    );
  }

  String get tipoLabel {
    switch (tipo) {
      case 'insumo':
        return 'Insumo';
      case 'terminado':
        return 'Terminado';
      case 'ambos':
        return 'Insumo / Terminado';
      default:
        return tipo;
    }
  }

  String get unidadMedidaLabel {
    switch (unidadMedida) {
      case 'unidad':
        return 'Unidad';
      case 'fraccion_cuartos':
        return 'Cuartos (1/4)';
      default:
        return unidadMedida;
    }
  }
}

class ProductosAdminState {
  final List<ProductoItem> productos;
  final bool isLoading;
  final bool isSubmitting;
  final String filtroTipo; // 'todos', 'insumo', 'terminado', 'ambos', 'fraccion_cuartos'
  final String busqueda;
  final String? errorMessage;
  final String? successMessage;

  const ProductosAdminState({
    this.productos = const [],
    this.isLoading = false,
    this.isSubmitting = false,
    this.filtroTipo = 'todos',
    this.busqueda = '',
    this.errorMessage,
    this.successMessage,
  });

  List<ProductoItem> get productosFiltrados {
    return productos.where((p) {
      // Filtro por tipo o unidad
      if (filtroTipo == 'fraccion_cuartos') {
        if (p.unidadMedida != 'fraccion_cuartos') return false;
      } else if (filtroTipo != 'todos') {
        if (p.tipo != filtroTipo && p.tipo != 'ambos') return false;
      }

      // Filtro por búsqueda
      if (busqueda.trim().isNotEmpty) {
        final query = busqueda.trim().toLowerCase();
        final matchesName = p.nombre.toLowerCase().contains(query);
        final matchesCode = (p.codigoBarra ?? '').toLowerCase().contains(query);
        if (!matchesName && !matchesCode) return false;
      }

      return true;
    }).toList();
  }

  ProductosAdminState copyWith({
    List<ProductoItem>? productos,
    bool? isLoading,
    bool? isSubmitting,
    String? filtroTipo,
    String? busqueda,
    String? errorMessage,
    String? successMessage,
  }) {
    return ProductosAdminState(
      productos: productos ?? this.productos,
      isLoading: isLoading ?? this.isLoading,
      isSubmitting: isSubmitting ?? this.isSubmitting,
      filtroTipo: filtroTipo ?? this.filtroTipo,
      busqueda: busqueda ?? this.busqueda,
      errorMessage: errorMessage,
      successMessage: successMessage,
    );
  }
}

class ProductosAdminNotifier extends StateNotifier<ProductosAdminState> {
  final ApiClient apiClient;

  ProductosAdminNotifier(this.apiClient) : super(const ProductosAdminState());

  void setFiltroTipo(String tipo) {
    state = state.copyWith(filtroTipo: tipo);
  }

  void setBusqueda(String query) {
    state = state.copyWith(busqueda: query);
  }

  Future<void> cargarProductos() async {
    state = state.copyWith(isLoading: true, errorMessage: null);
    try {
      final res = await apiClient.get('/productos');
      if (res.statusCode == 200 && res.data['success'] == true) {
        final rawList = res.data['data'] as List<dynamic>;
        final items = rawList.map((e) => ProductoItem.fromJson(e as Map<String, dynamic>)).toList();
        state = state.copyWith(
          productos: items,
          isLoading: false,
        );
      } else {
        state = state.copyWith(
          isLoading: false,
          errorMessage: res.data['error'] ?? 'No se pudo cargar el catálogo de productos.',
        );
      }
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        errorMessage: 'Error de red al consultar productos: $e',
      );
    }
  }

  Future<bool> crearProducto({
    required String nombre,
    String? codigoBarra,
    required String tipo,
    required String unidadMedida,
    required bool esTransformable,
    bool activo = true,
  }) async {
    state = state.copyWith(isSubmitting: true, errorMessage: null, successMessage: null);

    try {
      final payload = {
        'nombre': nombre.trim(),
        'codigo_barra': codigoBarra != null && codigoBarra.trim().isNotEmpty ? codigoBarra.trim() : null,
        'tipo': tipo,
        'unidad_medida': unidadMedida,
        'es_transformable': esTransformable,
        'activo': activo,
      };

      final res = await apiClient.post('/productos', data: payload);
      if (res.statusCode == 201 || (res.statusCode == 200 && res.data['success'] == true)) {
        final nuevo = ProductoItem.fromJson(res.data['data'] as Map<String, dynamic>);
        state = state.copyWith(
          productos: [...state.productos, nuevo],
          isSubmitting: false,
          successMessage: 'Producto "${nuevo.nombre}" agregado con éxito.',
        );
        return true;
      } else {
        state = state.copyWith(
          isSubmitting: false,
          errorMessage: res.data['message'] ?? res.data['error'] ?? 'Error al crear producto.',
        );
        return false;
      }
    } catch (e) {
      state = state.copyWith(
        isSubmitting: false,
        errorMessage: 'Error al comunicarse con el servidor: $e',
      );
      return false;
    }
  }

  Future<bool> actualizarProducto(
    int id, {
    required String nombre,
    String? codigoBarra,
    required String tipo,
    required String unidadMedida,
    required bool esTransformable,
    required bool activo,
  }) async {
    state = state.copyWith(isSubmitting: true, errorMessage: null, successMessage: null);

    try {
      final payload = {
        'nombre': nombre.trim(),
        'codigo_barra': codigoBarra != null && codigoBarra.trim().isNotEmpty ? codigoBarra.trim() : null,
        'tipo': tipo,
        'unidad_medida': unidadMedida,
        'es_transformable': esTransformable,
        'activo': activo,
      };

      final res = await apiClient.put('/productos/$id', data: payload);
      if (res.statusCode == 200 && res.data['success'] == true) {
        final updated = ProductoItem.fromJson(res.data['data'] as Map<String, dynamic>);
        final list = state.productos.map((p) => p.id == id ? updated : p).toList();
        state = state.copyWith(
          productos: list,
          isSubmitting: false,
          successMessage: 'Producto "${updated.nombre}" actualizado con éxito.',
        );
        return true;
      } else {
        state = state.copyWith(
          isSubmitting: false,
          errorMessage: res.data['message'] ?? res.data['error'] ?? 'Error al actualizar producto.',
        );
        return false;
      }
    } catch (e) {
      state = state.copyWith(
        isSubmitting: false,
        errorMessage: 'Error al actualizar el producto: $e',
      );
      return false;
    }
  }

  Future<bool> toggleActivo(int id) async {
    try {
      final res = await apiClient.patch('/productos/$id/toggle-activo');
      if (res.statusCode == 200 && res.data['success'] == true) {
        final updated = ProductoItem.fromJson(res.data['data'] as Map<String, dynamic>);
        final list = state.productos.map((p) => p.id == id ? updated : p).toList();
        state = state.copyWith(
          productos: list,
          successMessage: 'Estado de "${updated.nombre}" actualizado a ${updated.activo ? "Activo" : "Inactivo"}.',
        );
        return true;
      }
    } catch (e) {
      state = state.copyWith(errorMessage: 'No se pudo cambiar el estado del producto.');
    }
    return false;
  }
}

final productosAdminProvider = StateNotifierProvider<ProductosAdminNotifier, ProductosAdminState>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return ProductosAdminNotifier(apiClient);
});
