import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../models/misc.dart';

final ratingRepositoryProvider = Provider<RatingRepository>((ref) => RatingRepository(ref.watch(apiClientProvider)));

/// Trips waiting for the user's rating. Never blocks a screen: empty on any failure.
final pendingRatingsProvider = FutureProvider.autoDispose<List<PendingRating>>((ref) async {
  ref.refreshOnFresh('ratings/pending');
  try {
    return await ref.watch(ratingRepositoryProvider).pending();
  } on ApiException {
    return const [];
  }
});

class RatingRepository {
  RatingRepository(this._api);

  final ApiClient _api;

  Future<List<PendingRating>> pending() async {
    final json = await _api.getCached('ratings/pending');
    return (json as List).map((e) => PendingRating.fromJson(Map<String, dynamic>.from(e as Map))).toList();
  }

  Future<void> rate(PendingRating subject, {required int stars, String? comment, List<String> tags = const []}) =>
      _api.post('ratings', body: {
        'subject_type': subject.subjectType,
        'subject_id': subject.subjectId,
        'stars': stars,
        if (comment != null && comment.trim().isNotEmpty) 'comment': comment.trim(),
        if (tags.isNotEmpty) 'tags': tags,
      });

  /// The ✕ on the home rating card, saved on the server so it never comes back for that trip.
  Future<void> dismiss(Iterable<PendingRating> ratings) => _api.post(
        'ratings/dismiss',
        body: {
          'items': [for (final r in ratings) {'subject_type': r.subjectType, 'subject_id': r.subjectId}],
        },
        silent: true, // the card disappears at once; this just records it
      );
}
