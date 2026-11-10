import 'package:flutter_test/flutter_test.dart';
import 'package:musafir_maak/models/app_config.dart';
import 'package:musafir_maak/models/chat.dart';
import 'package:musafir_maak/models/parcel.dart';

import 'trips_bookings_test.dart' show tripJson;

Map<String, dynamic> parcelJson({String status = 'accepted'}) => {
      'id': 31,
      'status': status,
      'status_label': 'مقبولة',
      'description': 'ملابس في كرتونة',
      'size': 'medium',
      'size_label': 'متوسطة',
      'photo_url': 'https://example.test/p.jpg',
      'receiver_name': 'خالد عمر',
      'receiver_phone': '+249123456789',
      'payer': 'receiver',
      'price': 4000,
      'message': null,
      'created_at': '2026-11-10T14:00:00+02:00',
      'expires_at': status == 'pending' ? '2026-11-11T02:00:00+02:00' : null,
      'accepted_at': null,
      'picked_up_at': null,
      'pickup_photo_url': null,
      'delivered_at': null,
      'rejection_reason': null,
      'refusal_reason': null,
      'cancel_reason': null,
      'delivery_code': status == 'accepted' ? '4821' : null,
      'receiver_notified': status == 'accepted',
      'share_text': status == 'accepted' ? 'لديك ارسالية…' : null,
      'can_cancel': true,
      'can_dispute': false,
      'conversation_id': 8,
      'trip': tripJson(),
      'contact': status == 'accepted'
          ? {'driver_phone': '+249912345678', 'driver_whatsapp': '+249912345678', 'plate_number': 'خ 12345'}
          : null,
    };

Map<String, dynamic> conversationJson() => {
      'id': 8,
      'subject': {
        'type': 'parcel',
        'id': 31,
        'status': 'accepted',
        'status_label': 'مقبولة',
        'summary': 'الخرطوم ← ود مدني — 2026-11-12 07:30',
      },
      'my_role': 'sender',
      'other': {'id': 9, 'name': 'أحمد محمد', 'photo_url': null, 'rating_avg': 4.7, 'rating_count': 23, 'verified': true},
      'last_message': {'body': 'تمام، في الموعد', 'mine': false, 'created_at': '2026-11-10T15:20:00+02:00'},
      'unread': 2,
      'open': true,
      'last_message_at': '2026-11-10T15:20:00+02:00',
    };

void main() {
  group('Parcel.fromJson', () {
    test('accepted parcel carries the delivery code and driver contact', () {
      final parcel = Parcel.fromJson(parcelJson());

      expect(parcel.status, ParcelStatus.accepted);
      expect(parcel.deliveryCode, '4821');
      expect(parcel.receiverNotified, isTrue);
      expect(parcel.senderPays, isFalse);
      expect(parcel.contact?.plateNumber, 'خ 12345');
      expect(parcel.trip.destinationName, 'ود مدني');
      expect(parcel.conversationId, 8);
    });

    test('pending parcel has a countdown, no code, no contact', () {
      final parcel = Parcel.fromJson(parcelJson(status: 'pending'));

      expect(parcel.deliveryCode, isNull);
      expect(parcel.contact, isNull);
      expect(parcel.expiresAt, DateTime.utc(2026, 11, 11));
    });

    test('every backend status is recognised', () {
      const statuses = ['pending', 'accepted', 'picked_up', 'delivered', 'rejected', 'expired', 'refused', 'cancelled', 'disputed'];
      for (final status in statuses) {
        expect(ParcelStatus.parse(status), isNot(ParcelStatus.unknown), reason: status);
      }
      expect(ParcelStatus.parse('new_status'), ParcelStatus.unknown);
    });
  });

  group('Chat', () {
    test('Conversation.fromJson', () {
      final c = Conversation.fromJson(conversationJson());

      expect(c.isParcel, isTrue);
      expect(c.subjectId, 31);
      expect(c.other.name, 'أحمد محمد');
      expect(c.lastBody, 'تمام، في الموعد');
      expect(c.lastMine, isFalse);
      expect(c.unread, 2);
      expect(c.open, isTrue);
      expect([c.lastAt?.hour, c.lastAt?.minute], [15, 20]);
    });

    test('ChatPage reads messages and the conversation from the envelope', () {
      final page = ChatPage.fromJson({
        'data': [
          {'id': 1, 'mine': true, 'body': 'اتصل علي [رقم مخفي — يظهر بعد قبول الحجز]', 'was_masked': true, 'created_at': '2026-11-10T15:00:00+02:00'},
          {'id': 2, 'mine': false, 'body': 'تمام', 'was_masked': false, 'created_at': '2026-11-10T15:01:00+02:00'},
        ],
        'conversation': conversationJson(),
      });

      expect(page.messages.map((m) => m.id), [1, 2]);
      expect(page.messages.first.wasMasked, isTrue);
      expect(page.conversation?.id, 8);
    });

    test('ChatPage accepts a bare list (no new messages while polling)', () {
      final page = ChatPage.fromJson(<dynamic>[]);
      expect(page.messages, isEmpty);
      expect(page.conversation, isNull);
    });
  });

  group('PublicProfile.fromJson', () {
    test('driver with reviews', () {
      final profile = PublicProfile.fromJson({
        'data': {'id': 9, 'name': 'أحمد محمد', 'rating_avg': 4.0, 'rating_count': 2, 'trips_count': 3, 'verified': true, 'member_since': '2026-01-05'},
        'driver': {'rating_avg': 4.7, 'rating_count': 23, 'completed_trips': 41, 'driver_since': '2026-02-01'},
        'reviews': {
          'as_driver': [
            {
              'id': 1,
              'stars': 5,
              'comment': 'سائق ممتاز',
              'tags': [
                {'key': 'punctual', 'label': 'ملتزم بالموعد'},
              ],
              'role': 'as_driver',
              'rater_name': 'منى',
              'created_at': '2026-11-01T10:00:00+02:00',
            },
          ],
          'as_passenger': [],
        },
        'blocked_by_me': false,
      });

      expect(profile.isDriver, isTrue);
      expect(profile.driverRatingAvg, 4.7);
      expect(profile.driverTrips, 41);
      expect(profile.driverReviews.single.tags, ['ملتزم بالموعد']);
      expect(profile.driverReviews.single.raterName, 'منى');
      expect(profile.passengerReviews, isEmpty);
    });

    test('passenger only', () {
      final profile = PublicProfile.fromJson({
        'data': {'id': 4, 'name': 'منى'},
        'driver': null,
        'reviews': {'as_driver': [], 'as_passenger': []},
        'blocked_by_me': true,
      });

      expect(profile.isDriver, isFalse);
      expect(profile.blockedByMe, isTrue);
    });
  });

  test('AppConfig reads report reasons and rating tags', () {
    final config = AppConfig.fromJson({
      'settings': {},
      'rating_tags': {
        'for_driver': {'punctual': 'ملتزم بالموعد'},
        'for_passenger': {'polite': 'محترم'},
      },
      'report_reasons': {'harassment': 'تحرش أو مضايقة', 'other': 'أخرى'},
    });

    expect(config.reportReasons.keys, ['harassment', 'other']);
    expect(config.driverRatingTags['punctual'], 'ملتزم بالموعد');
    expect(config.passengerRatingTags['polite'], 'محترم');
  });
}
