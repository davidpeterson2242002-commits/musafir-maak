import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../models/user.dart';

final authRepositoryProvider = Provider<AuthRepository>((ref) => AuthRepository(ref.watch(apiClientProvider)));

/// Answer to "send me a code".
class OtpTicket {
  const OtpTicket({required this.phone, required this.resendAfter, this.debugCode, this.codeLength = 6});

  /// The number as the server normalised it (+249…).
  final String phone;

  /// Seconds before another code can be requested.
  final int resendAfter;

  /// Only returned by a local development backend (OTP_EXPOSE_IN_RESPONSE).
  final String? debugCode;

  /// How many digits the code has (admin setting, 4–8). The code boxes follow it.
  final int codeLength;
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

  Future<OtpTicket> requestOtp(String phone) async {
    final json = Map<String, dynamic>.from(await _api.post('auth/otp', body: {'phone': phone}) as Map);
    return OtpTicket(
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
      await _api.post('auth/device-login', body: {'phone': phone, 'device_token': deviceToken, 'device_name': 'android'})
          as Map,
    );
    return VerifiedSession.fromJson(json);
  }

  Future<void> logout() => _api.post('auth/logout');
}
