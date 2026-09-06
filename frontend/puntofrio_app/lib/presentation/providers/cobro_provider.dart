import 'dart:async';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/network/api_client.dart';
import 'auth_provider.dart';

class CobroState {
  final int? turnoId;
  final String barmanNombre;
  final String sucursal;
  final int totalUnidadesNetas;
  final double comisionBrutaBs;
  final double deudaDescontadaBs;
  final double montoNetoPagarBs;
  final bool yaCobrado;
  final String? codigoRecibo;
  final int segundosRestantes;
  final bool isLoading;
  final String? errorMessage;

  const CobroState({
    this.turnoId,
    this.barmanNombre = '',
    this.sucursal = '',
    this.totalUnidadesNetas = 0,
    this.comisionBrutaBs = 0.0,
    this.deudaDescontadaBs = 0.0,
    this.montoNetoPagarBs = 0.0,
    this.yaCobrado = false,
    this.codigoRecibo,
    this.segundosRestantes = 60,
    this.isLoading = false,
    this.errorMessage,
  });

  CobroState copyWith({
    int? turnoId,
    String? barmanNombre,
    String? sucursal,
    int? totalUnidadesNetas,
    double? comisionBrutaBs,
    double? deudaDescontadaBs,
    double? montoNetoPagarBs,
    bool? yaCobrado,
    String? codigoRecibo,
    int? segundosRestantes,
    bool? isLoading,
    String? errorMessage,
  }) {
    return CobroState(
      turnoId: turnoId ?? this.turnoId,
      barmanNombre: barmanNombre ?? this.barmanNombre,
      sucursal: sucursal ?? this.sucursal,
      totalUnidadesNetas: totalUnidadesNetas ?? this.totalUnidadesNetas,
      comisionBrutaBs: comisionBrutaBs ?? this.comisionBrutaBs,
      deudaDescontadaBs: deudaDescontadaBs ?? this.deudaDescontadaBs,
      montoNetoPagarBs: montoNetoPagarBs ?? this.montoNetoPagarBs,
      yaCobrado: yaCobrado ?? this.yaCobrado,
      codigoRecibo: codigoRecibo ?? this.codigoRecibo,
      segundosRestantes: segundosRestantes ?? this.segundosRestantes,
      isLoading: isLoading ?? this.isLoading,
      errorMessage: errorMessage,
    );
  }
}

class CobroNotifier extends StateNotifier<CobroState> {
  final ApiClient apiClient;
  Timer? _countdownTimer;

  CobroNotifier(this.apiClient) : super(const CobroState());

  Future<void> cargarResumen(int turnoId) async {
    state = state.copyWith(isLoading: true, errorMessage: null);

    try {
      final res = await apiClient.get('/turnos/$turnoId/resumen-cajera');
      if (res.statusCode == 200 && res.data['success'] == true) {
        final d = res.data['data'];
        state = CobroState(
          turnoId: d['turno_id'],
          barmanNombre: d['barman_nombre'] ?? '',
          sucursal: d['sucursal'] ?? '',
          totalUnidadesNetas: d['total_unidades_netas'] ?? 0,
          comisionBrutaBs: (d['comision_bruta_bs'] as num?)?.toDouble() ?? 0.0,
          deudaDescontadaBs: (d['deuda_descontada_bs'] as num?)?.toDouble() ?? 0.0,
          montoNetoPagarBs: (d['monto_neto_a_pagar_bs'] as num?)?.toDouble() ?? 0.0,
          yaCobrado: d['ya_cobrado'] ?? false,
          codigoRecibo: d['codigo_recibo'],
          isLoading: false,
        );
      } else {
        state = state.copyWith(
          isLoading: false,
          errorMessage: res.data['error'] ?? 'Error al cargar resumen',
        );
      }
    } catch (e) {
      state = state.copyWith(isLoading: false, errorMessage: 'Error de conexión: $e');
    }
  }

  Future<bool> confirmarCobroRecibido(int turnoId, {String? fotoComprobante}) async {
    state = state.copyWith(isLoading: true, errorMessage: null);

    try {
      final Map<String, dynamic> body = {};
      if (fotoComprobante != null) {
        body['foto_comprobante'] = fotoComprobante;
      }
      final res = await apiClient.post('/turnos/$turnoId/cobro-recibido', data: body);
      if (res.statusCode == 200 && res.data['success'] == true) {
        final d = res.data['data'];
        state = state.copyWith(
          yaCobrado: true,
          codigoRecibo: d['codigo_recibo'],
          segundosRestantes: 60,
          isLoading: false,
        );

        _iniciarTemporizador60Segundos();
        return true;
      } else {
        state = state.copyWith(
          isLoading: false,
          errorMessage: res.data['error'] ?? 'No se pudo confirmar el cobro',
        );
        return false;
      }
    } catch (e) {
      state = state.copyWith(isLoading: false, errorMessage: 'Error de red: $e');
      return false;
    }
  }

  void _iniciarTemporizador60Segundos() {
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

final cobroProvider = StateNotifierProvider<CobroNotifier, CobroState>((ref) {
  final client = ref.watch(apiClientProvider);
  return CobroNotifier(client);
});
