import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/formatters.dart';
import '../../core/widgets/common.dart';
import '../../models/chat.dart';
import '../reports/report_sheet.dart';
import 'chat_repository.dart';

/// "الرسائل" tab (spec P10): one conversation per booking or parcel.
class ConversationsScreen extends ConsumerWidget {
  const ConversationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final text = Theme.of(context).textTheme;
    final conversations = ref.watch(conversationsProvider);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 8),
          child: Text(S.tabMessages, style: text.displaySmall),
        ),
        Expanded(
          child: conversations.when(
            skipLoadingOnRefresh: true,
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (error, _) => LoadError(error: error, onRetry: () => ref.invalidate(conversationsProvider)),
            data: (list) {
              if (list.isEmpty) {
                return const EmptyState(
                  icon: Icons.chat_bubble_outline_rounded,
                  title: S.noMessagesTitle,
                  body: S.noMessagesBody,
                );
              }

              return RefreshIndicator(
                color: AppColors.green,
                onRefresh: () async {
                  ref.invalidate(conversationsProvider);
                  ref.invalidate(unreadCountProvider);
                },
                child: ListView.separated(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.only(bottom: 24),
                  itemCount: list.length,
                  separatorBuilder: (_, __) => const Divider(indent: 84),
                  itemBuilder: (context, index) => _ConversationTile(
                    conversation: list[index],
                    onTap: () async {
                      await context.push('/chats/${list[index].id}');
                      ref.invalidate(conversationsProvider);
                      ref.invalidate(unreadCountProvider);
                    },
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}

class _ConversationTile extends StatelessWidget {
  const _ConversationTile({required this.conversation, required this.onTap});

  final Conversation conversation;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final unread = conversation.unread > 0;
    final last = conversation.lastBody;

    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
        child: Row(
          children: [
            AppAvatar(photoUrl: conversation.other.photoUrl, name: conversation.other.name, size: 52),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(conversation.other.name, style: text.titleMedium, overflow: TextOverflow.ellipsis),
                      ),
                      if (conversation.lastAt != null) Text(Fmt.time(conversation.lastAt!), style: text.bodySmall),
                    ],
                  ),
                  Text(conversation.summary, style: text.bodySmall, maxLines: 1, overflow: TextOverflow.ellipsis),
                  if (last != null)
                    Text(
                      '${conversation.lastMine ? S.you : ''}$last',
                      style: text.bodyMedium?.copyWith(
                        color: unread ? AppColors.ink : AppColors.inkMuted,
                        fontWeight: unread ? FontWeight.w700 : FontWeight.w400,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                ],
              ),
            ),
            if (unread) ...[
              const SizedBox(width: 8),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(color: AppColors.green, borderRadius: BorderRadius.circular(12)),
                child: Text(
                  '${conversation.unread}',
                  style: const TextStyle(color: Colors.white, fontSize: 12.5, fontWeight: FontWeight.w700),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// One conversation. New messages are fetched every few seconds while the
/// screen is open (polling instead of websockets: simpler and kinder to weak
/// connections); a push notification covers the rest.
class ChatScreen extends ConsumerStatefulWidget {
  const ChatScreen({super.key, required this.conversationId});

  final int conversationId;

  @override
  ConsumerState<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends ConsumerState<ChatScreen> {
  static const _pollEvery = Duration(seconds: 6);

  final _input = TextEditingController();
  final _scroll = ScrollController();
  final List<ChatMessage> _messages = [];
  Conversation? _conversation;
  Timer? _poller;
  Object? _loadError;
  bool _loading = true;
  bool _sending = false;
  bool _polling = false;

  @override
  void initState() {
    super.initState();
    _load();
    _poller = Timer.periodic(_pollEvery, (_) => _poll());
  }

  @override
  void dispose() {
    _poller?.cancel();
    _input.dispose();
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _loadError = null;
    });
    try {
      final page = await ref.read(chatRepositoryProvider).messages(widget.conversationId);
      if (!mounted) return;
      setState(() {
        _messages
          ..clear()
          ..addAll(page.messages);
        _conversation = page.conversation;
        _loading = false;
      });
      _jumpToEnd();
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _loadError = e;
          _loading = false;
        });
      }
    }
  }

  Future<void> _poll() async {
    if (_polling || _loading || _loadError != null || !mounted) return;
    _polling = true;
    try {
      final lastId = _messages.isEmpty ? null : _messages.last.id;
      final page = await ref.read(chatRepositoryProvider).messages(widget.conversationId, afterId: lastId);
      if (!mounted) return;
      final known = _messages.map((m) => m.id).toSet();
      final fresh = page.messages.where((m) => !known.contains(m.id)).toList();
      setState(() {
        _messages.addAll(fresh);
        _conversation = page.conversation ?? _conversation;
      });
      if (fresh.isNotEmpty) _jumpToEnd();
    } on ApiException {
      // Offline or slow: the next tick tries again; the banner already says we're offline.
    } finally {
      _polling = false;
    }
  }

  Future<void> _send() async {
    final body = _input.text.trim();
    if (body.isEmpty || _sending) return;

    setState(() => _sending = true);
    try {
      final message = await ref.read(chatRepositoryProvider).send(widget.conversationId, body);
      if (!mounted) return;
      _input.clear();
      setState(() {
        if (!_messages.any((m) => m.id == message.id)) _messages.add(message);
      });
      _jumpToEnd();
    } on ApiException catch (e) {
      // The text stays in the box so nothing typed is lost.
      if (mounted) showMessage(context, e.isOffline ? S.messageFailed : (e.fieldError('body') ?? e.message));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  void _jumpToEnd() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scroll.hasClients) {
        _scroll.animateTo(
          _scroll.position.maxScrollExtent,
          duration: const Duration(milliseconds: 200),
          curve: Curves.easeOut,
        );
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final conversation = _conversation;

    return Scaffold(
      appBar: AppBar(
        backgroundColor: AppColors.surface,
        scrolledUnderElevation: 0,
        titleSpacing: 0,
        title: conversation == null
            ? null
            : InkWell(
                onTap: () => context.push('/users/${conversation.other.id}'),
                child: Row(
                  children: [
                    AppAvatar(photoUrl: conversation.other.photoUrl, name: conversation.other.name, size: 38),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(conversation.other.name, style: text.titleMedium, overflow: TextOverflow.ellipsis),
                          Text(conversation.summary, style: text.bodySmall, maxLines: 1, overflow: TextOverflow.ellipsis),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
        actions: [
          if (conversation != null)
            IconButton(
              tooltip: S.report,
              icon: const Icon(Icons.flag_outlined),
              onPressed: () => showReportSheet(
                context,
                userId: conversation.other.id,
                subjectType: 'conversation',
                subjectId: conversation.id,
              ),
            ),
        ],
      ),
      body: Column(
        children: [
          const OfflineBanner(),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _loadError != null
                    ? LoadError(error: _loadError!, onRetry: _load)
                    : _messages.isEmpty
                        ? Center(
                            child: Padding(
                              padding: const EdgeInsets.all(32),
                              child: Text(
                                S.noMessagesYet,
                                style: text.bodyMedium?.copyWith(color: AppColors.inkMuted),
                                textAlign: TextAlign.center,
                              ),
                            ),
                          )
                        : ListView.builder(
                            controller: _scroll,
                            padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
                            itemCount: _messages.length,
                            itemBuilder: (context, index) => _Bubble(message: _messages[index]),
                          ),
          ),
          if (conversation != null && !conversation.open)
            Container(
              width: double.infinity,
              color: AppColors.background,
              padding: const EdgeInsets.all(14),
              child: SafeArea(
                top: false,
                child: Text(
                  '${S.chatClosed} — ${conversation.statusLabel}',
                  style: text.bodyMedium?.copyWith(color: AppColors.inkMuted),
                  textAlign: TextAlign.center,
                ),
              ),
            )
          else if (!_loading && _loadError == null)
            Container(
              decoration: const BoxDecoration(
                color: AppColors.surface,
                border: Border(top: BorderSide(color: AppColors.line)),
              ),
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
              child: SafeArea(
                top: false,
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _input,
                        minLines: 1,
                        maxLines: 4,
                        maxLength: 2000,
                        textInputAction: TextInputAction.newline,
                        decoration: AppTheme.input(hint: S.typeMessage).copyWith(counterText: ''),
                      ),
                    ),
                    const SizedBox(width: 8),
                    IconButton(
                      onPressed: _sending ? null : _send,
                      style: IconButton.styleFrom(
                        backgroundColor: AppColors.green,
                        foregroundColor: Colors.white,
                        disabledBackgroundColor: AppColors.line,
                        fixedSize: const Size(50, 50),
                      ),
                      // Mirrors in RTL so the arrow points the way the message travels.
                      icon: const Icon(Icons.send_rounded),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.message});

  final ChatMessage message;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final mine = message.mine;

    return Align(
      // Mine on the start side (right in Arabic), theirs on the end side.
      alignment: mine ? AlignmentDirectional.centerStart : AlignmentDirectional.centerEnd,
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: 4),
        padding: const EdgeInsets.fromLTRB(14, 9, 14, 7),
        constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * 0.78),
        decoration: BoxDecoration(
          color: mine ? AppColors.green : AppColors.surface,
          borderRadius: BorderRadius.circular(16),
          border: mine ? null : Border.all(color: AppColors.line),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(message.body, style: text.bodyLarge?.copyWith(color: mine ? Colors.white : AppColors.ink, height: 1.5)),
            if (message.wasMasked && mine) ...[
              const SizedBox(height: 4),
              Text(S.numberHidden, style: TextStyle(fontSize: 12, height: 1.4, color: Colors.white.withValues(alpha: 0.85))),
            ],
            if (message.createdAt != null)
              Text(
                Fmt.time(message.createdAt!),
                style: TextStyle(fontSize: 11.5, color: mine ? Colors.white.withValues(alpha: 0.8) : AppColors.inkMuted),
              ),
          ],
        ),
      ),
    );
  }
}
