import 'package:dio/dio.dart';
import '../config/api_config.dart';

class ApiClient {
  final ApiConfig config;
  late final Dio dio;

  ApiClient(this.config) {
    dio = Dio(
      BaseOptions(
        baseUrl: config.baseUrl,
        connectTimeout: const Duration(seconds: 10),
        receiveTimeout: const Duration(seconds: 10),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      ),
    );

    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          options.baseUrl = config.baseUrl;
          final token = config.authToken;
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          return handler.next(options);
        },
        onError: (DioException e, handler) {
          // Logueo o tratamiento de errores offline
          return handler.next(e);
        },
      ),
    );
  }

  Future<Response> get(String path, {Map<String, dynamic>? queryParameters}) async {
    return dio.get(path, queryParameters: queryParameters);
  }

  Future<Response> post(String path, {dynamic data}) async {
    return dio.post(path, data: data);
  }

  Future<Response> put(String path, {dynamic data}) async {
    return dio.put(path, data: data);
  }

  Future<Response> patch(String path, {dynamic data}) async {
    return dio.patch(path, data: data);
  }

  Future<Response> delete(String path, {dynamic data}) async {
    return dio.delete(path, data: data);
  }
}
