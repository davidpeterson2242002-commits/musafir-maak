import 'package:flutter/material.dart';

import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/formatters.dart';
import '../../core/widgets/common.dart';
import '../../models/trip.dart';

/// "الخرطوم ← ود مدني" with the amber arrow.
class RouteTitle extends StatelessWidget {
  const RouteTitle({super.key, required this.from, required this.to, this.style, this.arrowColor = AppColors.amber});

  final String from;
  final String to;
  final TextStyle? style;
  final Color arrowColor;

  @override
  Widget build(BuildContext context) {
    final textStyle = style ?? Theme.of(context).textTheme.titleLarge;

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Flexible(child: Text(from, style: textStyle, overflow: TextOverflow.ellipsis)),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8),
          // Mirrors in RTL: points from the origin (right) to the destination (left).
          child: Icon(Icons.arrow_forward_rounded, size: (textStyle?.fontSize ?? 18) * 0.95, color: arrowColor),
        ),
        Flexible(child: Text(to, style: textStyle, overflow: TextOverflow.ellipsis)),
      ],
    );
  }
}

/// Small rounded label: women-only, accepts parcels, statuses.
class Tag extends StatelessWidget {
  const Tag(this.label, {super.key, this.icon, this.color = AppColors.green, this.background = AppColors.greenTint});

  final String label;
  final IconData? icon;
  final Color color;
  final Color background;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
      decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(20)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[Icon(icon, size: 14, color: color), const SizedBox(width: 4)],
          Text(label, style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: color, height: 1.3)),
        ],
      ),
    );
  }
}

/// Star + average, or "سائق جديد" when nobody has rated yet.
class RatingLabel extends StatelessWidget {
  const RatingLabel({super.key, required this.average, required this.count, this.emptyLabel = S.newDriver});

  final double average;
  final int count;
  final String emptyLabel;

  @override
  Widget build(BuildContext context) {
    final style = Theme.of(context).textTheme.labelMedium;

    if (count == 0) return Text(emptyLabel, style: style);

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        const Icon(Icons.star_rounded, size: 17, color: AppColors.amber),
        const SizedBox(width: 2),
        Text(
          '${average.toStringAsFixed(1)} ($count)',
          style: style?.copyWith(color: AppColors.ink, fontWeight: FontWeight.w700),
        ),
      ],
    );
  }
}

/// Driver line used on cards and pages: photo, name, verified mark, rating.
class DriverLine extends StatelessWidget {
  const DriverLine({super.key, required this.driver, this.avatarSize = 44, this.trailing});

  final PublicUser driver;
  final double avatarSize;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return Row(
      children: [
        AppAvatar(photoUrl: driver.photoUrl, name: driver.name, size: avatarSize),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Flexible(child: Text(driver.name, style: text.titleSmall, overflow: TextOverflow.ellipsis)),
                  if (driver.verified) ...[const SizedBox(width: 4), const VerifiedBadge(withLabel: false)],
                ],
              ),
              RatingLabel(average: driver.ratingAvg, count: driver.ratingCount),
            ],
          ),
        ),
        if (trailing != null) trailing!,
      ],
    );
  }
}

/// One search result (spec P4): time, driver, vehicle, price, seats, badges.
class TripCard extends StatelessWidget {
  const TripCard({super.key, required this.trip, required this.onTap, this.parcelMode = false, this.parcelSize});

  final Trip trip;
  final VoidCallback onTap;

  /// Searching for a parcel carrier: show parcel prices instead of the seat price.
  final bool parcelMode;

  /// The size chosen in the parcel draft: its price is shown instead of "from".
  final String? parcelSize;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final sizePrice = parcelSize == null ? null : trip.parcelPrices[parcelSize];
    final parcelFrom = sizePrice ??
        (trip.parcelPrices.values.isEmpty ? null : trip.parcelPrices.values.reduce((a, b) => a < b ? a : b));

    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(AppTheme.radius + 4),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppTheme.radius + 4),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppTheme.radius + 4),
            border: Border.all(color: AppColors.line),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(Fmt.time(trip.departsAt), style: text.headlineSmall),
                        Text(
                          '${trip.vehicleTypeLabel}${trip.vehicleName.isEmpty ? '' : ' — ${trip.vehicleName}'}',
                          style: text.bodySmall,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                    ),
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        Fmt.money(parcelMode ? (parcelFrom ?? 0) : trip.pricePerSeat),
                        style: text.titleLarge?.copyWith(color: AppColors.green),
                      ),
                      Text(parcelMode ? (sizePrice != null ? S.parcelPrice : S.parcelFrom) : S.perSeat, style: text.bodySmall),
                    ],
                  ),
                ],
              ),
              const Divider(height: 22),
              DriverLine(driver: trip.driver, avatarSize: 40),
              const SizedBox(height: 10),
              Wrap(
                spacing: 6,
                runSpacing: 6,
                children: [
                  if (!parcelMode)
                    Tag(
                      S.seatsLeft(trip.seatsAvailable),
                      icon: Icons.airline_seat_recline_normal_rounded,
                      color: AppColors.ink,
                      background: AppColors.background,
                    ),
                  if (trip.womenOnly) const Tag(S.womenOnly, icon: Icons.family_restroom_rounded),
                  if (trip.acceptsParcels && !parcelMode)
                    const Tag(
                      S.acceptsParcels,
                      icon: Icons.inventory_2_outlined,
                      color: AppColors.ink,
                      background: AppColors.amberTint,
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
