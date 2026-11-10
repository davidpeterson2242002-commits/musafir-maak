import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../models/driver_money.dart';

final driverMoneyRepositoryProvider = Provider<DriverMoneyRepository>(
  (ref) => DriverMoneyRepository(ref.watch(apiClientProvider)),
);

/// Parcel requests waiting for the driver's answer, soonest to expire first.
final driverParcelRequestsProvider = FutureProvider.autoDispose<List<DriverParcel>>(
  (ref) => ref.watch(driverMoneyRepositoryProvider).parcels('pending'),
);

final driverBalanceProvider = FutureProvider.autoDispose<DriverBalance>(
  (ref) => ref.watch(driverMoneyRepositoryProvider).balance(),
);

final ledgerProvider = FutureProvider.autoDispose<List<LedgerEntry>>(
  (ref) => ref.watch(driverMoneyRepositoryProvider).ledger(),
);

final settlementsProvider = FutureProvider.autoDispose<List<SettlementPayment>>(
  (ref) => ref.watch(driverMoneyRepositoryProvider).settlements(),
);

final paymentMethodsProvider = FutureProvider.autoDispose<List<PaymentMethod>>(
  (ref) => ref.watch(driverMoneyRepositoryProvider).paymentMethods(),
);

class DriverMoneyRepository {
  DriverMoneyRepository(this._api);

  final ApiClient _api;

  // ---- Parcels ----

  /// `status`: pending / active / done.
  Future<List<DriverParcel>> parcels(String status) async =>
      _list(await _api.get('driver/parcels', query: {'status': status}), DriverParcel.fromJson);

  Future<void> acceptParcel(int id) => _api.post('driver/parcels/$id/accept');

  Future<void> rejectParcel(int id) => _api.post('driver/parcels/$id/reject');

  /// The driver's own photo of the parcel as received (evidence if there's a dispute).
  Future<void> pickUpParcel(int id, String photoPath) =>
      _api.upload('driver/parcels/$id/pickup', field: 'photo', filePath: photoPath);

  Future<void> refuseParcel(int id, String reason) => _api.post('driver/parcels/$id/refuse', body: {'reason': reason});

  /// The 4-digit code the receiver got on WhatsApp.
  Future<void> deliverParcel(int id, String code) => _api.post('driver/parcels/$id/deliver', body: {'code': code});

  // ---- Balance and payments ----

  Future<DriverBalance> balance() async =>
      DriverBalance.fromJson(Map<String, dynamic>.from(await _api.get('driver/balance') as Map));

  Future<List<LedgerEntry>> ledger() async => _list(await _api.get('driver/ledger'), LedgerEntry.fromJson);

  Future<List<SettlementPayment>> settlements() async =>
      _list(await _api.get('driver/settlements'), SettlementPayment.fromJson);

  Future<List<PaymentMethod>> paymentMethods() async => _list(await _api.get('payment-methods'), PaymentMethod.fromJson);

  /// Reports a payment made outside the app; finance confirms it against the receipt.
  Future<void> submitPayment({
    required int methodId,
    required int amount,
    required String receiptPath,
    String? reference,
  }) =>
      _api.upload(
        'driver/settlements',
        field: 'receipt',
        filePath: receiptPath,
        fields: {
          'payment_method_id': methodId,
          'purpose': 'settlement',
          'amount': amount,
          if (reference != null && reference.trim().isNotEmpty) 'reference_number': reference.trim(),
        },
      );

  /// Lists arrive bare or paginated as `{data: [...], links, meta}`.
  List<T> _list<T>(dynamic json, T Function(Map<String, dynamic>) parse) {
    final items = json is Map ? (json['data'] as List? ?? const []) : (json as List);
    return items.map((e) => parse(Map<String, dynamic>.from(e as Map))).toList();
  }
}
