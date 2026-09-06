import 'package:shared_preferences/shared_preferences.dart';

class ApiConfig {
  static const String defaultBaseUrl = 'http://10.0.2.2:8000/api/v1';
  static const String keyBaseUrl = 'config_base_url';
  static const String keyAuthToken = 'auth_bearer_token';
  static const String keySucursalActiva = 'sucursal_activa_id';

  final SharedPreferences _prefs;

  ApiConfig(this._prefs);

  String get baseUrl => _prefs.getString(keyBaseUrl) ?? defaultBaseUrl;

  Future<void> setBaseUrl(String url) async {
    await _prefs.setString(keyBaseUrl, url);
  }

  String? get authToken => _prefs.getString(keyAuthToken);

  Future<void> setAuthToken(String? token) async {
    if (token == null) {
      await _prefs.remove(keyAuthToken);
    } else {
      await _prefs.setString(keyAuthToken, token);
    }
  }

  int? get sucursalActivaId => _prefs.getInt(keySucursalActiva);

  Future<void> setSucursalActivaId(int? id) async {
    if (id == null) {
      await _prefs.remove(keySucursalActiva);
    } else {
      await _prefs.setInt(keySucursalActiva, id);
    }
  }

  static const String keyUsuarioActivo = 'usuario_activo_id';

  int? get usuarioActivoId => _prefs.getInt(keyUsuarioActivo);

  Future<void> setUsuarioActivoId(int? id) async {
    if (id == null) {
      await _prefs.remove(keyUsuarioActivo);
    } else {
      await _prefs.setInt(keyUsuarioActivo, id);
    }
  }
}
