/// Build-time configuration, passed with `--dart-define`.
///
///   flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
///
/// The default points at the backend running on the developer's machine as
/// seen from the Android emulator (10.0.2.2 = the host's localhost).
class Env {
  const Env._();

  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1',
  );

  /// Sudan country code; phone numbers are sent to the API in local form and
  /// normalised by the server.
  static const String countryCode = '+249';
}
