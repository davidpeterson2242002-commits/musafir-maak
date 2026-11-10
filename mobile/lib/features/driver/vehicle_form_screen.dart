import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/api/api_exception.dart';
import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/phone.dart';
import '../../core/widgets/common.dart';
import '../../core/widgets/photo_tile.dart';
import '../../models/driver.dart';
import '../config/config_repository.dart';
import 'driver_repository.dart';

/// Vehicle details and its five photos (spec D3). The details are saved first;
/// then each photo uploads as it is taken.
class VehicleFormScreen extends ConsumerStatefulWidget {
  const VehicleFormScreen({super.key, this.vehicle});

  /// The vehicle to complete or edit; null to add a new one.
  final Vehicle? vehicle;

  @override
  ConsumerState<VehicleFormScreen> createState() => _VehicleFormScreenState();
}

class _VehicleFormScreenState extends ConsumerState<VehicleFormScreen> {
  static const _photoLabels = {
    'vehicle_front': (S.photoFront, Icons.directions_car_filled_outlined),
    'vehicle_back': (S.photoBack, Icons.directions_car_filled_outlined),
    'vehicle_side': (S.photoSide, Icons.directions_car_filled_outlined),
    'vehicle_interior': (S.photoInterior, Icons.event_seat_outlined),
    'vehicle_registration': (S.photoRegistration, Icons.description_outlined),
  };

  final _make = TextEditingController();
  final _model = TextEditingController();
  final _color = TextEditingController();
  final _year = TextEditingController();
  final _plate = TextEditingController();
  final _seats = TextEditingController();
  String? _type;
  Vehicle? _vehicle;
  bool _saving = false;
  String? _uploadingKind;
  Map<String, String> _errors = {};

  @override
  void initState() {
    super.initState();
    final v = widget.vehicle;
    _vehicle = v;
    if (v != null) {
      _type = v.type;
      _make.text = v.make;
      _model.text = v.model;
      _color.text = v.color;
      _year.text = v.year?.toString() ?? '';
      _plate.text = v.plateNumber;
      _seats.text = v.seats.toString();
    }
  }

  @override
  void dispose() {
    for (final c in [_make, _model, _color, _year, _plate, _seats]) {
      c.dispose();
    }
    super.dispose();
  }

  int? _number(TextEditingController c) => int.tryParse(Phone.westernDigits(c.text.trim()));

  Future<void> _save(String type) async {
    FocusScope.of(context).unfocus();
    final errors = <String, String>{};
    if (_make.text.trim().isEmpty) errors['make'] = S.fieldRequired;
    if (_model.text.trim().isEmpty) errors['model'] = S.fieldRequired;
    if (_color.text.trim().isEmpty) errors['color'] = S.fieldRequired;
    if (_plate.text.trim().length < 2) errors['plate_number'] = S.fieldRequired;
    if ((_number(_seats) ?? 0) < 1) errors['seats'] = S.fieldRequired;
    setState(() => _errors = errors);
    if (errors.isNotEmpty) return;

    setState(() => _saving = true);
    try {
      final saved = await ref.read(driverRepositoryProvider).saveVehicle(
            id: _vehicle?.id,
            type: type,
            make: _make.text,
            model: _model.text,
            color: _color.text,
            year: _number(_year),
            plateNumber: _plate.text,
            seats: _number(_seats)!,
          );
      ref.invalidate(driverAccountProvider);
      if (!mounted) return;
      setState(() => _vehicle = saved);
      showMessage(context, S.vehicleSaved);
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.fieldErrors.isNotEmpty) {
        setState(() => _errors = e.fieldErrors);
      } else {
        showMessage(context, e.message);
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _photo(String kind) async {
    final vehicle = _vehicle;
    if (vehicle == null) return;

    XFile? file;
    try {
      file = await ImagePicker().pickImage(source: ImageSource.camera, maxWidth: 1600, maxHeight: 1600, imageQuality: 80);
    } catch (_) {
      file = null;
    }
    if (file == null || !mounted) return;

    setState(() => _uploadingKind = kind);
    try {
      final updated = await ref.read(driverRepositoryProvider).uploadVehiclePhoto(vehicle.id, kind, file.path);
      ref.invalidate(driverAccountProvider);
      if (mounted) setState(() => _vehicle = updated);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.fieldError('file') ?? e.message);
    } finally {
      if (mounted) setState(() => _uploadingKind = null);
    }
  }

  Future<void> _run(Future<void> Function() action, String success) async {
    setState(() => _saving = true);
    try {
      await action();
      ref.invalidate(driverAccountProvider);
      if (!mounted) return;
      showMessage(context, success);
      context.pop();
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final types = ref.watch(appConfigProvider).valueOrNull?.vehicleTypes ?? const <String, String>{};
    final type = _type ?? (types.isEmpty ? 'car' : types.keys.first);
    final vehicle = _vehicle;
    final locked = vehicle != null && !vehicle.editable;
    // A driver who is already approved sends each new vehicle for its own review.
    final driverApproved = ref.watch(driverAccountProvider).valueOrNull?.isApproved ?? false;

    Widget field(TextEditingController controller, String label, String key, {String? hint, bool number = false}) => Padding(
          padding: const EdgeInsets.only(bottom: 14),
          child: TextField(
            controller: controller,
            enabled: !locked,
            keyboardType: number ? TextInputType.number : TextInputType.text,
            inputFormatters: number ? [FilteringTextInputFormatter.allow(RegExp(r'[0-9٠-٩]'))] : null,
            textInputAction: TextInputAction.next,
            decoration: AppTheme.input(label: label, hint: hint, errorText: _errors[key]),
          ),
        );

    return Scaffold(
      backgroundColor: AppColors.surface,
      appBar: AppBar(
        title: Text(S.vehicleDetails, style: text.titleLarge),
        backgroundColor: AppColors.surface,
        scrolledUnderElevation: 0,
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
          children: [
            if (vehicle != null && vehicle.rejectionReasons.isNotEmpty) ...[
              Panel(
                color: AppColors.dangerTint,
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [for (final reason in vehicle.rejectionReasons) Text('• $reason', style: text.bodyMedium)],
                ),
              ),
              const SizedBox(height: 14),
            ],
            Text(S.vehicleType, style: text.titleSmall),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 4,
              children: [
                for (final entry in types.entries)
                  ChoiceChip(
                    label: Text(entry.value),
                    selected: entry.key == type,
                    onSelected: locked ? null : (_) => setState(() => _type = entry.key),
                    showCheckmark: false,
                    selectedColor: AppColors.greenTint,
                    backgroundColor: AppColors.surface,
                    side: BorderSide(color: entry.key == type ? AppColors.green : AppColors.line),
                    labelStyle: TextStyle(
                      fontFamily: AppFonts.body,
                      fontSize: 14.5,
                      fontWeight: FontWeight.w700,
                      color: entry.key == type ? AppColors.green : AppColors.inkMuted,
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 16),
            field(_make, S.vehicleMake, 'make', hint: S.vehicleMakeHint),
            field(_model, S.vehicleModel, 'model', hint: S.vehicleModelHint),
            field(_color, S.vehicleColor, 'color'),
            field(_plate, S.plateNumber, 'plate_number'),
            field(_seats, S.passengerSeats, 'seats', number: true),
            field(_year, S.vehicleYear, 'year', number: true),
            if (!locked)
              PrimaryButton(
                label: vehicle == null ? S.saveAndAddPhotos : S.saveChanges,
                busy: _saving,
                onPressed: () => _save(type),
              ),
            if (vehicle != null) ...[
              const SizedBox(height: 24),
              Text(S.vehiclePhotos, style: text.titleMedium),
              const SizedBox(height: 10),
              for (final kind in vehiclePhotoKinds)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: PhotoUploadTile(
                    title: _photoLabels[kind]!.$1,
                    icon: _photoLabels[kind]!.$2,
                    uploaded: vehicle.uploadedKinds.contains(kind),
                    uploading: _uploadingKind == kind,
                    enabled: !locked && _uploadingKind == null && !_saving,
                    onTap: () => _photo(kind),
                  ),
                ),
              if (!locked && vehicle.photosComplete) ...[
                const SizedBox(height: 6),
                if (driverApproved)
                  FilledButton(
                    onPressed: _saving
                        ? null
                        : () => _run(
                              () async => ref.read(driverRepositoryProvider).submitVehicle(vehicle.id),
                              S.vehicleSent,
                            ),
                    child: const Text(S.sendVehicleForReview),
                  )
                else
                  FilledButton(onPressed: () => context.pop(), child: const Text(S.done)),
              ],
              if (!locked) ...[
                const SizedBox(height: 4),
                TextButton(
                  onPressed: _saving
                      ? null
                      : () async {
                          final ok = await confirmSheet(
                            context,
                            title: S.deleteVehicleConfirm,
                            confirmLabel: S.deleteVehicle,
                            destructive: true,
                          );
                          if (ok) await _run(() => ref.read(driverRepositoryProvider).deleteVehicle(vehicle.id), S.done);
                        },
                  style: TextButton.styleFrom(foregroundColor: AppColors.danger),
                  child: const Text(S.deleteVehicle),
                ),
              ],
            ],
          ],
        ),
      ),
    );
  }
}
