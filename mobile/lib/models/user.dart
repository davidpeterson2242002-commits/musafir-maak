import 'city.dart';

enum Gender {
  male,
  female;

  static Gender? parse(String? value) => switch (value) {
        'male' => Gender.male,
        'female' => Gender.female,
        _ => null,
      };
}

enum IdStatus {
  none,
  pending,
  verified,
  rejected;

  static IdStatus parse(String? value) => IdStatus.values.firstWhere(
        (s) => s.name == value,
        orElse: () => IdStatus.none,
      );
}

enum AccountStatus {
  active,
  suspended,
  banned;

  static AccountStatus parse(String? value) => AccountStatus.values.firstWhere(
        (s) => s.name == value,
        orElse: () => AccountStatus.active,
      );
}

/// The signed-in user's own profile (`GET /me`).
class User {
  const User({
    required this.id,
    required this.phone,
    this.name,
    this.phoneDisplay,
    this.whatsappPhone,
    this.email,
    this.gender,
    this.city,
    this.photoUrl,
    this.status = AccountStatus.active,
    this.suspendedUntil,
    this.idStatus = IdStatus.none,
    this.ratingAvg = 0,
    this.ratingCount = 0,
    this.profileCompleted = false,
    this.termsAccepted = false,
    this.driverStatus,
    this.isDriver = false,
    this.memberSince,
  });

  final int id;
  final String phone;
  final String? name;
  final String? phoneDisplay;
  final String? whatsappPhone;
  final String? email;
  final Gender? gender;
  final City? city;

  /// Signed URL, valid for a short time — never cache the image by this URL.
  final String? photoUrl;
  final AccountStatus status;
  final DateTime? suspendedUntil;
  final IdStatus idStatus;
  final double ratingAvg;
  final int ratingCount;
  final bool profileCompleted;
  final bool termsAccepted;

  /// null = never started driver registration. Otherwise draft, pending_review,
  /// rejected, active, suspended or frozen.
  final String? driverStatus;

  /// Approved driver who can switch to driver mode.
  final bool isDriver;
  final DateTime? memberSince;

  bool get isSuspended => status == AccountStatus.suspended;

  /// The WhatsApp number only when it differs from the login phone.
  String? get separateWhatsapp => (whatsappPhone != null && whatsappPhone != phone) ? whatsappPhone : null;

  factory User.fromJson(Map<String, dynamic> json) {
    final city = json['city'];
    return User(
      id: json['id'] as int,
      phone: json['phone'] as String,
      name: json['name'] as String?,
      phoneDisplay: json['phone_display'] as String?,
      whatsappPhone: json['whatsapp_phone'] as String?,
      email: json['email'] as String?,
      gender: Gender.parse(json['gender'] as String?),
      city: city is Map ? City.fromJson(Map<String, dynamic>.from(city)) : null,
      photoUrl: json['photo_url'] as String?,
      status: AccountStatus.parse(json['status'] as String?),
      suspendedUntil: DateTime.tryParse(json['suspended_until'] as String? ?? ''),
      idStatus: IdStatus.parse(json['id_verification_status'] as String?),
      ratingAvg: (json['rating_avg'] as num?)?.toDouble() ?? 0,
      ratingCount: (json['rating_count'] as num?)?.toInt() ?? 0,
      profileCompleted: json['profile_completed'] as bool? ?? false,
      termsAccepted: json['terms_accepted'] as bool? ?? false,
      driverStatus: json['driver_status'] as String?,
      isDriver: json['is_driver'] as bool? ?? false,
      memberSince: DateTime.tryParse(json['member_since'] as String? ?? ''),
    );
  }
}
