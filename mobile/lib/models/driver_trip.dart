import '../core/utils/api_time.dart';
import 'booking.dart';
import 'trip.dart';

/// A booking as the trip's driver sees it (spec D9/D10).
class DriverBooking {
  const DriverBooking({
    required this.id,
    required this.tripId,
    required this.status,
    required this.statusLabel,
    required this.passenger,
    required this.seats,
    required this.priceTotal,
    this.passengerVerified = false,
    this.extraBags = 0,
    this.includesFamily = false,
    this.pickupLabel,
    this.message,
    this.expiresAt,
    this.phone,
    this.whatsapp,
    this.feeAmount = 0,
    this.feeFree = false,
    this.conversationId,
    this.canMarkNoShow = false,
    this.tripRoute,
    this.tripDepartsAt,
  });

  final int id;
  final int tripId;
  final BookingStatus status;
  final String statusLabel;
  final PublicUser passenger;

  /// false = the passenger's ID is still under review (the driver decides).
  final bool passengerVerified;
  final int seats;
  final int priceTotal;
  final int extraBags;
  final bool includesFamily;
  final String? pickupLabel;
  final String? message;
  final DateTime? expiresAt;

  /// Revealed once the booking is accepted.
  final String? phone;
  final String? whatsapp;

  /// Commission for this booking (a preview while pending).
  final int feeAmount;
  final bool feeFree;
  final int? conversationId;
  final bool canMarkNoShow;

  /// Present in the requests list: "الخرطوم ← ود مدني" and the departure time.
  final String? tripRoute;
  final DateTime? tripDepartsAt;

  factory DriverBooking.fromJson(Map<String, dynamic> json) {
    final fee = json['fee'] is Map ? json['fee'] as Map : const {};
    final trip = json['trip'];
    final pickup = json['pickup_point'];

    return DriverBooking(
      id: json['id'] as int,
      tripId: (json['trip_id'] as num?)?.toInt() ?? 0,
      status: BookingStatus.parse(json['status'] as String?),
      statusLabel: json['status_label'] as String? ?? '',
      passenger: PublicUser.fromJson(Map<String, dynamic>.from(json['passenger'] as Map)),
      passengerVerified: json['passenger_id_status'] == 'verified',
      seats: (json['seats'] as num?)?.toInt() ?? 1,
      priceTotal: (json['price_total'] as num?)?.toInt() ?? 0,
      extraBags: (json['extra_bags'] as num?)?.toInt() ?? 0,
      includesFamily: json['includes_family'] as bool? ?? false,
      pickupLabel: pickup is Map ? pickup['label'] as String? : null,
      message: json['message'] as String?,
      expiresAt: ApiTime.instant(json['expires_at']),
      phone: json['phone'] as String?,
      whatsapp: json['whatsapp'] as String?,
      feeAmount: (fee['amount'] as num?)?.toInt() ?? 0,
      feeFree: fee['free'] as bool? ?? false,
      conversationId: (json['conversation_id'] as num?)?.toInt(),
      canMarkNoShow: json['can_mark_no_show'] as bool? ?? false,
      tripRoute: trip is Map ? trip['route'] as String? : null,
      tripDepartsAt: trip is Map ? ApiTime.wall(trip['departs_at']) : null,
    );
  }
}

/// One of the driver's own trips (spec D8/D9).
class DriverTrip {
  const DriverTrip({
    required this.id,
    required this.status,
    required this.statusLabel,
    required this.departsAt,
    required this.originId,
    required this.originName,
    required this.destinationId,
    required this.destinationName,
    required this.vehicleId,
    required this.vehicleLabel,
    required this.pricePerSeat,
    required this.seatsTotal,
    required this.seatsAvailable,
    this.pendingRequests = 0,
    this.luggageLarge = 1,
    this.luggageSmall = 1,
    this.extraBagPrice,
    this.acceptsParcels = false,
    this.parcelPrices = const {},
    this.maxParcels = 0,
    this.womenOnly = false,
    this.notes,
    this.cancelReason,
    this.hiddenByAdmin = false,
    this.pickupPoints = const [],
    this.dropoffPoints = const [],
    this.canEditAll = false,
    this.canDepart = false,
    this.canComplete = false,
    this.canCancel = false,
    this.bookings = const [],
    this.parcelsCount = 0,
  });

  final int id;

  /// scheduled / departed / completed / cancelled.
  final String status;
  final String statusLabel;
  final DateTime departsAt;
  final int originId;
  final String originName;
  final int destinationId;
  final String destinationName;
  final int vehicleId;
  final String vehicleLabel;
  final int pricePerSeat;
  final int seatsTotal;
  final int seatsAvailable;
  final int pendingRequests;
  final int luggageLarge;
  final int luggageSmall;
  final int? extraBagPrice;
  final bool acceptsParcels;
  final Map<String, int> parcelPrices;
  final int maxParcels;
  final bool womenOnly;
  final String? notes;
  final String? cancelReason;
  final bool hiddenByAdmin;
  final List<String> pickupPoints;
  final List<String> dropoffPoints;

  /// No active bookings yet: everything can still be changed.
  final bool canEditAll;
  final bool canDepart;
  final bool canComplete;
  final bool canCancel;

  /// Loaded on the trip page only; pending requests first.
  final List<DriverBooking> bookings;
  final int parcelsCount;

  int get seatsBooked => seatsTotal - seatsAvailable;

  factory DriverTrip.fromJson(Map<String, dynamic> json) {
    final route = Map<String, dynamic>.from(json['route'] as Map);
    final origin = Map<String, dynamic>.from(route['origin'] as Map);
    final destination = Map<String, dynamic>.from(route['destination'] as Map);
    final vehicle = json['vehicle'] is Map ? json['vehicle'] as Map : const {};
    final luggage = json['luggage'] is Map ? json['luggage'] as Map : const {};
    final actions = json['actions'] is Map ? json['actions'] as Map : const {};
    final prices = json['parcel_prices'];

    List<String> labels(Object? list) => list is List
        ? list.map((e) => (e as Map)['label']?.toString() ?? '').where((l) => l.isNotEmpty).toList()
        : const [];

    return DriverTrip(
      id: json['id'] as int,
      status: json['status'] as String? ?? 'scheduled',
      statusLabel: json['status_label'] as String? ?? '',
      departsAt: ApiTime.wall(json['departs_at']) ?? DateTime.utc(2000),
      originId: origin['id'] as int,
      originName: origin['name'] as String? ?? '',
      destinationId: destination['id'] as int,
      destinationName: destination['name'] as String? ?? '',
      vehicleId: (vehicle['id'] as num?)?.toInt() ?? 0,
      vehicleLabel: vehicle['label'] as String? ?? '',
      pricePerSeat: (json['price_per_seat'] as num?)?.toInt() ?? 0,
      seatsTotal: (json['seats_total'] as num?)?.toInt() ?? 0,
      seatsAvailable: (json['seats_available'] as num?)?.toInt() ?? 0,
      pendingRequests: (json['pending_requests'] as num?)?.toInt() ?? 0,
      luggageLarge: (luggage['large_per_passenger'] as num?)?.toInt() ?? 0,
      luggageSmall: (luggage['small_per_passenger'] as num?)?.toInt() ?? 0,
      extraBagPrice: (luggage['extra_bag_price'] as num?)?.toInt(),
      acceptsParcels: json['accepts_parcels'] as bool? ?? false,
      parcelPrices: prices is Map
          ? {
              for (final entry in prices.entries)
                if (entry.value is num) entry.key.toString(): (entry.value as num).toInt(),
            }
          : const {},
      maxParcels: (json['max_parcels'] as num?)?.toInt() ?? 0,
      womenOnly: json['women_only'] as bool? ?? false,
      notes: json['notes'] as String?,
      cancelReason: json['cancel_reason'] as String?,
      hiddenByAdmin: json['hidden_by_admin'] as bool? ?? false,
      pickupPoints: labels(json['pickup_points']),
      dropoffPoints: labels(json['dropoff_points']),
      canEditAll: actions['can_edit_all'] as bool? ?? false,
      canDepart: actions['can_depart'] as bool? ?? false,
      canComplete: actions['can_complete'] as bool? ?? false,
      canCancel: actions['can_cancel'] as bool? ?? false,
      bookings: (json['bookings'] as List? ?? const [])
          .map((e) => DriverBooking.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList(),
      parcelsCount: (json['parcels'] as List?)?.length ?? 0,
    );
  }
}

/// What the "post a trip" form sends (`POST /driver/trips`, `PUT /driver/trips/{id}`).
class TripDraft {
  const TripDraft({
    required this.originCityId,
    required this.destinationCityId,
    required this.vehicleId,
    required this.departsAt,
    required this.seatsTotal,
    required this.pricePerSeat,
    required this.pickupPoints,
    required this.dropoffPoints,
    this.luggageLarge = 1,
    this.luggageSmall = 1,
    this.extraBagPrice,
    this.acceptsParcels = false,
    this.parcelPrices = const {},
    this.maxParcels = 0,
    this.womenOnly = false,
    this.notes,
  });

  final int originCityId;
  final int destinationCityId;
  final int vehicleId;

  /// Sudan clock time, as the driver picked it.
  final DateTime departsAt;
  final int seatsTotal;
  final int pricePerSeat;
  final List<String> pickupPoints;
  final List<String> dropoffPoints;
  final int luggageLarge;
  final int luggageSmall;
  final int? extraBagPrice;
  final bool acceptsParcels;
  final Map<String, int> parcelPrices;
  final int maxParcels;
  final bool womenOnly;
  final String? notes;

  /// [creating] false leaves the cities out: a posted trip's route can't change.
  Map<String, dynamic> toJson({required bool creating}) {
    String two(int n) => n.toString().padLeft(2, '0');
    // No offset: the server reads this as Sudan time.
    final when = '${departsAt.year}-${two(departsAt.month)}-${two(departsAt.day)} ${two(departsAt.hour)}:${two(departsAt.minute)}';

    return {
      if (creating) 'origin_city_id': originCityId,
      if (creating) 'destination_city_id': destinationCityId,
      'vehicle_id': vehicleId,
      'departs_at': when,
      'seats_total': seatsTotal,
      'price_per_seat': pricePerSeat,
      'pickup_points': [for (final label in pickupPoints) {'label': label}],
      'dropoff_points': [for (final label in dropoffPoints) {'label': label}],
      'luggage_large': luggageLarge,
      'luggage_small': luggageSmall,
      'extra_luggage_price': extraBagPrice,
      'accepts_parcels': acceptsParcels,
      if (acceptsParcels) ...{
        'parcel_price_small': parcelPrices['small'],
        'parcel_price_medium': parcelPrices['medium'],
        'parcel_price_large': parcelPrices['large'],
        'max_parcels': maxParcels,
      },
      'women_only': womenOnly,
      'notes': (notes ?? '').trim().isEmpty ? null : notes!.trim(),
    };
  }
}
