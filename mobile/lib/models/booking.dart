import '../core/utils/api_time.dart';
import 'trip.dart';

enum BookingStatus {
  pending,
  accepted,
  rejected,
  expired,
  cancelledByPassenger,
  cancelledByDriver,
  completed,
  noShow,
  unknown;

  static BookingStatus parse(String? value) => switch (value) {
        'pending' => pending,
        'accepted' => accepted,
        'rejected' => rejected,
        'expired' => expired,
        'cancelled_by_passenger' => cancelledByPassenger,
        'cancelled_by_driver' => cancelledByDriver,
        'completed' => completed,
        'no_show' => noShow,
        _ => unknown,
      };

  bool get isActive => this == pending || this == accepted;

  /// The request ended without a seat, through no choice of the passenger —
  /// the screen offers other trips.
  bool get offersAlternatives => this == rejected || this == expired || this == cancelledByDriver;
}

/// Driver contact, revealed once the booking is accepted (spec 5.3).
class BookingContact {
  const BookingContact({required this.driverPhone, required this.driverWhatsapp, required this.plateNumber});

  final String driverPhone;
  final String driverWhatsapp;
  final String plateNumber;
}

class NoShowInfo {
  const NoShowInfo({this.disputed = false, this.resolution, this.canDispute = false});

  final bool disputed;
  final String? resolution;
  final bool canDispute;
}

/// A seat booking from the passenger's side (spec P7/P8).
class Booking {
  const Booking({
    required this.id,
    required this.status,
    required this.statusLabel,
    required this.seats,
    required this.priceTotal,
    required this.trip,
    this.extraBags = 0,
    this.includesFamily = false,
    this.pickupPoint,
    this.message,
    this.expiresAt,
    this.cancelReason,
    this.rejectionReason,
    this.lateCancellation = false,
    this.noShow,
    this.canCancel = false,
    this.conversationId,
    this.contact,
    this.shareText,
    this.alternatives = const [],
  });

  final int id;
  final BookingStatus status;

  /// Arabic label from the server.
  final String statusLabel;
  final int seats;
  final int priceTotal;
  final Trip trip;
  final int extraBags;
  final bool includesFamily;
  final TripPoint? pickupPoint;
  final String? message;

  /// When a pending request expires (a real moment, for the countdown).
  final DateTime? expiresAt;
  final String? cancelReason;
  final String? rejectionReason;
  final bool lateCancellation;
  final NoShowInfo? noShow;
  final bool canCancel;
  final int? conversationId;
  final BookingContact? contact;

  /// Ready-made text for "share my trip with family".
  final String? shareText;

  /// Other trips on the same route and day, when this one fell through.
  final List<Trip> alternatives;

  /// `json` is the booking object; `envelope` the whole response when it has
  /// extras next to `data` (alternatives).
  factory Booking.fromJson(Map<String, dynamic> json, {Map<String, dynamic>? envelope}) {
    final contact = json['contact'];
    final noShow = json['no_show'];
    final pickup = json['pickup_point'];
    final alternatives = envelope?['alternatives'];

    return Booking(
      id: json['id'] as int,
      status: BookingStatus.parse(json['status'] as String?),
      statusLabel: json['status_label'] as String? ?? '',
      seats: (json['seats'] as num?)?.toInt() ?? 1,
      priceTotal: (json['price_total'] as num?)?.toInt() ?? 0,
      trip: Trip.fromJson(Map<String, dynamic>.from(json['trip'] as Map)),
      extraBags: (json['extra_bags'] as num?)?.toInt() ?? 0,
      includesFamily: json['includes_family'] as bool? ?? false,
      pickupPoint: pickup is Map ? TripPoint.fromJson(Map<String, dynamic>.from(pickup)) : null,
      message: json['message'] as String?,
      expiresAt: ApiTime.instant(json['expires_at']),
      cancelReason: json['cancel_reason'] as String?,
      rejectionReason: json['rejection_reason'] as String?,
      lateCancellation: json['late_cancellation'] as bool? ?? false,
      noShow: noShow is Map
          ? NoShowInfo(
              disputed: noShow['disputed_at'] != null,
              resolution: noShow['resolution'] as String?,
              canDispute: noShow['can_dispute'] as bool? ?? false,
            )
          : null,
      canCancel: json['can_cancel'] as bool? ?? false,
      conversationId: (json['conversation_id'] as num?)?.toInt(),
      contact: contact is Map
          ? BookingContact(
              driverPhone: contact['driver_phone'] as String? ?? '',
              driverWhatsapp: contact['driver_whatsapp'] as String? ?? '',
              plateNumber: contact['plate_number'] as String? ?? '',
            )
          : null,
      shareText: json['share_text'] as String?,
      alternatives: alternatives is List
          ? alternatives.map((e) => Trip.fromJson(Map<String, dynamic>.from(e as Map))).toList()
          : const [],
    );
  }
}
