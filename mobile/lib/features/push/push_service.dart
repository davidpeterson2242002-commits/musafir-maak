import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Push notifications. The Firebase implementation is added once the Firebase
/// project exists (it needs google-services.json); until then nothing is
/// registered and the rest of the app doesn't care.
final pushServiceProvider = Provider<PushService>((ref) => const NoPushService());

abstract class PushService {
  /// Asks for notification permission and registers this device with the backend
  /// (`POST /devices`). Called after login.
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
