import 'package:flutter/material.dart';

import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../core/widgets/common.dart';
import '../../models/booking.dart';
import '../trips/trip_widgets.dart';

/// Colours and wording for each booking state, in one place.
class BookingLook {
  const BookingLook(this.title, this.icon, this.color, this.tint);

  final String title;
  final IconData icon;
  final Color color;
  final Color tint;

  factory BookingLook.of(Booking booking) => switch (booking.status) {
        BookingStatus.pending =>
          const BookingLook(S.waitingDriver, Icons.hourglass_top_rounded, AppColors.ink, AppColors.amberTint),
        BookingStatus.accepted =>
          const BookingLook(S.accepted, Icons.check_circle_rounded, AppColors.green, AppColors.greenTint),
        BookingStatus.rejected =>
          const BookingLook(S.rejected, Icons.cancel_outlined, AppColors.danger, AppColors.dangerTint),
        BookingStatus.expired =>
          const BookingLook(S.expired, Icons.timer_off_outlined, AppColors.danger, AppColors.dangerTint),
        BookingStatus.cancelledByPassenger =>
          const BookingLook(S.cancelledByYou, Icons.cancel_outlined, AppColors.inkMuted, AppColors.background),
        BookingStatus.cancelledByDriver =>
          const BookingLook(S.cancelledByDriver, Icons.cancel_outlined, AppColors.danger, AppColors.dangerTint),
        BookingStatus.completed =>
          const BookingLook(S.completed, Icons.flag_rounded, AppColors.green, AppColors.greenTint),
        BookingStatus.noShow =>
          const BookingLook(S.noShow, Icons.person_off_outlined, AppColors.danger, AppColors.dangerTint),
        BookingStatus.unknown =>
          BookingLook(booking.statusLabel, Icons.info_outline_rounded, AppColors.ink, AppColors.background),
      };
}

/// A booking in a list (spec P8) and on the home screen ("your next trip").
class BookingCard extends StatelessWidget {
  const BookingCard({super.key, required this.booking, required this.onTap, this.heading});

  final Booking booking;
  final VoidCallback onTap;

  /// Optional small line above the route (e.g. "رحلتك القادمة").
  final String? heading;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final trip = booking.trip;
    final look = BookingLook.of(booking);

    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: AppColors.line),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (heading != null) ...[Text(heading!, style: text.labelMedium), const SizedBox(height: 2)],
              Row(
                children: [
                  Expanded(
                    child: Align(
                      alignment: AlignmentDirectional.centerStart,
                      child: RouteTitle(from: trip.originName, to: trip.destinationName, style: text.titleLarge),
                    ),
                  ),
                  Tag(
                    booking.statusLabel.isEmpty ? look.title : booking.statusLabel,
                    icon: look.icon,
                    color: look.color,
                    background: look.tint,
                  ),
                ],
              ),
              const SizedBox(height: 4),
              Text(
                '${Fmt.dayTime(trip.departsAt)} — ${S.seatsCount(booking.seats)}',
                style: text.bodyMedium?.copyWith(color: AppColors.inkMuted),
              ),
              const Divider(height: 20),
              Row(
                children: [
                  AppAvatar(photoUrl: trip.driver.photoUrl, name: trip.driver.name, size: 30),
                  const SizedBox(width: 8),
                  Expanded(child: Text(trip.driver.name, style: text.titleSmall, overflow: TextOverflow.ellipsis)),
                  Text(Fmt.money(booking.priceTotal), style: text.titleSmall?.copyWith(color: AppColors.green)),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
