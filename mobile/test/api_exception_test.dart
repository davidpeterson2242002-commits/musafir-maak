import 'package:flutter_test/flutter_test.dart';
import 'package:musafir_maak/core/api/api_client.dart';
import 'package:musafir_maak/core/api/api_exception.dart';
import 'package:musafir_maak/core/strings.dart';

void main() {
  group('ApiException.fromResponse', () {
    test('validation error exposes field errors and shows the first one', () {
      final e = ApiException.fromResponse(422, {
        'message': 'الاسم يجب ألا يقل عن 2 حروف. (and 3 more errors)',
        'errors': {
          'name': ['الاسم يجب ألا يقل عن 2 حروف.'],
          'gender': ['الجنس مطلوب.'],
        },
      });

      expect(e.kind, ApiErrorKind.validation);
      expect(e.message, 'الاسم يجب ألا يقل عن 2 حروف.');
      expect(e.fieldError('gender'), 'الجنس مطلوب.');
      expect(e.fieldError('city_id'), isNull);
      expect(e.isOffline, isFalse);
    });

    test('business rule keeps its code and context', () {
      final e = ApiException.fromResponse(422, {
        'message': 'وثّق هويتك قبل الحجز.',
        'code': 'id_verification_required',
        'status': 'none',
      });

      expect(e.kind, ApiErrorKind.validation);
      expect(e.code, 'id_verification_required');
      expect(e.message, 'وثّق هويتك قبل الحجز.');
      expect(e.data['status'], 'none');
    });

    test('rate limit shows the server message (OTP cooldown)', () {
      final e = ApiException.fromResponse(429, {'message': 'انتظر 60 ثانية قبل طلب رمز جديد.'});
      expect(e.kind, ApiErrorKind.rateLimited);
      expect(e.message, 'انتظر 60 ثانية قبل طلب رمز جديد.');
    });

    test('401 and 5xx use our own wording, not the server text', () {
      expect(ApiException.fromResponse(401, {'message': 'Unauthenticated.'}).message, S.sessionExpired);
      expect(ApiException.fromResponse(401, null).kind, ApiErrorKind.unauthorized);

      final server = ApiException.fromResponse(500, '<html>Server Error</html>');
      expect(server.kind, ApiErrorKind.server);
      expect(server.message, S.serverError);
    });

    test('banned account', () {
      final e = ApiException.fromResponse(403, {'message': 'تم حظر هذا الحساب.', 'code': 'account_banned'});
      expect(e.kind, ApiErrorKind.forbidden);
      expect(e.code, 'account_banned');
    });

    test('unexpected body falls back to a generic message', () {
      expect(ApiException.fromResponse(418, null).message, S.unknownError);
      expect(ApiException.fromResponse(404, {'message': ''}).message, S.unknownError);
    });
  });

  test('network and timeout errors count as offline', () {
    expect(const ApiException.network().isOffline, isTrue);
    expect(const ApiException.timeout().isOffline, isTrue);
    expect(const ApiException.unknown().isOffline, isFalse);
  });

  group('ApiClient.unwrap', () {
    test('removes the resource envelope', () {
      expect(ApiClient.unwrap({'data': {'id': 1}}), {'id': 1});
      expect(ApiClient.unwrap({'data': [1, 2]}), [1, 2]);
    });

    test('leaves plain responses alone', () {
      final verify = {'token': 't', 'is_new': true, 'user': {'id': 1}};
      expect(ApiClient.unwrap(verify), verify);
      expect(ApiClient.unwrap({'data': [], 'meta': {'total': 0}}), {'data': [], 'meta': {'total': 0}});
      expect(ApiClient.unwrap(null), isNull);
    });
  });
}
