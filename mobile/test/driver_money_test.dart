import 'package:flutter_test/flutter_test.dart';
import 'package:musafir_maak/models/driver_money.dart';
import 'package:musafir_maak/models/parcel.dart';

void main() {
  Map<String, dynamic> parcelJson({String status = 'pending'}) => {
        'id': 31,
        'trip_id': 5,
        'status': status,
        'status_label': 'x',
        'sender': {'id': 4, 'name': 'منى عثمان', 'rating_avg': 0, 'rating_count': 0},
        'sender_phone': status == 'pending' ? null : '+249911223344',
        'description': 'ملابس في كرتونة',
        'size': 'medium',
        'size_label': 'متوسطة',
        'photo_url': 'https://example.test/p.jpg',
        'receiver_name': 'خالد عمر',
        'receiver_phone': status == 'pending' ? null : '+249123456789',
        'receiver_city': 'ود مدني',
        'payer': 'receiver',
        'price': 4000,
        'message': null,
        'expires_at': status == 'pending' ? '2026-11-11T02:00:00+02:00' : null,
        'pickup_photo_url': null,
        'code_attempts_left': status == 'picked_up' ? 4 : null,
        'fee': status == 'pending' ? {'amount': 400, 'free': false} : {'amount': 400, 'free': false, 'reversed': false},
        'conversation_id': 8,
      };

  group('DriverParcel.fromJson', () {
    test('pending request: fee preview, no phones', () {
      final parcel = DriverParcel.fromJson(parcelJson());

      expect(parcel.status, ParcelStatus.pending);
      expect(parcel.sender.name, 'منى عثمان');
      expect(parcel.receiverCity, 'ود مدني');
      expect(parcel.receiverPays, isTrue);
      expect(parcel.feeAmount, 400);
      expect(parcel.senderPhone, isNull);
      expect(parcel.receiverPhone, isNull);
      expect(parcel.expiresAt, isNotNull);
    });

    test('picked up: phones and code attempts', () {
      final parcel = DriverParcel.fromJson(parcelJson(status: 'picked_up'));

      expect(parcel.status, ParcelStatus.pickedUp);
      expect(parcel.receiverPhone, '+249123456789');
      expect(parcel.codeAttemptsLeft, 4);
    });
  });

  test('DriverBalance.fromJson', () {
    final balance = DriverBalance.fromJson({
      'status': 'frozen',
      'balance_owed': 4500,
      'wallet_balance': 0,
      'settlement_due_at': '2026-11-14T23:59:00+02:00',
      'freezes_at': '2026-11-16T23:59:00+02:00',
      'overdue': true,
      'free_bookings_remaining': 0,
      'pending_payments': 2000,
      'fee_terms': {'model': 'percentage', 'cycle_days': 7, 'grace_hours': 48},
    });

    expect(balance.balanceOwed, 4500);
    expect(balance.frozen, isTrue);
    expect(balance.overdue, isTrue);
    expect(balance.settlementDueAt?.day, 14);
    expect(balance.freezesAt?.day, 16);
    expect(balance.pendingPayments, 2000);
    expect(balance.cycleDays, 7);

    const empty = DriverBalance();
    expect(empty.balanceOwed, 0);
    expect(empty.frozen, isFalse);
  });

  test('LedgerEntry, PaymentMethod and SettlementPayment', () {
    final fee = LedgerEntry.fromJson({
      'id': 1,
      'type': 'booking_fee',
      'type_label': 'عمولة حجز',
      'bucket': 'balance',
      'amount': 1500,
      'balance_after': 4500,
      'wallet_after': 0,
      'reference': {'type': 'booking', 'id': 77},
      'note': null,
      'created_at': '2026-11-10T14:00:00+02:00',
    });
    expect(fee.label, 'عمولة حجز');
    expect(fee.amount, 1500);
    expect(fee.createdAt?.hour, 14);

    final method = PaymentMethod.fromJson({
      'id': 2,
      'name': 'بنكك',
      'account_name': 'مسافر معاك',
      'account_number': '1234567',
      'instructions': 'حوّل ثم أرفق الإشعار',
      'agent_locations': [],
      'requires_reference': true,
    });
    expect(method.requiresReference, isTrue);
    expect(method.accountNumber, '1234567');
    expect(method.agentLocations, isEmpty);

    final payment = SettlementPayment.fromJson({
      'id': 9,
      'purpose': 'settlement',
      'purpose_label': 'سداد الرصيد',
      'amount': 4500,
      'payment_method': 'بنكك',
      'reference_number': 'TX1',
      'receipt_url': 'u',
      'status': 'rejected',
      'status_label': 'مرفوض',
      'reject_reason': 'الإشعار غير واضح',
      'created_at': '2026-11-10T15:00:00+02:00',
      'reviewed_at': null,
    });
    expect(payment.status, 'rejected');
    expect(payment.rejectReason, 'الإشعار غير واضح');
  });
}
