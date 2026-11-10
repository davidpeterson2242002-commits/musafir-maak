import 'package:flutter_test/flutter_test.dart';
import 'package:musafir_maak/models/driver.dart';
import 'package:musafir_maak/models/identity.dart';

Map<String, dynamic> vehicleJson({List<String> photos = const []}) => {
      'id': 3,
      'type': 'minibus',
      'type_label': 'حافلة صغيرة (هايس)',
      'make': 'تويوتا',
      'model': 'هايس',
      'color': 'أبيض',
      'year': 2015,
      'plate_number': 'خ 12345',
      'seats': 14,
      'status': 'draft',
      'status_label': 'مسودة',
      'editable': true,
      'documents': [
        for (final kind in photos) {'kind': kind, 'label': kind, 'url': 'u', 'uploaded_at': 'x'},
      ],
      'missing_documents': [],
      'review': null,
    };

void main() {
  test('Vehicle tracks which photos are uploaded', () {
    final partial = Vehicle.fromJson(vehicleJson(photos: ['vehicle_front', 'vehicle_back']));
    expect(partial.name, 'تويوتا هايس');
    expect(partial.photosComplete, isFalse);
    expect(partial.uploadedKinds, {'vehicle_front', 'vehicle_back'});

    final complete = Vehicle.fromJson(vehicleJson(photos: vehiclePhotoKinds));
    expect(complete.photosComplete, isTrue);
    expect(complete.isApproved, isFalse);
  });

  group('DriverAccount.fromJson', () {
    Map<String, dynamic> accountJson({String status = 'draft', Map<String, dynamic>? review}) => {
          'status': status,
          'status_label': 'x',
          'editable': status == 'draft' || status == 'rejected',
          'can_operate': status == 'active',
          'free_bookings_remaining': 3,
          'balance_owed': 0,
          'wallet_balance': 0,
          'checklist': {'identity': true, 'identity_verified': false, 'driving_licence': true, 'vehicle': false},
          'documents': [
            {'kind': 'driving_licence', 'label': 'رخصة', 'url': 'u', 'uploaded_at': 'x'},
          ],
          'identity': {
            'status': 'none',
            'can_upload': true,
            'allows_booking': false,
            'documents': [
              {'kind': 'selfie'},
              {'kind': 'passport'},
            ],
            'review': null,
          },
          'vehicles': [vehicleJson()],
          'review': review,
        };

    test('draft with an unfinished vehicle is not ready to submit', () {
      final account = DriverAccount.fromJson(accountJson());

      expect(account.identityDone, isTrue);
      expect(account.licenceDone, isTrue);
      expect(account.vehicleDone, isFalse);
      expect(account.readyToSubmit, isFalse);
      expect(account.idDocumentType, IdDocumentType.passport);
      expect(account.vehicles.single.plateNumber, 'خ 12345');
      expect(account.isPending, isFalse);
      expect(account.isApproved, isFalse);
    });

    test('rejected application shows the reviewer\'s reasons', () {
      final account = DriverAccount.fromJson(accountJson(
        status: 'rejected',
        review: {'status': 'rejected', 'reasons': ['صورة الرخصة غير واضحة'], 'note': 'أعد تصوير الرخصة'},
      ));

      expect(account.editable, isTrue);
      expect(account.rejectionReasons, ['صورة الرخصة غير واضحة']);
      expect(account.reviewNote, 'أعد تصوير الرخصة');
    });

    test('pending and approved states', () {
      expect(DriverAccount.fromJson(accountJson(status: 'pending_review')).isPending, isTrue);
      final active = DriverAccount.fromJson(accountJson(status: 'active', review: {'status': 'approved', 'reasons': []}));
      expect(active.isApproved, isTrue);
      expect(active.canOperate, isTrue);
      expect(active.rejectionReasons, isEmpty);
      expect(DriverAccount.fromJson(accountJson(status: 'frozen')).isApproved, isTrue);
    });
  });
}
