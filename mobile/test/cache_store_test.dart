import 'package:flutter_test/flutter_test.dart';
import 'package:musafir_maak/core/storage/cache_store.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  late PrefsCacheStore cache;

  setUp(() async {
    SharedPreferences.setMockInitialValues({'cache.broken': 'not json'});
    cache = PrefsCacheStore(await SharedPreferences.getInstance());
  });

  test('writes and reads JSON with a timestamp', () async {
    await cache.write(CacheKeys.cities, [
      {'id': 1, 'name': 'الخرطوم'},
    ]);

    final entry = cache.read(CacheKeys.cities)!;
    expect((entry.data as List).first['name'], 'الخرطوم');
    expect(entry.isOlderThan(const Duration(hours: 1)), isFalse);
    expect(entry.isOlderThan(const Duration(hours: 1), now: DateTime.now().add(const Duration(hours: 2))), isTrue);
  });

  test('missing or unreadable entries read as null', () {
    expect(cache.read('nothing'), isNull);
    expect(cache.read('broken'), isNull);
  });

  test('clearUserData removes only the signed-in user\'s entries', () async {
    await cache.write(CacheKeys.me, {'id': 1});
    await cache.write('${CacheKeys.user}bookings', []);
    await cache.write(CacheKeys.config, {'settings': {}});
    await cache.write(CacheKeys.onboardingSeen, true);

    await cache.clearUserData();

    expect(cache.read(CacheKeys.me), isNull);
    expect(cache.read('${CacheKeys.user}bookings'), isNull);
    expect(cache.read(CacheKeys.config), isNotNull);
    expect(cache.read(CacheKeys.onboardingSeen)?.data, isTrue);
  });
}
