import '../core/utils/api_time.dart';
import 'booking.dart';
import 'trip.dart';

enum ParcelStatus {
  pending,
  accepted,
  pickedUp,
  delivered,
  rejected,
  expired,
  refused,
  cancelled,
  disputed,
  unknown;

  static ParcelStatus parse(String? value) => switch (value) {
        'pending' => pending,
        'accepted' => accepted,
        'picked_up' => pickedUp,
        'delivered' => delivered,
        'rejected' => rejected,
        'expired' => expired,
        'refused' => refused,
        'cancelled' => cancelled,
        'disputed' => disputed,
        _ => unknown,
      };
}

/// A parcel (ارسالية) from the sender's side (spec S3).
class Parcel {
  const Parcel({
    required this.id,
    required this.status,
    required this.statusLabel,
    required this.description,
    required this.size,
    required this.sizeLabel,
    required this.receiverName,
    required this.receiverPhone,
    required this.payer,
    required this.price,
    required this.trip,
    this.photoUrl,
    this.pickupPhotoUrl,
    this.message,
    this.expiresAt,
    this.rejectionReason,
    this.refusalReason,
    this.cancelReason,
    this.deliveryCode,
    this.receiverNotified = false,
    this.shareText,
    this.canCancel = false,
    this.canDispute = false,
    this.conversationId,
    this.contact,
  });

  final int id;
  final ParcelStatus status;
  final String statusLabel;
  final String description;

  /// small / medium / large.
  final String size;
  final String sizeLabel;
  final String receiverName;
  final String receiverPhone;

  /// `sender` or `receiver`: who pays the driver.
  final String payer;
  final int price;
  final Trip trip;
  final String? photoUrl;

  /// The driver's photo at pickup.
  final String? pickupPhotoUrl;
  final String? message;
  final DateTime? expiresAt;
  final String? rejectionReason;
  final String? refusalReason;
  final String? cancelReason;

  /// 4-digit code the receiver gives the driver. Present from acceptance until delivery.
  final String? deliveryCode;

  /// The receiver's WhatsApp message went out.
  final bool receiverNotified;

  /// Full message for the receiver (driver, car, date, payment, code).
  final String? shareText;
  final bool canCancel;
  final bool canDispute;
  final int? conversationId;
  final BookingContact? contact;

  bool get senderPays => payer == 'sender';

  factory Parcel.fromJson(Map<String, dynamic> json) {
    final contact = json['contact'];

    return Parcel(
      id: json['id'] as int,
      status: ParcelStatus.parse(json['status'] as String?),
      statusLabel: json['status_label'] as String? ?? '',
      description: json['description'] as String? ?? '',
      size: json['size'] as String? ?? 'small',
      sizeLabel: json['size_label'] as String? ?? '',
      receiverName: json['receiver_name'] as String? ?? '',
      receiverPhone: json['receiver_phone'] as String? ?? '',
      payer: json['payer'] as String? ?? 'sender',
      price: (json['price'] as num?)?.toInt() ?? 0,
      trip: Trip.fromJson(Map<String, dynamic>.from(json['trip'] as Map)),
      photoUrl: json['photo_url'] as String?,
      pickupPhotoUrl: json['pickup_photo_url'] as String?,
      message: json['message'] as String?,
      expiresAt: ApiTime.instant(json['expires_at']),
      rejectionReason: json['rejection_reason'] as String?,
      refusalReason: json['refusal_reason'] as String?,
      cancelReason: json['cancel_reason'] as String?,
      deliveryCode: json['delivery_code']?.toString(),
      receiverNotified: json['receiver_notified'] as bool? ?? false,
      shareText: json['share_text'] as String?,
      canCancel: json['can_cancel'] as bool? ?? false,
      canDispute: json['can_dispute'] as bool? ?? false,
      conversationId: (json['conversation_id'] as num?)?.toInt(),
      contact: contact is Map
          ? BookingContact(
              driverPhone: contact['driver_phone'] as String? ?? '',
              driverWhatsapp: contact['driver_whatsapp'] as String? ?? '',
              plateNumber: contact['plate_number'] as String? ?? '',
            )
          : null,
    );
  }
}
