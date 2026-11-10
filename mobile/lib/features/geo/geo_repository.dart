import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../core/storage/cache_store.dart';
import '../../models/city.dart';

final geoRepositoryProvider = Provider<GeoRepository>(
  (ref) => GeoRepository(ref.watch(apiClientProvider), ref.watch(cacheStoreProvider)),
);

/// Enabled cities, in the admin's order (grouped by state in the picker).
final citiesProvider = FutureProvider<List<City>>((ref) => ref.watch(geoRepositoryProvider).cities());

/// Routes the admin marked as popular — the quick chips on the home screen.
final popularRoutesProvider = FutureProvider<List<TravelRoute>>(
  (ref) => ref.watch(geoRepositoryProvider).popularRoutes(),
);

class GeoRepository {
  GeoRepository(this._api, this._cache);

  /// Cities rarely change; a copy younger than this is used without a request,
  /// which saves data on every app start.
  static const freshFor = Duration(hours: 12);

  final ApiClient _api;
  final CacheStore _cache;

  Future<List<City>> cities() async {
    final list = await _cachedList(CacheKeys.cities, () => _api.get('cities'));
    return list.map(City.fromJson).toList();
  }

  Future<List<TravelRoute>> popularRoutes() async {
    final list = await _cachedList(
      CacheKeys.popularRoutes,
      () => _api.get('routes', query: {'popular': 1}),
    );
    return list.map(TravelRoute.fromJson).toList();
  }

  /// Fresh cache → no request. Otherwise ask the server and fall back to any
  /// saved copy when offline.
  Future<List<Map<String, dynamic>>> _cachedList(String key, Future<dynamic> Function() fetch) async {
    final cached = _cache.read(key);
    if (cached != null && cached.data is List && !cached.isOlderThan(freshFor)) {
      return _maps(cached.data);
    }

    try {
      final data = await fetch();
      await _cache.write(key, data);
      return _maps(data);
    } on ApiException {
      if (cached != null && cached.data is List) {
        return _maps(cached.data);
      }
      rethrow;
    }
  }

  List<Map<String, dynamic>> _maps(dynamic data) =>
      (data as List).map((e) => Map<String, dynamic>.from(e as Map)).toList();
}
