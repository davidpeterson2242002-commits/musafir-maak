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
/// Late-cancellation rules for a booking (`cancel_policy`).
class CancelPolicy {
  const CancelPolicy({
    required this.lateWindowHours,
    required this.lateNow,
    required this.lateLimit,
    required this.windowDays,
    required this.restrictionDays,
  });

  /// Cancelling an accepted seat closer than this to departure counts as late.
  final int lateWindowHours;

  /// Cancelling right now would count as late.
  final bool lateNow;
  final int lateLimit;
  final int windowDays;
  final int restrictionDays;

  factory CancelPolicy.fromJson(Map<String, dynamic> json) {
    int n(String k, int d) => (json[k] as num?)?.toInt() ?? d;
    return CancelPolicy(
      lateWindowHours: n('late_window_hours', 3),
      lateNow: json['late_now'] as bool? ?? false,
      lateLimit: n('late_limit', 3),
      windowDays: n('window_days', 30),
      restrictionDays: n('restriction_days', 7),
    );
  }
}

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
    this.cancelPolicy,
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

  /// The rules shown next to the cancel button (numbers come from the admin settings).
  final CancelPolicy? cancelPolicy;
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
      cancelPolicy: json['cancel_policy'] is Map ? CancelPolicy.fromJson(Map<String, dynamic>.from(json['cancel_policy'] as Map)) : null,
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
