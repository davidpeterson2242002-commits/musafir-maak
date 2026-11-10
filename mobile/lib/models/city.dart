/// A city users travel between. Greater Khartoum (Khartoum, Omdurman, Bahri)
/// is a single city on the backend; neighbourhoods are pickup points.
class City {
  const City({required this.id, required this.name, this.nameEn, this.stateName});

  final int id;
  final String name;
  final String? nameEn;
  final String? stateName;

  factory City.fromJson(Map<String, dynamic> json) {
    final state = json['state'];
    return City(
      id: json['id'] as int,
      name: json['name'] as String,
      nameEn: json['name_en'] as String?,
      stateName: state is Map ? state['name'] as String? : null,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'name_en': nameEn,
        if (stateName != null) 'state': {'name': stateName},
      };

  @override
  bool operator ==(Object other) => other is City && other.id == id;

  @override
  int get hashCode => id.hashCode;
}

/// An admin-defined route between two cities (spec 5.11).
class TravelRoute {
  const TravelRoute({
    required this.id,
    required this.origin,
    required this.destination,
    required this.open,
    this.closureMessage,
    this.maxPricePerSeat,
    this.isPopular = false,
  });

  final int id;
  final City origin;
  final City destination;
  final bool open;
  final String? closureMessage;
  final int? maxPricePerSeat;
  final bool isPopular;

  factory TravelRoute.fromJson(Map<String, dynamic> json) => TravelRoute(
        id: json['id'] as int,
        origin: City.fromJson(Map<String, dynamic>.from(json['origin'] as Map)),
        destination: City.fromJson(Map<String, dynamic>.from(json['destination'] as Map)),
        open: json['open'] as bool? ?? true,
        closureMessage: json['closure_message'] as String?,
        maxPricePerSeat: (json['max_price_per_seat'] as num?)?.toInt(),
        isPopular: json['is_popular'] as bool? ?? false,
      );
}
