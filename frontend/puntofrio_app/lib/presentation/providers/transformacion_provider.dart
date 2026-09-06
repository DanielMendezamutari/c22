import 'dart:convert';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';
import '../../core/database/sqlite_helper.dart';
import '../../core/network/api_client.dart';
import 'auth_provider.dart';

class TransformacionState {
  final double cantidadInsumo;
  final int cantidadProducida;
  final int cantidadRoturas;
  final double tarifaComision;
  final bool isSubmitting;
  final String? errorMessage;
  final bool submitSuccess;

  const TransformacionState({
    this.cantidadInsumo = 0.0,
    this.cantidadProducida = 0,
    this.cantidadRoturas = 0,
    this.tarifaComision = 1.00,
    this.isSubmitting = false,
    this.errorMessage,
    this.submitSuccess = false,
  });

  int get unidadesNetas => (cantidadProducida - cantidadRoturas).clamp(0, 9999);

  double get comisionDevengada => double.parse((unidadesNetas * tarifaComision).toStringAsFixed(2));

  double get ratioEmpirico =>
      cantidadProducida > 0 ? double.parse((cantidadInsumo / cantidadProducida).toStringAsFixed(3)) : 0.0;

  TransformacionState copyWith({
    double? cantidadInsumo,
    int? cantidadProducida,
    int? cantidadRoturas,
    double? tarifaComision,
    bool? isSubmitting,
    String? errorMessage,
    bool? submitSuccess,
  }) {
    return TransformacionState(
      cantidadInsumo: cantidadInsumo ?? this.cantidadInsumo,
      cantidadProducida: cantidadProducida ?? this.cantidadProducida,
      cantidadRoturas: cantidadRoturas ?? this.cantidadRoturas,
      tarifaComision: tarifaComision ?? this.tarifaComision,
      isSubmitting: isSubmitting ?? this.isSubmitting,
      errorMessage: errorMessage,
      submitSuccess: submitSuccess ?? this.submitSuccess,
    );
  }
}

class TransformacionNotifier extends StateNotifier<TransformacionState> {
  final ApiClient apiClient;

  TransformacionNotifier(this.apiClient) : super(const TransformacionState());

  void setCantidadInsumo(double val) {
    state = state.copyWith(cantidadInsumo: val);
  }

  void setCantidadProducida(int val) {
    state = state.copyWith(cantidadProducida: val);
  }

  void setCantidadRoturas(int val) {
    state = state.copyWith(cantidadRoturas: val);
  }

  void setTarifa(double val) {
    state = state.copyWith(tarifaComision: val);
  }

  Future<bool> registrarRelleno({
    required int turnoId,
    required int recetaId,
    int? insumoOrigenId,
    List<Map<String, dynamic>>? insumosOrigen,
    double? cantidadInsumo,
    int? cantidadProducida,
    int? cantidadRoturas,
    String? observaciones,
  }) async {
    final double insumo = cantidadInsumo ?? state.cantidadInsumo;
    final int producida = cantidadProducida ?? state.cantidadProducida;
    final int roturas = cantidadRoturas ?? state.cantidadRoturas;

    if (insumo <= 0 || producida <= 0) {
      state = state.copyWith(errorMessage: 'Debe ingresar cantidades mayores a 0.');
      return false;
    }

    state = state.copyWith(isSubmitting: true, errorMessage: null);

    final uuidLocal = const Uuid().v4();
    final Map<String, dynamic> payload = {
      'uuid_local': uuidLocal,
      'turno_id': turnoId,
      'receta_id': recetaId,
      'cantidad_insumo': insumo,
      'cantidad_producida': producida,
      'cantidad_roturas': roturas,
      'observaciones': observaciones,
    };

    if (insumoOrigenId != null) {
      payload['insumo_origen_id'] = insumoOrigenId;
    }

    if (insumosOrigen != null && insumosOrigen.isNotEmpty) {
      payload['insumos_origen'] = insumosOrigen;
    }

    try {
      // 1. Encolar en SQLite Local-First
      await SqliteHelper.encolarOperacion(
        uuid: uuidLocal,
        endpoint: '/transformaciones/relleno',
        method: 'POST',
        payload: jsonEncode(payload),
      );

      // 2. Intentar llamada directa a la API
      try {
        final res = await apiClient.post('/transformaciones/relleno', data: payload);
        if (res.statusCode == 201) {
          // Marcar sincronizado en SQLite
          final pendientes = await SqliteHelper.obtenerPendientes();
          final matching = pendientes.where((p) => p['uuid'] == uuidLocal);
          if (matching.isNotEmpty) {
            await SqliteHelper.marcarSincronizado(matching.first['id'] as int);
          }
        }
      } catch (_) {
        // En caso de caída de red, queda en cola SQLite para SyncCoordinator
      }

      state = const TransformacionState(submitSuccess: true);
      return true;
    } catch (e) {
      state = state.copyWith(isSubmitting: false, errorMessage: e.toString());
      return false;
    }
  }
}

final transformacionProvider =
    StateNotifierProvider<TransformacionNotifier, TransformacionState>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return TransformacionNotifier(apiClient);
});
