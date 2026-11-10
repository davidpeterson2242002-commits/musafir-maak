import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api/api_exception.dart';
import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/formatters.dart';
import '../../core/utils/phone.dart';
import '../../core/widgets/common.dart';
import '../../models/driver_money.dart';
import '../../models/parcel.dart';
import '../trips/trip_widgets.dart';
import 'driver_money_repository.dart';
import 'driver_repository.dart';
import 'driver_trip_repository.dart';

/// A parcel as the driver handles it (spec D11/D12): accept or reject the
/// request, photograph it at pickup (or refuse it), then hand it over against
/// the receiver's 4-digit code.
class DriverParcelCard extends ConsumerStatefulWidget {
  const DriverParcelCard({super.key, required this.parcel});

  final DriverParcel parcel;

  @override
  ConsumerState<DriverParcelCard> createState() => _DriverParcelCardState();
}

class _DriverParcelCardState extends ConsumerState<DriverParcelCard> {
  bool _busy = false;

  DriverMoneyRepository get _repo => ref.read(driverMoneyRepositoryProvider);

  Future<void> _run(Future<void> Function() action, String success) async {
    setState(() => _busy = true);
    try {
      await action();
      ref.invalidate(driverParcelRequestsProvider);
      ref.invalidate(driverTripProvider(widget.parcel.tripId));
      ref.invalidate(driverAccountProvider);
      if (mounted) showMessage(context, success);
    } on ApiException catch (e) {
      // Wrong code, no parcel slots left, frozen account… the server explains.
      ref.invalidate(driverTripProvider(widget.parcel.tripId));
      if (mounted) showMessage(context, e.fieldError('code') ?? e.fieldError('photo') ?? e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _pickUp() async {
    XFile? file;
    try {
      file = await ImagePicker().pickImage(source: ImageSource.camera, maxWidth: 1600, maxHeight: 1600, imageQuality: 80);
    } catch (_) {
      file = null;
    }
    if (file == null || !mounted) return;
    await _run(() => _repo.pickUpParcel(widget.parcel.id, file!.path), S.parcelPickedUpMsg);
  }

  Future<void> _deliver() async {
    final code = await _askCode(context, attemptsLeft: widget.parcel.codeAttemptsLeft);
    if (code != null && mounted) await _run(() => _repo.deliverParcel(widget.parcel.id, code), S.parcelDeliveredMsg);
  }

  Future<void> _open(String url) async {
    final opened = await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
    if (!opened && mounted) showMessage(context, S.unknownError);
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final parcel = widget.parcel;
    final pending = parcel.status == ParcelStatus.pending;
    final accepted = parcel.status == ParcelStatus.accepted;
    final pickedUp = parcel.status == ParcelStatus.pickedUp;

    Widget contact(String label, String name, String? phone) => Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(label, style: text.labelMedium),
                  Text(name, style: text.titleSmall),
                ],
              ),
            ),
            if (phone != null) ...[
              Ltr(Phone.local(phone), style: text.bodySmall),
              IconButton(
                tooltip: S.callDriver,
                onPressed: () => _open('tel:$phone'),
                icon: const Icon(Icons.call_rounded, color: AppColors.green),
              ),
            ],
          ],
        );

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
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (parcel.photoUrl != null) ...[
                ClipRRect(
                  borderRadius: BorderRadius.circular(10),
                  child: Image.network(
                    parcel.photoUrl!,
                    width: 68,
                    height: 68,
                    fit: BoxFit.cover,
                    cacheWidth: 220,
                    errorBuilder: (_, __, ___) => Container(
                      width: 68,
                      height: 68,
                      color: AppColors.background,
                      child: const Icon(Icons.inventory_2_outlined, color: AppColors.inkMuted),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
              ],
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('${parcel.sizeLabel} — ${parcel.description}', style: text.titleSmall),
                    Text(S.toReceiver(parcel.receiverName, parcel.receiverCity), style: text.bodySmall),
                    const SizedBox(height: 6),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [
                        Tag(Fmt.money(parcel.price)),
                        Tag(
                          parcel.receiverPays ? S.receiverPaysTag : S.senderPaysTag,
                          color: AppColors.ink,
                          background: AppColors.background,
                        ),
                        if (!pending) Tag(parcel.statusLabel, color: AppColors.ink, background: AppColors.amberTint),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
          if ((parcel.message ?? '').trim().isNotEmpty) ...[
            const SizedBox(height: 8),
            Text('"${parcel.message!.trim()}"', style: text.bodyMedium?.copyWith(color: AppColors.inkMuted)),
          ],
          const Divider(height: 22),
          InkWell(
            onTap: () => context.push('/users/${parcel.sender.id}'),
            child: contact(S.sender, parcel.sender.name, parcel.senderPhone),
          ),
          if (accepted || pickedUp) contact(S.receiver, parcel.receiverName, parcel.receiverPhone),
          if (pending) ...[
            const SizedBox(height: 4),
            Text(
              [
                parcel.feeFree ? S.feeFree : S.feeForBooking(Fmt.money(parcel.feeAmount)),
                if (parcel.expiresAt != null)
                  S.expiresIn(Fmt.remaining(parcel.expiresAt!.difference(DateTime.now().toUtc()))),
              ].join('\n'),
              style: text.bodySmall,
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: FilledButton(
                    onPressed: _busy ? null : () => _run(() => _repo.acceptParcel(parcel.id), S.parcelAcceptedMsg),
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
                            if (ok) await _run(() => _repo.rejectParcel(parcel.id), S.parcelRejectedMsg);
                          },
                    style: OutlinedButton.styleFrom(
                      minimumSize: const Size.fromHeight(46),
                      foregroundColor: AppColors.danger,
                      side: const BorderSide(color: AppColors.danger),
                    ),
                    child: const Text(S.reject),
                  ),
                ),
              ],
            ),
          ],
          if (accepted) ...[
            const SizedBox(height: 6),
            Text(S.pickupHelp, style: text.bodySmall),
            const SizedBox(height: 10),
            FilledButton.icon(
              onPressed: _busy ? null : _pickUp,
              icon: const Icon(Icons.photo_camera_outlined, size: 20),
              label: const Text(S.confirmPickup),
              style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(46)),
            ),
            TextButton(
              onPressed: _busy
                  ? null
                  : () async {
                      final reason = await askTextSheet(
                        context,
                        title: S.refusePickup,
                        hint: S.refuseReason,
                        action: S.refusePickup,
                      );
                      if (reason != null) await _run(() => _repo.refuseParcel(parcel.id, reason), S.parcelRefusedMsg);
                    },
              style: TextButton.styleFrom(foregroundColor: AppColors.danger),
              child: const Text(S.refusePickup),
            ),
          ],
          if (pickedUp) ...[
            const SizedBox(height: 10),
            FilledButton.icon(
              onPressed: _busy ? null : _deliver,
              icon: const Icon(Icons.pin_outlined, size: 20),
              label: const Text(S.deliverParcel),
              style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(46)),
            ),
          ],
          if (parcel.conversationId != null && (pending || accepted || pickedUp))
            Align(
              alignment: AlignmentDirectional.centerEnd,
              child: TextButton.icon(
                onPressed: () => context.push('/chats/${parcel.conversationId}'),
                icon: const Icon(Icons.forum_outlined, size: 18),
                label: const Text(S.chat),
              ),
            ),
        ],
      ),
    );
  }
}

/// Asks for the receiver's 4-digit delivery code.
Future<String?> _askCode(BuildContext context, {int? attemptsLeft}) {
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
          Text(S.enterDeliveryCode, style: Theme.of(sheet).textTheme.titleLarge),
          if (attemptsLeft != null) ...[
            const SizedBox(height: 4),
            Text(S.attemptsLeft(attemptsLeft), style: Theme.of(sheet).textTheme.bodySmall),
          ],
          const SizedBox(height: 12),
          Directionality(
            textDirection: TextDirection.ltr,
            child: TextField(
              controller: controller,
              autofocus: true,
              keyboardType: TextInputType.number,
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 30, fontWeight: FontWeight.w700, letterSpacing: 12),
              inputFormatters: [
                FilteringTextInputFormatter.allow(RegExp(r'[0-9٠-٩]')),
                LengthLimitingTextInputFormatter(4),
              ],
              decoration: AppTheme.input(hint: S.deliveryCodeHint),
            ),
          ),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: () {
              final code = Phone.westernDigits(controller.text.trim());
              if (code.length == 4) Navigator.of(sheet).pop(code);
            },
            child: const Text(S.confirmDelivery),
          ),
        ],
      ),
    ),
  );
}
