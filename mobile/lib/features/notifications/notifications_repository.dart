import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/utils/api_time.dart';

/// One entry behind the bell: every push the user was sent, kept so a missed
/// or swiped-away notification can still be opened.
class AppNotification {
  const AppNotification({
    required this.id,
    required this.event,
    required this.title,
    required this.body,
    required this.data,
    required this.read,
    this.createdAt,
  });

  final int id;
  final String event;
  final String title;
  final String body;

  /// Same payload as the push (event + ids), so tapping opens the same screen.
  final Map<String, dynamic> data;
  final bool read;
  final DateTime? createdAt;

  factory AppNotification.fromJson(Map<String, dynamic> json) => AppNotification(
        id: (json['id'] as num).toInt(),
        event: json['event'] as String? ?? '',
        title: json['title'] as String? ?? '',
        body: json['body'] as String? ?? '',
        data: json['data'] is Map ? Map<String, dynamic>.from(json['data'] as Map) : const {},
        read: json['read'] as bool? ?? false,
        createdAt: ApiTime.wall(json['created_at']),
      );

  AppNotification markedRead() =>
      AppNotification(id: id, event: event, title: title, body: body, data: data, read: true, createdAt: createdAt);
}

class NotificationPage {
  const NotificationPage(this.items, {this.hasMore = false});

  final List<AppNotification> items;
  final bool hasMore;
}

final notificationRepositoryProvider = Provider<NotificationRepository>(
  (ref) => NotificationRepository(ref.watch(apiClientProvider)),
);

/// First page; shows the saved copy at once and refreshes in the background.
final notificationsProvider = FutureProvider.autoDispose<NotificationPage>((ref) {
  ref.refreshOnFresh('me/notifications');
  return ref.watch(notificationRepositoryProvider).page();
});

class NotificationRepository {
  NotificationRepository(this._api);

  final ApiClient _api;

  Future<NotificationPage> page({int? beforeId}) async {
    final json = beforeId == null
        ? await _api.getCached('me/notifications')
        : await _api.get('me/notifications', query: {'before_id': beforeId});
    final map = json is Map ? json : const {};
    return NotificationPage(
      [for (final e in (map['data'] as List? ?? const [])) AppNotification.fromJson(Map<String, dynamic>.from(e as Map))],
      hasMore: map['has_more'] as bool? ?? false,
    );
  }

  /// No ids = everything.
  Future<void> markRead([List<int>? ids]) =>
      _api.post('me/notifications/read', body: {if (ids != null) 'ids': ids}, silent: true);
}
