import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/formatters.dart';
import '../../core/utils/phone.dart';
import '../../core/widgets/common.dart';
import '../../models/parcel.dart';
import '../../models/trip.dart';
import '../../models/user.dart';
import '../auth/session_controller.dart';
import '../config/config_repository.dart';
import '../profile/photo_picker.dart';
import '../trips/trip_repository.dart';
import '../trips/trip_widgets.dart';
import 'parcel_repository.dart';

/// Parcel request (spec S2): what it is, how big, a photo, who receives it,
/// who pays, and the prohibited-items confirmation.
class ParcelRequestScreen extends ConsumerStatefulWidget {
  const ParcelRequestScreen({super.key, required this.tripId});

  final int tripId;

  @override
  ConsumerState<ParcelRequestScreen> createState() => _ParcelRequestScreenState();
}

class _ParcelRequestScreenState extends ConsumerState<ParcelRequestScreen> {
  static const _sizeHelp = {'small': S.sizeSmallHelp, 'medium': S.sizeMediumHelp, 'large': S.sizeLargeHelp};

  final _description = TextEditingController();
  final _receiverName = TextEditingController();
  final _receiverPhone = TextEditingController();
  final _message = TextEditingController();
  String? _size;
  String? _photoPath;
  bool _senderPays = true;
  bool _confirmed = false;
  bool _busy = false;
  Map<String, String> _errors = {};

  @override
  void dispose() {
    _description.dispose();
    _receiverName.dispose();
    _receiverPhone.dispose();
    _message.dispose();
    super.dispose();
  }

  bool _validate(String size) {
    final errors = <String, String>{};
    if (_description.text.trim().length < 3) errors['description'] = S.parcelDescriptionShort;
    if (_photoPath == null) errors['photo'] = S.parcelPhotoRequired;
    if (_receiverName.text.trim().length < 2) errors['receiver_name'] = S.receiverNameRequired;
    if (!Phone.isValid(_receiverPhone.text)) errors['receiver_phone'] = S.phoneInvalid;
    if (!_confirmed) errors['accept_prohibited'] = S.prohibitedRequired;
    setState(() => _errors = errors);
    return errors.isEmpty;
  }

  Future<void> _submit(Trip trip, String size) async {
    FocusScope.of(context).unfocus();
    if (!_validate(size)) return;

    final idStatus = ref.read(currentUserProvider)?.idStatus;
    if ((idStatus == IdStatus.none || idStatus == IdStatus.rejected) && !await _verifyIdentity()) return;

    setState(() => _busy = true);
    try {
      late Parcel parcel;
      try {
        parcel = await _create(trip, size);
      } on ApiException catch (e) {
        if (e.code != 'id_verification_required' || !await _verifyIdentity()) rethrow;
        parcel = await _create(trip, size);
      }

      if (!mounted) return;
      ref.invalidate(parcelsProvider);
      ref.invalidate(tripDetailsProvider(widget.tripId));
      showMessage(context, S.requestSent);
      context.pushReplacement('/parcels/${parcel.id}');
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.fieldErrors.isNotEmpty) {
        setState(() => _errors = e.fieldErrors);
      } else {
        showMessage(context, e.message);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<Parcel> _create(Trip trip, String size) => ref.read(parcelRepositoryProvider).create(
        trip.id,
        description: _description.text,
        size: size,
        photoPath: _photoPath!,
        receiverName: _receiverName.text,
        receiverPhone: _receiverPhone.text,
        senderPays: _senderPays,
        message: _message.text,
      );

  Future<bool> _verifyIdentity() async => await context.push<bool>('/identity', extra: true) ?? false;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final details = ref.watch(tripDetailsProvider(widget.tripId));
    final config = ref.watch(appConfigProvider).valueOrNull;
    final user = ref.watch(currentUserProvider);

    return Scaffold(
      backgroundColor: AppColors.surface,
      appBar: AppBar(
        title: Text(S.parcelRequest, style: text.titleLarge),
        backgroundColor: AppColors.surface,
        scrolledUnderElevation: 0,
      ),
      body: details.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => LoadError(error: error, onRetry: () => ref.invalidate(tripDetailsProvider(widget.tripId))),
        data: (details) {
          final trip = details.trip;
          final sizes = trip.parcelPrices.keys.toList();
          if (sizes.isEmpty || details.parcelSlotsLeft < 1) {
            return const EmptyState(icon: Icons.inventory_2_outlined, title: S.noParcelSlots, body: '');
          }

          final size = _size ?? sizes.first;
          final price = trip.parcelPrices[size] ?? 0;
          final sizeLabels = config?.parcelSizes ?? const <String, String>{};
          final prohibited = config?.prohibitedItems ?? const <String>[];
          final idMissing = user?.idStatus == IdStatus.none || user?.idStatus == IdStatus.rejected;

          return SafeArea(
            child: Column(
              children: [
                Expanded(
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
                    children: [
                      RouteTitle(from: trip.originName, to: trip.destinationName, style: text.headlineSmall),
                      Text(
                        '${Fmt.dayTime(trip.departsAt)} — ${trip.driver.name}',
                        style: text.bodyMedium?.copyWith(color: AppColors.inkMuted),
                      ),
                      const SizedBox(height: 20),
                      TextField(
                        controller: _description,
                        maxLines: 2,
                        maxLength: 500,
                        decoration: AppTheme.input(
                          label: S.parcelDescription,
                          hint: S.parcelDescriptionHint,
                          errorText: _errors['description'],
                        ).copyWith(counterText: ''),
                      ),
                      const SizedBox(height: 18),
                      Text(S.parcelSize, style: text.titleSmall),
                      const SizedBox(height: 8),
                      for (final key in sizes)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: _SizeTile(
                            label: sizeLabels[key] ?? key,
                            help: _sizeHelp[key] ?? '',
                            price: Fmt.money(trip.parcelPrices[key] ?? 0),
                            selected: key == size,
                            onTap: () => setState(() => _size = key),
                          ),
                        ),
                      const SizedBox(height: 10),
                      _PhotoRow(
                        path: _photoPath,
                        error: _errors['photo'],
                        onTap: () async {
                          final path = await pickProfilePhoto(context, preferFrontCamera: false);
                          if (path != null && mounted) setState(() => _photoPath = path);
                        },
                      ),
                      const Divider(height: 36),
                      Text(S.receiver, style: text.titleMedium),
                      const SizedBox(height: 12),
                      TextField(
                        controller: _receiverName,
                        textInputAction: TextInputAction.next,
                        decoration: AppTheme.input(label: S.receiverName, errorText: _errors['receiver_name']),
                      ),
                      const SizedBox(height: 14),
                      Directionality(
                        textDirection: TextDirection.ltr,
                        child: TextField(
                          controller: _receiverPhone,
                          keyboardType: TextInputType.phone,
                          decoration: AppTheme.input(hint: S.phoneHint, errorText: _errors['receiver_phone']),
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text('${S.receiverPhone} — ${S.receiverPhoneHelp}', style: text.bodySmall),
                      const SizedBox(height: 18),
                      Text(S.whoPays, style: text.titleSmall),
                      const SizedBox(height: 8),
                      Row(
                        children: [
                          Expanded(
                            child: _PayerTile(
                              label: S.iPay,
                              selected: _senderPays,
                              onTap: () => setState(() => _senderPays = true),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: _PayerTile(
                              label: S.receiverPays,
                              selected: !_senderPays,
                              onTap: () => setState(() => _senderPays = false),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 18),
                      TextField(
                        controller: _message,
                        maxLines: 2,
                        maxLength: 500,
                        decoration: AppTheme.input(hint: S.noteToDriver).copyWith(counterText: ''),
                      ),
                      const SizedBox(height: 18),
                      if (prohibited.isNotEmpty)
                        Panel(
                          color: AppColors.dangerTint,
                          padding: const EdgeInsets.all(12),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(S.prohibitedTitle, style: text.titleSmall?.copyWith(color: AppColors.danger)),
                              const SizedBox(height: 4),
                              Text(prohibited.join(' • '), style: text.bodyMedium?.copyWith(height: 1.6)),
                            ],
                          ),
                        ),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Checkbox(
                            value: _confirmed,
                            activeColor: AppColors.green,
                            onChanged: (value) => setState(() => _confirmed = value ?? false),
                          ),
                          Expanded(
                            child: Padding(
                              padding: const EdgeInsets.only(top: 11),
                              child: Text(S.prohibitedConfirm, style: text.bodyMedium),
                            ),
                          ),
                        ],
                      ),
                      if (_errors['accept_prohibited'] != null)
                        Padding(
                          padding: const EdgeInsetsDirectional.only(start: 12),
                          child: Text(
                            _errors['accept_prohibited']!,
                            style: const TextStyle(color: AppColors.danger, fontSize: 13.5),
                          ),
                        ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.fromLTRB(20, 12, 20, 12),
                  decoration: const BoxDecoration(border: Border(top: BorderSide(color: AppColors.line))),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Row(
                        children: [
                          Expanded(child: Text(S.parcelPrice, style: text.titleMedium)),
                          Text(Fmt.money(price), style: text.titleLarge?.copyWith(color: AppColors.green)),
                        ],
                      ),
                      Text(
                        _senderPays ? S.payDriverDirectly : '${S.receiverPays} عند الاستلام.',
                        style: text.bodySmall,
                      ),
                      const SizedBox(height: 10),
                      PrimaryButton(
                        label: idMissing ? S.verifyThenSend : S.sendParcelRequest,
                        busy: _busy,
                        onPressed: () => _submit(trip, size),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _SizeTile extends StatelessWidget {
  const _SizeTile({
    required this.label,
    required this.help,
    required this.price,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final String help;
  final String price;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppTheme.radius),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(
          color: selected ? AppColors.greenTint : AppColors.surface,
          borderRadius: BorderRadius.circular(AppTheme.radius),
          border: Border.all(color: selected ? AppColors.green : AppColors.line, width: selected ? 2 : 1),
        ),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(label, style: text.titleSmall),
                  if (help.isNotEmpty) Text(help, style: text.bodySmall),
                ],
              ),
            ),
            Text(price, style: text.titleSmall?.copyWith(color: AppColors.green)),
          ],
        ),
      ),
    );
  }
}

class _PayerTile extends StatelessWidget {
  const _PayerTile({required this.label, required this.selected, required this.onTap});

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppTheme.radius),
      child: Container(
        height: 52,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: selected ? AppColors.greenTint : AppColors.surface,
          borderRadius: BorderRadius.circular(AppTheme.radius),
          border: Border.all(color: selected ? AppColors.green : AppColors.line, width: selected ? 2 : 1),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 16,
            fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
            color: selected ? AppColors.green : AppColors.ink,
          ),
        ),
      ),
    );
  }
}

class _PhotoRow extends StatelessWidget {
  const _PhotoRow({required this.path, required this.onTap, this.error});

  final String? path;
  final String? error;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppTheme.radius),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(AppTheme.radius),
          border: Border.all(color: error != null ? AppColors.danger : AppColors.line),
        ),
        child: Row(
          children: [
            Container(
              width: 64,
              height: 64,
              clipBehavior: Clip.antiAlias,
              decoration: BoxDecoration(color: AppColors.greenTint, borderRadius: BorderRadius.circular(10)),
              child: path == null
                  ? const Icon(Icons.add_a_photo_outlined, color: AppColors.green)
                  : Image.file(File(path!), fit: BoxFit.cover, cacheWidth: 200),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(S.parcelPhoto, style: text.titleSmall),
                  Text(
                    error ?? S.parcelPhotoHelp,
                    style: text.bodySmall?.copyWith(color: error != null ? AppColors.danger : null),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
