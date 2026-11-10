import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api/api_exception.dart';
import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/formatters.dart';
import '../../core/utils/phone.dart';
import '../../core/widgets/common.dart';
import '../../models/booking.dart';
import '../../models/driver_trip.dart';
import '../trips/trip_widgets.dart';
import 'driver_repository.dart';
import 'driver_trip_repository.dart';

/// A passenger's booking as the driver sees it (spec D9/D10): who, how many
/// seats, where from, the commission — with accept / reject while pending and
/// call / chat / no-show once accepted.
class DriverBookingCard extends ConsumerStatefulWidget {
  const DriverBookingCard({super.key, required this.booking, this.showTrip = false});

  final DriverBooking booking;

  /// On the driver home the card also says which trip the request is for.
  final bool showTrip;

  @override
  ConsumerState<DriverBookingCard> createState() => _DriverBookingCardState();
}

class _DriverBookingCardState extends ConsumerState<DriverBookingCard> {
  bool _busy = false;

  Future<void> _run(Future<void> Function() action, String success) async {
    setState(() => _busy = true);
    try {
      await action();
      // Everything that shows this booking, the seat count or the balance.
      ref.invalidate(driverRequestsProvider);
      ref.invalidate(driverTripProvider(widget.booking.tripId));
      ref.invalidate(driverTripsProvider);
      ref.invalidate(driverAccountProvider);
      if (mounted) showMessage(context, success);
    } on ApiException catch (e) {
      // Frozen account, balance limit, seats gone… the server explains.
      if (mounted) showMessage(context, e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _open(String url) async {
    final opened = await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
    if (!opened && mounted) showMessage(context, S.unknownError);
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final booking = widget.booking;
    final repo = ref.read(driverTripRepositoryProvider);
    final pending = booking.status == BookingStatus.pending;
    final accepted = booking.status == BookingStatus.accepted;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(AppTheme.radius + 4),
        border: Border.all(color: pending ? AppColors.amber : AppColors.line, width: pending ? 1.5 : 1),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (widget.showTrip && booking.tripRoute != null) ...[
            Text(
              '${booking.tripRoute}${booking.tripDepartsAt == null ? '' : ' — ${Fmt.dayTime(booking.tripDepartsAt!)}'}',
              style: text.labelMedium,
            ),
            const SizedBox(height: 8),
          ],
          InkWell(
            onTap: () => context.push('/users/${booking.passenger.id}'),
            borderRadius: BorderRadius.circular(10),
            child: Row(
              children: [
                AppAvatar(photoUrl: booking.passenger.photoUrl, name: booking.passenger.name, size: 44),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Flexible(child: Text(booking.passenger.name, style: text.titleSmall, overflow: TextOverflow.ellipsis)),
                          if (booking.passengerVerified) ...[const SizedBox(width: 4), const VerifiedBadge(withLabel: false)],
                        ],
                      ),
                      RatingLabel(
                        average: booking.passenger.ratingAvg,
                        count: booking.passenger.ratingCount,
                        emptyLabel: S.noRatingsYet,
                      ),
                    ],
                  ),
                ),
                if (!pending)
                  Tag(
                    booking.statusLabel,
                    color: accepted ? AppColors.green : AppColors.inkMuted,
                    background: accepted ? AppColors.greenTint : AppColors.background,
                  ),
              ],
            ),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              Tag(S.seatsCount(booking.seats), color: AppColors.ink, background: AppColors.background),
              Tag(Fmt.money(booking.priceTotal)),
              if (booking.extraBags > 0)
                Tag('${S.extraBags}: ${booking.extraBags}', color: AppColors.ink, background: AppColors.background),
              if (booking.includesFamily) const Tag(S.withFamily, icon: Icons.family_restroom_rounded),
              if (!booking.passengerVerified) const Tag(S.idUnderReview, color: AppColors.ink, background: AppColors.amberTint),
            ],
          ),
          if (booking.pickupLabel != null) ...[
            const SizedBox(height: 8),
            Row(
              children: [
                const Icon(Icons.trip_origin_rounded, size: 15, color: AppColors.green),
                const SizedBox(width: 6),
                Expanded(child: Text(booking.pickupLabel!, style: text.bodyMedium)),
              ],
            ),
          ],
          if ((booking.message ?? '').trim().isNotEmpty) ...[
            const SizedBox(height: 6),
            Text('"${booking.message!.trim()}"', style: text.bodyMedium?.copyWith(color: AppColors.inkMuted)),
          ],
          if (pending) ...[
            const SizedBox(height: 8),
            Text(
              [
                booking.feeFree ? S.feeFree : S.feeForBooking(Fmt.money(booking.feeAmount)),
                if (booking.expiresAt != null)
                  S.expiresIn(Fmt.remaining(booking.expiresAt!.difference(DateTime.now().toUtc()))),
              ].join('\n'),
              style: text.bodySmall,
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: FilledButton(
                    onPressed: _busy ? null : () => _run(() => repo.accept(booking.id), S.requestAccepted),
                    style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(46)),
                    child: const Text(S.accept),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: OutlinedButton(
                    onPressed: _busy
                        ? null
                        : () async {
                            final ok = await confirmSheet(
                              context,
                              title: S.rejectConfirm,
                              confirmLabel: S.reject,
                              destructive: true,
                            );
                            if (ok) await _run(() => repo.reject(booking.id), S.requestRejected);
                          },
                    style: OutlinedButton.styleFrom(
                      minimumSize: const Size.fromHeight(46),
                      foregroundColor: AppColors.danger,
                      side: const BorderSide(color: AppColors.danger),
                    ),
                    child: const Text(S.reject),
                  ),
                ),
                if (booking.conversationId != null) ...[
                  const SizedBox(width: 4),
                  IconButton(
                    tooltip: S.chat,
                    onPressed: () => context.push('/chats/${booking.conversationId}'),
                    icon: const Icon(Icons.forum_outlined, color: AppColors.green),
                  ),
                ],
              ],
            ),
          ],
          if (accepted) ...[
            const Divider(height: 22),
            Row(
              children: [
                if (booking.phone != null) ...[
                  Expanded(child: Ltr(Phone.local(booking.phone!), style: text.titleSmall)),
                  IconButton(
                    tooltip: S.callDriver,
                    onPressed: () => _open('tel:${booking.phone}'),
                    icon: const Icon(Icons.call_rounded, color: AppColors.green),
                  ),
                  IconButton(
                    tooltip: S.whatsappDriver,
                    onPressed: () => _open('https://wa.me/${(booking.whatsapp ?? booking.phone!).replaceAll('+', '')}'),
                    icon: const Icon(Icons.chat_rounded, color: AppColors.green),
                  ),
                ] else
                  const Spacer(),
                if (booking.conversationId != null)
                  IconButton(
                    tooltip: S.chat,
                    onPressed: () => context.push('/chats/${booking.conversationId}'),
                    icon: const Icon(Icons.forum_outlined, color: AppColors.green),
                  ),
              ],
            ),
            if (booking.canMarkNoShow)
              Align(
                alignment: AlignmentDirectional.centerStart,
                child: TextButton(
                  onPressed: _busy
                      ? null
                      : () async {
                          final ok = await confirmSheet(
                            context,
                            title: S.markNoShow,
                            body: S.markNoShowConfirm,
                            confirmLabel: S.markNoShow,
                            destructive: true,
                          );
                          if (ok) await _run(() => repo.markNoShow(booking.id), S.noShowMarked);
                        },
                  style: TextButton.styleFrom(foregroundColor: AppColors.danger),
                  child: const Text(S.markNoShow),
                ),
              ),
          ],
        ],
      ),
    );
  }
}

/// One of the driver's trips in a list: route, time, seats filled, waiting requests.
class DriverTripCard extends StatelessWidget {
  const DriverTripCard({super.key, required this.trip, required this.onTap});

  final DriverTrip trip;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(borderRadius: BorderRadius.circular(18), border: Border.all(color: AppColors.line)),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Align(
                      alignment: AlignmentDirectional.centerStart,
                      child: RouteTitle(from: trip.originName, to: trip.destinationName, style: text.titleLarge),
                    ),
                  ),
                  Text(Fmt.money(trip.pricePerSeat), style: text.titleSmall?.copyWith(color: AppColors.green)),
                ],
              ),
              const SizedBox(height: 4),
              Text(Fmt.dayTime(trip.departsAt), style: text.bodyMedium?.copyWith(color: AppColors.inkMuted)),
              const SizedBox(height: 10),
              // Seats filled, as a bar: the first thing a driver wants to know.
              ClipRRect(
                borderRadius: BorderRadius.circular(4),
                child: LinearProgressIndicator(
                  value: trip.seatsTotal == 0 ? 0 : trip.seatsBooked / trip.seatsTotal,
                  minHeight: 7,
                  backgroundColor: AppColors.background,
                  color: AppColors.green,
                ),
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 6,
                runSpacing: 6,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [
                  Text(S.seatsBooked(trip.seatsBooked, trip.seatsTotal), style: text.labelMedium),
                  if (trip.pendingRequests > 0)
                    Tag(
                      S.pendingCount(trip.pendingRequests),
                      icon: Icons.notifications_active_rounded,
                      color: AppColors.ink,
                      background: AppColors.amberTint,
                    ),
                  if (trip.womenOnly) const Tag(S.womenOnly, icon: Icons.family_restroom_rounded),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
