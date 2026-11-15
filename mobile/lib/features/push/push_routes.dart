/// Where tapping a push notification should take the user, from the FCM `data`
/// payload the backend sends (`event` plus ids). Pure function so it can be tested.
String pushRoute(Map<String, dynamic> data) {
  int? id(String key) => int.tryParse('${data[key] ?? ''}');
  final event = '${data['event'] ?? ''}';
  final booking = id('booking_id');
  final parcel = id('parcel_id');
  final trip = id('trip_id');
  final conversation = id('conversation_id');

  if (conversation != null) return '/chats/$conversation';

  switch (event) {
    // For the driver.
    case 'booking_requested':
    case 'booking_reminder':
    case 'booking_cancelled_by_passenger':
    case 'parcel_requested':
      return trip != null ? '/driver/trips/$trip' : '/';
    case 'settlement_due_soon':
    case 'account_frozen':
    case 'account_unfrozen':
    case 'payment_confirmed':
    case 'payment_rejected':
    case 'weekly_statement':
      return '/driver/balance';
    case 'driver_approved':
    case 'driver_rejected':
    case 'vehicle_approved':
    case 'vehicle_rejected':
      return '/driver/register';
    case 'id_approved':
    case 'id_rejected':
      return '/identity';
    case 'route_alert_match':
      return trip != null ? '/trips/$trip' : '/alerts';
    case 'trip_reminder':
      // Passengers get their booking; the driver gets the trip.
      if (booking != null) return '/bookings/$booking';
      return trip != null ? '/driver/trips/$trip' : '/';
    case 'rate_trip':
      return '/'; // the home screen shows the rating cards
  }

  if (booking != null) return '/bookings/$booking';
  if (parcel != null) return '/parcels/$parcel';
  return '/';
}
