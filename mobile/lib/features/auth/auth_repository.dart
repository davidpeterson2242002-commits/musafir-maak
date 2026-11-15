import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../models/user.dart';

final authRepositoryProvider = Provider<AuthRepository>((ref) => AuthRepository(ref.watch(apiClientProvider)));

/// Answer to "send me a code".
class OtpTicket {
  const OtpTicket({
    required this.phone,
    required this.resendAfter,
    this.debugCode,
    this.codeLength = 6,
    this.channel = 'email',
    this.sentTo,
    this.email,
  });

  /// The number as the server normalised it (+249…).
  final String phone;

  /// Seconds before another code can be requested.
  final int resendAfter;

  /// Only returned by a local development backend (OTP_EXPOSE_IN_RESPONSE).
  final String? debugCode;

  /// How many digits the code has (admin setting, 4–8). The code boxes follow it.
  final int codeLength;

  /// `email` or `whatsapp` (admin setting).
  final String channel;

  /// Masked address the code went to (m•••@gmail.com), when sent by email.
  final String? sentTo;

  /// The email the user typed (new accounts), sent again on "resend".
  final String? email;
}

class VerifiedSession {
  const VerifiedSession({required this.token, required this.user, required this.isNew, this.deviceToken});

  final String token;
  final User user;
  final bool isNew;

  /// Trusted-phone token for logging back in without a code (null when the server has it off).
  final String? deviceToken;

  factory VerifiedSession.fromJson(Map<String, dynamic> json) => VerifiedSession(
        token: json['token'] as String,
        user: User.fromJson(Map<String, dynamic>.from(json['user'] as Map)),
        isNew: json['is_new'] as bool? ?? false,
        deviceToken: json['device_token'] as String?,
      );
}

class AuthRepository {
  AuthRepository(this._api);

  final ApiClient _api;

  /// Throws [ApiException] with code `email_required` when this number has no account email
  /// yet: ask for one and call again with [email].
  Future<OtpTicket> requestOtp(String phone, {String? email}) async {
    final json = Map<String, dynamic>.from(
      await _api.post('auth/otp', body: {'phone': phone, if (email != null) 'email': email}) as Map,
    );
    return OtpTicket(
      channel: json['channel'] as String? ?? 'email',
      sentTo: json['sent_to'] as String?,
      email: email,
      phone: json['phone'] as String? ?? phone,
      resendAfter: (json['resend_after'] as num?)?.toInt() ?? 60,
      debugCode: json['debug_code'] as String?,
      codeLength: ((json['code_length'] as num?)?.toInt() ?? (json['debug_code'] as String?)?.length ?? 6).clamp(4, 8),
    );
  }

  Future<VerifiedSession> verifyOtp(String phone, String code) async {
    final json = Map<String, dynamic>.from(
      await _api.post('auth/verify', body: {'phone': phone, 'code': code, 'device_name': 'android'}) as Map,
    );
    return VerifiedSession.fromJson(json);
  }

  /// Logs back in on a trusted phone. Throws [ApiException] with code
  /// `device_not_trusted` when a code is needed instead.
  Future<VerifiedSession> deviceLogin(String phone, String deviceToken) async {
    final json = Map<String, dynamic>.from(
      await _api.post('auth/device-login', body: {'phone': phone, 'device_token': deviceToken, 'device_name': 'android'}, silent: true)
          as Map,
    );
    return VerifiedSession.fromJson(json);
  }

  Future<void> logout() => _api.post('auth/logout');
}
