import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/storage/cache_store.dart';
import '../live/live_updates.dart';

/// Push notifications through Firebase Cloud Messaging. Firebase is initialised in
/// `main()`; when that failed (or in tests) the no-op service is used and the app
/// falls back to polling `me/badges` while open.
final pushServiceProvider = Provider<PushService>((ref) {
  if (Firebase.apps.isEmpty) return const NoPushService();
  return FcmPushService(ref);
});

/// A tapped notification's data, waiting for the router to be ready.
final pushTapProvider = StateProvider<Map<String, dynamic>?>((ref) => null);

/// The last notification that arrived while the app was open (shown as an in-app banner).
final pushForegroundProvider = StateProvider<RemoteNotification?>((ref) => null);

abstract class PushService {
  /// Asks for notification permission and registers this device with the backend
  /// (`POST /devices`). Called after login and on every app start while signed in.
  Future<void> register();

  /// Removes this device's token from the backend (`DELETE /devices`). Called before logout.
  Future<void> unregister();
}

class NoPushService implements PushService {
  const NoPushService();

  @override
  Future<void> register() async {}

  @override
  Future<void> unregister() async {}
}

class FcmPushService implements PushService {
  FcmPushService(this._ref) {
    // Tapped while the app was in the background.
    FirebaseMessaging.onMessageOpenedApp.listen((m) => _ref.read(pushTapProvider.notifier).state = m.data);
    // Arrived while the app is open: Android doesn't show it, so refresh and show a banner.
    FirebaseMessaging.onMessage.listen((m) {
      _ref.read(liveUpdatesProvider.notifier).refresh();
      if (m.notification != null) _ref.read(pushForegroundProvider.notifier).state = m.notification;
    });
    // Tapped while the app was closed.
    FirebaseMessaging.instance.getInitialMessage().then((m) {
      if (m != null) _ref.read(pushTapProvider.notifier).state = m.data;
    });
  }

  static const _cacheKey = 'push.token';
  final Ref _ref;
  StreamSubscription<String>? _refreshSub;

  FirebaseMessaging get _fm => FirebaseMessaging.instance;

  @override
  Future<void> register() async {
    try {
      // Android 13+ shows the system permission dialog once; older versions allow by default.
      final settings = await _fm.requestPermission();
      if (settings.authorizationStatus == AuthorizationStatus.denied) return;
      final token = await _fm.getToken();
      if (token != null) await _send(token);
      _refreshSub ??= _fm.onTokenRefresh.listen(_send);
    } catch (e) {
      // No Google Play services, offline, … — the app still works with polling.
      debugPrint('push register failed: $e');
    }
  }

  Future<void> _send(String token) async {
    await _ref.read(apiClientProvider).post('devices', body: {'token': token, 'platform': 'android'});
    await _ref.read(cacheStoreProvider).write(_cacheKey, {'token': token});
  }

  @override
  Future<void> unregister() async {
    final cache = _ref.read(cacheStoreProvider);
    final token = (cache.read(_cacheKey)?.data as Map?)?['token'] as String?;
    await _refreshSub?.cancel();
    _refreshSub = null;
    try {
      if (token != null) await _ref.read(apiClientProvider).delete('devices', body: {'token': token});
    } finally {
      await cache.remove(_cacheKey);
      // A fresh token for the next account on this phone.
      unawaited(_fm.deleteToken().catchError((_) {}));
    }
  }
}
