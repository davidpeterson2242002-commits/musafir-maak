import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/api/api_client.dart';
import 'core/router/app_router.dart';
import 'core/strings.dart';
import 'core/theme/app_theme.dart';
import 'core/widgets/busy_overlay.dart';
import 'features/auth/session_controller.dart';
import 'features/push/push_routes.dart';
import 'features/push/push_service.dart';

final _messengerKey = GlobalKey<ScaffoldMessengerState>();

class MusafirApp extends ConsumerWidget {
  const MusafirApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    ref.watch(pushServiceProvider); // starts listening for notification taps

    // A tapped notification opens its screen once the user is signed in.
    void openTapped() {
      final data = ref.read(pushTapProvider);
      final session = ref.read(sessionProvider);
      if (data == null || !session.isSignedIn || session.needsProfile) return;
      ref.read(pushTapProvider.notifier).state = null;
      final route = pushRoute(data);
      final router = ref.read(routerProvider);
      route == '/' ? router.go('/') : router.push(route);
    }

    ref.listen(pushTapProvider, (_, __) => Future.microtask(openTapped));
    ref.listen(sessionProvider, (_, __) => Future.microtask(openTapped));

    // A notification that arrived while the app was open.
    ref.listen<RemoteNotification?>(pushForegroundProvider, (_, next) {
      if (next == null) return;
      final title = next.title;
      final body = next.body;
      _messengerKey.currentState
        ?..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(
          behavior: SnackBarBehavior.floating,
          duration: const Duration(seconds: 5),
          content: Text([if (title != null) title, if (body != null) body].join('\n')),
        ));
      ref.read(pushForegroundProvider.notifier).state = null;
    });

    return MaterialApp.router(
      scaffoldMessengerKey: _messengerKey,
      title: S.appName,
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light(),
      routerConfig: ref.watch(routerProvider),
      // Arabic only for now (spec section 3): the whole app is right-to-left.
      locale: const Locale('ar'),
      supportedLocales: const [Locale('ar')],
      localizationsDelegates: GlobalMaterialLocalizations.delegates,
      builder: (context, child) {
        // Respect the user's font-size setting, but cap it so layouts don't break on small screens.
        final media = MediaQuery.of(context);
        return MediaQuery(
          data: media.copyWith(textScaler: media.textScaler.clamp(minScaleFactor: 0.9, maxScaleFactor: 1.3)),
          child: BusyOverlay(
            busy: ref.watch(apiClientProvider).busy,
            child: child ?? const SizedBox.shrink(),
          ),
        );
      },
    );
  }
}
