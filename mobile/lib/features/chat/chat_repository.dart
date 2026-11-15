import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../models/chat.dart';

final chatRepositoryProvider = Provider<ChatRepository>((ref) => ChatRepository(ref.watch(apiClientProvider)));

final conversationsProvider = FutureProvider.autoDispose<List<Conversation>>(
  (ref) => ref.watch(chatRepositoryProvider).conversations(),
);

/// Total unread messages, for the badge on the messages tab. 0 on any failure.
final unreadCountProvider = FutureProvider.autoDispose<int>((ref) async {
  try {
    return await ref.watch(chatRepositoryProvider).unreadCount();
  } on ApiException {
    return 0;
  }
});

class ChatRepository {
  ChatRepository(this._api);

  final ApiClient _api;

  Future<List<Conversation>> conversations() async {
    final items = await fetchAllPages(_api, 'conversations', const {}, maxPages: 3);
    return items.map((e) => Conversation.fromJson(Map<String, dynamic>.from(e as Map))).toList();
  }

  /// Loading messages also marks the conversation as read.
  /// Newest 50, or the 50 after [afterId] (polling) / before [beforeId] (older history).
  Future<ChatPage> messages(int conversationId, {int? afterId, int? beforeId}) async => ChatPage.fromJson(
        await _api.get('conversations/$conversationId/messages', query: {
          if (afterId != null) 'after_id': afterId,
          if (beforeId != null) 'before_id': beforeId,
        }),
      );

  /// [clientId] makes a retry safe: the server returns the first copy instead of a duplicate.
  Future<ChatMessage> send(int conversationId, String body, {String? clientId}) async {
    final json = await _api.post(
      'conversations/$conversationId/messages',
      body: {'body': body, if (clientId != null) 'client_id': clientId},
    );
    return ChatMessage.fromJson(Map<String, dynamic>.from(json as Map));
  }

  Future<int> unreadCount() async {
    final json = await _api.get('conversations/unread-count');
    return json is Map ? (json['unread'] as num?)?.toInt() ?? 0 : 0;
  }
}
