import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../models/user.dart';

final profileRepositoryProvider = Provider<ProfileRepository>(
  (ref) => ProfileRepository(ref.watch(apiClientProvider)),
);

class ProfileRepository {
  ProfileRepository(this._api);

  final ApiClient _api;

  /// Raw JSON, so the session can cache exactly what the server sent.
  Future<Map<String, dynamic>> fetchMe() async => Map<String, dynamic>.from(await _api.get('me') as Map);

  /// First-time setup and later edits use the same endpoint; send only what changed.
  Future<Map<String, dynamic>> update({
    String? name,
    Gender? gender,
    int? cityId,
    String? email,
    bool clearEmail = false,
    String? whatsappPhone,
    bool clearWhatsapp = false,
    bool acceptTerms = false,
  }) async {
    final body = <String, dynamic>{
      if (name != null) 'name': name,
      if (gender != null) 'gender': gender.name,
      if (cityId != null) 'city_id': cityId,
      if (email != null) 'email': email else if (clearEmail) 'email': null,
      if (whatsappPhone != null) 'whatsapp_phone': whatsappPhone else if (clearWhatsapp) 'whatsapp_phone': null,
      if (acceptTerms) 'accept_terms': true,
    };
    return Map<String, dynamic>.from(await _api.put('me', body: body) as Map);
  }

  Future<Map<String, dynamic>> uploadPhoto(String filePath) async =>
      Map<String, dynamic>.from(await _api.upload('me/photo', field: 'photo', filePath: filePath) as Map);

  Future<void> deleteAccount() => _api.delete('me');
}
