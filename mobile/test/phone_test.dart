import 'package:flutter_test/flutter_test.dart';
import 'package:musafir_maak/core/utils/phone.dart';

void main() {
  group('Phone.normalize', () {
    test('accepts the common ways people type a Sudanese number', () {
      const expected = '+249912345678';
      expect(Phone.normalize('0912345678'), expected);
      expect(Phone.normalize('912345678'), expected);
      expect(Phone.normalize('+249912345678'), expected);
      expect(Phone.normalize('249912345678'), expected);
      expect(Phone.normalize('00249912345678'), expected);
      expect(Phone.normalize('091 234-5678'), expected);
      expect(Phone.normalize('٠٩١٢٣٤٥٦٧٨'), expected);
    });

    test('accepts numbers starting with 1 (Sudani / MTN ranges)', () {
      expect(Phone.normalize('0123456789'), '+249123456789');
    });

    test('rejects anything else', () {
      expect(Phone.normalize(null), isNull);
      expect(Phone.normalize(''), isNull);
      expect(Phone.normalize('123'), isNull);
      expect(Phone.normalize('0812345678'), isNull); // wrong prefix
      expect(Phone.normalize('09123456789'), isNull); // too long
      expect(Phone.normalize('+201001234567'), isNull); // not Sudan
      expect(Phone.isValid('abc'), isFalse);
    });
  });

  test('local display form', () {
    expect(Phone.local('+249912345678'), '091 234 5678');
    expect(Phone.local('not-a-number'), 'not-a-number');
  });

  test('westernDigits converts Arabic-Indic digits only', () {
    expect(Phone.westernDigits('٤٨٢٩١٣'), '482913');
    expect(Phone.westernDigits('رمز 12'), 'رمز 12');
  });
}
