/// Display helpers. Numbers always use Western digits (as on Sudanese
/// banknotes, plates and phone keypads), whatever the device locale.
class Fmt {
  const Fmt._();

  static const _months = [
    'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
    'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر',
  ];

  static const _weekdays = ['الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت', 'الأحد'];

  /// `5000` → `5,000`.
  static String number(num value) {
    final digits = value.round().abs().toString();
    final out = StringBuffer();
    for (var i = 0; i < digits.length; i++) {
      if (i > 0 && (digits.length - i) % 3 == 0) out.write(',');
      out.write(digits[i]);
    }
    return value < 0 ? '-$out' : out.toString();
  }

  static String money(num sdg) => '${number(sdg)} جنيه';

  /// `الخميس 12 نوفمبر`.
  static String dayMonth(DateTime date) => '${_weekdays[date.weekday - 1]} ${date.day} ${_months[date.month - 1]}';

  /// `نوفمبر 2026`.
  static String monthYear(DateTime date) => '${_months[date.month - 1]} ${date.year}';

  /// API date parameter, `2026-11-12`.
  static String isoDate(DateTime date) =>
      '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

  /// `75` seconds → `1:15`.
  static String countdown(int seconds) {
    final s = seconds < 0 ? 0 : seconds;
    return '${s ~/ 60}:${(s % 60).toString().padLeft(2, '0')}';
  }

  static bool isSameDay(DateTime a, DateTime b) => a.year == b.year && a.month == b.month && a.day == b.day;

  /// First letter of a name, for avatar placeholders.
  static String initial(String? name) {
    final trimmed = (name ?? '').trim();
    return trimmed.isEmpty ? '؟' : String.fromCharCode(trimmed.runes.first);
  }
}
