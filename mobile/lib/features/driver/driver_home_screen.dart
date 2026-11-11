import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../core/widgets/common.dart';
import '../../models/driver.dart';
import '../../models/driver_money.dart';
import '../ratings/rating_cards.dart';
import '../ratings/rating_repository.dart';
import 'driver_booking_card.dart';
import 'driver_money_repository.dart';
import 'driver_parcel_card.dart';
import 'driver_repository.dart';
import 'driver_trip_repository.dart';

/// Driver home (spec D6): account warnings, what's owed, requests waiting for
/// an answer, the next trips, and the button to post one.
class DriverHomeScreen extends ConsumerWidget {
  const DriverHomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final text = Theme.of(context).textTheme;
    final account = ref.watch(driverAccountProvider).valueOrNull;
    final requests = ref.watch(driverRequestsProvider);
    final trips = ref.watch(driverTripsProvider('upcoming'));
    final parcelRequests = ref.watch(driverParcelRequestsProvider).valueOrNull ?? const <DriverParcel>[];
    final canPost = account?.canOperate ?? false;

    return RefreshIndicator(
      color: AppColors.green,
      onRefresh: () async {
        ref.invalidate(driverAccountProvider);
        ref.invalidate(driverRequestsProvider);
        ref.invalidate(driverTripsProvider);
        ref.invalidate(driverParcelRequestsProvider);
        ref.invalidate(pendingRatingsProvider);
      },
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 28),
        children: [
          Text(S.driverHomeTitle, style: text.displaySmall),
          const SizedBox(height: 14),
          if (account != null) ...[
            if (account.isFrozen || account.balanceLimitReached) ...[
              Panel(
                color: AppColors.dangerTint,
                padding: const EdgeInsets.all(12),
                child: Text(
                  account.isFrozen ? S.frozenBanner : S.limitBanner,
                  style: text.bodyMedium?.copyWith(color: AppColors.danger, fontWeight: FontWeight.w500),
                ),
              ),
              const SizedBox(height: 12),
            ],
            _BalanceCard(account: account),
            const SizedBox(height: 14),
          ],
          PrimaryButton(
            label: S.postTrip,
            icon: Icons.add_road_rounded,
            onPressed: canPost
                ? () => context.push('/driver/trips/new')
                : () => showMessage(context, account?.isFrozen ?? false ? S.frozenBanner : S.noApprovedVehicle),
          ),
          const SizedBox(height: 14),
          // Rate the passengers or senders of a finished trip (spec D15).
          const RatingCards(driverSide: true),
          const SizedBox(height: 26),
          Text(S.newRequests, style: text.titleMedium),
          const SizedBox(height: 10),
          requests.when(
            skipLoadingOnRefresh: true,
            loading: () => const Padding(padding: EdgeInsets.all(16), child: Center(child: CircularProgressIndicator())),
            error: (error, _) => Text(errorMessage(error), style: text.bodyMedium?.copyWith(color: AppColors.inkMuted)),
            data: (list) => list.isEmpty
                ? Text(S.noRequests, style: text.bodyMedium?.copyWith(color: AppColors.inkMuted))
                : Column(
                    children: [
                      for (final booking in list)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: DriverBookingCard(booking: booking, showTrip: true),
                        ),
                    ],
                  ),
          ),
          if (parcelRequests.isNotEmpty) ...[
            const SizedBox(height: 22),
            Text(S.parcelRequests, style: text.titleMedium),
            const SizedBox(height: 10),
            for (final parcel in parcelRequests)
              Padding(padding: const EdgeInsets.only(bottom: 10), child: DriverParcelCard(parcel: parcel)),
          ],
          const SizedBox(height: 22),
          Text(S.upcomingTrips, style: text.titleMedium),
          const SizedBox(height: 10),
          trips.when(
            skipLoadingOnRefresh: true,
            loading: () => const Padding(padding: EdgeInsets.all(16), child: Center(child: CircularProgressIndicator())),
            error: (error, _) => Text(errorMessage(error), style: text.bodyMedium?.copyWith(color: AppColors.inkMuted)),
            data: (list) => list.isEmpty
                ? Text(S.noDriverTripsBody, style: text.bodyMedium?.copyWith(color: AppColors.inkMuted))
                : Column(
                    children: [
                      for (final trip in list.take(3))
                        Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: DriverTripCard(trip: trip, onTap: () => context.push('/driver/trips/${trip.id}')),
                        ),
                    ],
                  ),
          ),
        ],
      ),
    );
  }
}

/// What the driver owes the platform and when (spec D6/D13). Paying is M10c.
class _BalanceCard extends StatelessWidget {
  const _BalanceCard({required this.account});

  final DriverAccount account;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final owes = account.balanceOwed > 0;

    // The whole card opens the statement; the button is the shortcut when something is owed.
    return GestureDetector(
      onTap: () => context.push('/driver/balance'),
      child: Panel(
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(S.balanceOwed, style: text.labelMedium),
                Text(
                  Fmt.money(account.balanceOwed),
                  style: text.headlineSmall?.copyWith(color: owes ? AppColors.ink : AppColors.green),
                ),
                if (owes && account.settlementDueAt != null)
                  Text(S.dueOn(Fmt.dayMonth(account.settlementDueAt!)), style: text.bodySmall)
                else if (account.freeBookingsRemaining > 0)
                  Text(S.freeBookingsLeft(account.freeBookingsRemaining), style: text.bodySmall),
              ],
            ),
          ),
          if (owes)
            OutlinedButton(
              onPressed: () => context.push('/driver/balance'),
              style: OutlinedButton.styleFrom(minimumSize: const Size(0, 44)),
              child: const Text(S.payNow),
            )
          else
            const Icon(Icons.chevron_left_rounded, color: AppColors.inkMuted, textDirection: TextDirection.ltr),
        ],
      ),
      ),
    );
  }
}
