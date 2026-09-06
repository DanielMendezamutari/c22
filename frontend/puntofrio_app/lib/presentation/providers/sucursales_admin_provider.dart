import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/network/api_client.dart';
import 'auth_provider.dart';

class SucursalItem {
  final int id;
  final String nombre;
  final String codigo;
  final String? direccion;
  final bool activo;
  final int usuariosCount;
  final int turnosCount;

  const SucursalItem({
    required this.id,
    required this.nombre,
    required this.codigo,
    this.direccion,
    required this.activo,
    this.usuariosCount = 0,
    this.turnosCount = 0,
  });

  factory SucursalItem.fromJson(Map<String, dynamic> json) {
    return SucursalItem(
      id: json['id'] as int,
      nombre: json['nombre'] as String? ?? '',
      codigo: json['codigo'] as String? ?? '',
      direccion: json['direccion'] as String?,
      activo: json['activo'] == true || json['activo'] == 1,
      usuariosCount: json['usuarios_count'] as int? ?? 0,
      turnosCount: json['turnos_count'] as int? ?? 0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'nombre': nombre,
      'codigo': codigo,
      'direccion': direccion,
      'activo': activo,
    };
  }

  SucursalItem copyWith({
    int? id,
    String? nombre,
    String? codigo,
    String? direccion,
    bool? activo,
    int? usuariosCount,
    int? turnosCount,
  }) {
    return SucursalItem(
      id: id ?? this.id,
      nombre: nombre ?? this.nombre,
      codigo: codigo ?? this.codigo,
      direccion: direccion ?? this.direccion,
      activo: activo ?? this.activo,
      usuariosCount: usuariosCount ?? this.usuariosCount,
      turnosCount: turnosCount ?? this.turnosCount,
    );
  }
}

class SucursalesAdminState {
  final List<SucursalItem> sucursales;
  final bool isLoading;
  final String? error;
  final String? filtroActivo; // 'todas', 'activas', 'suspendidas'

  const SucursalesAdminState({
    this.sucursales = const [],
    this.isLoading = false,
    this.error,
    this.filtroActivo = 'todas',
  });

  List<SucursalItem> get sucursalesFiltradas {
    if (filtroActivo == 'activas') {
      return sucursales.where((s) => s.activo).toList();
    }
    if (filtroActivo == 'suspendidas') {
      return sucursales.where((s) => !s.activo).toList();
    }
    return sucursales;
  }

  SucursalesAdminState copyWith({
    List<SucursalItem>? sucursales,
    bool? isLoading,
    String? error,
    String? filtroActivo,
  }) {
    return SucursalesAdminState(
      sucursales: sucursales ?? this.sucursales,
      isLoading: isLoading ?? this.isLoading,
      error: error,
      filtroActivo: filtroActivo ?? this.filtroActivo,
    );
  }
}

class SucursalesAdminNotifier extends StateNotifier<SucursalesAdminState> {
  final ApiClient _apiClient;

  SucursalesAdminNotifier(this._apiClient) : super(const SucursalesAdminState()) {
    cargarSucursales();
  }

  void setFiltro(String filtro) {
    state = state.copyWith(filtroActivo: filtro);
  }

  Future<void> cargarSucursales() async {
    state = state.copyWith(isLoading: true, error: null);
    try {
      final response = await _apiClient.get('/sucursales');
      final data = response.data;
      if (data != null && data['success'] == true && data['data'] is List) {
        final list = (data['data'] as List)
            .map((item) => SucursalItem.fromJson(item as Map<String, dynamic>))
            .toList();
        state = state.copyWith(sucursales: list, isLoading: false);
      } else {
        state = state.copyWith(
          isLoading: false,
          error: 'No se pudo cargar la lista de sucursales',
        );
      }
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: 'Error de conexión: $e',
      );
    }
  }

  Future<bool> crearSucursal({
    required String nombre,
    required String codigo,
    String? direccion,
  }) async {
    state = state.copyWith(isLoading: true, error: null);
    try {
      final response = await _apiClient.post('/sucursales', data: {
        'nombre': nombre.trim(),
        'codigo': codigo.trim().toUpperCase(),
        if (direccion != null && direccion.trim().isNotEmpty)
          'direccion': direccion.trim(),
        'activo': true,
      });

      if (response.data != null && response.data['success'] == true) {
        await cargarSucursales();
        return true;
      } else {
        state = state.copyWith(
          isLoading: false,
          error: response.data['message'] ?? 'Error al crear la sucursal',
        );
        return false;
      }
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: 'Error al crear sucursal: $e',
      );
      return false;
    }
  }

  Future<bool> actualizarSucursal({
    required int id,
    required String nombre,
    required String codigo,
    String? direccion,
  }) async {
    state = state.copyWith(isLoading: true, error: null);
    try {
      final response = await _apiClient.put('/sucursales/$id', data: {
        'nombre': nombre.trim(),
        'codigo': codigo.trim().toUpperCase(),
        'direccion': direccion?.trim(),
      });

      if (response.data != null && response.data['success'] == true) {
        await cargarSucursales();
        return true;
      } else {
        state = state.copyWith(
          isLoading: false,
          error: response.data['message'] ?? 'Error al actualizar la sucursal',
        );
        return false;
      }
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: 'Error al actualizar sucursal: $e',
      );
      return false;
    }
  }

  Future<bool> toggleActivo(int id) async {
    try {
      final response = await _apiClient.patch('/sucursales/$id/toggle-activo');
      if (response.data != null && response.data['success'] == true) {
        await cargarSucursales();
        return true;
      }
      return false;
    } catch (e) {
      state = state.copyWith(error: 'Error al cambiar estado: $e');
      return false;
    }
  }
}

final sucursalesAdminProvider =
    StateNotifierProvider<SucursalesAdminNotifier, SucursalesAdminState>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return SucursalesAdminNotifier(apiClient);
});
