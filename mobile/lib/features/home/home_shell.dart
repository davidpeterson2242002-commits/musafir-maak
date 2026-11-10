import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/widgets/common.dart';
import '../auth/session_controller.dart';
import '../bookings/my_bookings_screen.dart';
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

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
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
                  const HomeScreen(),
                  const MyBookingsScreen(),
                  const EmptyState(
                    icon: Icons.chat_bubble_outline_rounded,
                    title: S.noMessagesTitle,
                    body: S.noMessagesBody,
                  ),
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
          onDestinationSelected: (index) => ref.read(homeTabProvider.notifier).state = index,
          destinations: const [
            NavigationDestination(
              icon: Icon(Icons.home_outlined),
              selectedIcon: Icon(Icons.home_rounded),
              label: S.tabHome,
            ),
            NavigationDestination(
              icon: Icon(Icons.confirmation_number_outlined),
              selectedIcon: Icon(Icons.confirmation_number_rounded),
              label: S.tabBookings,
            ),
            NavigationDestination(
              icon: Icon(Icons.chat_bubble_outline_rounded),
              selectedIcon: Icon(Icons.chat_bubble_rounded),
              label: S.tabMessages,
            ),
            NavigationDestination(
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
