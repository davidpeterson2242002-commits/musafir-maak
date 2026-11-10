import '../core/utils/api_time.dart';
import 'identity.dart';

/// Photos required for every vehicle, in the order they are asked for.
const vehiclePhotoKinds = ['vehicle_front', 'vehicle_back', 'vehicle_side', 'vehicle_interior', 'vehicle_registration'];

class Vehicle {
  const Vehicle({
    required this.id,
    required this.type,
    required this.typeLabel,
    required this.make,
    required this.model,
    required this.color,
    required this.plateNumber,
    required this.seats,
    required this.status,
    required this.statusLabel,
    this.year,
    this.editable = false,
    this.uploadedKinds = const {},
    this.rejectionReasons = const [],
  });

  final int id;

  /// car / boksi / minibus / bus / pickup.
  final String type;
  final String typeLabel;
  final String make;
  final String model;
  final String color;
  final int? year;
  final String plateNumber;

  /// Passenger seats, not counting the driver.
  final int seats;

  /// draft / pending / approved / rejected.
  final String status;
  final String statusLabel;
  final bool editable;
  final Set<String> uploadedKinds;
  final List<String> rejectionReasons;

  String get name => '$make $model';

  bool get photosComplete => vehiclePhotoKinds.every(uploadedKinds.contains);

  bool get isApproved => status == 'approved';

  factory Vehicle.fromJson(Map<String, dynamic> json) {
    final review = json['review'];

    return Vehicle(
      id: json['id'] as int,
      type: json['type'] as String? ?? 'car',
      typeLabel: json['type_label'] as String? ?? '',
      make: json['make'] as String? ?? '',
      model: json['model'] as String? ?? '',
      color: json['color'] as String? ?? '',
      year: (json['year'] as num?)?.toInt(),
      plateNumber: json['plate_number'] as String? ?? '',
      seats: (json['seats'] as num?)?.toInt() ?? 0,
      status: json['status'] as String? ?? 'draft',
      statusLabel: json['status_label'] as String? ?? '',
      editable: json['editable'] as bool? ?? false,
      uploadedKinds: _kinds(json['documents']),
      rejectionReasons: review is Map && review['status'] != 'approved'
          ? (review['reasons'] as List? ?? const []).map((e) => e.toString()).toList()
          : const [],
    );
  }
}

Set<String> _kinds(Object? documents) => {
      if (documents is List)
        for (final doc in documents)
          if (doc is Map && doc['kind'] is String) doc['kind'] as String,
    };

/// `GET /driver`: the user's driver registration and account (spec 5.1, D1–D5).
class DriverAccount {
  const DriverAccount({
    required this.status,
    required this.statusLabel,
    this.editable = false,
    this.canOperate = false,
    this.identityDone = false,
    this.licenceDone = false,
    this.vehicleDone = false,
    this.identity,
    this.vehicles = const [],
    this.rejectionReasons = const [],
    this.reviewNote,
    this.freeBookingsRemaining = 0,
    this.balanceOwed = 0,
    this.walletBalance = 0,
    this.settlementDueAt,
    this.balanceLimitReached = false,
  });

  /// draft / pending_review / rejected / active / suspended / frozen.
  final String status;
  final String statusLabel;

  /// Documents and vehicles can be changed (draft or rejected).
  final bool editable;

  /// Approved with at least one approved vehicle: may post trips.
  final bool canOperate;
  final bool identityDone;
  final bool licenceDone;
  final bool vehicleDone;
  final Identity? identity;
  final List<Vehicle> vehicles;
  final List<String> rejectionReasons;
  final String? reviewNote;
  final int freeBookingsRemaining;
  final int balanceOwed;
  final int walletBalance;

  /// When the owed balance must be paid (Sudan clock time); null when nothing is due.
  final DateTime? settlementDueAt;

  /// Owes the maximum allowed: can't accept new requests until paying.
  final bool balanceLimitReached;

  bool get isFrozen => status == 'frozen';

  bool get readyToSubmit => identityDone && licenceDone && vehicleDone;

  bool get isPending => status == 'pending_review';

  bool get isApproved => status == 'active' || status == 'frozen' || status == 'suspended';

  /// Which ID the user photographed, for the application (`id_document_type`).
  IdDocumentType get idDocumentType {
    final kinds = identity?.uploadedKinds ?? const <String>{};
    return kinds.contains('passport') && !kinds.contains('national_id_front')
        ? IdDocumentType.passport
        : IdDocumentType.nationalId;
  }

  factory DriverAccount.fromJson(Map<String, dynamic> json) {
    final checklist = json['checklist'] is Map ? json['checklist'] as Map : const {};
    final review = json['review'];
    final identity = json['identity'];
    final needsFix = review is Map && (review['status'] == 'rejected' || review['status'] == 'info_requested');

    return DriverAccount(
      status: json['status'] as String? ?? 'draft',
      statusLabel: json['status_label'] as String? ?? '',
      editable: json['editable'] as bool? ?? false,
      canOperate: json['can_operate'] as bool? ?? false,
      identityDone: checklist['identity'] as bool? ?? false,
      licenceDone: checklist['driving_licence'] as bool? ?? false,
      vehicleDone: checklist['vehicle'] as bool? ?? false,
      identity: identity is Map ? Identity.fromJson(Map<String, dynamic>.from(identity)) : null,
      vehicles: (json['vehicles'] as List? ?? const [])
          .map((e) => Vehicle.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList(),
      rejectionReasons: needsFix ? (review['reasons'] as List? ?? const []).map((e) => e.toString()).toList() : const [],
      reviewNote: needsFix ? review['note'] as String? : null,
      freeBookingsRemaining: (json['free_bookings_remaining'] as num?)?.toInt() ?? 0,
      balanceOwed: (json['balance_owed'] as num?)?.toInt() ?? 0,
      walletBalance: (json['wallet_balance'] as num?)?.toInt() ?? 0,
      settlementDueAt: ApiTime.wall(json['settlement_due_at']),
      balanceLimitReached: json['balance_limit_reached'] as bool? ?? false,
    );
  }
}
