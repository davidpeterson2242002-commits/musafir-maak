import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/api/api_exception.dart';
import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import '../../models/identity.dart';
import '../../models/user.dart';
import '../auth/session_controller.dart';
import 'identity_repository.dart';

/// ID verification (spec 5.12, P6): choose the document, photograph it and a
/// selfie, send for review. Photos upload one at a time, as each is taken, so
/// a dropped connection never loses more than one.
///
/// Pops with `true` when the user may now request bookings.
class IdentityScreen extends ConsumerStatefulWidget {
  const IdentityScreen({super.key, this.forBooking = false, this.collectOnly = false});

  /// Driver registration: only collect the photos here — they are sent for
  /// review together with the licence and vehicle, as one application.
  final bool collectOnly;

  /// Opened from the booking form: the last button returns to it.
  final bool forBooking;

  @override
  ConsumerState<IdentityScreen> createState() => _IdentityScreenState();
}

class _IdentityScreenState extends ConsumerState<IdentityScreen> {
  static const _labels = {
    'selfie': (S.selfie, S.selfieHelp, Icons.face_rounded),
    'national_id_front': (S.idFront, S.docHelp, Icons.badge_outlined),
    'national_id_back': (S.idBack, S.docHelp, Icons.badge_outlined),
    'passport': (S.passportPage, S.docHelp, Icons.menu_book_rounded),
  };

  IdDocumentType _type = IdDocumentType.nationalId;
  bool _typeChosen = false;
  String? _uploadingKind;
  bool _submitting = false;
  Identity? _latest;

  Future<void> _capture(String kind) async {
    final selfie = kind == 'selfie';

    XFile? file;
    try {
      // Always the camera: a live photo of the person and of the real document.
      file = await ImagePicker().pickImage(
        source: ImageSource.camera,
        preferredCameraDevice: selfie ? CameraDevice.front : CameraDevice.rear,
        // Big enough to read the small print on an ID, small enough for mobile data (~300 KB).
        maxWidth: 1600,
        maxHeight: 1600,
        imageQuality: 80,
      );
    } catch (_) {
      file = null;
    }
    if (file == null || !mounted) return;

    setState(() => _uploadingKind = kind);
    try {
      final identity = await ref.read(identityRepositoryProvider).upload(kind, file.path);
      if (mounted) setState(() => _latest = identity);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.fieldError('file') ?? e.message);
    } finally {
      if (mounted) setState(() => _uploadingKind = null);
    }
  }

  Future<void> _submit() async {
    setState(() => _submitting = true);
    try {
      final identity = await ref.read(identityRepositoryProvider).submit(_type);
      await ref.read(sessionProvider.notifier).refresh();
      ref.invalidate(identityProvider);
      if (!mounted) return;
      setState(() => _latest = identity);
      showMessage(context, S.idSubmitted);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final loaded = ref.watch(identityProvider);

    return Scaffold(
      backgroundColor: AppColors.surface,
      appBar: AppBar(
        title: Text(S.idTitle, style: text.titleLarge),
        backgroundColor: AppColors.surface,
        scrolledUnderElevation: 0,
      ),
      body: loaded.when(
        skipLoadingOnRefresh: true,
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => LoadError(error: error, onRetry: () => ref.invalidate(identityProvider)),
        data: (fromServer) {
          final identity = _latest ?? fromServer;

          // A previous attempt with a passport: keep that choice selected.
          if (!_typeChosen && identity.uploadedKinds.contains('passport') && !identity.uploadedKinds.contains('national_id_front')) {
            _type = IdDocumentType.passport;
          }

          return SafeArea(
            child: switch (identity.status) {
              IdStatus.pending => _Result(
                  icon: Icons.hourglass_top_rounded,
                  title: S.idPending,
                  body: S.idPendingLong,
                  buttonLabel: widget.forBooking && identity.allowsBooking ? S.continueBooking : S.done,
                  onDone: () => context.pop(identity.allowsBooking),
                ),
              IdStatus.verified => _Result(
                  icon: Icons.verified_rounded,
                  title: S.idVerified,
                  body: S.idVerifiedLong,
                  buttonLabel: widget.forBooking ? S.continueBooking : S.done,
                  onDone: () => context.pop(true),
                ),
              _ => _form(context, identity),
            },
          );
        },
      ),
    );
  }

  Widget _form(BuildContext context, Identity identity) {
    final text = Theme.of(context).textTheme;
    final ready = identity.hasAll(_type);

    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
            children: [
              Text(S.idIntro, style: text.bodyMedium?.copyWith(color: AppColors.inkMuted)),
              if (identity.status == IdStatus.rejected) ...[
                const SizedBox(height: 14),
                Panel(
                  color: AppColors.dangerTint,
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(S.idRejectedIntro, style: text.titleSmall?.copyWith(color: AppColors.danger)),
                      for (final reason in identity.rejectionReasons) Text('• $reason', style: text.bodyMedium),
                      if ((identity.reviewNote ?? '').isNotEmpty) Text(identity.reviewNote!, style: text.bodyMedium),
                    ],
                  ),
                ),
              ],
              const SizedBox(height: 18),
              Text(S.idType, style: text.titleSmall),
              const SizedBox(height: 8),
              Row(
                children: [
                  for (final type in IdDocumentType.values) ...[
                    if (type != IdDocumentType.values.first) const SizedBox(width: 12),
                    Expanded(
                      child: _TypeTile(
                        label: type == IdDocumentType.nationalId ? S.nationalId : S.passport,
                        selected: _type == type,
                        onTap: () => setState(() {
                          _type = type;
                          _typeChosen = true;
                        }),
                      ),
                    ),
                  ],
                ],
              ),
              const SizedBox(height: 18),
              for (final kind in _type.requiredKinds)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: _PhotoTile(
                    title: _labels[kind]!.$1,
                    help: _labels[kind]!.$2,
                    icon: _labels[kind]!.$3,
                    uploaded: identity.uploadedKinds.contains(kind),
                    uploading: _uploadingKind == kind,
                    enabled: _uploadingKind == null && !_submitting,
                    onTap: () => _capture(kind),
                  ),
                ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
          child: widget.collectOnly
              ? FilledButton(
                  onPressed: ready && _uploadingKind == null ? () => context.pop(true) : null,
                  child: const Text(S.done),
                )
              : PrimaryButton(
                  label: S.submitForReview,
                  busy: _submitting,
                  onPressed: ready && _uploadingKind == null ? _submit : null,
                ),
        ),
      ],
    );
  }
}

class _TypeTile extends StatelessWidget {
  const _TypeTile({required this.label, required this.selected, required this.onTap});

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

/// One required photo: what to shoot, and whether it's on the server yet.
class _PhotoTile extends StatelessWidget {
  const _PhotoTile({
    required this.title,
    required this.help,
    required this.icon,
    required this.uploaded,
    required this.uploading,
    required this.enabled,
    required this.onTap,
  });

  final String title;
  final String help;
  final IconData icon;
  final bool uploaded;
  final bool uploading;
  final bool enabled;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: uploaded ? AppColors.greenTint : AppColors.surface,
        borderRadius: BorderRadius.circular(AppTheme.radius + 4),
        border: Border.all(color: uploaded ? AppColors.green : AppColors.line),
      ),
      child: Row(
        children: [
          Icon(uploaded ? Icons.check_circle_rounded : icon, color: AppColors.green, size: 30),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: text.titleSmall),
                Text(uploading ? S.uploading : (uploaded ? S.uploaded : help), style: text.bodySmall),
              ],
            ),
          ),
          const SizedBox(width: 8),
          if (uploading)
            const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2.5, color: AppColors.green))
          else
            TextButton.icon(
              onPressed: enabled ? onTap : null,
              icon: const Icon(Icons.photo_camera_outlined, size: 20),
              label: Text(uploaded ? S.retakePhoto : S.takePhoto),
            ),
        ],
      ),
    );
  }
}

class _Result extends StatelessWidget {
  const _Result({
    required this.icon,
    required this.title,
    required this.body,
    required this.buttonLabel,
    required this.onDone,
  });

  final IconData icon;
  final String title;
  final String body;
  final String buttonLabel;
  final VoidCallback onDone;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Expanded(child: EmptyState(icon: icon, title: title, body: body)),
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
          child: FilledButton(onPressed: onDone, child: Text(buttonLabel)),
        ),
      ],
    );
  }
}
