import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:musafir_maak/core/api/api_exception.dart';
import 'package:musafir_maak/core/router/app_router.dart';
import 'package:musafir_maak/core/storage/cache_store.dart';
import 'package:musafir_maak/core/storage/token_store.dart';
import 'package:musafir_maak/features/auth/auth_repository.dart';
import 'package:musafir_maak/features/auth/session_controller.dart';
import 'package:musafir_maak/features/profile/profile_repository.dart';
import 'package:musafir_maak/models/user.dart';

Map<String, dynamic> meJson({bool completed = true, String name = 'محمد أحمد'}) => {
      'id': 7,
      'name': completed ? name : null,
      'phone': '+249912345678',
      'status': 'active',
      'id_verification_status': 'none',
      'profile_completed': completed,
      'terms_accepted': completed,
      'is_driver': false,
    };

/// Stands in for the server's `/me` endpoint.
class FakeProfileRepository implements ProfileRepository {
  FakeProfileRepository({this.me, this.error});

  Map<String, dynamic>? me;
  ApiException? error;
  int fetches = 0;

  @override
  Future<Map<String, dynamic>> fetchMe() async {
    fetches++;
    if (error != null) throw error!;
    return me!;
  }

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

class FakeAuthRepository implements AuthRepository {
  int logouts = 0;

  @override
  Future<void> logout() async => logouts++;

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

void main() {
  late MemoryTokenStore tokens;
  late MemoryCacheStore cache;
  late FakeProfileRepository profile;
  late FakeAuthRepository auth;

  ProviderContainer container() {
    final c = ProviderContainer(
      overrides: [
        tokenStoreProvider.overrideWithValue(tokens),
        cacheStoreProvider.overrideWithValue(cache),
        profileRepositoryProvider.overrideWithValue(profile),
        authRepositoryProvider.overrideWithValue(auth),
      ],
    );
    addTearDown(c.dispose);
    return c;
  }

  setUp(() {
    tokens = MemoryTokenStore();
    cache = MemoryCacheStore();
    profile = FakeProfileRepository(me: meJson());
    auth = FakeAuthRepository();
  });

  group('restore at startup', () {
    test('no token → signed out, no request', () async {
      final c = container();
      await c.read(sessionProvider.notifier).restore();

      expect(c.read(sessionProvider).status, SessionStatus.signedOut);
      expect(profile.fetches, 0);
    });

    test('token + cached profile → signed in immediately, even offline', () async {
      await tokens.write('tok');
      await cache.write(CacheKeys.me, meJson(name: 'اسم محفوظ'));
      profile.error = const ApiException.network();

      final c = container();
      await c.read(sessionProvider.notifier).restore();

      final state = c.read(sessionProvider);
      expect(state.isSignedIn, isTrue);
      expect(state.user?.name, 'اسم محفوظ');
      expect(await tokens.read(), 'tok', reason: 'being offline must not log the user out');
    });

    test('token + cached profile → refreshed from the server in the background', () async {
      await tokens.write('tok');
      await cache.write(CacheKeys.me, meJson(name: 'قديم'));
      profile.me = meJson(name: 'جديد');

      final c = container();
      await c.read(sessionProvider.notifier).restore();
      await Future<void>.delayed(Duration.zero);

      expect(c.read(sessionProvider).user?.name, 'جديد');
      expect((cache.read(CacheKeys.me)!.data as Map)['name'], 'جديد');
    });

    test('token without cache → loads the profile from the server', () async {
      await tokens.write('tok');

      final c = container();
      await c.read(sessionProvider.notifier).restore();

      expect(c.read(sessionProvider).isSignedIn, isTrue);
      expect(cache.read(CacheKeys.me), isNotNull);
    });

    test('token without cache while offline → login screen with the reason', () async {
      await tokens.write('tok');
      profile.error = const ApiException.network();

      final c = container();
      await c.read(sessionProvider.notifier).restore();

      final state = c.read(sessionProvider);
      expect(state.status, SessionStatus.signedOut);
      expect(state.notice, isNotNull);
    });
  });

  test('sign in stores the token and marks an empty profile as needing setup', () async {
    profile.me = meJson(completed: false);
    final c = container();

    await c.read(sessionProvider.notifier).signIn(
          VerifiedSession(token: 'new-token', user: User.fromJson(meJson(completed: false)), isNew: true),
        );

    await Future<void>.delayed(Duration.zero); // let the background profile refresh finish

    expect(await tokens.read(), 'new-token');
    expect(c.read(sessionProvider).needsProfile, isTrue);
  });

  test('profile update replaces the user and the cached copy', () async {
    await tokens.write('tok');
    final c = container();
    await c.read(sessionProvider.notifier).restore();

    await c.read(sessionProvider.notifier).updateFromJson(meJson(name: 'اسم جديد'));

    expect(c.read(currentUserProvider)?.name, 'اسم جديد');
    expect((cache.read(CacheKeys.me)!.data as Map)['name'], 'اسم جديد');
  });

  test('sign out clears the token and the user cache but keeps public data', () async {
    await tokens.write('tok');
    await cache.write(CacheKeys.cities, [{'id': 1, 'name': 'الخرطوم'}]);
    final c = container();
    await c.read(sessionProvider.notifier).restore();

    await c.read(sessionProvider.notifier).signOut();

    expect(c.read(sessionProvider).status, SessionStatus.signedOut);
    expect(await tokens.read(), isNull);
    expect(cache.read(CacheKeys.me), isNull);
    expect(cache.read(CacheKeys.cities), isNotNull);
    expect(auth.logouts, 1);
  });

  group('router redirects', () {
    const unknown = SessionState.unknown();
    const signedOut = SessionState.signedOut();
    final incomplete = SessionState.signedIn(User.fromJson(meJson(completed: false)));
    final complete = SessionState.signedIn(User.fromJson(meJson()));

    String? go(SessionState session, String location, {bool seen = true}) =>
        redirectFor(session: session, location: location, onboardingSeen: seen);

    test('splash until the session is known', () {
      expect(go(unknown, '/splash'), isNull);
      expect(go(unknown, '/'), '/splash');
    });

    test('signed out: onboarding the first time, login afterwards', () {
      expect(go(signedOut, '/splash', seen: false), '/welcome');
      expect(go(signedOut, '/splash'), '/login');
      expect(go(signedOut, '/'), '/login');
      expect(go(signedOut, '/login'), isNull);
      expect(go(signedOut, '/login/code'), isNull);
      expect(go(signedOut, '/welcome'), isNull);
    });

    test('signed in without a profile is held on setup', () {
      expect(go(incomplete, '/login/code'), '/setup');
      expect(go(incomplete, '/'), '/setup');
      expect(go(incomplete, '/setup'), isNull);
    });

    test('signed in with a profile goes home and stays out of auth screens', () {
      expect(go(complete, '/splash'), '/');
      expect(go(complete, '/login'), '/');
      expect(go(complete, '/setup'), '/');
      expect(go(complete, '/'), isNull);
      expect(go(complete, '/profile/edit'), isNull);
    });

    test('terms and privacy are readable in every state', () {
      for (final session in [signedOut, incomplete, complete]) {
        expect(go(session, '/page/terms'), isNull);
      }
    });
  });
}
