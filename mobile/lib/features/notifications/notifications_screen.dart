import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../core/widgets/common.dart';
import '../live/live_updates.dart';
import '../push/push_routes.dart';
import 'notifications_repository.dart';

/// Everything the user was notified about, newest first (the bell in the top bar).
/// Opening the list clears the bell's count; unread ones stay highlighted until left.
class NotificationsScreen extends ConsumerStatefulWidget {
  const NotificationsScreen({super.key});

  @override
  ConsumerState<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends ConsumerState<NotificationsScreen> {
  final List<AppNotification> _older = [];
  bool _hasMoreOlder = false;
  bool _loadingMore = false;
  bool _markedRead = false;

  Future<void> _markAllRead() async {
    if (_markedRead) return;
    _markedRead = true;
    try {
      await ref.read(notificationRepositoryProvider).markRead();
      ref.read(liveUpdatesProvider.notifier).refresh();
    } on ApiException {
      _markedRead = false; // try again next time
    }
  }

  Future<void> _loadMore(int beforeId) async {
    setState(() => _loadingMore = true);
    try {
      final page = await ref.read(notificationRepositoryProvider).page(beforeId: beforeId);
      setState(() {
        _older.addAll(page.items);
        _hasMoreOlder = page.hasMore;
      });
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message);
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  void _open(AppNotification n) {
    final route = pushRoute(n.data);
    if (route == '/') {
      context.go('/');
    } else {
      context.push(route);
    }
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final first = ref.watch(notificationsProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(S.notifications, style: text.titleLarge),
        backgroundColor: AppColors.surface,
        scrolledUnderElevation: 0,
      ),
      body: first.when(
        skipLoadingOnRefresh: true,
        skipLoadingOnReload: true,
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => LoadError(error: e, onRetry: () => ref.invalidate(notificationsProvider)),
        data: (page) {
          if (page.items.any((n) => !n.read)) Future.microtask(_markAllRead);
          final items = [...page.items, ..._older];
          final hasMore = _older.isEmpty ? page.hasMore : _hasMoreOlder;
          if (items.isEmpty) {
            return const EmptyState(
              icon: Icons.notifications_none_rounded,
              title: S.noNotifications,
              body: S.noNotificationsBody,
            );
          }

          return RefreshIndicator(
            color: AppColors.navy,
            onRefresh: () async {
              _older.clear();
              ref.invalidate(notificationsProvider);
            },
            child: ListView.separated(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.symmetric(vertical: 6),
              itemCount: items.length + (hasMore ? 1 : 0),
              separatorBuilder: (_, __) => const Divider(height: 1, indent: 68),
              itemBuilder: (context, i) {
                if (i == items.length) {
                  return Padding(
                    padding: const EdgeInsets.all(12),
                    child: Center(
                      child: _loadingMore
                          ? const CircularProgressIndicator()
                          : TextButton(onPressed: () => _loadMore(items.last.id), child: const Text(S.loadMore)),
                    ),
                  );
                }
                return _NotificationTile(item: items[i], onTap: () => _open(items[i]));
              },
            ),
          );
        },
      ),
    );
  }
}

class _NotificationTile extends StatelessWidget {
  const _NotificationTile({required this.item, required this.onTap});

  final AppNotification item;
  final VoidCallback onTap;

  static IconData iconFor(String event) {
    if (event.startsWith('booking') || event == 'no_show_marked') return Icons.event_seat_rounded;
    if (event.startsWith('parcel')) return Icons.inventory_2_rounded;
    if (event.startsWith('route')) return Icons.alt_route_rounded;
    if (event.startsWith('trip')) return Icons.directions_car_filled_rounded;
    if (event.contains('payment') || event.contains('settlement') || event.contains('frozen') || event.contains('statement')) {
      return Icons.account_balance_wallet_rounded;
    }
    if (event.endsWith('approved') || event.endsWith('rejected')) return Icons.verified_user_rounded;
    if (event == 'rate_trip') return Icons.star_rounded;
    if (event == 'broadcast') return Icons.campaign_rounded;
    return Icons.notifications_rounded;
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final unread = !item.read;

    return InkWell(
      onTap: onTap,
      child: Container(
        color: unread ? AppColors.navyTint.withValues(alpha: 0.55) : null,
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: unread ? AppColors.navy : AppColors.background,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(iconFor(item.event), size: 21, color: unread ? AppColors.surface : AppColors.inkMuted),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(item.title, style: text.titleSmall?.copyWith(fontWeight: unread ? FontWeight.w700 : FontWeight.w500)),
                  const SizedBox(height: 2),
                  Text(item.body, style: text.bodyMedium?.copyWith(color: AppColors.inkMuted, height: 1.35)),
                  if (item.createdAt != null) ...[
                    const SizedBox(height: 4),
                    Text(Fmt.dayTime(item.createdAt!), style: text.bodySmall),
                  ],
                ],
              ),
            ),
            const Icon(Icons.chevron_left_rounded, color: AppColors.inkMuted, textDirection: TextDirection.ltr),
          ],
        ),
      ),
    );
  }
}
