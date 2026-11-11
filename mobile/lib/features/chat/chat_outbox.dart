import 'dart:async';
import 'dart:math';

import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/storage/cache_store.dart';
import '../../models/chat.dart';
import '../auth/session_controller.dart';
import 'chat_repository.dart';

/// A message typed but not yet confirmed by the server.
class OutboxItem {
  const OutboxItem({
    required this.clientId,
    required this.conversationId,
    required this.body,
    required this.createdAt,
    this.error,
  });

  final String clientId;
  final int conversationId;
  final String body;
  final DateTime createdAt;

  /// Set when the server refused it for good (chat closed, blocked…): shown with a
  /// red mark; the person can try again or delete it.
  final String? error;

  bool get failed => error != null;

  OutboxItem withError(String? error) =>
      OutboxItem(clientId: clientId, conversationId: conversationId, body: body, createdAt: createdAt, error: error);

  Map<String, dynamic> toJson() => {
        'client_id': clientId,
        'conversation_id': conversationId,
        'body': body,
        'created_at': createdAt.toIso8601String(),
        if (error != null) 'error': error,
      };

  factory OutboxItem.fromJson(Map<String, dynamic> json) => OutboxItem(
        clientId: json['client_id'] as String,
        conversationId: (json['conversation_id'] as num).toInt(),
        body: json['body'] as String,
        createdAt: DateTime.tryParse(json['created_at'] as String? ?? '') ?? DateTime.now(),
        error: json['error'] as String?,
      );
}

class OutboxState {
  const OutboxState({this.pending = const [], this.sent = const {}});

  /// Waiting (clock) or failed, oldest first.
  final List<OutboxItem> pending;

  /// Confirmed by the server this session, by client id — the chat screen swaps
  /// its clock bubble for the real message.
  final Map<String, ({int conversationId, ChatMessage message})> sent;

  List<OutboxItem> forConversation(int id) => pending.where((m) => m.conversationId == id).toList();

  List<ChatMessage> sentIn(int id) =>
      [for (final e in sent.values) if (e.conversationId == id) e.message];
}

/// WhatsApp-style sending: a message leaves the text box at once and shows with a
/// clock; it is kept on the phone (survives closing the app) and sent as soon as
/// there is a connection, retrying every few seconds, then gets its ticks.
final chatOutboxProvider = NotifierProvider<ChatOutbox, OutboxState>(ChatOutbox.new);

class ChatOutbox extends Notifier<OutboxState> with WidgetsBindingObserver {
  static const _key = '${CacheKeys.user}chat_outbox';
  static const _retryEvery = Duration(seconds: 5);

  Timer? _timer;
  bool _flushing = false;
  final _random = Random();

  @override
  OutboxState build() {
    // Another account on this phone starts with its own (empty) outbox.
    ref.watch(sessionProvider.select((s) => s.user?.id));
    WidgetsBinding.instance.addObserver(this);
    ref.onDispose(() {
      _timer?.cancel();
      WidgetsBinding.instance.removeObserver(this);
    });
    final raw = ref.read(cacheStoreProvider).read(_key)?.data;
    final pending = raw is List
        ? raw.whereType<Map>().map((e) => OutboxItem.fromJson(Map<String, dynamic>.from(e))).toList()
        : <OutboxItem>[];
    if (pending.isNotEmpty) Future.microtask(flush);
    _timer = Timer.periodic(_retryEvery, (_) => flush());
    return OutboxState(pending: pending);
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState lifecycle) {
    if (lifecycle == AppLifecycleState.resumed) flush();
  }

  /// Puts a message in the outbox and tries to send it right away.
  void enqueue(int conversationId, String body) {
    final item = OutboxItem(
      clientId: '${DateTime.now().microsecondsSinceEpoch}-${_random.nextInt(1 << 32)}',
      conversationId: conversationId,
      body: body,
      createdAt: DateTime.now(),
    );
    _set(OutboxState(pending: [...state.pending, item], sent: state.sent));
    flush();
  }

  void retry(OutboxItem item) {
    _set(OutboxState(
      pending: [for (final m in state.pending) m.clientId == item.clientId ? m.withError(null) : m],
      sent: state.sent,
    ));
    flush();
  }

  void remove(OutboxItem item) {
    _set(OutboxState(pending: state.pending.where((m) => m.clientId != item.clientId).toList(), sent: state.sent));
  }

  /// Sends waiting messages in order. Stops at the first connection problem
  /// (the rest would fail too) and tries again on the next tick.
  Future<void> flush() async {
    if (_flushing) return;
    _flushing = true;
    try {
      final repo = ref.read(chatRepositoryProvider);
      for (final item in List<OutboxItem>.of(state.pending)) {
        if (item.failed) continue;
        try {
          final message = await repo.send(item.conversationId, item.body, clientId: item.clientId);
          _set(OutboxState(
            pending: state.pending.where((m) => m.clientId != item.clientId).toList(),
            sent: {...state.sent, item.clientId: (conversationId: item.conversationId, message: message)},
          ));
          ref.invalidate(conversationsProvider);
        } on ApiException catch (e) {
          final temporary = e.isOffline ||
              e.kind == ApiErrorKind.server ||
              e.kind == ApiErrorKind.rateLimited ||
              e.kind == ApiErrorKind.unauthorized;
          if (temporary) break;
          _set(OutboxState(
            pending: [for (final m in state.pending) m.clientId == item.clientId ? m.withError(e.fieldError('body') ?? e.message) : m],
            sent: state.sent,
          ));
        }
      }
    } finally {
      _flushing = false;
    }
  }

  void _set(OutboxState next) {
    state = next;
    ref.read(cacheStoreProvider).write(_key, next.pending.map((m) => m.toJson()).toList());
  }
}
