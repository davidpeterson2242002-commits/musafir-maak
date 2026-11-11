import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import '../auth/session_controller.dart';
import '../bookings/my_bookings_screen.dart';
import '../chat/chat_outbox.dart';
import '../chat/chat_repository.dart';
import '../chat/chat_screens.dart';
import '../driver/driver_home_screen.dart';
import '../driver/driver_trip_repository.dart';
import '../driver/driver_trips_screen.dart';
import '../live/live_updates.dart';
import '../profile/profile_screen.dart';
import 'home_screen.dart';

/// Selected bottom tab (0 home, 1 bookings / my trips, 2 messages, 3 profile).
final homeTabProvider = StateProvider<int>((ref) => 0);

/// Frame around every signed-in page: the bottom bar stays on inner pages too
/// (a booking, a trip, a chat), so any tab is one tap away without pressing back
/// again and again. Tapping a tab closes the inner pages and shows that tab.
class AppFrame extends ConsumerWidget {
  const AppFrame({super.key, required this.location, required this.child});

  final String location;
  final Widget child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    // Keeps the live updates (badges, statuses) and the chat outbox running
    // while signed in, without rebuilding the frame on each change.
    ref.listen(liveUpdatesProvider, (_, __) {});
    ref.listen(chatOutboxProvider, (_, __) {});
    final keyboardOpen = MediaQuery.viewInsetsOf(context).bottom > 0;

    return Scaffold(
      // Inner pages handle the keyboard themselves; the bar hides while typing.
      resizeToAvoidBottomInset: false,
      body: child,
      bottomNavigationBar: keyboardOpen ? null : _BottomBar(location: location),
    );
  }
}

class _BottomBar extends ConsumerWidget {
  const _BottomBar({required this.location});

  final String location;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final onHome = location == '/';
    final tab = ref.watch(homeTabProvider);
    final driverMode = ref.watch(driverModeProvider);
    final live = ref.watch(liveUpdatesProvider);
    final unread = live.badges.unreadMessages > 0
        ? live.badges.unreadMessages
        : (ref.watch(unreadCountProvider).valueOrNull ?? 0);
    final driverPending = driverMode ? live.badges.driverPending : 0;
    final notices = driverMode ? 0 : live.notices.length;

    Widget badge(int count, Widget icon) => Badge(
          isLabelVisible: count > 0,
          label: Text('$count'),
          backgroundColor: AppColors.danger,
          child: icon,
        );

    return DecoratedBox(
      decoration: const BoxDecoration(border: Border(top: BorderSide(color: AppColors.line))),
      child: NavigationBar(
        // On an inner page no tab is "current", but the last one stays marked.
        selectedIndex: tab,
        onDestinationSelected: (index) {
          ref.read(homeTabProvider.notifier).state = index;
          if (!onHome) context.go('/');
          // Opening a tab is a good moment to refresh its list and the badges.
          ref.read(liveUpdatesProvider.notifier).refresh();
          if (index == 2) ref.invalidate(conversationsProvider);
        },
        destinations: [
          NavigationDestination(
            icon: badge(driverPending, const Icon(Icons.home_outlined)),
            selectedIcon: badge(driverPending, const Icon(Icons.home_rounded)),
            label: S.tabHome,
          ),
          NavigationDestination(
            icon: badge(notices, Icon(driverMode ? Icons.route_outlined : Icons.confirmation_number_outlined)),
            selectedIcon: badge(notices, Icon(driverMode ? Icons.route_rounded : Icons.confirmation_number_rounded)),
            label: driverMode ? S.tabMyTrips : S.tabBookings,
          ),
          NavigationDestination(
            icon: badge(unread, const Icon(Icons.chat_bubble_outline_rounded)),
            selectedIcon: badge(unread, const Icon(Icons.chat_bubble_rounded)),
            label: S.tabMessages,
          ),
          const NavigationDestination(
            icon: Icon(Icons.person_outline_rounded),
            selectedIcon: Icon(Icons.person_rounded),
            label: S.tabProfile,
          ),
        ],
      ),
    );
  }
}

/// The four main tabs, under a slim top bar (logo, mode, profile picture).
class HomeShell extends ConsumerWidget {
  const HomeShell({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final tab = ref.watch(homeTabProvider);
    final suspended = ref.watch(currentUserProvider)?.isSuspended ?? false;
    final driverMode = ref.watch(driverModeProvider);

    return SafeArea(
      bottom: false,
      child: Column(
        children: [
          const TopBar(),
          const OfflineBanner(),
          if (suspended)
            Container(
              width: double.infinity,
              color: AppColors.dangerTint,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              child: const Text(
                S.suspendedBanner,
                style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w500, color: AppColors.danger),
              ),
            ),
          Expanded(
            child: IndexedStack(
              index: tab,
              children: [
                if (driverMode) const DriverHomeScreen() else const HomeScreen(),
                if (driverMode) const DriverTripsScreen() else const MyBookingsScreen(),
                const ConversationsScreen(),
                const ProfileScreen(),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Slim app bar on the main tabs: the app's mark and name, the current mode
/// (drivers only — tap to switch), and the profile picture (opens the profile tab).
class TopBar extends ConsumerWidget {
  const TopBar({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(currentUserProvider);
    final isDriver = user?.isDriver ?? false;
    final driverMode = ref.watch(driverModeProvider);

    return Container(
      height: 54,
      padding: const EdgeInsets.symmetric(horizontal: 16),
      decoration: const BoxDecoration(
        color: AppColors.surface,
        border: Border(bottom: BorderSide(color: AppColors.line)),
      ),
      child: Row(
        children: [
          Container(
            width: 30,
            height: 30,
            decoration: BoxDecoration(color: AppColors.green, borderRadius: BorderRadius.circular(8)),
            child: const Icon(Icons.alt_route_rounded, size: 19, color: AppColors.amber),
          ),
          const SizedBox(width: 8),
          const Text(
            S.appName,
            style: TextStyle(fontFamily: AppFonts.display, fontSize: 21, height: 1.2, color: AppColors.greenDark),
          ),
          const Spacer(),
          if (isDriver) ...[
            _ModePill(driverMode: driverMode),
            const SizedBox(width: 10),
          ],
          GestureDetector(
            onTap: () => ref.read(homeTabProvider.notifier).state = 3,
            child: AppAvatar(photoUrl: user?.photoUrl, name: user?.name, size: 34),
          ),
        ],
      ),
    );
  }
}

/// Shows the current mode; a tap asks before switching, so it's never flipped by accident.
class _ModePill extends ConsumerWidget {
  const _ModePill({required this.driverMode});

  final bool driverMode;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return InkWell(
      borderRadius: BorderRadius.circular(999),
      onTap: () async {
        final ok = await confirmSheet(
          context,
          title: driverMode ? S.switchToPassengerTitle : S.switchToDriverTitle,
          body: S.switchModeBody,
          confirmLabel: driverMode ? S.switchToPassenger : S.switchToDriver,
        );
        if (!ok) return;
        ref.read(driverModeProvider.notifier).set(!driverMode);
        ref.read(homeTabProvider.notifier).state = 0;
      },
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
        decoration: BoxDecoration(
          color: driverMode ? AppColors.amberTint : AppColors.greenTint,
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: AppColors.line),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              driverMode ? Icons.directions_car_filled_rounded : Icons.person_rounded,
              size: 16,
              color: AppColors.ink,
            ),
            const SizedBox(width: 4),
            Text(
              driverMode ? S.driverMode : S.passengerMode,
              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.ink),
            ),
            const SizedBox(width: 2),
            const Icon(Icons.swap_horiz_rounded, size: 16, color: AppColors.inkMuted),
          ],
        ),
      ),
    );
  }
}
