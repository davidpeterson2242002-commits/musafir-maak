import 'package:flutter_test/flutter_test.dart';
import 'package:musafir_maak/core/utils/api_time.dart';
import 'package:musafir_maak/core/utils/formatters.dart';
import 'package:musafir_maak/features/trips/trip_repository.dart';
import 'package:musafir_maak/models/booking.dart';
import 'package:musafir_maak/models/identity.dart';
import 'package:musafir_maak/models/misc.dart';
import 'package:musafir_maak/models/trip.dart';
import 'package:musafir_maak/models/user.dart';

/// A trip as `TripResource` sends it in search results.
Map<String, dynamic> tripJson({bool details = false}) => {
      'id': 5,
      'status': 'scheduled',
      'departs_at': '2026-11-12T07:30:00+02:00',
      'est_arrival_at': null,
      'route': {
        'id': 1,
        'origin': {'id': 1, 'name': 'الخرطوم'},
        'destination': {'id': 2, 'name': 'ود مدني'},
      },
      'price_per_seat': 15000,
      'seats_available': 3,
      'seats_total': 4,
      'women_only': false,
      'accepts_parcels': true,
      'parcel_prices': {'small': 2000, 'medium': 4000, 'large': 7000},
      'driver': {
        'id': 9,
        'name': 'أحمد محمد',
        'photo_url': null,
        'gender': 'male',
        'rating_avg': 4.7,
        'rating_count': 23,
        'trips_count': 41,
        'verified': true,
        'member_since': '2026-01-05',
      },
      'vehicle': {'type': 'car', 'type_label': 'عربية خاصة', 'make': 'تويوتا', 'model': 'كورولا', 'color': 'أبيض'},
      if (details) ...{
        'pickup_points': [
          {'id': 11, 'label': 'موقف شندي — بحري', 'lat': 15.64, 'lng': 32.53},
          {'id': 12, 'label': 'السوق العربي', 'lat': null, 'lng': null},
        ],
        'dropoff_points': [
          {'id': 13, 'label': 'السوق الكبير', 'lat': null, 'lng': null},
        ],
        'luggage': {'large_per_passenger': 1, 'small_per_passenger': 1, 'extra_bag_price': 1500},
        'notes': 'نقيف في الحصاحيصا للفطور',
        'vehicle_photos': [
          {'kind': 'vehicle_side', 'url': 'https://example.test/side.jpg'},
        ],
      },
    };

Map<String, dynamic> bookingJson({String status = 'accepted'}) => {
      'id': 77,
      'status': status,
      'status_label': 'مقبول',
      'seats': 2,
      'extra_bags': 1,
      'includes_family': false,
      'price_total': 30000,
      'pickup_point': {'id': 11, 'label': 'موقف شندي — بحري', 'lat': 15.64, 'lng': 32.53},
      'message': null,
      'created_at': '2026-11-10T14:00:00+02:00',
      'expires_at': status == 'pending' ? '2026-11-11T02:00:00+02:00' : null,
      'accepted_at': null,
      'cancelled_at': null,
      'cancel_reason': null,
      'rejection_reason': null,
      'late_cancellation': false,
      'no_show': null,
      'can_cancel': true,
      'conversation_id': 3,
      'trip': tripJson(details: true),
      'contact': status == 'accepted'
          ? {'driver_phone': '+249912345678', 'driver_whatsapp': '+249912345678', 'plate_number': 'خ 12345'}
          : null,
      'share_text': status == 'accepted' ? 'رحلتي مع مسافر معاك' : null,
    };

void main() {
  group('ApiTime', () {
    test('wall keeps the Sudan clock time whatever the phone timezone', () {
      final t = ApiTime.wall('2026-11-12T07:30:00+02:00')!;
      expect([t.year, t.month, t.day, t.hour, t.minute], [2026, 11, 12, 7, 30]);
      expect(ApiTime.wall(null), isNull);
      expect(ApiTime.wall('garbage'), isNull);
    });

    test('instant is the real moment', () {
      expect(ApiTime.instant('2026-11-12T07:30:00+02:00'), DateTime.utc(2026, 11, 12, 5, 30));
    });

    test('day', () {
      expect(ApiTime.day('2026-11-12'), DateTime(2026, 11, 12));
      expect(ApiTime.day(null), isNull);
    });
  });

  group('Trip.fromJson', () {
    test('search card fields', () {
      final trip = Trip.fromJson(tripJson());

      expect(trip.originName, 'الخرطوم');
      expect(trip.destinationName, 'ود مدني');
      expect(Fmt.time(trip.departsAt), '7:30 ص');
      expect(trip.pricePerSeat, 15000);
      expect(trip.parcelPrices['large'], 7000);
      expect(trip.driver.verified, isTrue);
      expect(trip.driver.tripsCount, 41);
      expect(trip.vehicleName, 'تويوتا كورولا');
      expect(trip.pickupPoints, isEmpty);
      expect(trip.extraBagPrice, isNull);
    });

    test('details', () {
      final trip = Trip.fromJson(tripJson(details: true));

      expect(trip.pickupPoints.map((p) => p.id), [11, 12]);
      expect(trip.pickupPoints.first.hasPin, isTrue);
      expect(trip.pickupPoints.last.hasPin, isFalse);
      expect(trip.luggageLarge, 1);
      expect(trip.extraBagPrice, 1500);
      expect(trip.vehiclePhotos, ['https://example.test/side.jpg']);
    });

    test('trip page envelope', () {
      final details = TripDetails.fromJson({
        'data': tripJson(details: true),
        'bookable': true,
        'parcel_slots_left': 2,
        'my_booking_id': null,
      });

      expect(details.bookable, isTrue);
      expect(details.myBookingId, isNull);
      expect(details.parcelSlotsLeft, 2);
    });
  });

  group('SearchResult.fromJson', () {
    test('trips', () {
      final result = SearchResult.fromJson({
        'data': [tripJson()],
        'route': null,
        'closed': false,
        'closure_message': null,
        'nearby_dates': [],
      });
      expect(result.trips, hasLength(1));
      expect(result.closed, isFalse);
    });

    test('empty day with nearby dates, and a closed route', () {
      final empty = SearchResult.fromJson({
        'data': [],
        'closed': false,
        'nearby_dates': [
          {'date': '2026-11-13', 'trips': 2},
          {'date': '2026-11-14', 'trips': 1},
        ],
      });
      expect(empty.nearbyDates.map((d) => d.trips), [2, 1]);
      expect(empty.nearbyDates.first.date, DateTime(2026, 11, 13));

      final closed = SearchResult.fromJson({'data': [], 'closed': true, 'closure_message': 'الطريق مقفول', 'nearby_dates': []});
      expect(closed.closed, isTrue);
      expect(closed.closureMessage, 'الطريق مقفول');
    });
  });

  group('Booking.fromJson', () {
    test('accepted booking reveals contact and share text', () {
      final booking = Booking.fromJson(bookingJson());

      expect(booking.status, BookingStatus.accepted);
      expect(booking.status.isActive, isTrue);
      expect(booking.contact?.plateNumber, 'خ 12345');
      expect(booking.shareText, isNotNull);
      expect(booking.pickupPoint?.label, 'موقف شندي — بحري');
      expect(booking.trip.notes, isNotNull);
      expect(booking.expiresAt, isNull);
    });

    test('pending booking has a countdown and no contact', () {
      final booking = Booking.fromJson(bookingJson(status: 'pending'));

      expect(booking.contact, isNull);
      expect(booking.expiresAt, DateTime.utc(2026, 11, 11, 0, 0));
    });

    test('alternatives come from the envelope', () {
      final json = bookingJson(status: 'expired');
      final booking = Booking.fromJson(json, envelope: {
        'data': json,
        'alternatives': [tripJson()],
      });

      expect(booking.status.offersAlternatives, isTrue);
      expect(booking.alternatives.single.id, 5);
    });

    test('no-show and unknown statuses', () {
      final noShow = Booking.fromJson({
        ...bookingJson(status: 'no_show'),
        'no_show': {'marked_at': '2026-11-12T08:00:00+02:00', 'disputed_at': null, 'resolution': null, 'can_dispute': true},
      });
      expect(noShow.status, BookingStatus.noShow);
      expect(noShow.noShow?.canDispute, isTrue);
      expect(noShow.noShow?.disputed, isFalse);

      expect(Booking.fromJson(bookingJson(status: 'something_new')).status, BookingStatus.unknown);
    });
  });

  group('Identity.fromJson', () {
    test('tracks which photos are uploaded per document type', () {
      final identity = Identity.fromJson({
        'status': 'none',
        'can_upload': true,
        'allows_booking': false,
        'documents': [
          {'kind': 'selfie', 'label': 'سيلفي', 'url': 'u', 'uploaded_at': 'x'},
          {'kind': 'passport', 'label': 'جواز', 'url': 'u', 'uploaded_at': 'x'},
        ],
        'review': null,
      });

      expect(identity.status, IdStatus.none);
      expect(identity.hasAll(IdDocumentType.passport), isTrue);
      expect(identity.hasAll(IdDocumentType.nationalId), isFalse);
      expect(identity.rejectionReasons, isEmpty);
    });

    test('rejection reasons', () {
      final identity = Identity.fromJson({
        'status': 'rejected',
        'can_upload': true,
        'allows_booking': false,
        'documents': [],
        'review': {'status': 'rejected', 'reasons': ['الصورة غير واضحة'], 'note': 'أعد تصوير البطاقة'},
      });

      expect(identity.rejectionReasons, ['الصورة غير واضحة']);
      expect(identity.reviewNote, 'أعد تصوير البطاقة');
    });
  });

  test('RouteAlert and PendingRating', () {
    final alert = RouteAlert.fromJson({
      'id': 4,
      'origin': {'id': 1, 'name': 'الخرطوم'},
      'destination': {'id': 3, 'name': 'عطبرة'},
      'date': null,
      'active': true,
      'last_notified_at': null,
    });
    expect(alert.destinationName, 'عطبرة');
    expect(alert.date, isNull);

    final pending = PendingRating.fromJson({
      'subject_type': 'booking',
      'subject_id': 77,
      'rate_as': 'passenger',
      'ratee': {'id': 9, 'name': 'أحمد محمد'},
      'summary': 'الخرطوم ← ود مدني — 2026-11-12',
    });
    expect(pending.ratingDriver, isTrue);
    expect(pending.rateeName, 'أحمد محمد');
  });

  group('SearchFilters', () {
    test('default sends only the sort', () {
      expect(const SearchFilters().toQuery(), {'sort': 'earliest'});
      expect(const SearchFilters().activeCount, 0);
    });

    test('active filters', () {
      const filters = SearchFilters(timesOfDay: {'morning'}, vehicleTypes: {'car', 'minibus'}, womenOnly: true, minRating: 4);
      final query = filters.withSort(TripSort.cheapest).toQuery();

      expect(query['time_of_day'], ['morning']);
      expect(query['vehicle_types'], ['car', 'minibus']);
      expect(query['women_only'], 1);
      expect(query['min_rating'], 4.0);
      expect(query['sort'], 'cheapest');
      expect(filters.activeCount, 4);
    });
  });

  test('time and remaining-time wording', () {
    expect(Fmt.time(DateTime.utc(2026, 1, 1, 0, 5)), '12:05 ص');
    expect(Fmt.time(DateTime.utc(2026, 1, 1, 12, 0)), '12:00 م');
    expect(Fmt.time(DateTime.utc(2026, 1, 1, 16, 45)), '4:45 م');
    expect(Fmt.remaining(const Duration(seconds: 20)), 'أقل من دقيقة');
    expect(Fmt.remaining(const Duration(minutes: 45)), '45 دقيقة');
    expect(Fmt.remaining(const Duration(hours: 1)), 'ساعة');
    expect(Fmt.remaining(const Duration(hours: 2, minutes: 5)), 'ساعتين و5 دقائق');
    expect(Fmt.remaining(const Duration(hours: 11, minutes: 30)), '11 ساعة و30 دقيقة');
  });
}
