/// Build-time configuration, passed with `--dart-define`.
///
///   flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
///
/// The default is the live server, so any build works on a phone. For a local
/// backend from the Android emulator use http://10.0.2.2:8000/api/v1.
class Env {
  const Env._();

  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://musafir.statement-ds.com/api/v1',
  );

  /// Sudan country code; phone numbers are sent to the API in local form and
  /// normalised by the server.
  static const String countryCode = '+249';
}
