import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../models/chat.dart';

final userRepositoryProvider = Provider<UserRepository>((ref) => UserRepository(ref.watch(apiClientProvider)));

final publicProfileProvider = FutureProvider.autoDispose.family<PublicProfile, int>(
  (ref, id) => ref.watch(userRepositoryProvider).profile(id),
);

class UserRepository {
  UserRepository(this._api);

  final ApiClient _api;

  Future<PublicProfile> profile(int userId) async =>
      PublicProfile.fromJson(Map<String, dynamic>.from(await _api.get('users/$userId') as Map));

  Future<void> block(int userId) => _api.post('users/$userId/block');

  Future<void> unblock(int userId) => _api.delete('users/$userId/block');

  /// Returns the server's thank-you message.
  Future<String?> report({
    required int userId,
    required String reason,
    String? details,
    String? subjectType,
    int? subjectId,
  }) async {
    final json = await _api.post('reports', body: {
      'reported_user_id': userId,
      'reason': reason,
      if (details != null && details.trim().isNotEmpty) 'details': details.trim(),
      if (subjectType != null && subjectId != null) ...{'subject_type': subjectType, 'subject_id': subjectId},
    });
    return json is Map ? json['message'] as String? : null;
  }
}
