import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../core/storage/cache_store.dart';
import '../../models/booking.dart';

final bookingRepositoryProvider = Provider<BookingRepository>(
  (ref) => BookingRepository(ref.watch(apiClientProvider), ref.watch(cacheStoreProvider)),
);

/// upcoming / past / cancelled (spec P8).
final bookingsProvider = FutureProvider.autoDispose.family<List<Booking>, String>(
  (ref, tab) => ref.watch(bookingRepositoryProvider).list(tab),
);

final bookingProvider = FutureProvider.autoDispose.family<Booking, int>(
  (ref, id) => ref.watch(bookingRepositoryProvider).get(id),
);

/// Result of cancelling: the booking plus any late-cancellation consequence to tell the user.
class CancelOutcome {
  const CancelOutcome(this.booking, {this.warning});

  final Booking booking;

  /// "One more late cancellation and booking is paused for a week" and similar.
  final String? warning;
}

/// Bookings are saved after every successful load so they open without a
/// connection — on the road is exactly when the passenger needs the driver's
/// phone, plate and pickup point (spec 12, offline viewing).
class BookingRepository {
  BookingRepository(this._api, this._cache);

  final ApiClient _api;
  final CacheStore _cache;

  static String _listKey(String tab) => '${CacheKeys.user}bookings.$tab';
  static String _itemKey(int id) => '${CacheKeys.user}booking.$id';

  Future<List<Booking>> list(String tab) async {
    try {
      final json = await _api.get('bookings', query: {'tab': tab});
      // Paginated: {data: [...], links, meta}. The first page (20) covers every realistic passenger.
      final items = json is Map ? (json['data'] as List? ?? const []) : (json as List);
      await _cache.write(_listKey(tab), items);
      for (final item in items) {
        await _cache.write(_itemKey((item as Map)['id'] as int), {'data': item});
      }
      return _parseList(items);
    } on ApiException catch (e) {
      final cached = _cache.read(_listKey(tab));
      if (e.isOffline && cached?.data is List) return _parseList(cached!.data as List);
      rethrow;
    }
  }

  Future<Booking> get(int id) async {
    try {
      final json = await _api.get('bookings/$id');
      final envelope = _envelope(json);
      await _cache.write(_itemKey(id), envelope);
      return _parse(envelope);
    } on ApiException catch (e) {
      final cached = _cache.read(_itemKey(id));
      if (e.isOffline && cached?.data is Map) return _parse(Map<String, dynamic>.from(cached!.data as Map));
      rethrow;
    }
  }

  Future<Booking> create(
    int tripId, {
    required int seats,
    int? pickupPointId,
    int extraBags = 0,
    bool includesFamily = false,
    String? message,
  }) async {
    final json = await _api.post('trips/$tripId/bookings', body: {
      'seats': seats,
      if (pickupPointId != null) 'pickup_point_id': pickupPointId,
      'extra_bags': extraBags,
      'includes_family': includesFamily,
      if (message != null && message.trim().isNotEmpty) 'message': message.trim(),
    });
    return _store(json);
  }

  Future<CancelOutcome> cancel(int id, {String? reason}) async {
    final json = await _api.post('bookings/$id/cancel', body: {
      if (reason != null && reason.trim().isNotEmpty) 'reason': reason.trim(),
    });
    final warning = json is Map ? json['warning'] : null;
    return CancelOutcome(await _store(json), warning: warning is String ? warning : null);
  }

  Future<Booking> disputeNoShow(int id, String details) async =>
      _store(await _api.post('bookings/$id/dispute-no-show', body: {'details': details}));

  Future<Booking> _store(dynamic json) async {
    final envelope = _envelope(json);
    final booking = _parse(envelope);
    await _cache.write(_itemKey(booking.id), envelope);
    return booking;
  }

  /// Responses are either the bare booking (already unwrapped) or
  /// `{data: booking, …extras}`; normalise to the second form.
  Map<String, dynamic> _envelope(dynamic json) {
    final map = Map<String, dynamic>.from(json as Map);
    return map['data'] is Map && map.containsKey('data') && !map.containsKey('trip') ? map : {'data': map};
  }

  Booking _parse(Map<String, dynamic> envelope) =>
      Booking.fromJson(Map<String, dynamic>.from(envelope['data'] as Map), envelope: envelope);

  List<Booking> _parseList(List items) =>
      items.map((e) => Booking.fromJson(Map<String, dynamic>.from(e as Map))).toList();
}
