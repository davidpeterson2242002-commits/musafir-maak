/// The API sends times as Sudan local time with an offset, e.g.
/// `2026-11-12T07:30:00+02:00`.
///
/// Trip times must read the same on every phone, whatever its timezone setting,
/// so for display we keep the wall-clock part as written ([wall]). Countdowns
/// compare real moments, so those use [instant].
class ApiTime {
  const ApiTime._();

  /// The clock time exactly as written by the server (offset ignored).
  static DateTime? wall(Object? value) {
    if (value is! String || value.length < 16) return null;
    final match = RegExp(r'^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})').firstMatch(value);
    if (match == null) return null;
    return DateTime.utc(
      int.parse(match.group(1)!),
      int.parse(match.group(2)!),
      int.parse(match.group(3)!),
      int.parse(match.group(4)!),
      int.parse(match.group(5)!),
    );
  }

  /// The actual moment in time (for "expires in…" countdowns).
  static DateTime? instant(Object? value) => value is String ? DateTime.tryParse(value)?.toUtc() : null;

  /// A calendar day from `2026-11-12`.
  static DateTime? day(Object? value) {
    if (value is! String) return null;
    final match = RegExp(r'^(\d{4})-(\d{2})-(\d{2})').firstMatch(value);
    if (match == null) return null;
    return DateTime(int.parse(match.group(1)!), int.parse(match.group(2)!), int.parse(match.group(3)!));
  }
}
