import 'package:flutter_test/flutter_test.dart';
import 'package:musafir_maak/features/push/push_routes.dart';

void main() {
  test('chat messages open the conversation', () {
    expect(pushRoute({'event': 'new_chat_message', 'conversation_id': '12'}), '/chats/12');
  });

  test('driver events open the trip', () {
    expect(pushRoute({'event': 'booking_requested', 'booking_id': '3', 'trip_id': '9'}), '/driver/trips/9');
    expect(pushRoute({'event': 'parcel_requested', 'parcel_id': '4', 'trip_id': '9'}), '/driver/trips/9');
    expect(pushRoute({'event': 'booking_reminder', 'parcel_id': '4'}), '/');
  });

  test('passenger events open the booking or parcel', () {
    expect(pushRoute({'event': 'booking_accepted', 'booking_id': '3', 'trip_id': '9'}), '/bookings/3');
    expect(pushRoute({'event': 'trip_cancelled', 'booking_id': '3', 'trip_id': '9'}), '/bookings/3');
    expect(pushRoute({'event': 'parcel_delivered', 'parcel_id': '4'}), '/parcels/4');
  });

  test('trip reminder: passenger booking, else driver trip', () {
    expect(pushRoute({'event': 'trip_reminder', 'booking_id': '3', 'trip_id': '9'}), '/bookings/3');
    expect(pushRoute({'event': 'trip_reminder', 'trip_id': '9'}), '/driver/trips/9');
  });

  test('account and money events', () {
    expect(pushRoute({'event': 'payment_confirmed', 'settlement_request_id': '1'}), '/driver/balance');
    expect(pushRoute({'event': 'driver_approved'}), '/driver/register');
    expect(pushRoute({'event': 'id_rejected'}), '/identity');
    expect(pushRoute({'event': 'route_alert_match', 'trip_id': '5'}), '/trips/5');
    expect(pushRoute({'event': 'broadcast', 'broadcast_id': '2'}), '/');
    expect(pushRoute({}), '/');
  });
}
