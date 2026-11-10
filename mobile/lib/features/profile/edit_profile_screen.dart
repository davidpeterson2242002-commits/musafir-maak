import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/phone.dart';
import '../../core/widgets/common.dart';
import '../../models/city.dart';
import '../auth/session_controller.dart';
import '../geo/city_picker.dart';
import 'photo_picker.dart';
import 'profile_repository.dart';

/// Edit name, photo, city, email and WhatsApp number (spec P11). Gender is
/// fixed after signup; the phone number is the account itself.
class EditProfileScreen extends ConsumerStatefulWidget {
  const EditProfileScreen({super.key});

  @override
  ConsumerState<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends ConsumerState<EditProfileScreen> {
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _whatsapp = TextEditingController();
  City? _city;
  bool _busy = false;
  bool _uploading = false;
  Map<String, String> _errors = {};

  @override
  void initState() {
    super.initState();
    final user = ref.read(currentUserProvider);
    _name.text = user?.name ?? '';
    _email.text = user?.email ?? '';
    final whatsapp = user?.separateWhatsapp;
    _whatsapp.text = whatsapp == null ? '' : Phone.local(whatsapp).replaceAll(' ', '');
    _city = user?.city;
  }

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _whatsapp.dispose();
    super.dispose();
  }

  Future<void> _changePhoto() async {
    final path = await pickProfilePhoto(context);
    if (path == null || !mounted) return;

    setState(() => _uploading = true);
    try {
      final json = await ref.read(profileRepositoryProvider).uploadPhoto(path);
      await ref.read(sessionProvider.notifier).updateFromJson(json);
      if (mounted) showMessage(context, S.photoUpdated);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.fieldError('photo') ?? e.message);
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  Future<void> _save() async {
    FocusScope.of(context).unfocus();
    final errors = <String, String>{};
    final name = _name.text.trim();
    final email = _email.text.trim();
    final whatsapp = _whatsapp.text.trim();

    if (name.length < 2) errors['name'] = S.nameTooShort;
    if (email.isNotEmpty && !RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email)) errors['email'] = S.emailInvalid;
    if (whatsapp.isNotEmpty && !Phone.isValid(whatsapp)) errors['whatsapp_phone'] = S.phoneInvalid;

    setState(() => _errors = errors);
    if (errors.isNotEmpty) return;

    setState(() => _busy = true);
    try {
      final json = await ref.read(profileRepositoryProvider).update(
            name: name,
            cityId: _city?.id,
            email: email.isEmpty ? null : email,
            clearEmail: email.isEmpty,
            whatsappPhone: whatsapp.isEmpty ? null : whatsapp,
            clearWhatsapp: whatsapp.isEmpty,
          );
      await ref.read(sessionProvider.notifier).updateFromJson(json);
      if (!mounted) return;
      showMessage(context, S.changesSaved);
      context.pop();
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

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final user = ref.watch(currentUserProvider);

    return Scaffold(
      backgroundColor: AppColors.surface,
      appBar: AppBar(
        title: Text(S.editProfile, style: text.titleLarge),
        backgroundColor: AppColors.surface,
        scrolledUnderElevation: 0,
      ),
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(20, 12, 20, 16),
                children: [
                  Center(
                    child: Stack(
                      alignment: Alignment.center,
                      children: [
                        AppAvatar(photoUrl: user?.photoUrl, name: user?.name, size: 96),
                        if (_uploading) const CircularProgressIndicator(color: AppColors.green),
                      ],
                    ),
                  ),
                  Center(
                    child: TextButton(
                      onPressed: _uploading ? null : _changePhoto,
                      child: Text(user?.photoUrl == null ? S.addPhoto : S.changePhoto),
                    ),
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _name,
                    textInputAction: TextInputAction.next,
                    decoration: AppTheme.input(label: S.fullName, errorText: _errors['name']),
                  ),
                  const SizedBox(height: 18),
                  CityField(
                    label: S.city,
                    value: _city,
                    errorText: _errors['city_id'],
                    onChanged: (city) => setState(() => _city = city),
                  ),
                  const SizedBox(height: 18),
                  Directionality(
                    textDirection: TextDirection.ltr,
                    child: TextField(
                      controller: _email,
                      keyboardType: TextInputType.emailAddress,
                      textInputAction: TextInputAction.next,
                      decoration: AppTheme.input(hint: 'name@example.com', errorText: _errors['email']),
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text('${S.emailOptional} — ${S.emailHelp}', style: text.bodySmall),
                  const SizedBox(height: 18),
                  Directionality(
                    textDirection: TextDirection.ltr,
                    child: TextField(
                      controller: _whatsapp,
                      keyboardType: TextInputType.phone,
                      textInputAction: TextInputAction.done,
                      decoration: AppTheme.input(hint: S.phoneHint, errorText: _errors['whatsapp_phone']),
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text('${S.whatsappNumber} — ${S.whatsappHelp}', style: text.bodySmall),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
              child: PrimaryButton(label: S.saveChanges, onPressed: _save, busy: _busy),
            ),
          ],
        ),
      ),
    );
  }
}
