import 'package:flutter_test/flutter_test.dart';
import 'package:musafir_maak/models/booking.dart';
import 'package:musafir_maak/models/driver_trip.dart';

Map<String, dynamic> driverBookingJson({String status = 'pending'}) => {
      'id': 77,
      'trip_id': 5,
      'status': status,
      'status_label': 'بانتظار ردك',
      'passenger': {'id': 4, 'name': 'منى عثمان', 'rating_avg': 4.8, 'rating_count': 6, 'verified': true},
      'passenger_id_status': 'verified',
      'seats': 2,
      'extra_bags': 1,
      'includes_family': false,
      'pickup_point': {'id': 11, 'label': 'موقف شندي — بحري', 'lat': null, 'lng': null},
      'message': 'معاي شنطة كبيرة',
      'price_total': 31500,
      'created_at': '2026-11-10T14:00:00+02:00',
      'expires_at': status == 'pending' ? '2026-11-11T02:00:00+02:00' : null,
      'phone': status == 'accepted' ? '+249123456789' : null,
      'whatsapp': status == 'accepted' ? '+249123456789' : null,
      'fee': status == 'pending'
          ? {'amount': 1500, 'free': false, 'free_bookings_remaining': 0}
          : {'amount': 1500, 'free': false, 'reversed': false},
      'conversation_id': 3,
      'can_mark_no_show': false,
      'trip': {'id': 5, 'departs_at': '2026-11-12T07:30:00+02:00', 'route': 'الخرطوم ← ود مدني', 'seats_available': 3},
    };

Map<String, dynamic> driverTripJson() => {
      'id': 5,
      'status': 'scheduled',
      'status_label': 'مجدولة',
      'departs_at': '2026-11-12T07:30:00+02:00',
      'est_arrival_at': null,
      'route': {
        'origin': {'id': 1, 'name': 'الخرطوم'},
        'destination': {'id': 2, 'name': 'ود مدني'},
      },
      'vehicle': {'id': 3, 'label': 'تويوتا هايس — خ 12345'},
      'price_per_seat': 15000,
      'seats_total': 14,
      'seats_booked': 4,
      'seats_available': 10,
      'pending_requests': 1,
      'luggage': {'large_per_passenger': 1, 'small_per_passenger': 1, 'extra_bag_price': 1500},
      'accepts_parcels': true,
      'parcel_prices': {'small': 2000, 'medium': 4000, 'large': 7000},
      'max_parcels': 5,
      'women_only': false,
      'notes': null,
      'cancel_reason': null,
      'hidden_by_admin': false,
      'pickup_points': [
        {'id': 11, 'label': 'موقف شندي — بحري', 'lat': null, 'lng': null},
      ],
      'dropoff_points': [
        {'id': 13, 'label': 'السوق الكبير', 'lat': null, 'lng': null},
      ],
      'actions': {'can_edit_all': false, 'can_edit_limited': true, 'can_depart': false, 'can_complete': false, 'can_cancel': true},
      'parcels': [],
      'parcel_slots_left': 5,
      'bookings': [driverBookingJson(), driverBookingJson(status: 'accepted')],
    };

void main() {
  group('DriverBooking.fromJson', () {
    test('pending request shows the fee preview and hides the phone', () {
      final booking = DriverBooking.fromJson(driverBookingJson());

      expect(booking.status, BookingStatus.pending);
      expect(booking.passenger.name, 'منى عثمان');
      expect(booking.passengerVerified, isTrue);
      expect(booking.pickupLabel, 'موقف شندي — بحري');
      expect(booking.feeAmount, 1500);
      expect(booking.feeFree, isFalse);
      expect(booking.phone, isNull);
      expect(booking.tripRoute, 'الخرطوم ← ود مدني');
      expect(booking.tripDepartsAt?.hour, 7);
      expect(booking.expiresAt, DateTime.utc(2026, 11, 11));
    });

    test('accepted booking reveals the passenger phone', () {
      final booking = DriverBooking.fromJson(driverBookingJson(status: 'accepted'));
      expect(booking.phone, '+249123456789');
      expect(booking.expiresAt, isNull);
    });

    test('free booking and unverified passenger', () {
      final booking = DriverBooking.fromJson({
        ...driverBookingJson(),
        'passenger_id_status': 'under_review',
        'fee': {'amount': 0, 'free': true, 'free_bookings_remaining': 2},
      });
      expect(booking.feeFree, isTrue);
      expect(booking.passengerVerified, isFalse);
    });
  });

  test('DriverTrip.fromJson', () {
    final trip = DriverTrip.fromJson(driverTripJson());

    expect(trip.originName, 'الخرطوم');
    expect(trip.destinationId, 2);
    expect(trip.vehicleId, 3);
    expect([trip.departsAt.hour, trip.departsAt.minute], [7, 30]);
    expect(trip.seatsBooked, 4);
    expect(trip.pendingRequests, 1);
    expect(trip.parcelPrices['medium'], 4000);
    expect(trip.pickupPoints, ['موقف شندي — بحري']);
    expect(trip.canCancel, isTrue);
    expect(trip.canEditAll, isFalse);
    expect(trip.bookings, hasLength(2));
    expect(trip.parcels, isEmpty);
  });

  group('TripDraft.toJson', () {
    final draft = TripDraft(
      originCityId: 1,
      destinationCityId: 2,
      vehicleId: 3,
      departsAt: DateTime(2026, 11, 12, 7, 5),
      seatsTotal: 4,
      pricePerSeat: 15000,
      pickupPoints: const ['موقف شندي'],
      dropoffPoints: const ['السوق الكبير'],
      notes: '  ',
    );

    test('new trip sends cities and Sudan clock time without an offset', () {
      final json = draft.toJson(creating: true);

      expect(json['origin_city_id'], 1);
      expect(json['destination_city_id'], 2);
      expect(json['departs_at'], '2026-11-12 07:05');
      expect(json['pickup_points'], [
        {'label': 'موقف شندي'},
      ]);
      expect(json['accepts_parcels'], isFalse);
      expect(json.containsKey('parcel_price_small'), isFalse);
      expect(json['notes'], isNull);
    });

    test('editing leaves the cities out (the server forbids them)', () {
      final json = draft.toJson(creating: false);
      expect(json.containsKey('origin_city_id'), isFalse);
      expect(json.containsKey('destination_city_id'), isFalse);
    });

    test('parcel prices are sent only when parcels are accepted', () {
      final json = TripDraft(
        originCityId: 1,
        destinationCityId: 2,
        vehicleId: 3,
        departsAt: DateTime(2026, 11, 12, 16),
        seatsTotal: 4,
        pricePerSeat: 15000,
        pickupPoints: const ['أ'],
        dropoffPoints: const ['ب'],
        acceptsParcels: true,
        parcelPrices: const {'small': 2000, 'medium': 4000, 'large': 7000},
        maxParcels: 5,
      ).toJson(creating: true);

      expect(json['parcel_price_large'], 7000);
      expect(json['max_parcels'], 5);
      expect(json['departs_at'], '2026-11-12 16:00');
    });
  });
}
