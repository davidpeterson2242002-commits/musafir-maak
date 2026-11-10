import '../core/utils/api_time.dart';

/// What one user may see about another (no phone numbers).
class PublicUser {
  const PublicUser({
    required this.id,
    required this.name,
    this.photoUrl,
    this.ratingAvg = 0,
    this.ratingCount = 0,
    this.tripsCount,
    this.verified = false,
    this.memberSince,
  });

  final int id;
  final String name;
  final String? photoUrl;
  final double ratingAvg;
  final int ratingCount;
  final int? tripsCount;
  final bool verified;
  final DateTime? memberSince;

  factory PublicUser.fromJson(Map<String, dynamic> json) => PublicUser(
        id: json['id'] as int,
        name: json['name'] as String? ?? '',
        photoUrl: json['photo_url'] as String?,
        ratingAvg: (json['rating_avg'] as num?)?.toDouble() ?? 0,
        ratingCount: (json['rating_count'] as num?)?.toInt() ?? 0,
        tripsCount: (json['trips_count'] as num?)?.toInt(),
        verified: json['verified'] as bool? ?? false,
        memberSince: ApiTime.day(json['member_since']),
      );
}

/// A pickup or drop-off place the driver named.
class TripPoint {
  const TripPoint({required this.id, required this.label, this.lat, this.lng});

  final int id;
  final String label;
  final double? lat;
  final double? lng;

  bool get hasPin => lat != null && lng != null;

  factory TripPoint.fromJson(Map<String, dynamic> json) => TripPoint(
        id: json['id'] as int,
        label: json['label'] as String? ?? '',
        lat: (json['lat'] as num?)?.toDouble(),
        lng: (json['lng'] as num?)?.toDouble(),
      );
}

/// A trip as passengers see it. Search results carry the card fields; the trip
/// page and bookings add points, luggage, notes and photos.
class Trip {
  const Trip({
    required this.id,
    required this.status,
    required this.departsAt,
    this.estArrivalAt,
    required this.originName,
    required this.destinationName,
    required this.pricePerSeat,
    required this.seatsAvailable,
    required this.seatsTotal,
    required this.womenOnly,
    required this.acceptsParcels,
    this.parcelPrices = const {},
    required this.driver,
    required this.vehicleType,
    required this.vehicleTypeLabel,
    this.vehicleMake = '',
    this.vehicleModel = '',
    this.vehicleColor = '',
    this.pickupPoints = const [],
    this.dropoffPoints = const [],
    this.luggageLarge = 0,
    this.luggageSmall = 0,
    this.extraBagPrice,
    this.notes,
    this.vehiclePhotos = const [],
  });

  final int id;

  /// scheduled / departed / completed / cancelled.
  final String status;

  /// Sudan wall-clock time (see [ApiTime.wall]).
  final DateTime departsAt;
  final DateTime? estArrivalAt;
  final String originName;
  final String destinationName;
  final int pricePerSeat;
  final int seatsAvailable;
  final int seatsTotal;
  final bool womenOnly;
  final bool acceptsParcels;

  /// small / medium / large → price, when the trip accepts parcels.
  final Map<String, int> parcelPrices;
  final PublicUser driver;
  final String vehicleType;
  final String vehicleTypeLabel;
  final String vehicleMake;
  final String vehicleModel;
  final String vehicleColor;

  // Details
  final List<TripPoint> pickupPoints;
  final List<TripPoint> dropoffPoints;
  final int luggageLarge;
  final int luggageSmall;

  /// null = the driver doesn't take extra bags.
  final int? extraBagPrice;
  final String? notes;

  /// Signed URLs of the photos that don't show the plate.
  final List<String> vehiclePhotos;

  String get vehicleName => [vehicleMake, vehicleModel].where((s) => s.isNotEmpty).join(' ');

  bool get isScheduled => status == 'scheduled';

  factory Trip.fromJson(Map<String, dynamic> json) {
    final route = Map<String, dynamic>.from(json['route'] as Map);
    final vehicle = Map<String, dynamic>.from(json['vehicle'] as Map? ?? const {});
    final luggage = json['luggage'] is Map ? Map<String, dynamic>.from(json['luggage'] as Map) : const <String, dynamic>{};
    final prices = json['parcel_prices'];

    List<TripPoint> points(Object? list) => list is List
        ? list.map((e) => TripPoint.fromJson(Map<String, dynamic>.from(e as Map))).toList()
        : const [];

    return Trip(
      id: json['id'] as int,
      status: json['status'] as String? ?? 'scheduled',
      departsAt: ApiTime.wall(json['departs_at']) ?? DateTime.utc(2000),
      estArrivalAt: ApiTime.wall(json['est_arrival_at']),
      originName: (route['origin'] as Map?)?['name'] as String? ?? '',
      destinationName: (route['destination'] as Map?)?['name'] as String? ?? '',
      pricePerSeat: (json['price_per_seat'] as num?)?.toInt() ?? 0,
      seatsAvailable: (json['seats_available'] as num?)?.toInt() ?? 0,
      seatsTotal: (json['seats_total'] as num?)?.toInt() ?? 0,
      womenOnly: json['women_only'] as bool? ?? false,
      acceptsParcels: json['accepts_parcels'] as bool? ?? false,
      parcelPrices: prices is Map
          ? {
              for (final entry in prices.entries)
                if (entry.value is num) entry.key.toString(): (entry.value as num).toInt(),
            }
          : const {},
      driver: PublicUser.fromJson(Map<String, dynamic>.from(json['driver'] as Map)),
      vehicleType: vehicle['type'] as String? ?? '',
      vehicleTypeLabel: vehicle['type_label'] as String? ?? '',
      vehicleMake: vehicle['make'] as String? ?? '',
      vehicleModel: vehicle['model'] as String? ?? '',
      vehicleColor: vehicle['color'] as String? ?? '',
      pickupPoints: points(json['pickup_points']),
      dropoffPoints: points(json['dropoff_points']),
      luggageLarge: (luggage['large_per_passenger'] as num?)?.toInt() ?? 0,
      luggageSmall: (luggage['small_per_passenger'] as num?)?.toInt() ?? 0,
      extraBagPrice: (luggage['extra_bag_price'] as num?)?.toInt(),
      notes: json['notes'] as String?,
      vehiclePhotos: json['vehicle_photos'] is List
          ? (json['vehicle_photos'] as List)
              .map((e) => (e as Map)['url'])
              .whereType<String>()
              .toList()
          : const [],
    );
  }
}

/// `GET /trips/{id}`: the trip plus what this user can do with it.
class TripDetails {
  const TripDetails({required this.trip, required this.bookable, this.myBookingId, this.parcelSlotsLeft = 0});

  final Trip trip;
  final bool bookable;

  /// The user's active booking on this trip, if any.
  final int? myBookingId;
  final int parcelSlotsLeft;

  factory TripDetails.fromJson(Map<String, dynamic> json) => TripDetails(
        trip: Trip.fromJson(Map<String, dynamic>.from(json['data'] as Map)),
        bookable: json['bookable'] as bool? ?? false,
        myBookingId: (json['my_booking_id'] as num?)?.toInt(),
        parcelSlotsLeft: (json['parcel_slots_left'] as num?)?.toInt() ?? 0,
      );
}

/// A day near the searched date that has trips (shown when the day is empty).
class NearbyDate {
  const NearbyDate(this.date, this.trips);

  final DateTime date;
  final int trips;
}

/// `GET /trips/search`.
class SearchResult {
  const SearchResult({
    this.trips = const [],
    this.closed = false,
    this.closureMessage,
    this.nearbyDates = const [],
  });

  final List<Trip> trips;

  /// The admin closed this route; [closureMessage] says why.
  final bool closed;
  final String? closureMessage;
  final List<NearbyDate> nearbyDates;

  factory SearchResult.fromJson(Map<String, dynamic> json) {
    final nearby = <NearbyDate>[];
    for (final item in (json['nearby_dates'] as List? ?? const [])) {
      final date = ApiTime.day((item as Map)['date']);
      if (date != null) nearby.add(NearbyDate(date, (item['trips'] as num?)?.toInt() ?? 0));
    }

    return SearchResult(
      trips: (json['data'] as List? ?? const [])
          .map((e) => Trip.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList(),
      closed: json['closed'] as bool? ?? false,
      closureMessage: json['closure_message'] as String?,
      nearbyDates: nearby,
    );
  }
}
