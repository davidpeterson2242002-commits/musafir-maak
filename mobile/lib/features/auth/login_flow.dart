import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/config/env.dart';
import '../../core/strings.dart';
import '../../core/storage/cache_store.dart';
import '../../core/storage/token_store.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/formatters.dart';
import '../../core/utils/phone.dart';
import '../../core/widgets/common.dart';
import 'auth_repository.dart';
import 'session_controller.dart';

/// Step 1: phone number → WhatsApp code (spec P2).
class PhoneScreen extends ConsumerStatefulWidget {
  const PhoneScreen({super.key});

  @override
  ConsumerState<PhoneScreen> createState() => _PhoneScreenState();
}

class _PhoneScreenState extends ConsumerState<PhoneScreen> {
  final _controller = TextEditingController();
  String? _error;
  bool _busy = false;

  /// The account that last logged in on this phone: "welcome back" mode.
  Map<String, dynamic>? _returning;

  @override
  void initState() {
    super.initState();
    final last = ref.read(cacheStoreProvider).read(CacheKeys.lastLogin)?.data;
    if (last is Map && last['phone'] is String) {
      _returning = Map<String, dynamic>.from(last);
      _controller.text = Phone.local(_returning!['phone'] as String);
    }
    // "Your session expired" and similar, shown once.
    final notice = ref.read(sessionProvider).notice;
    if (notice != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) showMessage(context, notice);
      });
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final phone = Phone.normalize(_controller.text);
    if (phone == null) {
      setState(() => _error = S.phoneInvalid);
      return;
    }

    setState(() {
      _error = null;
      _busy = true;
    });

    // Trusted phone: this number verified a code on this phone before → log
    // straight in. If the server says the trust ran out, fall back to a code.
    final tokens = ref.read(tokenStoreProvider);
    final deviceToken = await tokens.readDevice(phone);
    if (deviceToken != null) {
      try {
        final session = await ref.read(authRepositoryProvider).deviceLogin(phone, deviceToken);
        await ref.read(sessionProvider.notifier).signIn(session);
        return; // the router takes it from here
      } on ApiException catch (e) {
        if (e.code == 'device_not_trusted') {
          await tokens.clearDevice(phone);
        } else {
          if (mounted) {
            setState(() {
              _error = e.message;
              _busy = false;
            });
          }
          return;
        }
      }
    }

    try {
      final ticket = await ref.read(authRepositoryProvider).requestOtp(phone);
      if (mounted) context.push('/login/code', extra: ticket);
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.kind == ApiErrorKind.rateLimited) {
        // A code was sent less than a minute ago: let them type it instead of blocking here.
        showMessage(context, e.message);
        context.push('/login/code', extra: OtpTicket(phone: phone, resendAfter: 60));
      } else {
        setState(() => _error = e.fieldError('phone') ?? e.message);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return Scaffold(
      backgroundColor: AppColors.surface,
      body: SafeArea(
        child: Padding(
          padding: AppTheme.screenPadding,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 48),
              if (_returning != null) ...[
                Align(
                  alignment: AlignmentDirectional.centerStart,
                  child: AppAvatar(
                    photoUrl: _returning!['photo_url'] as String?,
                    name: _returning!['name'] as String?,
                    size: 64,
                  ),
                ),
                const SizedBox(height: 14),
                Text(S.welcomeBack(_returning!['name'] as String? ?? ''), style: text.displaySmall),
                const SizedBox(height: 6),
                Text(S.loginBody, style: text.bodyLarge?.copyWith(color: AppColors.inkMuted)),
              ] else ...[
                Text(S.phoneTitle, style: text.displaySmall),
                const SizedBox(height: 6),
                Text(S.phoneBody, style: text.bodyLarge?.copyWith(color: AppColors.inkMuted)),
              ],
              const SizedBox(height: 28),
              // Digits are typed and read left-to-right.
              Directionality(
                textDirection: TextDirection.ltr,
                child: TextField(
                  controller: _controller,
                  autofocus: _returning == null,
                  keyboardType: TextInputType.phone,
                  textInputAction: TextInputAction.done,
                  autofillHints: const [AutofillHints.telephoneNumber],
                  inputFormatters: [LengthLimitingTextInputFormatter(16)],
                  style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w700, letterSpacing: 1.5),
                  onChanged: (_) {
                    if (_error != null) setState(() => _error = null);
                  },
                  onSubmitted: (_) => _submit(),
                  decoration: AppTheme.input(
                    hint: S.phoneHint,
                    prefixIcon: const Padding(
                      padding: EdgeInsets.only(left: 16, right: 10),
                      child: Text(
                        Env.countryCode,
                        style: TextStyle(fontSize: 18, fontWeight: FontWeight.w500, color: AppColors.inkMuted),
                      ),
                    ),
                  ).copyWith(prefixIconConstraints: const BoxConstraints(minWidth: 0, minHeight: 0)),
                ),
              ),
              if (_error != null) ...[
                const SizedBox(height: 8),
                Text(_error!, style: const TextStyle(color: AppColors.danger, fontSize: 14.5)),
              ],
              const Spacer(),
              PrimaryButton(label: _returning != null ? S.login : S.sendCode, onPressed: _submit, busy: _busy),
              if (_returning != null)
                TextButton(
                  onPressed: () => setState(() {
                    _returning = null;
                    _controller.clear();
                  }),
                  child: const Text(S.otherNumber),
                ),
              const SizedBox(height: 16),
            ],
          ),
        ),
      ),
    );
  }
}

/// Step 2: the 6-digit code, with a resend countdown.
class OtpScreen extends ConsumerStatefulWidget {
  const OtpScreen({super.key, required this.ticket});

  final OtpTicket ticket;

  @override
  ConsumerState<OtpScreen> createState() => _OtpScreenState();
}

class _OtpScreenState extends ConsumerState<OtpScreen> {
  static const _length = 6;

  final _controller = TextEditingController();
  final _focus = FocusNode();
  Timer? _timer;
  late int _secondsLeft;
  String? _error;
  bool _busy = false;
  bool _resending = false;

  @override
  void initState() {
    super.initState();
    _startCountdown(widget.ticket.resendAfter);
    // Local development backend returns the code; fill it to save typing.
    final debug = widget.ticket.debugCode;
    if (debug != null) {
      _controller.text = debug;
      // Test numbers: confirm straight away, no extra tap.
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _verify();
      });
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    _controller.dispose();
    _focus.dispose();
    super.dispose();
  }

  void _startCountdown(int seconds) {
    _timer?.cancel();
    _secondsLeft = seconds;
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) return;
      setState(() => _secondsLeft--);
      if (_secondsLeft <= 0) timer.cancel();
    });
  }

  Future<void> _verify() async {
    final code = Phone.westernDigits(_controller.text);
    if (code.length != _length || _busy) return;

    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      final session = await ref.read(authRepositoryProvider).verifyOtp(widget.ticket.phone, code);
      // The router redirects to profile setup or home once the session changes.
      await ref.read(sessionProvider.notifier).signIn(session);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.fieldError('code') ?? e.message);
      _controller.clear();
      _focus.requestFocus();
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _resend() async {
    setState(() {
      _resending = true;
      _error = null;
    });

    try {
      final ticket = await ref.read(authRepositoryProvider).requestOtp(widget.ticket.phone);
      if (!mounted) return;
      _startCountdown(ticket.resendAfter);
      if (ticket.debugCode != null) {
        _controller.text = ticket.debugCode!;
        _verify();
      }
      showMessage(context, S.codeResent);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _resending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final code = _controller.text;

    return Scaffold(
      backgroundColor: AppColors.surface,
      appBar: AppBar(backgroundColor: AppColors.surface, elevation: 0, scrolledUnderElevation: 0),
      body: SafeArea(
        child: Padding(
          padding: AppTheme.screenPadding,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(S.otpTitle, style: text.displaySmall),
              const SizedBox(height: 6),
              Text(
                S.otpBody(Phone.local(widget.ticket.phone)),
                style: text.bodyLarge?.copyWith(color: AppColors.inkMuted),
              ),
              const SizedBox(height: 28),
              // One real (invisible) field under six boxes: paste and SMS-style autofill just work.
              Directionality(
                textDirection: TextDirection.ltr,
                child: Stack(
                  children: [
                    Row(
                      children: [
                        for (var i = 0; i < _length; i++) ...[
                          if (i > 0) const SizedBox(width: 8),
                          Expanded(
                            child: _CodeBox(
                              digit: i < code.length ? code[i] : '',
                              active: _focus.hasFocus && i == code.length.clamp(0, _length - 1),
                              hasError: _error != null,
                            ),
                          ),
                        ],
                      ],
                    ),
                    Positioned.fill(
                      child: Opacity(
                        opacity: 0,
                        child: TextField(
                          controller: _controller,
                          focusNode: _focus,
                          autofocus: true,
                          keyboardType: TextInputType.number,
                          autofillHints: const [AutofillHints.oneTimeCode],
                          enableInteractiveSelection: false,
                          showCursor: false,
                          inputFormatters: [
                            FilteringTextInputFormatter.allow(RegExp(r'[0-9٠-٩]')),
                            LengthLimitingTextInputFormatter(_length),
                          ],
                          decoration: const InputDecoration(border: InputBorder.none, counterText: ''),
                          onChanged: (value) {
                            setState(() => _error = null);
                            if (value.length == _length) _verify();
                          },
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              if (_error != null) ...[
                const SizedBox(height: 12),
                Text(_error!, style: const TextStyle(color: AppColors.danger, fontSize: 14.5)),
              ],
              const SizedBox(height: 20),
              if (_secondsLeft > 0)
                Text(S.resendIn(Fmt.countdown(_secondsLeft)), style: text.bodyMedium?.copyWith(color: AppColors.inkMuted))
              else
                Align(
                  alignment: AlignmentDirectional.centerStart,
                  child: TextButton.icon(
                    onPressed: _resending ? null : _resend,
                    icon: const Icon(Icons.refresh_rounded, size: 20),
                    label: const Text(S.resendCode),
                  ),
                ),
              const Spacer(),
              PrimaryButton(
                label: S.verify,
                onPressed: code.length == _length ? _verify : null,
                busy: _busy,
              ),
              TextButton(onPressed: () => context.pop(), child: const Text(S.changeNumber)),
              const SizedBox(height: 8),
            ],
          ),
        ),
      ),
    );
  }
}

class _CodeBox extends StatelessWidget {
  const _CodeBox({required this.digit, required this.active, required this.hasError});

  final String digit;
  final bool active;
  final bool hasError;

  @override
  Widget build(BuildContext context) {
    final borderColor = hasError ? AppColors.danger : (active ? AppColors.green : AppColors.line);

    return Container(
      height: 60,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: digit.isEmpty ? AppColors.surface : AppColors.greenTint,
        borderRadius: BorderRadius.circular(AppTheme.radius),
        border: Border.all(color: borderColor, width: active || hasError ? 2 : 1),
      ),
      child: Text(
        digit,
        style: const TextStyle(fontSize: 26, fontWeight: FontWeight.w700, color: AppColors.ink),
      ),
    );
  }
}
