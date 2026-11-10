import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/widgets/common.dart';
import '../auth/session_controller.dart';
import '../bookings/my_bookings_screen.dart';
import '../chat/chat_repository.dart';
import '../chat/chat_screens.dart';
import '../driver/driver_home_screen.dart';
import '../driver/driver_trip_repository.dart';
import '../driver/driver_trips_screen.dart';
import '../profile/profile_screen.dart';
import 'home_screen.dart';

/// Selected bottom tab (0 home, 1 bookings, 2 messages, 3 profile).
final homeTabProvider = StateProvider<int>((ref) => 0);

/// The signed-in app: four tabs that keep their state (spec P3 bottom navigation).
class HomeShell extends ConsumerWidget {
  const HomeShell({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final tab = ref.watch(homeTabProvider);
    final suspended = ref.watch(currentUserProvider)?.isSuspended ?? false;
    final unread = ref.watch(unreadCountProvider).valueOrNull ?? 0;
    final isDriver = ref.watch(currentUserProvider)?.isDriver ?? false;
    final driverMode = ref.watch(driverModeProvider);

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            const OfflineBanner(),
            if (isDriver)
              _ModeSwitch(
                driverMode: driverMode,
                onChanged: (value) => ref.read(driverModeProvider.notifier).set(value),
              ),
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
      ),
      bottomNavigationBar: DecoratedBox(
        decoration: const BoxDecoration(border: Border(top: BorderSide(color: AppColors.line))),
        child: NavigationBar(
          selectedIndex: tab,
          onDestinationSelected: (index) {
            ref.read(homeTabProvider.notifier).state = index;
            // Opening a tab is a good moment to refresh its list and the unread badge.
            ref.invalidate(unreadCountProvider);
            if (index == 2) ref.invalidate(conversationsProvider);
          },
          destinations: [
            const NavigationDestination(
              icon: Icon(Icons.home_outlined),
              selectedIcon: Icon(Icons.home_rounded),
              label: S.tabHome,
            ),
            NavigationDestination(
              icon: Icon(driverMode ? Icons.route_outlined : Icons.confirmation_number_outlined),
              selectedIcon: Icon(driverMode ? Icons.route_rounded : Icons.confirmation_number_rounded),
              label: driverMode ? S.tabMyTrips : S.tabBookings,
            ),
            NavigationDestination(
              icon: Badge(
                isLabelVisible: unread > 0,
                label: Text('$unread'),
                backgroundColor: AppColors.danger,
                child: const Icon(Icons.chat_bubble_outline_rounded),
              ),
              selectedIcon: const Icon(Icons.chat_bubble_rounded),
              label: S.tabMessages,
            ),
            const NavigationDestination(
              icon: Icon(Icons.person_outline_rounded),
              selectedIcon: Icon(Icons.person_rounded),
              label: S.tabProfile,
            ),
          ],
        ),
      ),
    );
  }
}

/// راكب / سائق — shown only to approved drivers (spec: one app, two modes).
class _ModeSwitch extends StatelessWidget {
  const _ModeSwitch({required this.driverMode, required this.onChanged});

  final bool driverMode;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    Widget option(String label, IconData icon, bool value) {
      final selected = driverMode == value;
      return Expanded(
        child: GestureDetector(
          behavior: HitTestBehavior.opaque,
          onTap: () => onChanged(value),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 160),
            height: 38,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: selected ? AppColors.green : Colors.transparent,
              borderRadius: BorderRadius.circular(9),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(icon, size: 18, color: selected ? Colors.white : AppColors.inkMuted),
                const SizedBox(width: 6),
                Text(
                  label,
                  style: TextStyle(
                    fontSize: 14.5,
                    fontWeight: FontWeight.w700,
                    color: selected ? Colors.white : AppColors.inkMuted,
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return Container(
      margin: const EdgeInsets.fromLTRB(20, 10, 20, 0),
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.line),
      ),
      child: Row(
        children: [
          option(S.passengerMode, Icons.person_rounded, false),
          option(S.driverMode, Icons.directions_car_filled_rounded, true),
        ],
      ),
    );
  }
}
