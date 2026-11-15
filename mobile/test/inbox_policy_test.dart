import 'package:flutter_test/flutter_test.dart';
import 'package:musafir_maak/features/notifications/notifications_repository.dart';
import 'package:musafir_maak/models/booking.dart';
import 'package:musafir_maak/models/city.dart';
import 'package:musafir_maak/models/misc.dart';
import 'package:musafir_maak/models/trip.dart';

void main() {
  test('notification from GET me/notifications', () {
    final n = AppNotification.fromJson({
      'id': 5,
      'event': 'route_alert_match',
      'title': 'رحلة جديدة على طريقك',
      'body': 'الخرطوم ← مدني',
      'data': {'event': 'route_alert_match', 'trip_id': 9},
      'read': false,
      'created_at': '2026-11-16T07:30:00+02:00',
    });
    expect(n.id, 5);
    expect(n.read, isFalse);
    expect(n.data['trip_id'], 9);
    expect(n.createdAt, DateTime.utc(2026, 11, 16, 7, 30));
    expect(n.markedRead().read, isTrue);
  });

  test('cancel policy', () {
    final p = CancelPolicy.fromJson({'late_window_hours': 3, 'late_now': true, 'late_limit': 3, 'window_days': 30, 'restriction_days': 7});
    expect(p.lateNow, isTrue);
    expect(p.lateWindowHours, 3);
    expect(p.restrictionDays, 7);
  });

  test('closed city, reopen alert, search result flag', () {
    expect(City.fromJson({'id': 1, 'name': 'كسلا', 'available': false}).available, isFalse);
    expect(City.fromJson({'id': 1, 'name': 'كسلا'}).available, isTrue);
    final alert = RouteAlert.fromJson({'id': 2, 'origin': {'name': 'أ'}, 'destination': {'name': 'ب'}, 'kind': 'reopen'});
    expect(alert.isReopen, isTrue);
    expect(SearchResult.fromJson({'data': [], 'closed': true, 'reopen_alert': true}).reopenAlert, isTrue);
  });
}
