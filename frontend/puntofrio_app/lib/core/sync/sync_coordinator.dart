import 'dart:convert';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
import '../network/api_client.dart';
import '../database/sqlite_helper.dart';

class SyncCoordinator {
  final ApiClient apiClient;
  bool _isSyncing = false;

  SyncCoordinator(this.apiClient) {
    // Escuchar cambios de conectividad
    Connectivity().onConnectivityChanged.listen((List<ConnectivityResult> results) {
      final isOnline = results.any((r) => r != ConnectivityResult.none);
      if (isOnline) {
        procesarCola();
      }
    });
  }

  Future<void> procesarCola() async {
    if (_isSyncing) return;
    _isSyncing = true;

    try {
      final pendientes = await SqliteHelper.obtenerPendientes();

      for (final item in pendientes) {
        final id = item['id'] as int;
        final endpoint = item['endpoint'] as String;
        final method = item['method'] as String;
        final payloadJson = item['payload'] as String;
        final fotoPath = item['foto_local_path'] as String?;

        try {
          dynamic requestData;
          if (fotoPath != null && fotoPath.isNotEmpty) {
            final Map<String, dynamic> dataMap = jsonDecode(payloadJson);
            final formDataMap = Map<String, dynamic>.from(dataMap);
            formDataMap['foto'] = await MultipartFile.fromFile(fotoPath);
            requestData = FormData.fromMap(formDataMap);
          } else {
            requestData = jsonDecode(payloadJson);
          }

          Response response;
          if (method.toUpperCase() == 'POST') {
            response = await apiClient.post(endpoint, data: requestData);
          } else if (method.toUpperCase() == 'PUT') {
            response = await apiClient.put(endpoint, data: requestData);
          } else {
            response = await apiClient.get(endpoint);
          }

          if (response.statusCode == 200 || response.statusCode == 201) {
            await SqliteHelper.marcarSincronizado(id);
          } else {
            await SqliteHelper.registrarFallo(
              id,
              'HTTP ${response.statusCode}: ${response.statusMessage}',
            );
          }
        } catch (e) {
          await SqliteHelper.registrarFallo(id, e.toString());
        }
      }
    } finally {
      _isSyncing = false;
    }
  }
}
