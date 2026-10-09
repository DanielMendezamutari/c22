import 'dart:async';
import 'dart:convert';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../core/network/api_client.dart';
import 'auth_provider.dart';

class CorteGarzonState {
  final Map<int, double> conteos;
  final double jornalBaseBs;
  final double costoBotellaBs;
  final double faltanteBotellas;
  final double descuentoFaltanteBs;
  final double totalACobrarBs;
  final String? codigoCobro;
  final int segundosRestantes;
  final bool yaLiquidado;
  final bool isLoading;
  final String? errorMessage;

  const CorteGarzonState({
    this.conteos = const {},
    this.jornalBaseBs = 120.0,
    this.costoBotellaBs = 15.0,
    this.faltanteBotellas = 0.0,
    this.descuentoFaltanteBs = 0.0,
    this.totalACobrarBs = 120.0,
    this.codigoCobro,
    this.segundosRestantes = 60,
    this.yaLiquidado = false,
    this.isLoading = false,
    this.errorMessage,
  });

  CorteGarzonState copyWith({
    Map<int, double>? conteos,
    double? jornalBaseBs,
    double? costoBotellaBs,
    double? faltanteBotellas,
    double? descuentoFaltanteBs,
    double? totalACobrarBs,
    String? codigoCobro,
    int? segundosRestantes,
    bool? yaLiquidado,
    bool? isLoading,
    String? errorMessage,
  }) {
    return CorteGarzonState(
      conteos: conteos ?? this.conteos,
      jornalBaseBs: jornalBaseBs ?? this.jornalBaseBs,
      costoBotellaBs: costoBotellaBs ?? this.costoBotellaBs,
      faltanteBotellas: faltanteBotellas ?? this.faltanteBotellas,
      descuentoFaltanteBs: descuentoFaltanteBs ?? this.descuentoFaltanteBs,
      totalACobrarBs: totalACobrarBs ?? this.totalACobrarBs,
      codigoCobro: codigoCobro ?? this.codigoCobro,
      segundosRestantes: segundosRestantes ?? this.segundosRestantes,
      yaLiquidado: yaLiquidado ?? this.yaLiquidado,
      isLoading: isLoading ?? this.isLoading,
      errorMessage: errorMessage,
    );
  }
}

class CorteGarzonNotifier extends StateNotifier<CorteGarzonState> {
  final ApiClient apiClient;
  Timer? _countdownTimer;

  CorteGarzonNotifier(this.apiClient) : super(const CorteGarzonState());

  /// Carga la memoria persistente en SharedPreferences para garantizar tolerancia a desconexión
  Future<void> cargarPersistencia(int turnoId) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final key = 'corte_garzon_turno_$turnoId';
      final jsonStr = prefs.getString(key);
      if (jsonStr != null) {
        final decoded = json.decode(jsonStr) as Map<String, dynamic>;
        final map = <int, double>{};
        decoded.forEach((k, v) {
          final id = int.tryParse(k);
          if (id != null) {
            map[id] = (v as num).toDouble();
          }
        });
        state = state.copyWith(conteos: map);
      }
    } catch (e) {
      // Ignorar fallo de lectura local
    }
  }

  /// Incremento reactivo ultra-rápido en memoria (<10ms) con persistencia asíncrona sin lag
  void incrementarRapido(int productoId, double delta, int turnoId) {
    final nuevosConteos = Map<int, double>.from(state.conteos);
    final actual = nuevosConteos[productoId] ?? 0.0;
    final nuevoTotal = (actual + delta) < 0 ? 0.0 : (actual + delta);
    nuevosConteos[productoId] = nuevoTotal;

    state = state.copyWith(conteos: nuevosConteos);

    // Auto-persistencia en segundo plano sin bloquear el hilo principal
    _guardarEnStorage(turnoId, nuevosConteos);
  }

  /// Establecer valor exacto directamente
  void setCantidad(int productoId, double cantidad, int turnoId) {
    final nuevosConteos = Map<int, double>.from(state.conteos);
    nuevosConteos[productoId] = cantidad < 0 ? 0.0 : cantidad;

    state = state.copyWith(conteos: nuevosConteos);
    _guardarEnStorage(turnoId, nuevosConteos);
  }

  void _guardarEnStorage(int turnoId, Map<int, double> conteos) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final key = 'corte_garzon_turno_$turnoId';
      final stringKeyMap = conteos.map((k, v) => MapEntry(k.toString(), v));
      await prefs.setString(key, json.encode(stringKeyMap));
    } catch (_) {}
  }

  /// Calcula la liquidación del jornal restando las botellas faltantes
  void calcularLiquidacion({
    double? jornalBase,
    double? faltanteBotellas,
    double? costoUnitario,
  }) {
    final base = jornalBase ?? state.jornalBaseBs;
    final faltante = faltanteBotellas ?? state.faltanteBotellas;
    final costo = costoUnitario ?? state.costoBotellaBs;

    final descuento = faltante * costo;
    final totalCobro = (base - descuento) < 0 ? 0.0 : (base - descuento);

    state = state.copyWith(
      jornalBaseBs: base,
      faltanteBotellas: faltante,
      costoBotellaBs: costo,
      descuentoFaltanteBs: descuento,
      totalACobrarBs: totalCobro,
    );
  }

  /// Registra la liquidación de jornal en el backend
  Future<bool> liquidarJornal({
    required int turnoId,
    required int usuarioId,
    String? fotoUrl,
  }) async {
    state = state.copyWith(isLoading: true, errorMessage: null);

    try {
      final res = await apiClient.post('/turnos/$turnoId/liquidar-garzon', data: {
        'usuario_id': usuarioId,
        'jornal_base_bs': state.jornalBaseBs,
        'faltante_botellas_unidades': state.faltanteBotellas,
        'descuento_faltante_bs': state.descuentoFaltanteBs,
        'costo_unitario_bs': state.costoBotellaBs,
        'total_neto_pagado_bs': state.totalACobrarBs,
        'foto_comprobante_url': fotoUrl,
      });

      if (res.statusCode == 200 && res.data['success'] == true) {
        final codigo = 'JRN-$turnoId-$usuarioId-${DateTime.now().millisecondsSinceEpoch.toString().substring(7)}';

        state = state.copyWith(
          yaLiquidado: true,
          codigoCobro: codigo,
          segundosRestantes: 60,
          isLoading: false,
        );

        _iniciarTemporizador60s();
        return true;
      } else {
        state = state.copyWith(
          isLoading: false,
          errorMessage: res.data['error'] ?? 'Error al liquidar jornal',
        );
        return false;
      }
    } catch (e) {
      state = state.copyWith(isLoading: false, errorMessage: 'Error de conexión: $e');
      return false;
    }
  }

  void _iniciarTemporizador60s() {
    _countdownTimer?.cancel();
    _countdownTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (state.segundosRestantes > 1) {
        state = state.copyWith(segundosRestantes: state.segundosRestantes - 1);
      } else {
        timer.cancel();
        state = state.copyWith(segundosRestantes: 0);
      }
    });
  }

  @override
  void dispose() {
    _countdownTimer?.cancel();
    super.dispose();
  }
}

final corteGarzonProvider = StateNotifierProvider<CorteGarzonNotifier, CorteGarzonState>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return CorteGarzonNotifier(apiClient);
});
