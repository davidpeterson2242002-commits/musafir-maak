import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/auth_repository.dart';
import '../../features/auth/login_flow.dart';
import '../../features/auth/onboarding_screen.dart';
import '../../features/auth/profile_setup_screen.dart';
import '../../features/auth/session_controller.dart';
import '../../features/auth/splash_screen.dart';
import '../../features/home/home_shell.dart';
import '../../features/profile/edit_profile_screen.dart';
import '../../features/profile/text_page_screen.dart';
import '../storage/cache_store.dart';

/// Where a location is allowed to be opened from.
///
///   /splash             while the saved session is being restored
///   /welcome, /login…   signed out
///   /setup              signed in, profile not completed yet
///   /, /profile/edit…   signed in with a completed profile
///   /page/…             terms and privacy: readable in any state
final routerProvider = Provider<GoRouter>((ref) {
  final refresh = ValueNotifier<int>(0);
  ref.listen<SessionState>(sessionProvider, (_, __) => refresh.value++);
  ref.onDispose(refresh.dispose);

  return GoRouter(
    initialLocation: '/splash',
    refreshListenable: refresh,
    redirect: (context, state) => redirectFor(
      session: ref.read(sessionProvider),
      location: state.matchedLocation,
      onboardingSeen: ref.read(cacheStoreProvider).read(CacheKeys.onboardingSeen)?.data == true,
    ),
    routes: [
      GoRoute(path: '/splash', builder: (context, state) => const SplashScreen()),
      GoRoute(path: '/welcome', builder: (context, state) => const OnboardingScreen()),
      GoRoute(
        path: '/login',
        builder: (context, state) => const PhoneScreen(),
        routes: [
          GoRoute(
            path: 'code',
            // Opened only from the phone screen; without a ticket go back to it.
            redirect: (context, state) => state.extra is OtpTicket ? null : '/login',
            builder: (context, state) => OtpScreen(ticket: state.extra! as OtpTicket),
          ),
        ],
      ),
      GoRoute(path: '/setup', builder: (context, state) => const ProfileSetupScreen()),
      GoRoute(
        path: '/',
        builder: (context, state) => const HomeShell(),
        routes: [
          GoRoute(path: 'profile/edit', builder: (context, state) => const EditProfileScreen()),
        ],
      ),
      GoRoute(
        path: '/page/:name',
        builder: (context, state) => TextPageScreen(page: state.pathParameters['name'] ?? 'terms'),
      ),
    ],
  );
});

/// The routing rules as a pure function (unit-tested). Returns the location to
/// go to instead, or null to stay.
String? redirectFor({required SessionState session, required String location, required bool onboardingSeen}) {
  final isPublicPage = location.startsWith('/page/');
  final isAuthArea = location == '/welcome' || location.startsWith('/login');

  switch (session.status) {
    case SessionStatus.unknown:
      return location == '/splash' ? null : '/splash';

    case SessionStatus.signedOut:
      if (isAuthArea || isPublicPage) return null;
      return onboardingSeen ? '/login' : '/welcome';

    case SessionStatus.signedIn:
      if (isPublicPage) return null;
      if (session.needsProfile) {
        return location == '/setup' ? null : '/setup';
      }
      if (location == '/splash' || location == '/setup' || isAuthArea) return '/';
      return null;
  }
}
