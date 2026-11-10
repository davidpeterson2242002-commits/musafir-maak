import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/api/api_exception.dart';
import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import '../../core/widgets/photo_tile.dart';
import '../../models/driver.dart';
import '../auth/session_controller.dart';
import '../trips/trip_widgets.dart';
import 'driver_repository.dart';

/// Driver registration (spec D1–D5) as one checklist the driver can leave and
/// come back to: identity, licence, vehicle, then send. Every photo uploads
/// on its own, so a weak connection never costs more than one retry.
class DriverRegistrationScreen extends ConsumerStatefulWidget {
  const DriverRegistrationScreen({super.key});

  @override
  ConsumerState<DriverRegistrationScreen> createState() => _DriverRegistrationScreenState();
}

class _DriverRegistrationScreenState extends ConsumerState<DriverRegistrationScreen> {
  bool _busy = false;
  bool _uploadingLicence = false;
  bool _acceptedTerms = false;
  String? _error;

  void _reload() => ref.invalidate(driverAccountProvider);

  Future<void> _start() async {
    setState(() => _busy = true);
    try {
      await ref.read(driverRepositoryProvider).start();
      _reload();
      ref.read(sessionProvider.notifier).refresh();
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _uploadLicence() async {
    XFile? file;
    try {
      file = await ImagePicker().pickImage(source: ImageSource.camera, maxWidth: 1600, maxHeight: 1600, imageQuality: 80);
    } catch (_) {
      file = null;
    }
    if (file == null || !mounted) return;

    setState(() => _uploadingLicence = true);
    try {
      await ref.read(driverRepositoryProvider).uploadLicence(file.path);
      _reload();
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.fieldError('file') ?? e.message);
    } finally {
      if (mounted) setState(() => _uploadingLicence = false);
    }
  }

  Future<void> _submit(DriverAccount account) async {
    if (!account.readyToSubmit) {
      setState(() => _error = S.completeStepsFirst);
      return;
    }
    if (!_acceptedTerms) {
      setState(() => _error = S.driverTermsRequired);
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(driverRepositoryProvider).submit(account.idDocumentType);
      _reload();
      await ref.read(sessionProvider.notifier).refresh();
      if (mounted) showMessage(context, S.applicationSent);
    } on ApiException catch (e) {
      // The server lists everything still missing in one message.
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final loaded = ref.watch(driverAccountProvider);

    return Scaffold(
      backgroundColor: AppColors.surface,
      appBar: AppBar(
        title: Text(S.driverRegTitle, style: text.titleLarge),
        backgroundColor: AppColors.surface,
        scrolledUnderElevation: 0,
      ),
      body: loaded.when(
        skipLoadingOnRefresh: true,
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => LoadError(error: error, onRetry: _reload),
        data: (account) => SafeArea(
          child: switch (account) {
            null => _Intro(busy: _busy, onStart: _start),
            DriverAccount(isPending: true) => const EmptyState(
                icon: Icons.hourglass_top_rounded,
                title: S.driverPending,
                body: S.driverPendingLong,
              ),
            DriverAccount(isApproved: true) && final approved => _Approved(account: approved, onChanged: _reload),
            final DriverAccount draft => _checklist(context, draft),
          },
        ),
      ),
    );
  }

  Widget _checklist(BuildContext context, DriverAccount account) {
    final text = Theme.of(context).textTheme;
    final vehicle = account.vehicles.isEmpty ? null : account.vehicles.first;

    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
            children: [
              if (account.status == 'rejected') ...[
                Panel(
                  color: AppColors.dangerTint,
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(S.driverRejectedTitle, style: text.titleSmall?.copyWith(color: AppColors.danger)),
                      for (final reason in account.rejectionReasons) Text('• $reason', style: text.bodyMedium),
                      if ((account.reviewNote ?? '').isNotEmpty) Text(account.reviewNote!, style: text.bodyMedium),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
              ],
              _StepCard(
                number: 1,
                title: S.stepIdentity,
                done: account.identityDone,
                onTap: () async {
                  await context.push('/identity', extra: 'driver');
                  _reload();
                },
              ),
              const SizedBox(height: 12),
              PhotoUploadTile(
                title: '2. ${S.stepLicence}',
                help: S.docHelp,
                icon: Icons.credit_card_rounded,
                uploaded: account.licenceDone,
                uploading: _uploadingLicence,
                enabled: !_busy,
                onTap: _uploadLicence,
              ),
              const SizedBox(height: 12),
              _StepCard(
                number: 3,
                title: S.stepVehicle,
                subtitle: vehicle == null ? null : '${vehicle.typeLabel} — ${vehicle.name} — ${vehicle.plateNumber}',
                done: account.vehicleDone,
                onTap: () async {
                  await context.push('/driver/vehicle', extra: vehicle);
                  _reload();
                },
              ),
              const SizedBox(height: 10),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Checkbox(
                    value: _acceptedTerms,
                    activeColor: AppColors.green,
                    onChanged: (value) => setState(() => _acceptedTerms = value ?? false),
                  ),
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.only(top: 11),
                      child: Text(S.acceptDriverTerms, style: text.bodyMedium),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (_error != null) ...[
                Text(_error!, style: const TextStyle(color: AppColors.danger, fontSize: 14.5)),
                const SizedBox(height: 8),
              ],
              PrimaryButton(label: S.submitApplication, busy: _busy, onPressed: () => _submit(account)),
            ],
          ),
        ),
      ],
    );
  }
}

/// D1: why drive with us, what to prepare, how fees work.
class _Intro extends StatelessWidget {
  const _Intro({required this.busy, required this.onStart});

  final bool busy;
  final VoidCallback onStart;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    Widget line(IconData icon, String label) => Padding(
          padding: const EdgeInsets.symmetric(vertical: 5),
          child: Row(
            children: [
              Icon(icon, size: 22, color: AppColors.green),
              const SizedBox(width: 10),
              Expanded(child: Text(label, style: text.bodyLarge?.copyWith(height: 1.4))),
            ],
          ),
        );

    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
            children: [
              Text(S.driverIntroTitle, style: text.displaySmall),
              const SizedBox(height: 10),
              line(Icons.airline_seat_recline_normal_rounded, S.driverBenefit1),
              line(Icons.inventory_2_outlined, S.driverBenefit2),
              line(Icons.verified_rounded, S.driverBenefit3),
              const SizedBox(height: 18),
              Text(S.driverNeedsTitle, style: text.titleMedium),
              const SizedBox(height: 4),
              line(Icons.badge_outlined, S.driverNeed1),
              line(Icons.credit_card_rounded, S.driverNeed2),
              line(Icons.description_outlined, S.driverNeed3),
              line(Icons.photo_camera_outlined, S.driverNeed4),
              const SizedBox(height: 18),
              Panel(
                color: AppColors.amberTint,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(S.driverFeesTitle, style: text.titleMedium),
                    const SizedBox(height: 4),
                    Text(S.driverFeesBody, style: text.bodyMedium),
                  ],
                ),
              ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
          child: PrimaryButton(label: S.startRegistration, busy: busy, onPressed: onStart),
        ),
      ],
    );
  }
}

class _StepCard extends StatelessWidget {
  const _StepCard({required this.number, required this.title, required this.done, required this.onTap, this.subtitle});

  final int number;
  final String title;
  final String? subtitle;
  final bool done;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppTheme.radius + 4),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: done ? AppColors.greenTint : AppColors.surface,
          borderRadius: BorderRadius.circular(AppTheme.radius + 4),
          border: Border.all(color: done ? AppColors.green : AppColors.line),
        ),
        child: Row(
          children: [
            Icon(done ? Icons.check_circle_rounded : Icons.radio_button_unchecked_rounded, color: AppColors.green, size: 28),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('$number. $title', style: text.titleSmall),
                  Text(subtitle ?? (done ? S.stepDone : S.stepTodo), style: text.bodySmall),
                ],
              ),
            ),
            const Icon(Icons.chevron_left_rounded, color: AppColors.inkMuted, textDirection: TextDirection.ltr),
          ],
        ),
      ),
    );
  }
}

/// After approval: account state and the driver's vehicles (spec D16).
class _Approved extends StatelessWidget {
  const _Approved({required this.account, required this.onChanged});

  final DriverAccount account;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final suspended = account.status == 'suspended';

    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
      children: [
        Panel(
          color: suspended ? AppColors.dangerTint : AppColors.greenTint,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                suspended ? Icons.pause_circle_outline_rounded : Icons.verified_rounded,
                color: suspended ? AppColors.danger : AppColors.green,
                size: 28,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(suspended ? S.driverSuspended : S.driverActive, style: text.titleMedium),
                    if (!suspended) Text(S.driverActiveLong, style: text.bodyMedium),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        OutlinedButton.icon(
          onPressed: () => context.push('/driver/balance'),
          icon: const Icon(Icons.account_balance_wallet_outlined, size: 20),
          label: const Text(S.balanceTitle),
        ),
        const SizedBox(height: 20),
        Text(S.myVehicles, style: text.titleMedium),
        const SizedBox(height: 10),
        for (final vehicle in account.vehicles)
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: InkWell(
              borderRadius: BorderRadius.circular(18),
              // Approved vehicles are locked (support changes them); others can be completed.
              onTap: vehicle.editable
                  ? () async {
                      await context.push('/driver/vehicle', extra: vehicle);
                      onChanged();
                    }
                  : null,
              child: Panel(
                child: Row(
                  children: [
                    const Icon(Icons.directions_car_filled_outlined, color: AppColors.green),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('${vehicle.typeLabel} — ${vehicle.name}', style: text.titleSmall),
                          Text('${vehicle.color} — ${vehicle.plateNumber}', style: text.bodySmall),
                        ],
                      ),
                    ),
                    Tag(
                      vehicle.statusLabel,
                      color: vehicle.isApproved ? AppColors.green : AppColors.ink,
                      background: vehicle.isApproved ? AppColors.greenTint : AppColors.amberTint,
                    ),
                  ],
                ),
              ),
            ),
          ),
        OutlinedButton.icon(
          onPressed: () async {
            await context.push('/driver/vehicle');
            onChanged();
          },
          icon: const Icon(Icons.add_rounded, size: 20),
          label: const Text(S.addVehicle),
        ),
      ],
    );
  }
}
