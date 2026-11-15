import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../models/chat.dart';

final userRepositoryProvider = Provider<UserRepository>((ref) => UserRepository(ref.watch(apiClientProvider)));

final publicProfileProvider = FutureProvider.autoDispose.family<PublicProfile, int>(
  (ref, id) => ref.watch(userRepositoryProvider).profile(id),
);

final myBlocksProvider = FutureProvider.autoDispose((ref) => ref.watch(userRepositoryProvider).blocks());

class UserRepository {
  UserRepository(this._api);

  final ApiClient _api;

  Future<PublicProfile> profile(int userId) async =>
      PublicProfile.fromJson(Map<String, dynamic>.from(await _api.get('users/$userId') as Map));

  Future<void> block(int userId) => _api.post('users/$userId/block');

  Future<void> unblock(int userId) => _api.delete('users/$userId/block');

  /// People I blocked: `[{id, name, blocked_at}]`.
  Future<List<({int id, String name})>> blocks() async {
    final json = await _api.get('me/blocks');
    final items = json is Map ? (json['data'] as List? ?? const []) : (json as List);
    return [
      for (final e in items.cast<Map>()) (id: (e['id'] as num).toInt(), name: (e['name'] as String?) ?? '—'),
    ];
  }

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
