import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Where the API token lives. Overridden in tests with [MemoryTokenStore].
final tokenStoreProvider = Provider<TokenStore>((ref) => SecureTokenStore());

abstract class TokenStore {
  Future<String?> read();

  Future<void> write(String token);

  Future<void> clear();

  /// "Trusted phone" token per number (+249…): logs back in without a code.
  /// Survives logout on purpose.
  Future<String?> readDevice(String phone);

  Future<void> writeDevice(String phone, String token);

  Future<void> clearDevice(String phone);
}

/// Android Keystore-backed storage; the token is also kept in memory so each
/// request doesn't hit the platform channel.
class SecureTokenStore implements TokenStore {
  SecureTokenStore([FlutterSecureStorage? storage]) : _storage = storage ?? const FlutterSecureStorage();

  static const _key = 'api_token';

  final FlutterSecureStorage _storage;
  String? _cached;
  bool _loaded = false;

  @override
  Future<String?> read() async {
    if (!_loaded) {
      try {
        _cached = await _storage.read(key: _key);
      } catch (_) {
        // Corrupted keystore entry (seen after OS restores): treat as logged out.
        _cached = null;
      }
      _loaded = true;
    }
    return _cached;
  }

  @override
  Future<void> write(String token) async {
    _cached = token;
    _loaded = true;
    await _storage.write(key: _key, value: token);
  }

  @override
  Future<void> clear() async {
    _cached = null;
    _loaded = true;
    try {
      await _storage.delete(key: _key);
    } catch (_) {
      // Nothing useful to do; the in-memory token is already gone.
    }
  }

  static String _deviceKey(String phone) => 'device_token_${phone.replaceAll('+', '')}';

  @override
  Future<String?> readDevice(String phone) async {
    try {
      return await _storage.read(key: _deviceKey(phone));
    } catch (_) {
      return null;
    }
  }

  @override
  Future<void> writeDevice(String phone, String token) => _storage.write(key: _deviceKey(phone), value: token);

  @override
  Future<void> clearDevice(String phone) async {
    try {
      await _storage.delete(key: _deviceKey(phone));
    } catch (_) {
      // Already gone or unreadable: nothing to do.
    }
  }
}

class MemoryTokenStore implements TokenStore {
  MemoryTokenStore([this._token]);

  String? _token;

  @override
  Future<String?> read() async => _token;

  @override
  Future<void> write(String token) async => _token = token;

  @override
  Future<void> clear() async => _token = null;

  final Map<String, String> _devices = {};

  @override
  Future<String?> readDevice(String phone) async => _devices[phone];

  @override
  Future<void> writeDevice(String phone, String token) async => _devices[phone] = token;

  @override
  Future<void> clearDevice(String phone) async => _devices.remove(phone);
}
