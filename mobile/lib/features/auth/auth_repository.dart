import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../models/user.dart';

final authRepositoryProvider = Provider<AuthRepository>((ref) => AuthRepository(ref.watch(apiClientProvider)));

/// Answer to "send me a code".
class OtpTicket {
  const OtpTicket({required this.phone, required this.resendAfter, this.debugCode});

  /// The number as the server normalised it (+249…).
  final String phone;

  /// Seconds before another code can be requested.
  final int resendAfter;

  /// Only returned by a local development backend (OTP_EXPOSE_IN_RESPONSE).
  final String? debugCode;
}

class VerifiedSession {
  const VerifiedSession({required this.token, required this.user, required this.isNew});

  final String token;
  final User user;
  final bool isNew;
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
    );
  }

  Future<VerifiedSession> verifyOtp(String phone, String code) async {
    final json = Map<String, dynamic>.from(
      await _api.post('auth/verify', body: {'phone': phone, 'code': code, 'device_name': 'android'}) as Map,
    );
    return VerifiedSession(
      token: json['token'] as String,
      user: User.fromJson(Map<String, dynamic>.from(json['user'] as Map)),
      isNew: json['is_new'] as bool? ?? false,
    );
  }

  Future<void> logout() => _api.post('auth/logout');
}
