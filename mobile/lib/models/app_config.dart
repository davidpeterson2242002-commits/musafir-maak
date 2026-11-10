/// Public configuration and texts from `GET /config` — everything here is
/// editable in the admin panel, so nothing is hard-coded in the app (spec 1.6).
class AppConfig {
  const AppConfig({
    this.settings = const {},
    this.vehicleTypes = const {},
    this.parcelSizes = const {},
  });

  final Map<String, dynamic> settings;
  final Map<String, String> vehicleTypes;
  final Map<String, String> parcelSizes;

  String get terms => _text('content.terms');
  String get privacy => _text('content.privacy');
  String get driverTerms => _text('content.driver_terms');
  String get supportWhatsapp => _text('content.support_whatsapp');
  int get otpResendSeconds => (settings['otp.resend_seconds'] as num?)?.toInt() ?? 60;

  List<String> get prohibitedItems {
    final items = settings['content.prohibited_items'];
    return items is List ? items.map((e) => e.toString()).toList() : const [];
  }

  String _text(String key) => settings[key]?.toString() ?? '';

  factory AppConfig.fromJson(Map<String, dynamic> json) {
    Map<String, String> labels(Object? value) =>
        value is Map ? value.map((k, v) => MapEntry(k.toString(), v.toString())) : const {};

    final settings = json['settings'];
    return AppConfig(
      settings: settings is Map ? Map<String, dynamic>.from(settings) : const {},
      vehicleTypes: labels(json['vehicle_types']),
      parcelSizes: labels(json['parcel_sizes']),
    );
  }
}
