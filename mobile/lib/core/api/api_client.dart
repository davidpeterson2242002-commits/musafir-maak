import 'dart:async';
import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/env.dart';
import '../storage/cache_store.dart';
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
    cache: ref.watch(cacheStoreProvider),
    onConnectivity: (offline) {
      if (ref.read(offlineProvider) != offline) {
        ref.read(offlineProvider.notifier).state = offline;
      }
    },
  );
  ref.onDispose(() {
    client.close();
    client.busy.dispose();
    client.disposeStreams();
  });
  return client;
});

/// Thin wrapper over Dio: adds the token, unwraps Laravel's `{data: …}`
/// envelope and turns every failure into an [ApiException].
class ApiClient {
  ApiClient({
    required String baseUrl,
    required TokenStore tokenStore,
    CacheStore? cache,
    void Function(bool offline)? onConnectivity,
    Dio? dio,
  })  : _tokenStore = tokenStore,
        _cache = cache,
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
  final CacheStore? _cache;
  final void Function(bool offline)? _onConnectivity;

  UnauthorizedHandler? onUnauthorized;

  /// Number of user actions (POST/PUT/DELETE/uploads) in flight. The app shows a
  /// loading overlay while it is above zero, so an action is never silent.
  /// Background calls pass `silent: true` (push token, chat outbox, photo tiles
  /// that show their own spinner).
  final ValueNotifier<int> busy = ValueNotifier<int>(0);

  void close() => _dio.close();

  // ---- Instant screens: saved answer first, fresh one in the background -------------------

  /// Paths (without query) whose saved answer was just replaced by a different fresh one.
  /// Providers listen through [FreshDataRef.refreshOnFresh] and rebuild.
  Stream<String> get freshData => _fresh.stream;
  final _fresh = StreamController<String>.broadcast();
  DateTime _lastAction = DateTime.fromMillisecondsSinceEpoch(0);

  void disposeStreams() => _fresh.close();

  /// GET that answers from the phone's copy right away when there is one, then refreshes it in
  /// the background (and tells [freshData] if it changed). Right after a user action the network
  /// answer is awaited instead, so the screen never shows the state from before the action.
  Future<dynamic> getCached(String path, {Map<String, dynamic>? query}) async {
    final cache = _cache;
    if (cache == null) return get(path, query: query);

    final key = '${CacheKeys.user}http.$path${query == null || query.isEmpty ? '' : '?${Uri(queryParameters: query.map((k, v) => MapEntry(k, '$v'))).query}'}';
    final saved = cache.read(key);
    final justActed = DateTime.now().difference(_lastAction) < const Duration(seconds: 15);

    if (saved == null || justActed) {
      try {
        final fresh = await get(path, query: query);
        unawaited(cache.write(key, fresh));
        return fresh;
      } on ApiException catch (e) {
        if (saved != null && e.isOffline) return saved.data;
        rethrow;
      }
    }

    // Copies younger than a few seconds are what a refresh just wrote: no new request.
    if (saved.isOlderThan(const Duration(seconds: 5))) {
      unawaited(() async {
        try {
          final fresh = await get(path, query: query);
          final changed = jsonEncode(fresh) != jsonEncode(saved.data);
          await cache.write(key, fresh);
          if (changed && !_fresh.isClosed) _fresh.add(path);
        } catch (_) {
          // Offline or a server hiccup: the saved copy stays on screen.
        }
      }());
    }
    return saved.data;
  }

  Future<dynamic> get(String path, {Map<String, dynamic>? query}) =>
      _send('GET', path, query: query, silent: true);

  Future<dynamic> post(String path, {Object? body, bool silent = false}) =>
      _send('POST', path, body: body, silent: silent);

  Future<dynamic> put(String path, {Object? body, bool silent = false}) =>
      _send('PUT', path, body: body, silent: silent);

  Future<dynamic> delete(String path, {Object? body, bool silent = false}) =>
      _send('DELETE', path, body: body, silent: silent);

  /// Multipart upload of one file plus optional text fields.
  Future<dynamic> upload(
    String path, {
    required String field,
    required String filePath,
    Map<String, dynamic> fields = const {},
    bool silent = false,
  }) async {
    final form = FormData.fromMap({
      ...fields,
      field: await MultipartFile.fromFile(filePath),
    });
    return _send('POST', path, body: form, silent: silent);
  }

  Future<dynamic> _send(String method, String path, {Object? body, Map<String, dynamic>? query, required bool silent}) async {
    if (!silent) busy.value++;
    try {
      final result = await _request(method, path, body: body, query: query);
      if (method != 'GET') _lastAction = DateTime.now();
      return result;
    } finally {
      if (!silent) busy.value--;
    }
  }

  Future<dynamic> _request(String method, String path, {Object? body, Map<String, dynamic>? query}) async {
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

/// Reads a Laravel paginated list (`{data, links, meta}`) page by page, up to [maxPages]
/// (20 per page), so long histories aren't cut at the first 20.
Future<List<dynamic>> fetchAllPages(
  ApiClient api,
  String path,
  Map<String, dynamic> query, {
  int maxPages = 5,
  bool cached = false,
}) async {
  final items = <dynamic>[];
  for (var page = 1; page <= maxPages; page++) {
    final pageQuery = {...query, 'page': page};
    final json = cached ? await api.getCached(path, query: pageQuery) : await api.get(path, query: pageQuery);
    if (json is! Map) {
      items.addAll(json as List);
      break;
    }
    items.addAll(json['data'] as List? ?? const []);
    final meta = json['meta'];
    final last = meta is Map ? (meta['last_page'] as num?)?.toInt() ?? 1 : 1;
    if (page >= last) break;
  }
  return items;
}

extension FreshDataRef on Ref {
  /// Rebuilds this provider when a background refresh brought a different answer for [path]
  /// (see [ApiClient.getCached]): the screen first shows the saved copy, then the fresh one.
  void refreshOnFresh(String path) {
    final sub = read(apiClientProvider).freshData.where((p) => p == path).listen((_) => invalidateSelf());
    onDispose(sub.cancel);
  }
}

