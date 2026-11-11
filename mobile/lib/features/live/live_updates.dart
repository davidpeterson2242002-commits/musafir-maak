import 'dart:async';

import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../core/storage/cache_store.dart';
import '../auth/session_controller.dart';
import '../bookings/booking_repository.dart';
import '../chat/chat_repository.dart';
import '../driver/driver_money_repository.dart';
import '../driver/driver_trip_repository.dart';
import '../parcels/parcel_repository.dart';
import '../ratings/rating_repository.dart';

/// What `GET me/badges` returns.
class LiveBadges {
  const LiveBadges({
    this.unreadMessages = 0,
    this.driverPendingRequests = 0,
    this.driverPendingParcels = 0,
    this.bookingStatuses = const {},
    this.parcelStatuses = const {},
    this.pendingRatings = 0,
  });

  factory LiveBadges.fromJson(Map<String, dynamic> json) {
    final driver = json['driver'] is Map ? Map<String, dynamic>.from(json['driver'] as Map) : null;
    Map<int, String> statuses(Object? list) => {
          for (final e in (list as List? ?? const []))
            ((e as Map)['id'] as num).toInt(): e['status'] as String,
        };
    return LiveBadges(
      unreadMessages: (json['unread_messages'] as num?)?.toInt() ?? 0,
      driverPendingRequests: (driver?['pending_requests'] as num?)?.toInt() ?? 0,
      driverPendingParcels: (driver?['pending_parcels'] as num?)?.toInt() ?? 0,
      bookingStatuses: statuses(json['bookings']),
      parcelStatuses: statuses(json['parcels']),
      pendingRatings: (json['pending_ratings'] as num?)?.toInt() ?? 0,
    );
  }

  final int unreadMessages;
  final int driverPendingRequests;
  final int driverPendingParcels;
  final Map<int, String> bookingStatuses;
  final Map<int, String> parcelStatuses;
  final int pendingRatings;

  int get driverPending => driverPendingRequests + driverPendingParcels;
}

/// A change on one of the passenger's bookings or parcels that they haven't opened yet
/// ("your booking was accepted").
class LiveNotice {
  const LiveNotice({required this.kind, required this.id, required this.status});

  /// `booking` or `parcel`.
  final String kind;
  final int id;
  final String status;

  String get route => kind == 'booking' ? '/bookings/$id' : '/parcels/$id';
}

/// Statuses worth telling the passenger about the moment they open the app.
const _noticeStatuses = {
  'booking': {'accepted', 'rejected', 'expired', 'cancelled_by_driver'},
  'parcel': {'accepted', 'rejected', 'expired', 'picked_up', 'delivered', 'cancelled'},
};

class LiveState {
  const LiveState({this.badges = const LiveBadges(), this.notices = const []});

  final LiveBadges badges;
  final List<LiveNotice> notices;
}

/// Polls `GET me/badges` every 12 seconds while the app is open and signed in
/// (and right away when the app comes back to the front), then refreshes only
/// what changed. Push notifications will make this instant once Firebase is set up.
final liveUpdatesProvider = NotifierProvider<LiveUpdates, LiveState>(LiveUpdates.new);

class LiveUpdates extends Notifier<LiveState> with WidgetsBindingObserver {
  static const interval = Duration(seconds: 12);
  static const _seenKey = '${CacheKeys.user}seen_statuses';

  Timer? _timer;
  bool _polling = false;

  @override
  LiveState build() {
    final signedIn = ref.watch(sessionProvider.select((s) => s.isSignedIn && !s.needsProfile));
    WidgetsBinding.instance.addObserver(this);
    ref.onDispose(() {
      _timer?.cancel();
      WidgetsBinding.instance.removeObserver(this);
    });
    if (signedIn) {
      _timer = Timer.periodic(interval, (_) => refresh());
      Future.microtask(refresh);
    }
    return const LiveState();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState lifecycle) {
    if (lifecycle == AppLifecycleState.resumed) refresh();
  }

  Future<void> refresh() async {
    if (_polling || !ref.read(sessionProvider).isSignedIn) return;
    _polling = true;
    try {
      final json = await ref.read(apiClientProvider).get('me/badges');
      if (json is! Map) return;
      final next = LiveBadges.fromJson(Map<String, dynamic>.from(json));
      _apply(state.badges, next);
    } on ApiException {
      // Offline or a blip: try again on the next tick.
    } finally {
      _polling = false;
    }
  }

  /// Marks a notice as seen (the passenger opened that booking or parcel).
  void dismiss(LiveNotice notice) {
    final seen = _seen();
    seen['${notice.kind}:${notice.id}'] = notice.status;
    ref.read(cacheStoreProvider).write(_seenKey, seen);
    state = LiveState(
      badges: state.badges,
      notices: state.notices.where((n) => !(n.kind == notice.kind && n.id == notice.id)).toList(),
    );
  }

  /// Called by the booking / parcel pages so opening one clears its notice.
  void opened(String kind, int id) {
    for (final n in state.notices) {
      if (n.kind == kind && n.id == id) return dismiss(n);
    }
  }

  Map<String, String> _seen() {
    final raw = ref.read(cacheStoreProvider).read(_seenKey)?.data;
    return raw is Map ? raw.map((k, v) => MapEntry('$k', '$v')) : <String, String>{};
  }

  void _apply(LiveBadges previous, LiveBadges next) {
    // Refresh the lists whose numbers moved.
    if (next.unreadMessages != previous.unreadMessages) {
      ref.invalidate(unreadCountProvider);
      ref.invalidate(conversationsProvider);
    }
    if (next.driverPendingRequests != previous.driverPendingRequests) {
      ref.invalidate(driverRequestsProvider);
      ref.invalidate(driverTripsProvider);
    }
    if (next.driverPendingParcels != previous.driverPendingParcels) {
      ref.invalidate(driverParcelRequestsProvider);
    }
    if (next.pendingRatings != previous.pendingRatings) ref.invalidate(pendingRatingsProvider);

    var bookingsChanged = false;
    next.bookingStatuses.forEach((id, status) {
      if (previous.bookingStatuses[id] != status) {
        bookingsChanged = true;
        ref.invalidate(bookingProvider(id));
      }
    });
    if (bookingsChanged) ref.invalidate(bookingsProvider);

    var parcelsChanged = false;
    next.parcelStatuses.forEach((id, status) {
      if (previous.parcelStatuses[id] != status) {
        parcelsChanged = true;
        ref.invalidate(parcelProvider(id));
      }
    });
    if (parcelsChanged) ref.invalidate(parcelsProvider);

    // Notices: statuses the passenger hasn't seen yet. On the very first run the
    // current statuses are taken as seen, so an old acceptance isn't announced.
    final cache = ref.read(cacheStoreProvider);
    final firstRun = cache.read(_seenKey) == null;
    final seen = _seen();
    final notices = <LiveNotice>[];
    void collect(String kind, Map<int, String> statuses) {
      statuses.forEach((id, status) {
        final key = '$kind:$id';
        if (firstRun) {
          seen[key] = status;
        } else if (seen[key] != status && _noticeStatuses[kind]!.contains(status)) {
          notices.add(LiveNotice(kind: kind, id: id, status: status));
        } else if (!_noticeStatuses[kind]!.contains(status)) {
          seen[key] = status; // pending, completed…: nothing to announce
        }
      });
    }

    collect('booking', next.bookingStatuses);
    collect('parcel', next.parcelStatuses);
    cache.write(_seenKey, seen);

    state = LiveState(badges: next, notices: notices);
  }
}
