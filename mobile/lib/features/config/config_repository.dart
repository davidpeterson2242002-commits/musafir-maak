import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../core/storage/cache_store.dart';
import '../../models/app_config.dart';

/// App configuration: fresh from the server when possible, otherwise the last
/// saved copy, otherwise built-in defaults (the app must always open).
final appConfigProvider = FutureProvider<AppConfig>((ref) async {
  final api = ref.watch(apiClientProvider);
  final cache = ref.watch(cacheStoreProvider);

  try {
    final json = await api.get('config');
    await cache.write(CacheKeys.config, json);
    return AppConfig.fromJson(Map<String, dynamic>.from(json as Map));
  } on ApiException {
    final cached = cache.read(CacheKeys.config);
    if (cached?.data is Map) {
      return AppConfig.fromJson(Map<String, dynamic>.from(cached!.data as Map));
    }
    return const AppConfig();
  }
});
