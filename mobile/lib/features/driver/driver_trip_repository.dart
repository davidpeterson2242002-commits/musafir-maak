import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/storage/cache_store.dart';
import '../../models/driver_trip.dart';
import '../auth/session_controller.dart';

/// Whether the app shows the driver side (spec: one app, switch between
/// passenger and driver mode). Only approved drivers can turn it on.
///
/// The choice is the driver's: it stays on this phone through restarts and
/// logging out and back in (kept per account, outside the `user.` cache that
/// logout wipes), and changes only when they switch it themselves.
final driverModeProvider = NotifierProvider<DriverModeController, bool>(DriverModeController.new);

class DriverModeController extends Notifier<bool> {
  static String _key(int userId) => 'driver_mode.$userId';

  @override
  bool build() {
    final user = ref.watch(currentUserProvider.select((u) => u == null ? null : (id: u.id, isDriver: u.isDriver)));
    if (user == null || !user.isDriver) return false;
    return ref.read(cacheStoreProvider).read(_key(user.id))?.data == true;
  }

  void set(bool driverMode) {
    final user = ref.read(currentUserProvider);
    if (user == null) return;
    state = driverMode && user.isDriver;
    ref.read(cacheStoreProvider).write(_key(user.id), state);
  }
}

final driverTripRepositoryProvider = Provider<DriverTripRepository>(
  (ref) => DriverTripRepository(ref.watch(apiClientProvider)),
);

/// upcoming / ongoing / past / cancelled.
final driverTripsProvider = FutureProvider.autoDispose.family<List<DriverTrip>, String>(
  (ref, tab) => ref.watch(driverTripRepositoryProvider).list(tab),
);

/// One trip with its bookings.
final driverTripProvider = FutureProvider.autoDispose.family<DriverTrip, int>(
  (ref, id) => ref.watch(driverTripRepositoryProvider).get(id),
);

/// Pending booking requests across all the driver's trips, soonest to expire first.
final driverRequestsProvider = FutureProvider.autoDispose<List<DriverBooking>>(
  (ref) => ref.watch(driverTripRepositoryProvider).requests(),
);

class DriverTripRepository {
  DriverTripRepository(this._api);

  final ApiClient _api;

  Future<List<DriverTrip>> list(String tab) async {
    final json = await _api.get('driver/trips', query: {'tab': tab});
    final items = json is Map ? (json['data'] as List? ?? const []) : (json as List);
    return items.map((e) => DriverTrip.fromJson(Map<String, dynamic>.from(e as Map))).toList();
  }

  Future<DriverTrip> get(int id) async => _trip(await _api.get('driver/trips/$id'));

  Future<DriverTrip> create(TripDraft draft) async =>
      _trip(await _api.post('driver/trips', body: draft.toJson(creating: true)));

  Future<DriverTrip> update(int id, TripDraft draft) async =>
      _trip(await _api.put('driver/trips/$id', body: draft.toJson(creating: false)));

  Future<DriverTrip> cancel(int id, String reason) async =>
      _trip(await _api.post('driver/trips/$id/cancel', body: {'reason': reason}));

  Future<DriverTrip> depart(int id) async => _trip(await _api.post('driver/trips/$id/depart'));

  Future<DriverTrip> complete(int id) async => _trip(await _api.post('driver/trips/$id/complete'));

  Future<List<DriverBooking>> requests() async {
    final json = await _api.get('driver/requests');
    return (json as List).map((e) => DriverBooking.fromJson(Map<String, dynamic>.from(e as Map))).toList();
  }

  Future<void> accept(int bookingId) => _api.post('driver/bookings/$bookingId/accept');

  Future<void> reject(int bookingId) => _api.post('driver/bookings/$bookingId/reject');

  Future<void> markNoShow(int bookingId) => _api.post('driver/bookings/$bookingId/no-show');

  DriverTrip _trip(dynamic json) => DriverTrip.fromJson(Map<String, dynamic>.from(json as Map));
}
