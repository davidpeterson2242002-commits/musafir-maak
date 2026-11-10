import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/formatters.dart';
import '../../core/utils/phone.dart';
import '../../core/widgets/common.dart';
import '../../models/booking.dart';
import '../../models/misc.dart';
import '../ratings/rating_repository.dart';
import '../trips/trip_details_screen.dart';
import '../trips/trip_widgets.dart';
import 'booking_repository.dart';
import 'booking_widgets.dart';

/// Booking page (spec P7): status, countdown while pending, driver contact
/// once accepted, sharing, cancelling, alternatives when it falls through.
class BookingStatusScreen extends ConsumerStatefulWidget {
  const BookingStatusScreen({super.key, required this.bookingId});

  final int bookingId;

  @override
  ConsumerState<BookingStatusScreen> createState() => _BookingStatusScreenState();
}

class _BookingStatusScreenState extends ConsumerState<BookingStatusScreen> {
  Timer? _ticker;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    // Keeps the countdown moving and picks up the driver's answer without websockets.
    _ticker = Timer.periodic(const Duration(seconds: 30), (_) {
      if (!mounted) return;
      final booking = ref.read(bookingProvider(widget.bookingId)).valueOrNull;
      if (booking?.status == BookingStatus.pending) {
        ref.invalidate(bookingProvider(widget.bookingId));
      }
      setState(() {});
    });
  }

  @override
  void dispose() {
    _ticker?.cancel();
    super.dispose();
  }

  void _reload() {
    ref.invalidate(bookingProvider(widget.bookingId));
    ref.invalidate(bookingsProvider);
  }

  Future<void> _cancel(Booking booking) async {
    final accepted = booking.status == BookingStatus.accepted;
    final ok = await confirmSheet(
      context,
      title: S.cancelBookingConfirm,
      body: accepted ? S.cancelLateWarning : null,
      confirmLabel: accepted ? S.cancelBooking : S.cancelRequest,
      destructive: true,
    );
    if (!ok || !mounted) return;

    setState(() => _busy = true);
    try {
      final outcome = await ref.read(bookingRepositoryProvider).cancel(booking.id);
      _reload();
      if (mounted) showMessage(context, outcome.warning ?? S.bookingCancelled);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _dispute(Booking booking) async {
    final details = await _askText(context, title: S.disputeNoShow, hint: S.disputeHint, action: S.sendDispute);
    if (details == null || !mounted) return;

    setState(() => _busy = true);
    try {
      await ref.read(bookingRepositoryProvider).disputeNoShow(booking.id, details);
      _reload();
      if (mounted) showMessage(context, S.disputeSent);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.fieldError('details') ?? e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _open(String url) async {
    final opened = await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
    if (!opened && mounted) showMessage(context, S.unknownError);
  }

  Future<void> _share(Booking booking) async {
    final textToShare = booking.shareText;
    if (textToShare == null) return;

    await showModalBottomSheet<void>(
      context: context,
      backgroundColor: AppColors.surface,
      showDragHandle: true,
      builder: (sheet) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(S.shareTrip, style: Theme.of(sheet).textTheme.titleLarge),
              const SizedBox(height: 10),
              Panel(color: AppColors.background, child: Text(textToShare, style: Theme.of(sheet).textTheme.bodyMedium)),
              const SizedBox(height: 14),
              FilledButton(
                onPressed: () {
                  Navigator.of(sheet).pop();
                  _open('https://wa.me/?text=${Uri.encodeComponent(textToShare)}');
                },
                child: const Text(S.shareWhatsapp),
              ),
              const SizedBox(height: 8),
              OutlinedButton(
                onPressed: () async {
                  await Clipboard.setData(ClipboardData(text: textToShare));
                  if (sheet.mounted) Navigator.of(sheet).pop();
                  if (mounted) showMessage(context, S.copied);
                },
                child: const Text(S.copyText),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final booking = ref.watch(bookingProvider(widget.bookingId));
    final offline = ref.watch(offlineProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(S.bookingTitle, style: text.titleLarge),
        backgroundColor: AppColors.surface,
        scrolledUnderElevation: 0,
      ),
      body: booking.when(
        skipLoadingOnRefresh: true,
        skipLoadingOnReload: true,
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => LoadError(error: error, onRetry: _reload),
        data: (booking) => Column(
          children: [
            if (offline)
              Container(
                width: double.infinity,
                color: AppColors.amberTint,
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                child: const Text(S.savedCopy, style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w500)),
              ),
            Expanded(
              child: RefreshIndicator(
                color: AppColors.green,
                onRefresh: () async => _reload(),
                child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
                  children: [
                    _StatusBanner(booking: booking),
                    const SizedBox(height: 12),
                    if (booking.contact != null) ...[
                      _ContactPanel(
                        booking: booking,
                        onCall: () => _open('tel:${booking.contact!.driverPhone}'),
                        onWhatsapp: () => _open('https://wa.me/${booking.contact!.driverWhatsapp.replaceAll('+', '')}'),
                        onChat: () => showMessage(context, S.chatSoon),
                      ),
                      const SizedBox(height: 12),
                    ],
                    TripSummaryPanel(
                      trip: booking.trip,
                      footer: _BookingFacts(booking: booking),
                    ),
                    if (booking.contact == null) ...[
                      const SizedBox(height: 12),
                      Panel(child: DriverLine(driver: booking.trip.driver, avatarSize: 48)),
                    ],
                    if (booking.status.offersAlternatives && booking.alternatives.isNotEmpty) ...[
                      const SizedBox(height: 24),
                      Text(S.otherTrips, style: text.titleMedium),
                      const SizedBox(height: 10),
                      for (final trip in booking.alternatives)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: TripCard(trip: trip, onTap: () => context.push('/trips/${trip.id}')),
                        ),
                    ],
                    const SizedBox(height: 20),
                    ..._actions(booking),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  List<Widget> _actions(Booking booking) {
    final pendingRating = ref
        .watch(pendingRatingsProvider)
        .valueOrNull
        ?.where((r) => r.subjectType == 'booking' && r.subjectId == booking.id)
        .firstOrNull;

    return [
      if (booking.shareText != null && booking.status == BookingStatus.accepted) ...[
        OutlinedButton.icon(
          onPressed: () => _share(booking),
          icon: const Icon(Icons.ios_share_rounded, size: 20),
          label: const Text(S.shareTrip),
        ),
        const SizedBox(height: 10),
      ],
      if (pendingRating != null) ...[
        FilledButton.icon(
          onPressed: () => _rate(pendingRating),
          icon: const Icon(Icons.star_rounded, size: 20),
          label: const Text(S.rateTrip),
        ),
        const SizedBox(height: 10),
      ],
      if (booking.noShow?.canDispute ?? false) ...[
        OutlinedButton(onPressed: _busy ? null : () => _dispute(booking), child: const Text(S.disputeNoShow)),
        const SizedBox(height: 10),
      ],
      if (booking.canCancel)
        TextButton(
          onPressed: _busy ? null : () => _cancel(booking),
          style: TextButton.styleFrom(foregroundColor: AppColors.danger),
          child: Text(booking.status == BookingStatus.accepted ? S.cancelBooking : S.cancelRequest),
        ),
    ];
  }

  Future<void> _rate(PendingRating rating) async {
    await context.push('/rate', extra: rating);
    ref.invalidate(pendingRatingsProvider);
  }
}

class _StatusBanner extends StatelessWidget {
  const _StatusBanner({required this.booking});

  final Booking booking;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final look = BookingLook.of(booking);

    final detail = switch (booking.status) {
      BookingStatus.pending => booking.expiresAt == null
          ? null
          : S.expiresIn(Fmt.remaining(booking.expiresAt!.difference(DateTime.now().toUtc()))),
      BookingStatus.accepted => S.acceptedBody,
      BookingStatus.rejected => booking.rejectionReason,
      BookingStatus.cancelledByDriver || BookingStatus.cancelledByPassenger => booking.cancelReason,
      BookingStatus.noShow => (booking.noShow?.disputed ?? false) ? S.noShowDisputed : null,
      _ => null,
    };

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: look.tint, borderRadius: BorderRadius.circular(AppTheme.radius + 4)),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(look.icon, color: look.color, size: 30),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(look.title, style: text.headlineSmall?.copyWith(color: look.color)),
                if (detail != null && detail.trim().isNotEmpty)
                  Text(detail, style: text.bodyMedium?.copyWith(color: AppColors.ink)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Driver phone, plate and car — shown only once the booking is accepted.
class _ContactPanel extends StatelessWidget {
  const _ContactPanel({required this.booking, required this.onCall, required this.onWhatsapp, required this.onChat});

  final Booking booking;
  final VoidCallback onCall;
  final VoidCallback onWhatsapp;
  final VoidCallback onChat;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final trip = booking.trip;
    final contact = booking.contact!;

    Widget action(IconData icon, String label, VoidCallback onTap, {bool primary = false}) => Expanded(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4),
            child: primary
                ? FilledButton.icon(
                    onPressed: onTap,
                    icon: Icon(icon, size: 19),
                    label: Text(label),
                    style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(46), padding: EdgeInsets.zero),
                  )
                : OutlinedButton.icon(
                    onPressed: onTap,
                    icon: Icon(icon, size: 19),
                    label: Text(label),
                    style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(46), padding: EdgeInsets.zero),
                  ),
          ),
        );

    return Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          DriverLine(
            driver: trip.driver,
            avatarSize: 52,
            trailing: Ltr(Phone.local(contact.driverPhone), style: text.titleSmall),
          ),
          const Divider(height: 24),
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(S.vehicle, style: text.labelMedium),
                    Text(
                      [trip.vehicleName, trip.vehicleColor].where((s) => s.isNotEmpty).join(' — '),
                      style: text.titleSmall,
                    ),
                  ],
                ),
              ),
              // The plate, drawn like one: what the passenger looks for at the pickup point.
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: AppColors.ink, width: 2),
                ),
                child: Column(
                  children: [
                    Text(S.plate, style: text.labelMedium?.copyWith(fontSize: 11)),
                    Text(contact.plateNumber, style: text.titleLarge?.copyWith(letterSpacing: 1)),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              action(Icons.call_rounded, S.callDriver, onCall, primary: true),
              action(Icons.chat_rounded, S.whatsappDriver, onWhatsapp),
              action(Icons.forum_outlined, S.chat, onChat),
            ],
          ),
        ],
      ),
    );
  }
}

/// Seats, pickup point, bags and what to pay.
class _BookingFacts extends StatelessWidget {
  const _BookingFacts({required this.booking});

  final Booking booking;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    Widget row(String label, String value, {bool strong = false}) => Padding(
          padding: const EdgeInsets.symmetric(vertical: 3),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(width: 110, child: Text(label, style: text.labelMedium)),
              Expanded(
                child: Text(
                  value,
                  style: strong ? text.titleMedium?.copyWith(color: AppColors.green) : text.titleSmall,
                ),
              ),
            ],
          ),
        );

    return Column(
      children: [
        row(S.seats, S.seatsCount(booking.seats)),
        if (booking.pickupPoint != null) row(S.pickupPoint, booking.pickupPoint!.label),
        if (booking.extraBags > 0) row(S.extraBags, '${booking.extraBags}'),
        row(S.total, '${Fmt.money(booking.priceTotal)} — ${S.payDriverDirectly}', strong: true),
      ],
    );
  }
}

Future<String?> _askText(BuildContext context, {required String title, required String hint, required String action}) {
  final controller = TextEditingController();

  return showModalBottomSheet<String>(
    context: context,
    isScrollControlled: true,
    backgroundColor: AppColors.surface,
    showDragHandle: true,
    builder: (sheet) => Padding(
      padding: EdgeInsets.fromLTRB(20, 0, 20, 16 + MediaQuery.viewInsetsOf(sheet).bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(title, style: Theme.of(sheet).textTheme.titleLarge),
          const SizedBox(height: 12),
          TextField(
            controller: controller,
            autofocus: true,
            maxLines: 4,
            maxLength: 1000,
            decoration: AppTheme.input(hint: hint),
          ),
          const SizedBox(height: 8),
          FilledButton(
            onPressed: () {
              final value = controller.text.trim();
              if (value.length >= 5) Navigator.of(sheet).pop(value);
            },
            child: Text(action),
          ),
        ],
      ),
    ),
  );
}
