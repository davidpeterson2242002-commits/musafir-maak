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

  /// `7:30 ص` / `4:05 م`.
  static String time(DateTime t) {
    final hour = t.hour % 12 == 0 ? 12 : t.hour % 12;
    return '$hour:${t.minute.toString().padLeft(2, '0')} ${t.hour < 12 ? 'ص' : 'م'}';
  }

  /// `الخميس 12 نوفمبر، 7:30 ص`.
  static String dayTime(DateTime t) => '${dayMonth(t)}، ${time(t)}';

  /// Arabic counting: 1 → واحد form, 2 → dual, 3–10 → plural, 11+ → singular.
  static String count(int n, {required String one, required String two, required String few, required String many}) {
    if (n == 1) return one;
    if (n == 2) return two;
    return n >= 3 && n <= 10 ? '$n $few' : '$n $many';
  }

  /// Time left as words: `5 ساعات و20 دقيقة`, `45 دقيقة`, `أقل من دقيقة`.
  static String remaining(Duration d) {
    if (d.inSeconds < 60) return 'أقل من دقيقة';
    final hours = d.inHours;
    final minutes = d.inMinutes % 60;
    final h = count(hours, one: 'ساعة', two: 'ساعتين', few: 'ساعات', many: 'ساعة');
    final m = count(minutes, one: 'دقيقة', two: 'دقيقتين', few: 'دقائق', many: 'دقيقة');
    if (hours == 0) return m;
    return minutes == 0 ? h : '$h و$m';
  }

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
