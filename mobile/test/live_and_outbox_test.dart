import 'package:flutter_test/flutter_test.dart';
import 'package:musafir_maak/features/chat/chat_outbox.dart';
import 'package:musafir_maak/features/live/live_updates.dart';
import 'package:musafir_maak/models/chat.dart';

void main() {
  test('badges parse driver counts and statuses', () {
    final b = LiveBadges.fromJson({
      'unread_messages': 2,
      'driver': {'pending_requests': 1, 'pending_parcels': 3},
      'bookings': [
        {'id': 5, 'status': 'accepted'},
      ],
      'parcels': [],
      'pending_ratings': 1,
    });
    expect(b.unreadMessages, 2);
    expect(b.driverPending, 4);
    expect(b.bookingStatuses, {5: 'accepted'});
    expect(b.pendingRatings, 1);
  });

  test('badges without driver section', () {
    final b = LiveBadges.fromJson({'unread_messages': 0, 'driver': null, 'bookings': [], 'parcels': []});
    expect(b.driverPending, 0);
    expect(b.bookingStatuses, isEmpty);
  });

  test('outbox item survives a round trip through the cache', () {
    final item = OutboxItem(clientId: 'a-1', conversationId: 9, body: 'سلام', createdAt: DateTime(2026, 11, 12, 10, 5));
    final back = OutboxItem.fromJson(item.toJson());
    expect(back.clientId, 'a-1');
    expect(back.conversationId, 9);
    expect(back.failed, isFalse);
    expect(back.withError('مغلقة').failed, isTrue);
  });

  test('message and conversation carry tick data', () {
    final m = ChatMessage.fromJson({'id': 1, 'mine': true, 'body': 'x', 'created_at': '2026-11-12T10:05:30+02:00', 'client_id': 'c1'});
    expect(m.clientId, 'c1');
    expect(m.sentAt, DateTime.utc(2026, 11, 12, 8, 5, 30));
  });
}
