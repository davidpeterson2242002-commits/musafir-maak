/// Sudanese mobile numbers, mirroring the backend's `App\Support\Phone`:
/// 9 digits after the country code, starting with 9 or 1.
class Phone {
  const Phone._();

  static const _arabicIndic = '٠١٢٣٤٥٦٧٨٩';
  static const _easternArabic = '۰۱۲۳۴۵۶۷۸۹';

  /// Converts Arabic-Indic digits to Western ones and leaves other characters alone.
  static String westernDigits(String input) {
    final out = StringBuffer();
    for (final rune in input.runes) {
      final char = String.fromCharCode(rune);
      final a = _arabicIndic.indexOf(char);
      final e = _easternArabic.indexOf(char);
      out.write(a >= 0 ? a : (e >= 0 ? e : char));
    }
    return out.toString();
  }

  /// `0912345678`, `912345678`, `+249912345678`, `00249…`, Arabic digits and
  /// spaces/dashes → `+249912345678`. Returns null when it isn't a valid number.
  static String? normalize(String? input) {
    if (input == null) return null;

    var digits = westernDigits(input).replaceAll(RegExp(r'[^0-9]'), '');
    if (digits.startsWith('00249')) {
      digits = digits.substring(5);
    } else if (digits.startsWith('249') && digits.length == 12) {
      digits = digits.substring(3);
    } else if (digits.startsWith('0')) {
      digits = digits.substring(1);
    }

    return RegExp(r'^[19][0-9]{8}$').hasMatch(digits) ? '+249$digits' : null;
  }

  static bool isValid(String? input) => normalize(input) != null;

  /// `+249912345678` → `091 234 5678`, the way people read numbers out locally.
  static String local(String e164) {
    final match = RegExp(r'^\+249([0-9]{2})([0-9]{3})([0-9]{4})$').firstMatch(e164);
    if (match == null) return e164;
    return '0${match.group(1)} ${match.group(2)} ${match.group(3)}';
  }
}
