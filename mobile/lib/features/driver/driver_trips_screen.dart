import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import 'driver_booking_card.dart';
import 'driver_trip_repository.dart';

/// "رحلاتي" tab in driver mode (spec D8).
class DriverTripsScreen extends ConsumerWidget {
  const DriverTripsScreen({super.key});

  static const _tabs = {'upcoming': S.upcoming, 'ongoing': S.ongoing, 'past': S.past, 'cancelled': S.cancelledTab};

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
            child: Text(S.tabMyTrips, style: text.displaySmall),
          ),
          TabBar(
            isScrollable: true,
            tabAlignment: TabAlignment.start,
            labelColor: AppColors.green,
            unselectedLabelColor: AppColors.inkMuted,
            indicatorColor: AppColors.green,
            dividerColor: AppColors.line,
            labelStyle: const TextStyle(fontFamily: AppFonts.body, fontSize: 15.5, fontWeight: FontWeight.w700),
            unselectedLabelStyle: const TextStyle(fontFamily: AppFonts.body, fontSize: 15.5, fontWeight: FontWeight.w500),
            tabs: [for (final label in _tabs.values) Tab(text: label)],
          ),
          Expanded(
            child: TabBarView(children: [for (final tab in _tabs.keys) _TripList(tab: tab)]),
          ),
        ],
      ),
    );
  }
}

class _TripList extends ConsumerWidget {
  const _TripList({required this.tab});

  final String tab;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final trips = ref.watch(driverTripsProvider(tab));

    return trips.when(
      skipLoadingOnRefresh: true,
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (error, _) => LoadError(error: error, onRetry: () => ref.invalidate(driverTripsProvider(tab))),
      data: (list) {
        if (list.isEmpty) {
          return EmptyState(
            icon: Icons.route_outlined,
            title: S.noDriverTripsTitle,
            body: tab == 'upcoming' ? S.noDriverTripsBody : '',
            actionLabel: tab == 'upcoming' ? S.postTrip : null,
            onAction: tab == 'upcoming' ? () => context.push('/driver/trips/new') : null,
          );
        }

        return RefreshIndicator(
          color: AppColors.green,
          onRefresh: () async => ref.invalidate(driverTripsProvider(tab)),
          child: ListView.separated(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
            itemCount: list.length,
            separatorBuilder: (_, __) => const SizedBox(height: 10),
            itemBuilder: (context, index) => DriverTripCard(
              trip: list[index],
              onTap: () => context.push('/driver/trips/${list[index].id}'),
            ),
          ),
        );
      },
    );
  }
}
