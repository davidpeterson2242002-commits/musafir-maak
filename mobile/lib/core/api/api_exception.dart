import '../strings.dart';

enum ApiErrorKind {
  /// No connection, DNS failure, connection refused.
  network,

  /// The request took too long (weak signal).
  timeout,

  /// 401 — the token is missing, expired or revoked.
  unauthorized,

  /// 403 — banned account or not allowed.
  forbidden,

  /// 404.
  notFound,

  /// 422 — form validation errors or a business rule (`code` is set for rules).
  validation,

  /// 429 — too many requests (e.g. OTP resend cooldown).
  rateLimited,

  /// 5xx.
  server,

  unknown,
}

/// Every failed API call surfaces as one of these, with a message that can be
/// shown to the user as-is (the backend already answers in Arabic).
class ApiException implements Exception {
  const ApiException({
    required this.kind,
    required this.message,
    this.statusCode,
    this.code,
    this.fieldErrors = const {},
    this.data = const {},
  });

  final ApiErrorKind kind;
  final String message;
  final int? statusCode;

  /// Business-rule code from the backend, e.g. `id_verification_required`, `account_banned`.
  final String? code;

  /// Validation errors keyed by field name (first message of each field).
  final Map<String, String> fieldErrors;

  /// The whole error body, for rules that return extra context.
  final Map<String, dynamic> data;

  bool get isOffline => kind == ApiErrorKind.network || kind == ApiErrorKind.timeout;

  String? fieldError(String field) => fieldErrors[field];

  /// Builds the exception from an HTTP error response.
  ///
  /// Laravel shapes: `{message}`, `{message, code, ...}` (business rules) and
  /// `{message, errors: {field: [messages]}}` (validation).
  factory ApiException.fromResponse(int? statusCode, Object? body) {
    final data = body is Map ? Map<String, dynamic>.from(body) : const <String, dynamic>{};
    final fieldErrors = <String, String>{};

    final errors = data['errors'];
    if (errors is Map) {
      errors.forEach((key, value) {
        if (value is List && value.isNotEmpty) {
          fieldErrors[key.toString()] = value.first.toString();
        } else if (value is String) {
          fieldErrors[key.toString()] = value;
        }
      });
    }

    final kind = switch (statusCode) {
      401 => ApiErrorKind.unauthorized,
      403 => ApiErrorKind.forbidden,
      404 => ApiErrorKind.notFound,
      422 => ApiErrorKind.validation,
      429 => ApiErrorKind.rateLimited,
      final int s when s >= 500 => ApiErrorKind.server,
      _ => ApiErrorKind.unknown,
    };

    // Laravel's summary reads "… (and 3 more errors)"; the first field error is cleaner.
    final serverMessage = fieldErrors.isNotEmpty ? fieldErrors.values.first : data['message'];

    final message = switch (kind) {
      ApiErrorKind.unauthorized => S.sessionExpired,
      ApiErrorKind.server => S.serverError,
      _ => (serverMessage is String && serverMessage.trim().isNotEmpty) ? serverMessage : S.unknownError,
    };

    return ApiException(
      kind: kind,
      message: message,
      statusCode: statusCode,
      code: data['code'] is String ? data['code'] as String : null,
      fieldErrors: fieldErrors,
      data: data,
    );
  }

  const ApiException.network()
      : kind = ApiErrorKind.network,
        message = S.noConnection,
        statusCode = null,
        code = null,
        fieldErrors = const {},
        data = const {};

  const ApiException.timeout()
      : kind = ApiErrorKind.timeout,
        message = S.timeout,
        statusCode = null,
        code = null,
        fieldErrors = const {},
        data = const {};

  const ApiException.unknown()
      : kind = ApiErrorKind.unknown,
        message = S.unknownError,
        statusCode = null,
        code = null,
        fieldErrors = const {},
        data = const {};

  @override
  String toString() => 'ApiException(${kind.name}, $statusCode, $message)';
}
