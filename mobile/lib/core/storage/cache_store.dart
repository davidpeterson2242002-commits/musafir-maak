import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Overridden in `main()` with the real instance (and with a memory one in tests).
final cacheStoreProvider = Provider<CacheStore>((ref) => MemoryCacheStore());

/// One cached API payload and when it was saved.
class CacheEntry {
  const CacheEntry(this.data, this.savedAt);

  final dynamic data;
  final DateTime savedAt;

  bool isOlderThan(Duration age, {DateTime? now}) => (now ?? DateTime.now()).difference(savedAt) > age;
}

/// Small JSON cache so the app opens — and shows the profile, cities and
/// (from M8) accepted bookings — without a connection (spec 12, offline viewing).
abstract class CacheStore {
  CacheEntry? read(String key);

  Future<void> write(String key, Object? data);

  Future<void> remove(String key);

  /// Removes everything saved for the signed-in user (called on logout).
  Future<void> clearUserData();
}

/// Keys. Anything under [user] is wiped on logout; the rest is public data.
class CacheKeys {
  const CacheKeys._();

  static const user = 'user.';
  static const me = '${user}me';
  static const config = 'config';
  static const cities = 'cities';
  static const popularRoutes = 'routes.popular';
  static const onboardingSeen = 'onboarding_seen';
}

class PrefsCacheStore implements CacheStore {
  PrefsCacheStore(this._prefs);

  static const _prefix = 'cache.';

  final SharedPreferences _prefs;

  @override
  CacheEntry? read(String key) {
    final raw = _prefs.getString('$_prefix$key');
    if (raw == null) return null;

    try {
      final decoded = jsonDecode(raw) as Map<String, dynamic>;
      return CacheEntry(
        decoded['d'],
        DateTime.fromMillisecondsSinceEpoch(decoded['t'] as int),
      );
    } catch (_) {
      return null; // unreadable entry: behave as if it was never cached
    }
  }

  @override
  Future<void> write(String key, Object? data) async {
    await _prefs.setString(
      '$_prefix$key',
      jsonEncode({'t': DateTime.now().millisecondsSinceEpoch, 'd': data}),
    );
  }

  @override
  Future<void> remove(String key) => _prefs.remove('$_prefix$key');

  @override
  Future<void> clearUserData() async {
    final keys = _prefs.getKeys().where((k) => k.startsWith('$_prefix${CacheKeys.user}')).toList();
    for (final key in keys) {
      await _prefs.remove(key);
    }
  }
}

class MemoryCacheStore implements CacheStore {
  final Map<String, CacheEntry> _entries = {};

  @override
  CacheEntry? read(String key) => _entries[key];

  @override
  Future<void> write(String key, Object? data) async {
    // Round-trip through JSON so tests see exactly what the real store returns.
    _entries[key] = CacheEntry(jsonDecode(jsonEncode(data)), DateTime.now());
  }

  @override
  Future<void> remove(String key) async => _entries.remove(key);

  @override
  Future<void> clearUserData() async => _entries.removeWhere((key, _) => key.startsWith(CacheKeys.user));
}
