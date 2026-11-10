import 'dart:io';

import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import '../../models/city.dart';
import '../../models/user.dart';
import '../geo/city_picker.dart';
import '../profile/photo_picker.dart';
import '../profile/profile_repository.dart';
import 'session_controller.dart';

/// First login: name, gender, city, optional email and photo, terms (spec P2).
class ProfileSetupScreen extends ConsumerStatefulWidget {
  const ProfileSetupScreen({super.key});

  @override
  ConsumerState<ProfileSetupScreen> createState() => _ProfileSetupScreenState();
}

class _ProfileSetupScreenState extends ConsumerState<ProfileSetupScreen> {
  final _name = TextEditingController();
  final _email = TextEditingController();
  late final TapGestureRecognizer _termsTap;
  late final TapGestureRecognizer _privacyTap;

  Gender? _gender;
  City? _city;
  String? _photoPath;
  bool _accepted = false;
  bool _busy = false;
  Map<String, String> _errors = {};

  @override
  void initState() {
    super.initState();
    _termsTap = TapGestureRecognizer()..onTap = () => context.push('/page/terms');
    _privacyTap = TapGestureRecognizer()..onTap = () => context.push('/page/privacy');

    // Returning user whose profile is half-filled (or gender already locked).
    final user = ref.read(currentUserProvider);
    _name.text = user?.name ?? '';
    _email.text = user?.email ?? '';
    _gender = user?.gender;
    _city = user?.city;
  }

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _termsTap.dispose();
    _privacyTap.dispose();
    super.dispose();
  }

  bool _validate() {
    final errors = <String, String>{};
    final email = _email.text.trim();

    if (_name.text.trim().length < 2) errors['name'] = S.nameTooShort;
    if (_gender == null) errors['gender'] = S.genderRequired;
    if (_city == null) errors['city_id'] = S.cityRequired;
    if (email.isNotEmpty && !RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email)) {
      errors['email'] = S.emailInvalid;
    }
    if (!_accepted) errors['accept_terms'] = S.termsRequired;

    setState(() => _errors = errors);
    return errors.isEmpty;
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!_validate()) return;

    setState(() => _busy = true);
    final repo = ref.read(profileRepositoryProvider);
    final session = ref.read(sessionProvider.notifier);
    final genderLocked = ref.read(currentUserProvider)?.gender != null;

    try {
      var json = await repo.update(
        name: _name.text.trim(),
        gender: genderLocked ? null : _gender,
        cityId: _city!.id,
        email: _email.text.trim().isEmpty ? null : _email.text.trim(),
        acceptTerms: true,
      );

      // The photo is optional: if its upload fails the account is still created
      // and the photo can be added later from the profile.
      if (_photoPath != null) {
        try {
          json = await repo.uploadPhoto(_photoPath!);
        } on ApiException catch (e) {
          if (mounted) showMessage(context, e.message);
        }
      }

      await session.updateFromJson(json); // router moves to home
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
    final genderLocked = ref.watch(currentUserProvider)?.gender != null;

    return Scaffold(
      backgroundColor: AppColors.surface,
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(20, 32, 20, 16),
                children: [
                  Text(S.setupTitle, style: text.displaySmall),
                  const SizedBox(height: 6),
                  Text(S.setupBody, style: text.bodyLarge?.copyWith(color: AppColors.inkMuted)),
                  const SizedBox(height: 24),
                  Center(
                    child: _PhotoButton(
                      path: _photoPath,
                      onTap: () async {
                        final path = await pickProfilePhoto(context);
                        if (path != null && mounted) setState(() => _photoPath = path);
                      },
                    ),
                  ),
                  const SizedBox(height: 24),
                  TextField(
                    controller: _name,
                    textInputAction: TextInputAction.next,
                    textCapitalization: TextCapitalization.words,
                    autofillHints: const [AutofillHints.name],
                    decoration: AppTheme.input(label: S.fullName, errorText: _errors['name']),
                  ),
                  const SizedBox(height: 18),
                  Text(S.gender, style: text.labelLarge),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: _ChoiceTile(
                          label: S.male,
                          selected: _gender == Gender.male,
                          enabled: !genderLocked,
                          onTap: () => setState(() => _gender = Gender.male),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _ChoiceTile(
                          label: S.female,
                          selected: _gender == Gender.female,
                          enabled: !genderLocked,
                          onTap: () => setState(() => _gender = Gender.female),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Text(
                    _errors['gender'] ?? S.genderHelp,
                    style: text.bodySmall?.copyWith(color: _errors['gender'] != null ? AppColors.danger : null),
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
                      textInputAction: TextInputAction.done,
                      autofillHints: const [AutofillHints.email],
                      textAlign: TextAlign.left,
                      decoration: AppTheme.input(hint: 'name@example.com', errorText: _errors['email']),
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text('${S.emailOptional} — ${S.emailHelp}', style: text.bodySmall),
                  const SizedBox(height: 14),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Checkbox(
                        value: _accepted,
                        activeColor: AppColors.green,
                        onChanged: (value) => setState(() => _accepted = value ?? false),
                      ),
                      Expanded(
                        child: Padding(
                          padding: const EdgeInsets.only(top: 11),
                          child: Text.rich(
                            TextSpan(
                              style: text.bodyMedium,
                              children: [
                                const TextSpan(text: S.acceptTermsPrefix),
                                TextSpan(text: S.terms, style: _linkStyle, recognizer: _termsTap),
                                const TextSpan(text: S.and),
                                TextSpan(text: S.privacy, style: _linkStyle, recognizer: _privacyTap),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                  if (_errors['accept_terms'] != null)
                    Padding(
                      padding: const EdgeInsetsDirectional.only(start: 12),
                      child: Text(_errors['accept_terms']!, style: const TextStyle(color: AppColors.danger, fontSize: 13.5)),
                    ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
              child: PrimaryButton(label: S.createAccount, onPressed: _submit, busy: _busy),
            ),
          ],
        ),
      ),
    );
  }

  static const _linkStyle = TextStyle(
    color: AppColors.green,
    fontWeight: FontWeight.w700,
    decoration: TextDecoration.underline,
    decorationColor: AppColors.green,
  );
}

class _PhotoButton extends StatelessWidget {
  const _PhotoButton({required this.path, required this.onTap});

  final String? path;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Column(
        children: [
          Container(
            width: 96,
            height: 96,
            clipBehavior: Clip.antiAlias,
            decoration: BoxDecoration(
              color: AppColors.greenTint,
              shape: BoxShape.circle,
              border: Border.all(color: AppColors.green, width: 1.5),
            ),
            child: path == null
                ? const Icon(Icons.add_a_photo_outlined, size: 32, color: AppColors.green)
                : Image.file(File(path!), fit: BoxFit.cover, cacheWidth: 300),
          ),
          const SizedBox(height: 8),
          Text(
            path == null ? S.addPhoto : S.changePhoto,
            style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w700, color: AppColors.green),
          ),
        ],
      ),
    );
  }
}

/// One of two large either/or choices (gender).
class _ChoiceTile extends StatelessWidget {
  const _ChoiceTile({required this.label, required this.selected, required this.onTap, this.enabled = true});

  final String label;
  final bool selected;
  final bool enabled;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: enabled ? onTap : null,
      borderRadius: BorderRadius.circular(AppTheme.radius),
      child: Container(
        height: 54,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: selected ? AppColors.greenTint : AppColors.surface,
          borderRadius: BorderRadius.circular(AppTheme.radius),
          border: Border.all(color: selected ? AppColors.green : AppColors.line, width: selected ? 2 : 1),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (selected) ...[
              const Icon(Icons.check_rounded, size: 20, color: AppColors.green),
              const SizedBox(width: 6),
            ],
            Text(
              label,
              style: TextStyle(
                fontSize: 16.5,
                fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                color: selected ? AppColors.green : AppColors.ink,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
