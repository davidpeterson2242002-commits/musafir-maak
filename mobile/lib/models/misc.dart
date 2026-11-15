import '../core/utils/api_time.dart';

/// "Tell me when a driver posts a trip on this route" (spec P12).
class RouteAlert {
  const RouteAlert({
    required this.id,
    required this.originName,
    required this.destinationName,
    this.date,
    this.active = true,
    this.kind = 'trips',
  });

  final int id;
  final String originName;
  final String destinationName;

  /// null = any date.
  final DateTime? date;

  /// Dated alerts switch off once the day has passed.
  final bool active;

  /// `trips` = new trips on this route; `reopen` = tell me when this closed route opens.
  final String kind;

  bool get isReopen => kind == 'reopen';

  factory RouteAlert.fromJson(Map<String, dynamic> json) => RouteAlert(
        id: json['id'] as int,
        originName: (json['origin'] as Map?)?['name'] as String? ?? '',
        destinationName: (json['destination'] as Map?)?['name'] as String? ?? '',
        date: ApiTime.day(json['date']),
        active: json['active'] as bool? ?? true,
        kind: json['kind'] as String? ?? 'trips',
      );
}

/// A finished trip or delivered parcel the user hasn't rated yet (`GET /ratings/pending`).
class PendingRating {
  const PendingRating({
    required this.subjectType,
    required this.subjectId,
    required this.rateAs,
    this.dismissed = false,
    required this.rateeName,
    required this.summary,
  });

  /// `booking` or `parcel`.
  final String subjectType;
  final int subjectId;

  /// `passenger` / `sender` (rating the driver) or `driver` (rating the customer).
  final String rateAs;

  /// Closed with ✕ on the home card (kept on the server): only the booking / trip page offers it.
  final bool dismissed;
  final String rateeName;

  /// e.g. "الخرطوم ← ود مدني — 2026-11-12".
  final String summary;

  bool get ratingDriver => rateAs != 'driver';

  factory PendingRating.fromJson(Map<String, dynamic> json) => PendingRating(
        subjectType: json['subject_type'] as String? ?? 'booking',
        subjectId: (json['subject_id'] as num).toInt(),
        rateAs: json['rate_as'] as String? ?? 'passenger',
        dismissed: json['dismissed'] as bool? ?? false,
        rateeName: (json['ratee'] as Map?)?['name'] as String? ?? '',
        summary: json['summary'] as String? ?? '',
      );
}
