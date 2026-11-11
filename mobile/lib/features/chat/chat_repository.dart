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
    final json = await _api.get('conversations');
    final items = json is Map ? (json['data'] as List? ?? const []) : (json as List);
    return items.map((e) => Conversation.fromJson(Map<String, dynamic>.from(e as Map))).toList();
  }

  /// The latest 50 messages, or only those newer than [afterId] (polling).
  /// Loading messages also marks the conversation as read.
  Future<ChatPage> messages(int conversationId, {int? afterId}) async => ChatPage.fromJson(
        await _api.get('conversations/$conversationId/messages', query: {if (afterId != null) 'after_id': afterId}),
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
