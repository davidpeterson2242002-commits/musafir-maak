import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'app.dart';
import 'core/firebase_config.dart';
import 'core/storage/cache_store.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);

  final prefs = await SharedPreferences.getInstance();

  // Push notifications. If Firebase can't start (no Google Play services) the app
  // still works: it keeps polling for updates while open.
  try {
    await Firebase.initializeApp(options: firebaseOptions);
  } catch (e) {
    debugPrint('Firebase init failed: $e');
  }

  runApp(
    ProviderScope(
      overrides: [cacheStoreProvider.overrideWithValue(PrefsCacheStore(prefs))],
      child: const MusafirApp(),
    ),
  );
}
