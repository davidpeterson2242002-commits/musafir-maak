import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
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
import '../../models/parcel.dart';
import '../reports/report_sheet.dart';
import '../trips/trip_details_screen.dart';
import '../trips/trip_widgets.dart';
import '../live/live_updates.dart';
import 'parcel_repository.dart';

/// Colours and wording for each parcel state.
({String title, String? body, IconData icon, Color color, Color tint}) parcelLook(Parcel parcel) =>
    switch (parcel.status) {
      ParcelStatus.pending => (
          title: S.parcelWaiting,
          body: parcel.expiresAt == null
              ? null
              : S.expiresIn(Fmt.remaining(parcel.expiresAt!.difference(DateTime.now().toUtc()))),
          icon: Icons.hourglass_top_rounded,
          color: AppColors.ink,
          tint: AppColors.amberTint,
        ),
      ParcelStatus.accepted => (
          title: S.parcelAccepted,
          body: S.parcelAcceptedBody,
          icon: Icons.check_circle_rounded,
          color: AppColors.green,
          tint: AppColors.greenTint,
        ),
      ParcelStatus.pickedUp => (
          title: S.parcelPickedUp,
          body: S.parcelPickedUpBody,
          icon: Icons.local_shipping_rounded,
          color: AppColors.green,
          tint: AppColors.greenTint,
        ),
      ParcelStatus.delivered => (
          title: S.parcelDelivered,
          body: null,
          icon: Icons.task_alt_rounded,
          color: AppColors.green,
          tint: AppColors.greenTint,
        ),
      ParcelStatus.rejected => (
          title: S.parcelRejected,
          body: parcel.rejectionReason,
          icon: Icons.cancel_outlined,
          color: AppColors.danger,
          tint: AppColors.dangerTint,
        ),
      ParcelStatus.expired => (
          title: S.parcelExpired,
          body: null,
          icon: Icons.timer_off_outlined,
          color: AppColors.danger,
          tint: AppColors.dangerTint,
        ),
      ParcelStatus.refused => (
          title: S.parcelRefused,
          body: parcel.refusalReason,
          icon: Icons.cancel_outlined,
          color: AppColors.danger,
          tint: AppColors.dangerTint,
        ),
      ParcelStatus.cancelled => (
          title: S.parcelCancelled,
          body: parcel.cancelReason,
          icon: Icons.cancel_outlined,
          color: AppColors.inkMuted,
          tint: AppColors.background,
        ),
      ParcelStatus.disputed => (
          title: S.parcelDisputed,
          body: S.parcelDisputedBody,
          icon: Icons.support_agent_rounded,
          color: AppColors.ink,
          tint: AppColors.amberTint,
        ),
      ParcelStatus.unknown => (
          title: parcel.statusLabel,
          body: null,
          icon: Icons.info_outline_rounded,
          color: AppColors.ink,
          tint: AppColors.background,
        ),
    };

/// Parcel page (spec S3): status, delivery code, driver contact, receiver,
/// sharing the details with the receiver, cancel and problem report.
class ParcelStatusScreen extends ConsumerStatefulWidget {
  const ParcelStatusScreen({super.key, required this.parcelId});

  final int parcelId;

  @override
  ConsumerState<ParcelStatusScreen> createState() => _ParcelStatusScreenState();
}

class _ParcelStatusScreenState extends ConsumerState<ParcelStatusScreen> {
  static const _moving = {ParcelStatus.pending, ParcelStatus.accepted, ParcelStatus.pickedUp};

  Timer? _ticker;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    // Opening it clears its "accepted / rejected…" notice on home.
    Future.microtask(() => ref.read(liveUpdatesProvider.notifier).opened('parcel', widget.parcelId));
    // Picks up the driver's answer, pickup and delivery while the page is open.
    _ticker = Timer.periodic(const Duration(seconds: 30), (_) {
      if (!mounted) return;
      final parcel = ref.read(parcelProvider(widget.parcelId)).valueOrNull;
      if (parcel != null && _moving.contains(parcel.status)) ref.invalidate(parcelProvider(widget.parcelId));
      setState(() {});
    });
  }

  @override
  void dispose() {
    _ticker?.cancel();
    super.dispose();
  }

  void _reload() {
    ref.invalidate(parcelProvider(widget.parcelId));
    ref.invalidate(parcelsProvider);
  }

  Future<void> _open(String url) async {
    final opened = await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
    if (!opened && mounted) showMessage(context, S.unknownError);
  }

  Future<void> _cancel(Parcel parcel) async {
    final ok = await confirmSheet(context, title: S.cancelParcelConfirm, confirmLabel: S.cancelParcel, destructive: true);
    if (!ok || !mounted) return;
    await _run(() => ref.read(parcelRepositoryProvider).cancel(parcel.id), S.parcelCancelled);
  }

  Future<void> _dispute(Parcel parcel) async {
    final controller = TextEditingController();
    final details = await showModalBottomSheet<String>(
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
            Text(S.reportProblem, style: Theme.of(sheet).textTheme.titleLarge),
            const SizedBox(height: 12),
            TextField(
              controller: controller,
              autofocus: true,
              maxLines: 4,
              maxLength: 1000,
              decoration: AppTheme.input(hint: S.parcelProblemHint),
            ),
            const SizedBox(height: 8),
            FilledButton(
              onPressed: () {
                final value = controller.text.trim();
                if (value.length >= 5) Navigator.of(sheet).pop(value);
              },
              child: const Text(S.sendReport),
            ),
          ],
        ),
      ),
    );
    if (details == null || !mounted) return;
    await _run(() => ref.read(parcelRepositoryProvider).dispute(parcel.id, details), S.disputeSent);
  }

  Future<void> _run(Future<Parcel> Function() action, String success) async {
    setState(() => _busy = true);
    try {
      await action();
      _reload();
      if (mounted) showMessage(context, success);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.fieldError('details') ?? e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final loaded = ref.watch(parcelProvider(widget.parcelId));

    return Scaffold(
      appBar: AppBar(
        title: Text(S.parcelTitle, style: text.titleLarge),
        backgroundColor: AppColors.surface,
        scrolledUnderElevation: 0,
        actions: [
          if (loaded.valueOrNull != null)
            IconButton(
              tooltip: S.report,
              icon: const Icon(Icons.flag_outlined),
              onPressed: () => showReportSheet(
                context,
                userId: loaded.valueOrNull!.trip.driver.id,
                subjectType: 'parcel',
                subjectId: widget.parcelId,
              ),
            ),
        ],
      ),
      body: loaded.when(
        skipLoadingOnRefresh: true,
        skipLoadingOnReload: true,
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => LoadError(error: error, onRetry: _reload),
        data: (parcel) {
          final look = parcelLook(parcel);
          final contact = parcel.contact;

          return Column(
            children: [
              const OfflineBanner(),
              Expanded(
                child: RefreshIndicator(
                  color: AppColors.green,
                  onRefresh: () async => _reload(),
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
                    children: [
                      Container(
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
                                  if ((look.body ?? '').trim().isNotEmpty) Text(look.body!, style: text.bodyMedium),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                      if (parcel.deliveryCode != null) ...[
                        const SizedBox(height: 12),
                        _CodePanel(parcel: parcel, onShare: () => _shareWithReceiver(parcel)),
                      ],
                      if (contact != null) ...[
                        const SizedBox(height: 12),
                        Panel(
                          child: Column(
                            children: [
                              DriverLine(
                                driver: parcel.trip.driver,
                                avatarSize: 48,
                                trailing: Ltr(Phone.local(contact.driverPhone), style: text.titleSmall),
                              ),
                              const SizedBox(height: 4),
                              Align(
                                alignment: AlignmentDirectional.centerStart,
                                child: Text(
                                  '${parcel.trip.vehicleName} — ${S.plate} ${contact.plateNumber}',
                                  style: text.bodySmall,
                                ),
                              ),
                              const SizedBox(height: 12),
                              Row(
                                children: [
                                  Expanded(
                                    child: FilledButton.icon(
                                      onPressed: () => _open('tel:${contact.driverPhone}'),
                                      icon: const Icon(Icons.call_rounded, size: 19),
                                      label: const Text(S.callDriver),
                                      style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(46)),
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: OutlinedButton.icon(
                                      onPressed: parcel.conversationId == null
                                          ? null
                                          : () => context.push('/chats/${parcel.conversationId}'),
                                      icon: const Icon(Icons.forum_outlined, size: 19),
                                      label: const Text(S.chat),
                                      style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(46)),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ],
                      const SizedBox(height: 12),
                      TripSummaryPanel(trip: parcel.trip, footer: _ParcelFacts(parcel: parcel)),
                      if (contact == null) ...[
                        const SizedBox(height: 12),
                        Panel(
                          child: DriverLine(
                            driver: parcel.trip.driver,
                            avatarSize: 48,
                            trailing: parcel.conversationId == null || parcel.status != ParcelStatus.pending
                                ? null
                                : IconButton(
                                    tooltip: S.chat,
                                    onPressed: () => context.push('/chats/${parcel.conversationId}'),
                                    icon: const Icon(Icons.forum_outlined, color: AppColors.green),
                                  ),
                          ),
                        ),
                      ],
                      if (parcel.pickupPhotoUrl != null) ...[
                        const SizedBox(height: 12),
                        Panel(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(S.pickupPhoto, style: text.labelMedium),
                              const SizedBox(height: 8),
                              ClipRRect(
                                borderRadius: BorderRadius.circular(12),
                                child: Image.network(
                                  parcel.pickupPhotoUrl!,
                                  height: 180,
                                  width: double.infinity,
                                  fit: BoxFit.cover,
                                  cacheWidth: 800,
                                  errorBuilder: (_, __, ___) => const SizedBox(height: 60),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                      const SizedBox(height: 20),
                      if (parcel.canDispute) ...[
                        OutlinedButton(
                          onPressed: _busy ? null : () => _dispute(parcel),
                          child: const Text(S.reportProblem),
                        ),
                        const SizedBox(height: 8),
                      ],
                      if (parcel.canCancel)
                        TextButton(
                          onPressed: _busy ? null : () => _cancel(parcel),
                          style: TextButton.styleFrom(foregroundColor: AppColors.danger),
                          child: const Text(S.cancelParcel),
                        ),
                    ],
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  /// Sends the ready-made message (driver, car, date, payment, code) to the
  /// receiver's WhatsApp, or copies it — the fallback when the automatic
  /// message didn't reach them (spec 5.6).
  Future<void> _shareWithReceiver(Parcel parcel) async {
    final message = parcel.shareText;
    if (message == null) return;

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
              Text(S.shareWithReceiver, style: Theme.of(sheet).textTheme.titleLarge),
              const SizedBox(height: 10),
              Panel(color: AppColors.background, child: Text(message, style: Theme.of(sheet).textTheme.bodyMedium)),
              const SizedBox(height: 14),
              FilledButton(
                onPressed: () {
                  Navigator.of(sheet).pop();
                  final digits = parcel.receiverPhone.replaceAll(RegExp(r'[^0-9]'), '');
                  _open('https://wa.me/$digits?text=${Uri.encodeComponent(message)}');
                },
                child: const Text(S.shareWhatsapp),
              ),
              const SizedBox(height: 8),
              OutlinedButton(
                onPressed: () async {
                  await Clipboard.setData(ClipboardData(text: message));
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
}

/// The 4-digit delivery code, large, with whether the receiver got it.
class _CodePanel extends StatelessWidget {
  const _CodePanel({required this.parcel, required this.onShare});

  final Parcel parcel;
  final VoidCallback onShare;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return Panel(
      child: Column(
        children: [
          Text(S.deliveryCode, style: text.labelMedium),
          Ltr(
            parcel.deliveryCode!.split('').join(' '),
            style: const TextStyle(fontFamily: AppFonts.display, fontSize: 44, height: 1.3, color: AppColors.green),
          ),
          Text(S.deliveryCodeHelp, style: text.bodySmall, textAlign: TextAlign.center),
          const SizedBox(height: 10),
          Tag(
            parcel.receiverNotified ? S.receiverNotified : S.receiverNotNotified,
            icon: parcel.receiverNotified ? Icons.check_rounded : Icons.warning_amber_rounded,
            color: parcel.receiverNotified ? AppColors.green : AppColors.ink,
            background: parcel.receiverNotified ? AppColors.greenTint : AppColors.amberTint,
          ),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: onShare,
            icon: const Icon(Icons.ios_share_rounded, size: 20),
            label: const Text(S.shareWithReceiver),
          ),
        ],
      ),
    );
  }
}

class _ParcelFacts extends StatelessWidget {
  const _ParcelFacts({required this.parcel});

  final Parcel parcel;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    Widget row(String label, Widget value) => Padding(
          padding: const EdgeInsets.symmetric(vertical: 3),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(width: 100, child: Text(label, style: text.labelMedium)),
              Expanded(child: value),
            ],
          ),
        );

    return Column(
      children: [
        row(S.parcelTitle, Text('${parcel.sizeLabel} — ${parcel.description}', style: text.titleSmall)),
        row(
          S.receiver,
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(parcel.receiverName, style: text.titleSmall),
              Ltr(Phone.local(parcel.receiverPhone), style: text.bodySmall),
            ],
          ),
        ),
        row(
          S.parcelPrice,
          Text(
            '${Fmt.money(parcel.price)} — ${parcel.senderPays ? S.iPay : S.receiverPays}',
            style: text.titleSmall?.copyWith(color: AppColors.green),
          ),
        ),
      ],
    );
  }
}
