import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../core/storage/cache_store.dart';
import '../../models/parcel.dart';

final parcelRepositoryProvider = Provider<ParcelRepository>(
  (ref) => ParcelRepository(ref.watch(apiClientProvider), ref.watch(cacheStoreProvider)),
);

/// The sender's parcels: those in progress first, then finished ones.
final parcelsProvider = FutureProvider.autoDispose<List<Parcel>>(
  (ref) => ref.watch(parcelRepositoryProvider).list(),
);

final parcelProvider = FutureProvider.autoDispose.family<Parcel, int>(
  (ref, id) => ref.watch(parcelRepositoryProvider).get(id),
);

/// Like bookings, parcels are saved after each load so the delivery code and
/// the driver's details can be opened without a connection.
class ParcelRepository {
  ParcelRepository(this._api, this._cache);

  final ApiClient _api;
  final CacheStore _cache;

  static const _listKey = '${CacheKeys.user}parcels';
  static String _itemKey(int id) => '${CacheKeys.user}parcel.$id';

  Future<List<Parcel>> list() async {
    try {
      final items = [
        ..._items(await _api.get('parcels', query: {'tab': 'active'})),
        ..._items(await _api.get('parcels', query: {'tab': 'done'})),
      ];
      await _cache.write(_listKey, items);
      return _parseList(items);
    } on ApiException catch (e) {
      final cached = _cache.read(_listKey);
      if (e.isOffline && cached?.data is List) return _parseList(cached!.data as List);
      rethrow;
    }
  }

  Future<Parcel> get(int id) async {
    try {
      return await _store(await _api.get('parcels/$id'));
    } on ApiException catch (e) {
      final cached = _cache.read(_itemKey(id));
      if (e.isOffline && cached?.data is Map) return Parcel.fromJson(Map<String, dynamic>.from(cached!.data as Map));
      rethrow;
    }
  }

  Future<Parcel> create(
    int tripId, {
    required String description,
    required String size,
    required String photoPath,
    required String receiverName,
    required String receiverPhone,
    required bool senderPays,
    String? message,
  }) async {
    final json = await _api.upload(
      'trips/$tripId/parcels',
      field: 'photo',
      filePath: photoPath,
      fields: {
        'description': description.trim(),
        'size': size,
        'receiver_name': receiverName.trim(),
        'receiver_phone': receiverPhone.trim(),
        'payer': senderPays ? 'sender' : 'receiver',
        if (message != null && message.trim().isNotEmpty) 'message': message.trim(),
        'accept_prohibited': '1',
      },
    );
    return _store(json);
  }

  Future<Parcel> cancel(int id) async => _store(await _api.post('parcels/$id/cancel'));

  Future<Parcel> dispute(int id, String details) async =>
      _store(await _api.post('parcels/$id/dispute', body: {'details': details}));

  Future<Parcel> _store(dynamic json) async {
    final map = Map<String, dynamic>.from(json as Map);
    await _cache.write(_itemKey(map['id'] as int), map);
    return Parcel.fromJson(map);
  }

  /// Paginated lists arrive as `{data: [...], links, meta}`.
  List _items(dynamic json) => json is Map ? (json['data'] as List? ?? const []) : (json as List);

  List<Parcel> _parseList(List items) =>
      items.map((e) => Parcel.fromJson(Map<String, dynamic>.from(e as Map))).toList();
}
