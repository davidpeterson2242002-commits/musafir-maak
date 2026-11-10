import '../core/utils/api_time.dart';
import 'trip.dart';

/// A chat between a customer and a driver about one booking or parcel (spec 5.8).
class Conversation {
  const Conversation({
    required this.id,
    required this.subjectType,
    required this.subjectId,
    required this.summary,
    required this.statusLabel,
    required this.myRole,
    required this.other,
    this.lastBody,
    this.lastMine = false,
    this.lastAt,
    this.unread = 0,
    this.open = true,
  });

  final int id;

  /// `booking` or `parcel`.
  final String subjectType;
  final int subjectId;

  /// e.g. "الخرطوم ← ود مدني — 2026-11-12 07:30".
  final String summary;
  final String statusLabel;

  /// passenger / sender / driver.
  final String myRole;
  final PublicUser other;
  final String? lastBody;
  final bool lastMine;
  final DateTime? lastAt;
  final int unread;

  /// Messages can still be sent (closed after rejection, cancellation, or a few days after the trip).
  final bool open;

  bool get isParcel => subjectType == 'parcel';

  factory Conversation.fromJson(Map<String, dynamic> json) {
    final subject = Map<String, dynamic>.from(json['subject'] as Map? ?? const {});
    final last = json['last_message'];

    return Conversation(
      id: json['id'] as int,
      subjectType: subject['type'] as String? ?? 'booking',
      subjectId: (subject['id'] as num?)?.toInt() ?? 0,
      summary: subject['summary'] as String? ?? '',
      statusLabel: subject['status_label'] as String? ?? '',
      myRole: json['my_role'] as String? ?? 'passenger',
      other: PublicUser.fromJson(Map<String, dynamic>.from(json['other'] as Map)),
      lastBody: last is Map ? last['body'] as String? : null,
      lastMine: last is Map ? (last['mine'] as bool? ?? false) : false,
      lastAt: ApiTime.wall(last is Map ? last['created_at'] : json['last_message_at']),
      unread: (json['unread'] as num?)?.toInt() ?? 0,
      open: json['open'] as bool? ?? true,
    );
  }
}

class ChatMessage {
  const ChatMessage({
    required this.id,
    required this.mine,
    required this.body,
    this.wasMasked = false,
    this.createdAt,
  });

  final int id;
  final bool mine;
  final String body;

  /// A phone number in this message was hidden (sent before the booking was accepted).
  final bool wasMasked;
  final DateTime? createdAt;

  factory ChatMessage.fromJson(Map<String, dynamic> json) => ChatMessage(
        id: json['id'] as int,
        mine: json['mine'] as bool? ?? false,
        body: json['body'] as String? ?? '',
        wasMasked: json['was_masked'] as bool? ?? false,
        createdAt: ApiTime.wall(json['created_at']),
      );
}

/// One page of `GET /conversations/{id}/messages`.
class ChatPage {
  const ChatPage(this.messages, this.conversation);

  final List<ChatMessage> messages;
  final Conversation? conversation;

  factory ChatPage.fromJson(dynamic json) {
    final map = json is Map ? json : {'data': json};
    final conversation = map['conversation'];

    return ChatPage(
      (map['data'] as List? ?? const [])
          .map((e) => ChatMessage.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList(),
      conversation is Map ? Conversation.fromJson(Map<String, dynamic>.from(conversation)) : null,
    );
  }
}

/// A review shown on someone's public profile.
class Review {
  const Review({required this.stars, this.comment, this.tags = const [], this.raterName, this.createdAt});

  final int stars;
  final String? comment;
  final List<String> tags;
  final String? raterName;
  final DateTime? createdAt;

  factory Review.fromJson(Map<String, dynamic> json) => Review(
        stars: (json['stars'] as num?)?.toInt() ?? 0,
        comment: json['comment'] as String?,
        tags: (json['tags'] as List? ?? const [])
            .map((t) => t is Map ? t['label']?.toString() ?? '' : t.toString())
            .where((t) => t.isNotEmpty)
            .toList(),
        raterName: json['rater_name'] as String?,
        createdAt: ApiTime.wall(json['created_at']),
      );
}

/// `GET /users/{id}` (spec P13).
class PublicProfile {
  const PublicProfile({
    required this.user,
    this.driverRatingAvg,
    this.driverRatingCount = 0,
    this.driverTrips = 0,
    this.driverSince,
    this.driverReviews = const [],
    this.passengerReviews = const [],
    this.blockedByMe = false,
  });

  final PublicUser user;

  /// null when the person isn't an approved driver.
  final double? driverRatingAvg;
  final int driverRatingCount;
  final int driverTrips;
  final DateTime? driverSince;
  final List<Review> driverReviews;
  final List<Review> passengerReviews;
  final bool blockedByMe;

  bool get isDriver => driverRatingAvg != null;

  factory PublicProfile.fromJson(Map<String, dynamic> json) {
    final driver = json['driver'];
    final reviews = json['reviews'];

    List<Review> list(Object? value) => value is List
        ? value.map((e) => Review.fromJson(Map<String, dynamic>.from(e as Map))).toList()
        : const [];

    return PublicProfile(
      user: PublicUser.fromJson(Map<String, dynamic>.from(json['data'] as Map)),
      driverRatingAvg: driver is Map ? (driver['rating_avg'] as num?)?.toDouble() ?? 0 : null,
      driverRatingCount: driver is Map ? (driver['rating_count'] as num?)?.toInt() ?? 0 : 0,
      driverTrips: driver is Map ? (driver['completed_trips'] as num?)?.toInt() ?? 0 : 0,
      driverSince: driver is Map ? ApiTime.day(driver['driver_since']) : null,
      driverReviews: list(reviews is Map ? reviews['as_driver'] : null),
      passengerReviews: list(reviews is Map ? reviews['as_passenger'] : null),
      blockedByMe: json['blocked_by_me'] as bool? ?? false,
    );
  }
}
