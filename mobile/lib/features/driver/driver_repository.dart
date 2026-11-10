import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../models/driver.dart';
import '../../models/identity.dart';

final driverRepositoryProvider = Provider<DriverRepository>((ref) => DriverRepository(ref.watch(apiClientProvider)));

/// The user's driver account; null when they never started registration.
final driverAccountProvider = FutureProvider.autoDispose<DriverAccount?>(
  (ref) => ref.watch(driverRepositoryProvider).fetch(),
);

class DriverRepository {
  DriverRepository(this._api);

  final ApiClient _api;

  Future<DriverAccount?> fetch() async {
    final json = await _api.get('driver');
    return json is Map ? DriverAccount.fromJson(Map<String, dynamic>.from(json)) : null;
  }

  /// "سجّل كسائق": creates the draft registration (safe to call twice).
  Future<DriverAccount> start() async => _account(await _api.post('driver'));

  Future<DriverAccount> uploadLicence(String filePath) async => _account(
        await _api.upload('driver/documents', field: 'file', filePath: filePath, fields: {'kind': 'driving_licence'}),
      );

  /// Sends identity + licence + vehicles for review in one application.
  Future<DriverAccount> submit(IdDocumentType idType) async => _account(
        await _api.post('driver/submit', body: {'id_document_type': idType.apiValue, 'accept_terms': true}),
      );

  Future<Vehicle> saveVehicle({
    int? id,
    required String type,
    required String make,
    required String model,
    required String color,
    int? year,
    required String plateNumber,
    required int seats,
  }) async {
    final body = {
      'type': type,
      'make': make.trim(),
      'model': model.trim(),
      'color': color.trim(),
      'year': year,
      'plate_number': plateNumber.trim(),
      'seats': seats,
    };
    final json = id == null ? await _api.post('driver/vehicles', body: body) : await _api.put('driver/vehicles/$id', body: body);
    return _vehicle(json);
  }

  /// `kind`: vehicle_front, vehicle_back, vehicle_side, vehicle_interior, vehicle_registration.
  Future<Vehicle> uploadVehiclePhoto(int vehicleId, String kind, String filePath) async => _vehicle(
        await _api.upload('driver/vehicles/$vehicleId/documents', field: 'file', filePath: filePath, fields: {'kind': kind}),
      );

  /// For a vehicle added after the driver was approved: it gets its own review.
  Future<Vehicle> submitVehicle(int vehicleId) async => _vehicle(await _api.post('driver/vehicles/$vehicleId/submit'));

  Future<void> deleteVehicle(int vehicleId) => _api.delete('driver/vehicles/$vehicleId');

  DriverAccount _account(dynamic json) => DriverAccount.fromJson(Map<String, dynamic>.from(json as Map));

  Vehicle _vehicle(dynamic json) => Vehicle.fromJson(Map<String, dynamic>.from(json as Map));
}
