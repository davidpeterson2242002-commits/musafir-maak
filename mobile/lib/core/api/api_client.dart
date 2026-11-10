import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/env.dart';
import '../storage/token_store.dart';
import 'api_exception.dart';

/// True while the last API call failed for lack of a connection. Drives the
/// offline banner; flips back on the next successful call.
final offlineProvider = StateProvider<bool>((ref) => false);

/// Called when the server answers 401/banned for an authenticated request, so
/// the session can be cleared. Set by the session controller.
typedef UnauthorizedHandler = void Function(ApiException error);

final apiClientProvider = Provider<ApiClient>((ref) {
  final client = ApiClient(
    baseUrl: Env.apiBaseUrl,
    tokenStore: ref.watch(tokenStoreProvider),
    onConnectivity: (offline) {
      if (ref.read(offlineProvider) != offline) {
        ref.read(offlineProvider.notifier).state = offline;
      }
    },
  );
  ref.onDispose(client.close);
  return client;
});

/// Thin wrapper over Dio: adds the token, unwraps Laravel's `{data: …}`
/// envelope and turns every failure into an [ApiException].
class ApiClient {
  ApiClient({
    required String baseUrl,
    required TokenStore tokenStore,
    void Function(bool offline)? onConnectivity,
    Dio? dio,
  })  : _tokenStore = tokenStore,
        _onConnectivity = onConnectivity,
        _dio = dio ??
            Dio(
              BaseOptions(
                baseUrl: baseUrl.endsWith('/') ? baseUrl : '$baseUrl/',
                // Generous timeouts: mobile data between cities is slow.
                connectTimeout: const Duration(seconds: 20),
                sendTimeout: const Duration(seconds: 60),
                receiveTimeout: const Duration(seconds: 30),
                headers: const {'Accept': 'application/json'},
                // Lists in the query string as key[]=a&key[]=b, which is what Laravel reads as an array.
                listFormat: ListFormat.multiCompatible,
              ),
            );

  final Dio _dio;
  final TokenStore _tokenStore;
  final void Function(bool offline)? _onConnectivity;

  UnauthorizedHandler? onUnauthorized;

  void close() => _dio.close();

  Future<dynamic> get(String path, {Map<String, dynamic>? query}) =>
      _send('GET', path, query: query);

  Future<dynamic> post(String path, {Object? body}) => _send('POST', path, body: body);

  Future<dynamic> put(String path, {Object? body}) => _send('PUT', path, body: body);

  Future<dynamic> delete(String path, {Object? body}) => _send('DELETE', path, body: body);

  /// Multipart upload of one file plus optional text fields.
  Future<dynamic> upload(
    String path, {
    required String field,
    required String filePath,
    Map<String, dynamic> fields = const {},
  }) async {
    final form = FormData.fromMap({
      ...fields,
      field: await MultipartFile.fromFile(filePath),
    });
    return _send('POST', path, body: form);
  }

  Future<dynamic> _send(String method, String path, {Object? body, Map<String, dynamic>? query}) async {
    final token = await _tokenStore.read();

    try {
      final response = await _dio.request<dynamic>(
        path.startsWith('/') ? path.substring(1) : path,
        data: body,
        queryParameters: query,
        options: Options(
          method: method,
          headers: token == null ? null : {'Authorization': 'Bearer $token'},
        ),
      );
      _onConnectivity?.call(false);
      return unwrap(response.data);
    } on DioException catch (e) {
      final error = _convert(e);
      if (error.isOffline) {
        _onConnectivity?.call(true);
      } else {
        _onConnectivity?.call(false);
      }

      final sessionDead = error.kind == ApiErrorKind.unauthorized || error.code == 'account_banned';
      if (sessionDead && token != null) {
        onUnauthorized?.call(error);
      }
      throw error;
    }
  }

  /// Laravel API resources wrap their payload in `{data: …}`; plain JSON
  /// responses (auth, config) are returned untouched.
  static dynamic unwrap(dynamic body) {
    if (body is Map && body.length == 1 && body.containsKey('data')) {
      return body['data'];
    }
    return body;
  }

  ApiException _convert(DioException e) {
    switch (e.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        return const ApiException.timeout();
      case DioExceptionType.connectionError:
        return const ApiException.network();
      case DioExceptionType.badResponse:
        return ApiException.fromResponse(e.response?.statusCode, e.response?.data);
      case DioExceptionType.cancel:
      case DioExceptionType.badCertificate:
      case DioExceptionType.unknown:
      default: // newer dio versions add types (e.g. transformTimeout)
        // A socket error with no response (airplane mode on some devices) lands here.
        return e.response == null ? const ApiException.network() : const ApiException.unknown();
    }
  }
}
