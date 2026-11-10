import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../core/widgets/common.dart';
import '../../models/trip.dart';
import 'trip_repository.dart';
import 'trip_widgets.dart';

/// Trip page (spec P5): everything a passenger needs before asking for a seat.
class TripDetailsScreen extends ConsumerWidget {
  const TripDetailsScreen({super.key, required this.tripId});

  final int tripId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final text = Theme.of(context).textTheme;
    final details = ref.watch(tripDetailsProvider(tripId));

    return Scaffold(
      appBar: AppBar(
        title: Text(S.tripDetails, style: text.titleLarge),
        backgroundColor: AppColors.surface,
        scrolledUnderElevation: 0,
      ),
      body: details.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => LoadError(error: error, onRetry: () => ref.invalidate(tripDetailsProvider(tripId))),
        data: (details) => Column(
          children: [
            const OfflineBanner(),
            Expanded(child: TripBody(trip: details.trip)),
            _BottomBar(details: details),
          ],
        ),
      ),
    );
  }
}

class _BottomBar extends StatelessWidget {
  const _BottomBar({required this.details});

  final TripDetails details;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final trip = details.trip;

    return Container(
      decoration: const BoxDecoration(
        color: AppColors.surface,
        border: Border(top: BorderSide(color: AppColors.line)),
      ),
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 12),
      child: SafeArea(
        top: false,
        child: Row(
          children: [
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(Fmt.money(trip.pricePerSeat), style: text.titleLarge?.copyWith(color: AppColors.green)),
                Text(S.perSeat, style: text.bodySmall),
              ],
            ),
            const SizedBox(width: 16),
            Expanded(
              child: details.myBookingId != null
                  ? OutlinedButton(
                      onPressed: () => context.push('/bookings/${details.myBookingId}'),
                      child: const Text(S.viewMyBooking),
                    )
                  : FilledButton(
                      onPressed: details.bookable ? () => context.push('/trips/${trip.id}/book') : null,
                      child: Text(details.bookable ? S.bookSeat : S.tripNotBookable, textAlign: TextAlign.center),
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

/// The scrollable trip description. Also used inside the booking screens.
class TripBody extends StatelessWidget {
  const TripBody({super.key, required this.trip});

  final Trip trip;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
      children: [
        TripSummaryPanel(trip: trip),
        if (trip.womenOnly) ...[
          const SizedBox(height: 12),
          const Panel(
            color: AppColors.greenTint,
            padding: EdgeInsets.all(12),
            child: Row(
              children: [
                Icon(Icons.family_restroom_rounded, color: AppColors.green),
                SizedBox(width: 10),
                Expanded(
                  child: Text(
                    S.womenOnlyNotice,
                    style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.green),
                  ),
                ),
              ],
            ),
          ),
        ],
        const SizedBox(height: 12),
        Panel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(S.driver, style: text.labelMedium),
              const SizedBox(height: 8),
              DriverLine(
                driver: trip.driver,
                avatarSize: 52,
                trailing: trip.driver.tripsCount == null
                    ? null
                    : Text(S.tripsDone(trip.driver.tripsCount!), style: text.labelMedium),
              ),
              if (trip.driver.memberSince != null) ...[
                const SizedBox(height: 8),
                Text('${S.memberSince} ${Fmt.monthYear(trip.driver.memberSince!)}', style: text.bodySmall),
              ],
            ],
          ),
        ),
        const SizedBox(height: 12),
        Panel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(S.vehicle, style: text.labelMedium),
              const SizedBox(height: 4),
              Text(
                [trip.vehicleTypeLabel, trip.vehicleName, trip.vehicleColor].where((s) => s.isNotEmpty).join(' — '),
                style: text.titleMedium,
              ),
              if (trip.vehiclePhotos.isNotEmpty) ...[
                const SizedBox(height: 10),
                SizedBox(
                  height: 120,
                  child: ListView.separated(
                    scrollDirection: Axis.horizontal,
                    itemCount: trip.vehiclePhotos.length,
                    separatorBuilder: (_, __) => const SizedBox(width: 8),
                    itemBuilder: (context, index) => ClipRRect(
                      borderRadius: BorderRadius.circular(12),
                      child: Image.network(
                        trip.vehiclePhotos[index],
                        width: 170,
                        height: 120,
                        fit: BoxFit.cover,
                        cacheWidth: 400,
                        errorBuilder: (_, __, ___) => Container(
                          width: 170,
                          color: AppColors.background,
                          child: const Icon(Icons.directions_car_filled_outlined, color: AppColors.inkMuted),
                        ),
                      ),
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 8),
              Text(S.plateAfterAccept, style: text.bodySmall),
            ],
          ),
        ),
        if (trip.pickupPoints.isNotEmpty || trip.dropoffPoints.isNotEmpty) ...[
          const SizedBox(height: 12),
          Panel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _PointList(title: S.pickupPoints, points: trip.pickupPoints, icon: Icons.trip_origin_rounded),
                if (trip.pickupPoints.isNotEmpty && trip.dropoffPoints.isNotEmpty) const Divider(height: 24),
                _PointList(title: S.dropoffPoints, points: trip.dropoffPoints, icon: Icons.location_on_rounded),
              ],
            ),
          ),
        ],
        const SizedBox(height: 12),
        Panel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(S.luggage, style: text.labelMedium),
              const SizedBox(height: 4),
              Text(S.luggageAllowance(trip.luggageLarge, trip.luggageSmall), style: text.titleMedium),
              Text(
                trip.extraBagPrice == null ? S.noExtraBags : S.extraBag(Fmt.money(trip.extraBagPrice!)),
                style: text.bodySmall,
              ),
            ],
          ),
        ),
        if ((trip.notes ?? '').trim().isNotEmpty) ...[
          const SizedBox(height: 12),
          Panel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(S.driverNotes, style: text.labelMedium),
                const SizedBox(height: 4),
                Text(trip.notes!, style: text.bodyLarge),
              ],
            ),
          ),
        ],
      ],
    );
  }
}

/// Route, date, time and seats: the top card of every trip and booking page.
class TripSummaryPanel extends StatelessWidget {
  const TripSummaryPanel({super.key, required this.trip, this.footer});

  final Trip trip;
  final Widget? footer;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          RouteTitle(from: trip.originName, to: trip.destinationName, style: text.headlineSmall),
          const SizedBox(height: 6),
          Row(
            children: [
              const Icon(Icons.calendar_today_outlined, size: 16, color: AppColors.green),
              const SizedBox(width: 6),
              Text(Fmt.dayTime(trip.departsAt), style: text.titleSmall),
            ],
          ),
          if (trip.estArrivalAt != null) ...[
            const SizedBox(height: 2),
            Text('${S.estimatedArrival}: ${Fmt.time(trip.estArrivalAt!)}', style: text.bodySmall),
          ],
          const SizedBox(height: 10),
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              if (trip.isScheduled)
                Tag(
                  S.seatsLeft(trip.seatsAvailable),
                  icon: Icons.airline_seat_recline_normal_rounded,
                  color: AppColors.ink,
                  background: AppColors.background,
                ),
              if (trip.acceptsParcels)
                const Tag(
                  S.acceptsParcels,
                  icon: Icons.inventory_2_outlined,
                  color: AppColors.ink,
                  background: AppColors.amberTint,
                ),
            ],
          ),
          if (footer != null) ...[const Divider(height: 24), footer!],
        ],
      ),
    );
  }
}

class _PointList extends StatelessWidget {
  const _PointList({required this.title, required this.points, required this.icon});

  final String title;
  final List<TripPoint> points;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    if (points.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: text.labelMedium),
        const SizedBox(height: 6),
        for (final point in points)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 3),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Icon(icon, size: 16, color: AppColors.green),
                ),
                const SizedBox(width: 8),
                Expanded(child: Text(point.label, style: text.bodyLarge?.copyWith(height: 1.4))),
              ],
            ),
          ),
      ],
    );
  }
}
