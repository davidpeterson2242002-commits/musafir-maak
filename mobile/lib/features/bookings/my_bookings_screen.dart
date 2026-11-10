import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import '../home/home_shell.dart';
import 'booking_repository.dart';
import 'booking_widgets.dart';

/// "حجوزاتي" tab (spec P8): upcoming, past and cancelled bookings.
class MyBookingsScreen extends ConsumerWidget {
  const MyBookingsScreen({super.key});

  static const _tabs = {'upcoming': S.upcoming, 'past': S.past, 'cancelled': S.cancelledTab};

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final text = Theme.of(context).textTheme;

    return DefaultTabController(
      length: _tabs.length,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 4),
            child: Text(S.tabBookings, style: text.displaySmall),
          ),
          TabBar(
            labelColor: AppColors.green,
            unselectedLabelColor: AppColors.inkMuted,
            indicatorColor: AppColors.green,
            dividerColor: AppColors.line,
            labelStyle: const TextStyle(fontFamily: AppFonts.body, fontSize: 15.5, fontWeight: FontWeight.w700),
            unselectedLabelStyle: const TextStyle(fontFamily: AppFonts.body, fontSize: 15.5, fontWeight: FontWeight.w500),
            tabs: [for (final label in _tabs.values) Tab(text: label)],
          ),
          Expanded(
            child: TabBarView(
              children: [for (final tab in _tabs.keys) _BookingList(tab: tab)],
            ),
          ),
        ],
      ),
    );
  }
}

class _BookingList extends ConsumerWidget {
  const _BookingList({required this.tab});

  final String tab;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final bookings = ref.watch(bookingsProvider(tab));

    return bookings.when(
      skipLoadingOnRefresh: true,
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (error, _) => LoadError(error: error, onRetry: () => ref.invalidate(bookingsProvider(tab))),
      data: (list) {
        if (list.isEmpty) {
          return switch (tab) {
            'upcoming' => EmptyState(
                icon: Icons.confirmation_number_outlined,
                title: S.noBookingsTitle,
                body: S.noBookingsBody,
                actionLabel: S.findTrip,
                onAction: () => ref.read(homeTabProvider.notifier).state = 0,
              ),
            'past' => const EmptyState(icon: Icons.history_rounded, title: S.noPastBookings, body: ''),
            _ => const EmptyState(icon: Icons.event_busy_rounded, title: S.noCancelledBookings, body: ''),
          };
        }

        return RefreshIndicator(
          color: AppColors.green,
          onRefresh: () async => ref.invalidate(bookingsProvider(tab)),
          child: ListView.separated(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
            itemCount: list.length,
            separatorBuilder: (_, __) => const SizedBox(height: 10),
            itemBuilder: (context, index) => BookingCard(
              booking: list[index],
              onTap: () => context.push('/bookings/${list[index].id}'),
            ),
          ),
        );
      },
    );
  }
}
