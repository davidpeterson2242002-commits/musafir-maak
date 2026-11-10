import 'package:flutter_test/flutter_test.dart';
import 'package:musafir_maak/core/utils/formatters.dart';
import 'package:musafir_maak/models/app_config.dart';
import 'package:musafir_maak/models/city.dart';
import 'package:musafir_maak/models/user.dart';

void main() {
  // Exactly what POST /auth/verify returns for a brand-new account.
  final newUserJson = <String, dynamic>{
    'id': 13,
    'name': null,
    'phone': '+249911223344',
    'phone_display': '+249 91 122 3344',
    'whatsapp_phone': '+249911223344',
    'email': null,
    'gender': null,
    'city': null,
    'photo_url': null,
    'status': 'active',
    'suspended_until': null,
    'id_verification_status': 'none',
    'rating_avg': 0,
    'rating_count': null,
    'profile_completed': false,
    'terms_accepted': false,
    'driver_status': null,
    'is_driver': false,
    'member_since': '2026-11-10',
  };

  group('User.fromJson', () {
    test('new account with empty profile', () {
      final user = User.fromJson(newUserJson);

      expect(user.id, 13);
      expect(user.name, isNull);
      expect(user.gender, isNull);
      expect(user.city, isNull);
      expect(user.ratingCount, 0);
      expect(user.profileCompleted, isFalse);
      expect(user.idStatus, IdStatus.none);
      expect(user.separateWhatsapp, isNull);
      expect(user.memberSince, DateTime(2026, 11, 10));
    });

    test('completed profile', () {
      final user = User.fromJson({
        ...newUserJson,
        'name': 'منى عثمان',
        'gender': 'female',
        'whatsapp_phone': '+249123456789',
        'city': {
          'id': 2,
          'name': 'ود مدني',
          'name_en': 'Wad Madani',
          'state': {'id': 2, 'name': 'الجزيرة', 'name_en': 'Al Jazirah'},
        },
        'status': 'suspended',
        'suspended_until': '2026-11-20T10:00:00+00:00',
        'id_verification_status': 'verified',
        'rating_avg': 4.5,
        'rating_count': 12,
        'profile_completed': true,
        'driver_status': 'pending_review',
      });

      expect(user.gender, Gender.female);
      expect(user.city?.name, 'ود مدني');
      expect(user.city?.stateName, 'الجزيرة');
      expect(user.isSuspended, isTrue);
      expect(user.suspendedUntil, isNotNull);
      expect(user.idStatus, IdStatus.verified);
      expect(user.ratingAvg, 4.5);
      expect(user.separateWhatsapp, '+249123456789');
      expect(user.driverStatus, 'pending_review');
    });

    test('unknown enum values do not crash older app versions', () {
      final user = User.fromJson({...newUserJson, 'status': 'something_new', 'id_verification_status': 'expired'});
      expect(user.status, AccountStatus.active);
      expect(user.idStatus, IdStatus.none);
    });
  });

  test('City survives a cache round-trip', () {
    const city = City(id: 1, name: 'الخرطوم', nameEn: 'Khartoum', stateName: 'الخرطوم');
    final copy = City.fromJson(city.toJson());
    expect(copy, city);
    expect(copy.stateName, 'الخرطوم');
    expect(copy.nameEn, 'Khartoum');
  });

  test('TravelRoute.fromJson', () {
    final route = TravelRoute.fromJson({
      'id': 1,
      'origin': {'id': 1, 'name': 'الخرطوم', 'name_en': 'Khartoum'},
      'destination': {'id': 2, 'name': 'ود مدني', 'name_en': 'Wad Madani'},
      'open': false,
      'closure_message': 'هذا الطريق موقوف مؤقتاً',
      'max_price_per_seat': 25000,
      'is_popular': true,
    });

    expect(route.origin.name, 'الخرطوم');
    expect(route.destination.id, 2);
    expect(route.open, isFalse);
    expect(route.closureMessage, isNotNull);
    expect(route.maxPricePerSeat, 25000);
  });

  group('AppConfig', () {
    test('reads public settings', () {
      final config = AppConfig.fromJson({
        'settings': {
          'otp.resend_seconds': 90,
          'content.prohibited_items': ['أسلحة وذخيرة', 'مخدرات'],
          'content.terms': 'الشروط',
          'content.support_whatsapp': '0912345678',
        },
        'vehicle_types': {'car': 'عربية خاصة', 'boksi': 'بوكسي'},
        'parcel_sizes': {'small': 'صغيرة'},
      });

      expect(config.otpResendSeconds, 90);
      expect(config.prohibitedItems, hasLength(2));
      expect(config.terms, 'الشروط');
      expect(config.privacy, '');
      expect(config.vehicleTypes['boksi'], 'بوكسي');
    });

    test('has safe defaults when nothing could be loaded', () {
      const config = AppConfig();
      expect(config.otpResendSeconds, 60);
      expect(config.prohibitedItems, isEmpty);
      expect(config.supportWhatsapp, '');
    });
  });

  group('Fmt', () {
    test('numbers and money', () {
      expect(Fmt.number(0), '0');
      expect(Fmt.number(999), '999');
      expect(Fmt.number(5000), '5,000');
      expect(Fmt.number(1250000), '1,250,000');
      expect(Fmt.money(25000), '25,000 جنيه');
    });

    test('dates', () {
      final date = DateTime(2026, 11, 12); // a Thursday
      expect(Fmt.dayMonth(date), 'الخميس 12 نوفمبر');
      expect(Fmt.monthYear(date), 'نوفمبر 2026');
      expect(Fmt.isoDate(DateTime(2026, 3, 5)), '2026-03-05');
      expect(Fmt.isSameDay(date, DateTime(2026, 11, 12, 23, 59)), isTrue);
    });

    test('countdown and initials', () {
      expect(Fmt.countdown(75), '1:15');
      expect(Fmt.countdown(9), '0:09');
      expect(Fmt.countdown(-3), '0:00');
      expect(Fmt.initial('محمد أحمد'), 'م');
      expect(Fmt.initial('  '), '؟');
      expect(Fmt.initial(null), '؟');
    });
  });
}
