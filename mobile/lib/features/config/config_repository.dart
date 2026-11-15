import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../core/storage/cache_store.dart';
import '../../models/app_config.dart';

/// App configuration: the saved copy right away when there is one (the app opens instantly,
/// even offline), refreshed in the background; the first launch waits for the server; built-in
/// defaults as the last resort (the app must always open).
final appConfigProvider = FutureProvider<AppConfig>((ref) async {
  final api = ref.watch(apiClientProvider);
  final cache = ref.watch(cacheStoreProvider);
  final saved = cache.read(CacheKeys.config);

  AppConfig parse(Object? json) => AppConfig.fromJson(Map<String, dynamic>.from(json as Map));

  if (saved?.data is Map) {
    // Refresh at most once a minute; rebuild only when something changed.
    if (saved!.isOlderThan(const Duration(minutes: 1))) {
      Future(() async {
        try {
          final json = await api.get('config');
          await cache.write(CacheKeys.config, json);
          if (jsonEncode(json) != jsonEncode(saved.data)) ref.invalidateSelf();
        } on ApiException {
          // Keep the saved copy.
        }
      });
    }
    return parse(saved.data);
  }

  try {
    final json = await api.get('config');
    await cache.write(CacheKeys.config, json);
    return parse(json);
  } on ApiException {
    return const AppConfig();
  }
});
