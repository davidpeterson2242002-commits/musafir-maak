/// Public configuration and texts from `GET /config` — everything here is
/// editable in the admin panel, so nothing is hard-coded in the app (spec 1.6).
class AppConfig {
  const AppConfig({
    this.settings = const {},
    this.vehicleTypes = const {},
    this.parcelSizes = const {},
    this.driverRatingTags = const {},
    this.passengerRatingTags = const {},
    this.reportReasons = const {},
  });

  final Map<String, dynamic> settings;
  final Map<String, String> vehicleTypes;
  final Map<String, String> parcelSizes;

  /// Quick tags when rating a driver / a passenger: key → Arabic label, in display order.
  final Map<String, String> driverRatingTags;
  final Map<String, String> passengerRatingTags;

  /// Reasons a user can pick when reporting someone: key → Arabic label.
  final Map<String, String> reportReasons;

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
    final tags = json['rating_tags'];
    return AppConfig(
      driverRatingTags: labels(tags is Map ? tags['for_driver'] : null),
      passengerRatingTags: labels(tags is Map ? tags['for_passenger'] : null),
      reportReasons: labels(json['report_reasons']),
      settings: settings is Map ? Map<String, dynamic>.from(settings) : const {},
      vehicleTypes: labels(json['vehicle_types']),
      parcelSizes: labels(json['parcel_sizes']),
    );
  }
}
