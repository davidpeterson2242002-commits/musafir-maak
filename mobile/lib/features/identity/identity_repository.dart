import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../models/identity.dart';

final identityRepositoryProvider = Provider<IdentityRepository>(
  (ref) => IdentityRepository(ref.watch(apiClientProvider)),
);

final identityProvider = FutureProvider.autoDispose<Identity>(
  (ref) => ref.watch(identityRepositoryProvider).fetch(),
);

class IdentityRepository {
  IdentityRepository(this._api);

  final ApiClient _api;

  Future<Identity> fetch() async => _parse(await _api.get('me/identity'));

  /// Uploads (or replaces) one photo. `kind`: selfie, passport, national_id_front, national_id_back.
  Future<Identity> upload(String kind, String filePath) async => _parse(
        await _api.upload('me/identity/documents', field: 'file', filePath: filePath, fields: {'kind': kind}),
      );

  /// Sends the uploaded photos for review.
  Future<Identity> submit(IdDocumentType type) async =>
      _parse(await _api.post('me/identity/submit', body: {'document_type': type.apiValue}));

  Identity _parse(dynamic json) => Identity.fromJson(Map<String, dynamic>.from(json as Map));
}
