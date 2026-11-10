import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/router/app_router.dart';
import 'core/strings.dart';
import 'core/theme/app_theme.dart';

class MusafirApp extends ConsumerWidget {
  const MusafirApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return MaterialApp.router(
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
          child: child ?? const SizedBox.shrink(),
        );
      },
    );
  }
}
