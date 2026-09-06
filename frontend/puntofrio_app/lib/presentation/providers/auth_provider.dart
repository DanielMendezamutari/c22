import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../core/config/api_config.dart';
import '../../core/network/api_client.dart';

final sharedPreferencesProvider = Provider<SharedPreferences>((ref) {
  throw UnimplementedError('Debe sobreescribirse en main.dart con SharedPreferences.getInstance()');
});

final apiConfigProvider = Provider<ApiConfig>((ref) {
  final prefs = ref.watch(sharedPreferencesProvider);
  return ApiConfig(prefs);
});

final apiClientProvider = Provider<ApiClient>((ref) {
  final config = ref.watch(apiConfigProvider);
  return ApiClient(config);
});

class AuthState {
  final bool isAuthenticated;
  final String? token;
  final int? usuarioId;
  final String? nombre;
  final String? rol; // 'barman', 'admin'
  final int? sucursalId;
  final String? sucursalNombre;
  final int? turnoActivoId;
  final bool isLoading;
  final String? errorMessage;

  const AuthState({
    this.isAuthenticated = false,
    this.token,
    this.usuarioId,
    this.nombre,
    this.rol,
    this.sucursalId,
    this.sucursalNombre,
    this.turnoActivoId,
    this.isLoading = false,
    this.errorMessage,
  });

  bool get esAdmin => rol == 'admin';
  bool get esBarman => rol == 'barman';

  AuthState copyWith({
    bool? isAuthenticated,
    String? token,
    int? usuarioId,
    String? nombre,
    String? rol,
    int? sucursalId,
    String? sucursalNombre,
    int? turnoActivoId,
    bool? isLoading,
    String? errorMessage,
  }) {
    return AuthState(
      isAuthenticated: isAuthenticated ?? this.isAuthenticated,
      token: token ?? this.token,
      usuarioId: usuarioId ?? this.usuarioId,
      nombre: nombre ?? this.nombre,
      rol: rol ?? this.rol,
      sucursalId: sucursalId ?? this.sucursalId,
      sucursalNombre: sucursalNombre ?? this.sucursalNombre,
      turnoActivoId: turnoActivoId ?? this.turnoActivoId,
      isLoading: isLoading ?? this.isLoading,
      errorMessage: errorMessage,
    );
  }
}

class AuthNotifier extends StateNotifier<AuthState> {
  final ApiClient apiClient;
  final ApiConfig apiConfig;

  AuthNotifier(this.apiClient, this.apiConfig) : super(const AuthState());

  Future<bool> loginConPin({
    int? sucursalId,
    required String pin,
    int? usuarioId,
  }) async {
    state = state.copyWith(isLoading: true, errorMessage: null);

    try {
      final Map<String, dynamic> body = {
        'pin': pin,
      };
      if (sucursalId != null && sucursalId > 0) {
        body['sucursal_id'] = sucursalId;
      }
      if (usuarioId != null && usuarioId > 0) {
        body['usuario_id'] = usuarioId;
      }

      final res = await apiClient.post('/auth/login-pin', data: body);

      if (res.statusCode == 200 && res.data['success'] == true) {
        final data = res.data['data'];
        final token = data['token'] as String;
        final usuario = data['usuario'];
        final sucursal = data['sucursal'];
        final turnoActivo = data['turno_activo'];

        await apiConfig.setAuthToken(token);
        if (sucursal != null && sucursal['id'] != null && (sucursal['id'] as int) > 0) {
          await apiConfig.setSucursalActivaId(sucursal['id'] as int);
        }

        state = AuthState(
          isAuthenticated: true,
          token: token,
          usuarioId: usuario['id'] as int,
          nombre: "${usuario['nombre']} ${usuario['apellido']}",
          rol: usuario['rol'] as String,
          sucursalId: (sucursal?['id'] as int?) ?? 0,
          sucursalNombre: (sucursal?['nombre'] as String?) ?? 'Todas las Sucursales (Global)',
          turnoActivoId: turnoActivo != null ? turnoActivo['id'] as int : null,
          isLoading: false,
        );
        return true;
      } else {
        state = state.copyWith(
          isLoading: false,
          errorMessage: res.data['error'] ?? 'PIN no reconocido',
        );
        return false;
      }
    } catch (e) {
      String mensaje = 'Error de conexión';
      if (e is DioException) {
        if (e.response?.data is Map && e.response?.data['error'] != null) {
          mensaje = e.response?.data['error']?.toString() ?? 'PIN incorrecto';
        } else if (e.response?.statusCode == 401) {
          mensaje = 'PIN incorrecto o no reconocido (401)';
        } else {
          mensaje = 'Error ${e.response?.statusCode ?? "de red"}: ${e.message}';
        }
      } else {
        mensaje = e.toString();
      }
      state = state.copyWith(isLoading: false, errorMessage: mensaje);
      return false;
    }
  }

  void actualizarTurnoActivo(int? turnoId) {
    state = state.copyWith(turnoActivoId: turnoId);
  }

  Future<void> logout() async {
    await apiConfig.setAuthToken(null);
    state = const AuthState();
  }
}

final authProvider = StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  final client = ref.watch(apiClientProvider);
  final config = ref.watch(apiConfigProvider);
  return AuthNotifier(client, config);
});
