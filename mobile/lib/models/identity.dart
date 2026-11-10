import 'user.dart';

/// The two accepted identity documents (spec 5.12).
enum IdDocumentType {
  nationalId('national_id', ['selfie', 'national_id_front', 'national_id_back']),
  passport('passport', ['selfie', 'passport']);

  const IdDocumentType(this.apiValue, this.requiredKinds);

  final String apiValue;

  /// Photo kinds to upload before submitting, in the order they are asked for.
  final List<String> requiredKinds;
}

/// `GET /me/identity`: where the user's ID verification stands.
class Identity {
  const Identity({
    required this.status,
    this.canUpload = false,
    this.allowsBooking = false,
    this.uploadedKinds = const {},
    this.rejectionReasons = const [],
    this.reviewNote,
  });

  final IdStatus status;

  /// Documents can be added or replaced (not while pending or once verified).
  final bool canUpload;

  /// Bookings may be requested in the current state (verified, or pending if the admin allows it).
  final bool allowsBooking;

  /// Kinds already on the server (`selfie`, `passport`, …).
  final Set<String> uploadedKinds;
  final List<String> rejectionReasons;
  final String? reviewNote;

  bool hasAll(IdDocumentType type) => type.requiredKinds.every(uploadedKinds.contains);

  factory Identity.fromJson(Map<String, dynamic> json) {
    final review = json['review'];
    final rejected = review is Map && (review['status'] == 'rejected' || review['status'] == 'info_requested');

    return Identity(
      status: IdStatus.parse(json['status'] as String?),
      canUpload: json['can_upload'] as bool? ?? false,
      allowsBooking: json['allows_booking'] as bool? ?? false,
      uploadedKinds: {
        for (final doc in (json['documents'] as List? ?? const []))
          if ((doc as Map)['kind'] is String) doc['kind'] as String,
      },
      rejectionReasons: rejected ? (review['reasons'] as List? ?? const []).map((e) => e.toString()).toList() : const [],
      reviewNote: rejected ? review['note'] as String? : null,
    );
  }
}
