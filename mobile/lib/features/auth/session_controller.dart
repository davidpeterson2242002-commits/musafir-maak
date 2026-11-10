import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../core/storage/cache_store.dart';
import '../../core/storage/token_store.dart';
import '../../models/user.dart';
import '../profile/profile_repository.dart';
import '../push/push_service.dart';
import 'auth_repository.dart';

enum SessionStatus {
  /// App just started; the saved token hasn't been read yet (splash).
  unknown,
  signedOut,
  signedIn,
}

class SessionState {
  const SessionState({required this.status, this.user, this.notice});

  const SessionState.unknown() : this(status: SessionStatus.unknown);

  const SessionState.signedOut({String? notice}) : this(status: SessionStatus.signedOut, notice: notice);

  const SessionState.signedIn(User user) : this(status: SessionStatus.signedIn, user: user);

  final SessionStatus status;
  final User? user;

  /// Message to show once on the login screen (e.g. "your session expired").
  final String? notice;

  bool get isSignedIn => status == SessionStatus.signedIn;

  /// Signed in but hasn't entered name / gender / city / terms yet.
  bool get needsProfile => isSignedIn && !(user?.profileCompleted ?? false);
}

final sessionProvider = NotifierProvider<SessionController, SessionState>(SessionController.new);

/// The signed-in user, or null.
final currentUserProvider = Provider<User?>((ref) => ref.watch(sessionProvider).user);

/// Owns the login state: restores it at startup (offline too), keeps the cached
/// profile fresh, and clears everything on logout or when the server rejects the token.
class SessionController extends Notifier<SessionState> {
  @override
  SessionState build() {
    ref.read(apiClientProvider).onUnauthorized = _onUnauthorized;
    return const SessionState.unknown();
  }

  TokenStore get _tokens => ref.read(tokenStoreProvider);
  CacheStore get _cache => ref.read(cacheStoreProvider);

  /// Called once from the splash screen.
  Future<void> restore() async {
    final token = await _tokens.read();
    if (token == null) {
      state = const SessionState.signedOut();
      return;
    }

    // Show the saved profile immediately so the app opens without a connection…
    final cached = _cache.read(CacheKeys.me);
    if (cached?.data is Map) {
      state = SessionState.signedIn(User.fromJson(Map<String, dynamic>.from(cached!.data as Map)));
      refresh(); // …and update it in the background.
      return;
    }

    // Token but no saved profile (first run after an update): we need the server.
    try {
      await refresh(rethrowErrors: true);
    } on ApiException catch (e) {
      if (state.status == SessionStatus.unknown) {
        // Offline with nothing cached: ask to log in again rather than hang on the splash.
        state = SessionState.signedOut(notice: e.message);
      }
    }
  }

  /// Reloads the profile from the server. Silent on failure unless asked.
  Future<void> refresh({bool rethrowErrors = false}) async {
    try {
      await _apply(await ref.read(profileRepositoryProvider).fetchMe());
    } on ApiException {
      if (rethrowErrors) rethrow;
    }
  }

  /// After a correct OTP.
  Future<void> signIn(VerifiedSession session) async {
    await _tokens.write(session.token);
    state = SessionState.signedIn(session.user);
    ref.read(pushServiceProvider).register();
    // The verify response has no city/state objects cached yet; store the full profile.
    refresh();
  }

  /// Replaces the user with a fresh `/me` payload (after profile edits).
  Future<void> updateFromJson(Map<String, dynamic> json) => _apply(json);

  Future<void> signOut() async {
    try {
      await ref.read(pushServiceProvider).unregister();
      await ref.read(authRepositoryProvider).logout();
    } on ApiException {
      // Offline or already invalid: logging out locally is what matters.
    }
    await _clear();
    state = const SessionState.signedOut();
  }

  /// After `DELETE /me` succeeded.
  Future<void> accountDeleted() async {
    await _clear();
    await _cache.remove(CacheKeys.onboardingSeen);
    state = const SessionState.signedOut();
  }

  void _onUnauthorized(ApiException error) {
    if (state.status == SessionStatus.signedOut) return;
    _clear();
    state = SessionState.signedOut(notice: error.message);
  }

  Future<void> _apply(Map<String, dynamic> json) async {
    await _cache.write(CacheKeys.me, json);
    state = SessionState.signedIn(User.fromJson(json));
  }

  Future<void> _clear() async {
    await _tokens.clear();
    await _cache.clearUserData();
  }
}
