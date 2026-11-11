import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../core/widgets/common.dart';
import '../../models/driver_trip.dart';
import '../../models/parcel.dart';
import '../trips/trip_widgets.dart';
import '../ratings/rating_repository.dart';
import '../home/home_shell.dart';
import 'driver_booking_card.dart';
import 'driver_parcel_card.dart';
import 'driver_trip_repository.dart';

/// Trip management (spec D9): who's on board, requests to answer, and the
/// trip's life cycle — start, complete, cancel.
class DriverTripScreen extends ConsumerStatefulWidget {
  const DriverTripScreen({super.key, required this.tripId});

  final int tripId;

  @override
  ConsumerState<DriverTripScreen> createState() => _DriverTripScreenState();
}

class _DriverTripScreenState extends ConsumerState<DriverTripScreen> {
  bool _busy = false;

  /// Returns true when the action went through.
  Future<bool> _run(Future<DriverTrip> Function() action, String success) async {
    setState(() => _busy = true);
    try {
      await action();
      ref.invalidate(driverTripProvider(widget.tripId));
      ref.invalidate(driverTripsProvider);
      ref.invalidate(driverRequestsProvider);
      if (mounted) showMessage(context, success);
      return true;
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.fieldError('reason') ?? e.message);
      return false;
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final loaded = ref.watch(driverTripProvider(widget.tripId));
    final repo = ref.read(driverTripRepositoryProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(S.manageTrip, style: text.titleLarge),
        backgroundColor: AppColors.surface,
        scrolledUnderElevation: 0,
        actions: [
          if (loaded.valueOrNull?.canEditAll ?? false)
            IconButton(
              tooltip: S.editTrip,
              icon: const Icon(Icons.edit_outlined),
              onPressed: () async {
                await context.push('/driver/trips/${widget.tripId}/edit', extra: loaded.valueOrNull);
                ref.invalidate(driverTripProvider(widget.tripId));
              },
            ),
        ],
      ),
      body: loaded.when(
        skipLoadingOnRefresh: true,
        skipLoadingOnReload: true,
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => LoadError(error: error, onRetry: () => ref.invalidate(driverTripProvider(widget.tripId))),
        data: (trip) => Column(
          children: [
            const OfflineBanner(),
            Expanded(
              child: RefreshIndicator(
                color: AppColors.green,
                onRefresh: () async => ref.invalidate(driverTripProvider(widget.tripId)),
                child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
                  children: [
                    Panel(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                child: Align(
                                  alignment: AlignmentDirectional.centerStart,
                                  child: RouteTitle(
                                    from: trip.originName,
                                    to: trip.destinationName,
                                    style: text.headlineSmall,
                                  ),
                                ),
                              ),
                              Tag(trip.statusLabel, color: AppColors.ink, background: AppColors.background),
                            ],
                          ),
                          const SizedBox(height: 4),
                          Text(Fmt.dayTime(trip.departsAt), style: text.titleSmall),
                          Text('${trip.vehicleLabel} — ${Fmt.money(trip.pricePerSeat)} ${S.perSeat}', style: text.bodySmall),
                          const SizedBox(height: 12),
                          ClipRRect(
                            borderRadius: BorderRadius.circular(4),
                            child: LinearProgressIndicator(
                              value: trip.seatsTotal == 0 ? 0 : trip.seatsBooked / trip.seatsTotal,
                              minHeight: 8,
                              backgroundColor: AppColors.background,
                              color: AppColors.green,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(S.seatsBooked(trip.seatsBooked, trip.seatsTotal), style: text.labelMedium),
                          if ((trip.cancelReason ?? '').isNotEmpty) ...[
                            const SizedBox(height: 8),
                            Text(trip.cancelReason!, style: text.bodyMedium?.copyWith(color: AppColors.danger)),
                          ],
                        ],
                      ),
                    ),
                    if (trip.hiddenByAdmin) ...[
                      const SizedBox(height: 12),
                      const Panel(
                        color: AppColors.dangerTint,
                        padding: EdgeInsets.all(12),
                        child: Text(S.hiddenByAdmin, style: TextStyle(color: AppColors.danger, fontSize: 14.5)),
                      ),
                    ],
                    const SizedBox(height: 20),
                    Text(S.passengers, style: text.titleMedium),
                    const SizedBox(height: 10),
                    if (trip.bookings.isEmpty)
                      Text(S.noPassengersYet, style: text.bodyMedium?.copyWith(color: AppColors.inkMuted))
                    else
                      for (final booking in trip.bookings)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: DriverBookingCard(booking: booking),
                        ),
                    if (trip.parcels.isNotEmpty) ...[
                      const SizedBox(height: 14),
                      Text(S.parcelsOnThisTrip, style: text.titleMedium),
                      const SizedBox(height: 10),
                      for (final parcel in trip.parcels)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: DriverParcelCard(parcel: parcel),
                        ),
                    ],
                    const SizedBox(height: 10),
                    if (trip.canCancel)
                      TextButton(
                        onPressed: _busy
                            ? null
                            : () async {
                                final reason = await askTextSheet(
                                  context,
                                  title: S.cancelTrip,
                                  hint: S.cancelTripReason,
                                  action: S.cancelTrip,
                                  warning: trip.seatsBooked > 0 ? S.cancelTripWarning : null,
                                );
                                if (reason != null) await _run(() => repo.cancel(trip.id, reason), S.tripCancelled);
                              },
                        style: TextButton.styleFrom(foregroundColor: AppColors.danger),
                        child: const Text(S.cancelTrip),
                      ),
                  ],
                ),
              ),
            ),
            if (trip.canDepart || trip.canComplete)
              Container(
                decoration: const BoxDecoration(
                  color: AppColors.surface,
                  border: Border(top: BorderSide(color: AppColors.line)),
                ),
                padding: const EdgeInsets.fromLTRB(20, 12, 20, 12),
                child: SafeArea(
                  top: false,
                  child: PrimaryButton(
                    label: trip.canComplete ? S.finishTrip : S.startTrip,
                    icon: trip.canComplete ? Icons.flag_rounded : Icons.play_arrow_rounded,
                    busy: _busy,
                    onPressed: () async {
                      // A parcel still in the car: deliver it (with the code) before ending the trip.
                      final undelivered = trip.parcels
                          .where((p) => p.status == ParcelStatus.accepted || p.status == ParcelStatus.pickedUp)
                          .length;
                      if (trip.canComplete && undelivered > 0) {
                        showMessage(context, S.parcelsNotDelivered(undelivered));
                        return;
                      }
                      final ok = await confirmSheet(
                        context,
                        title: trip.canComplete ? S.finishTrip : S.startTrip,
                        body: trip.canComplete ? S.finishTripConfirm : S.startTripConfirm,
                        confirmLabel: trip.canComplete ? S.finishTrip : S.startTrip,
                      );
                      if (!ok) return;
                      final finishing = trip.canComplete;
                      final done = await _run(
                        () => finishing ? repo.complete(trip.id) : repo.depart(trip.id),
                        finishing ? S.tripCompleted : S.tripStarted,
                      );
                      // Trip over: straight to the driver home, where the passengers to
                      // rate wait in one swipeable card.
                      if (done && finishing && context.mounted) {
                        ref.invalidate(pendingRatingsProvider);
                        ref.read(homeTabProvider.notifier).state = 0;
                        context.go('/');
                      }
                    },
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
