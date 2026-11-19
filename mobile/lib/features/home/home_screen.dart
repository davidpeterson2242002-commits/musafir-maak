import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/formatters.dart';
import '../../core/widgets/common.dart';
import '../../core/widgets/road.dart';
import '../../models/city.dart';
import '../alerts/reopen_alert.dart';
import '../auth/session_controller.dart';
import '../bookings/booking_repository.dart';
import '../bookings/booking_widgets.dart';
import '../geo/city_picker.dart';
import '../geo/geo_repository.dart';
import '../live/live_updates.dart';
import '../push/background_delivery_card.dart';
import '../ratings/rating_cards.dart';
import '../ratings/rating_repository.dart';
import '../trips/trip_repository.dart';

/// What the user is searching for: a seat or a driver to carry a parcel.
enum SearchKind { trip, parcel }

/// The search form's values. Kept in a provider so they survive tab switches
/// and so M8's results screen can read them.
class SearchQuery {
  const SearchQuery({this.kind = SearchKind.trip, this.from, this.to, required this.date, this.seats = 1});

  final SearchKind kind;
  final City? from;
  final City? to;
  final DateTime date;
  final int seats;

  SearchQuery copyWith({SearchKind? kind, City? from, City? to, DateTime? date, int? seats}) => SearchQuery(
        kind: kind ?? this.kind,
        from: from ?? this.from,
        to: to ?? this.to,
        date: date ?? this.date,
        seats: seats ?? this.seats,
      );

  SearchQuery swapped() => SearchQuery(kind: kind, from: to, to: from, date: date, seats: seats);
}

final searchQueryProvider = StateProvider<SearchQuery>((ref) {
  // Start from the user's own city: most trips begin at home. Rebuilt when another
  // account logs in, so it never keeps the previous user's search.
  ref.watch(currentUserProvider.select((user) => user?.id));
  final home = ref.read(currentUserProvider)?.city;
  return SearchQuery(from: home, date: DateTime.now());
});

/// Home tab (spec P3): a navy header ("ماشي وين؟") that the search card sits on,
/// then live notices, the next trip and one-tap popular routes.
class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final text = Theme.of(context).textTheme;
    final user = ref.watch(currentUserProvider);
    final firstName = (user?.name ?? '').trim().split(' ').first;

    return RefreshIndicator(
      color: AppColors.navy,
      onRefresh: () async {
        ref.invalidate(popularRoutesProvider);
        ref.invalidate(bookingsProvider('upcoming'));
        ref.invalidate(pendingRatingsProvider);
        await ref.read(sessionProvider.notifier).refresh();
      },
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.only(bottom: 28),
        children: [
          // Header band: continues the navy top bar; the search card overlaps its lower edge.
          Stack(
            children: [
              Positioned(
                top: 0,
                left: 0,
                right: 0,
                child: Container(height: firstName.isNotEmpty ? 132 : 112, color: AppColors.navy),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 6, 16, 0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (firstName.isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 4),
                        child: Text(S.hello(firstName),
                            style: text.bodyLarge?.copyWith(color: const Color(0xFFC9D1E0))),
                      ),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 4),
                      child: Text(S.whereTo, style: text.displaySmall?.copyWith(color: Colors.white)),
                    ),
                    const SizedBox(height: 12),
                    const _SearchCard(),
                  ],
                ),
              ),
            ],
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const _LiveNotices(),
                const BackgroundDeliveryCardView(),
                const RatingCards(driverSide: false),
                const _NextTrip(),
                Text(S.popularRoutes, style: text.titleMedium),
                const SizedBox(height: 12),
                const _PopularRoutes(),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SearchCard extends ConsumerWidget {
  const _SearchCard();

  static const _boxHeight = 58.0;
  static const _gap = 8.0;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final query = ref.watch(searchQueryProvider);
    final notifier = ref.read(searchQueryProvider.notifier);
    final isTrip = query.kind == SearchKind.trip;

    void submit() {
      if (query.from == null || query.to == null) {
        showMessage(context, S.chooseBothCities);
      } else if (query.from == query.to) {
        showMessage(context, S.sameCity);
      } else {
        // A new search starts without the previous search's filters.
        ref.read(searchFiltersProvider.notifier).state = const SearchFilters();
        // Parcels: the details come first, then only the trips that can carry it.
        context.push(isTrip ? '/search' : '/parcels/new');
      }
    }

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.line),
        boxShadow: const [BoxShadow(color: Color(0x1A0B1220), blurRadius: 18, offset: Offset(0, 6))],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _KindToggle(
            kind: query.kind,
            onChanged: (kind) => notifier.state = query.copyWith(kind: kind),
          ),
          const SizedBox(height: 12),
          // From / To: the route marker (ring → amber dashes → dot) on the leading side,
          // swap on the trailing side.
          Row(
            children: [
              const RouteMarker(height: _boxHeight * 2 + _gap),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  children: [
                    _CityBox(
                      label: S.from,
                      city: query.from,
                      height: _boxHeight,
                      onTap: () async {
                        final city = await pickCity(context, title: S.from, selected: query.from);
                        if (city != null) notifier.state = ref.read(searchQueryProvider).copyWith(from: city);
                      },
                    ),
                    const SizedBox(height: _gap),
                    _CityBox(
                      label: S.to,
                      city: query.to,
                      height: _boxHeight,
                      onTap: () async {
                        final city = await pickCity(context, title: S.to, selected: query.to, exclude: query.from);
                        if (city != null) notifier.state = ref.read(searchQueryProvider).copyWith(to: city);
                      },
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 6),
              IconButton(
                tooltip: S.swapCities,
                onPressed: () => notifier.state = query.swapped(),
                style: IconButton.styleFrom(
                  backgroundColor: AppColors.navyTint,
                  foregroundColor: AppColors.navy,
                  fixedSize: const Size(44, 44),
                ),
                icon: const Icon(Icons.swap_vert_rounded),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: _DateBox(
                  date: query.date,
                  onChanged: (date) => notifier.state = ref.read(searchQueryProvider).copyWith(date: date),
                ),
              ),
              if (isTrip) ...[
                const SizedBox(width: _gap),
                Expanded(
                  child: _SeatsBox(
                    seats: query.seats,
                    onChanged: (seats) => notifier.state = ref.read(searchQueryProvider).copyWith(seats: seats),
                  ),
                ),
              ],
            ],
          ),
          const SizedBox(height: 14),
          PrimaryButton(
            label: isTrip ? S.searchTrips : S.searchParcelTrips,
            icon: Icons.search_rounded,
            onPressed: submit,
          ),
        ],
      ),
    );
  }
}

/// رحلة / ارسالية switch (spec P3).
class _KindToggle extends StatelessWidget {
  const _KindToggle({required this.kind, required this.onChanged});

  final SearchKind kind;
  final ValueChanged<SearchKind> onChanged;

  @override
  Widget build(BuildContext context) {
    Widget option(SearchKind value, String label, IconData icon) {
      final selected = kind == value;
      return Expanded(
        child: GestureDetector(
          behavior: HitTestBehavior.opaque,
          onTap: () => onChanged(value),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 160),
            height: 44,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: selected ? AppColors.navy : Colors.transparent,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(icon, size: 19, color: selected ? Colors.white : AppColors.inkMuted),
                const SizedBox(width: 6),
                Text(
                  label,
                  style: TextStyle(
                    fontSize: 15.5,
                    fontWeight: FontWeight.w700,
                    color: selected ? Colors.white : AppColors.inkMuted,
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(color: AppColors.background, borderRadius: BorderRadius.circular(13)),
      child: Row(
        children: [
          option(SearchKind.trip, S.trip, Icons.airline_seat_recline_normal_rounded),
          option(SearchKind.parcel, S.parcel, Icons.inventory_2_outlined),
        ],
      ),
    );
  }
}

/// A grey field box (paper background): small label on top, value below.
class _FieldBox extends StatelessWidget {
  const _FieldBox({required this.label, required this.child, this.onTap, this.height = 58});

  final String label;
  final Widget child;
  final VoidCallback? onTap;
  final double height;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.background,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          height: height,
          padding: const EdgeInsets.symmetric(horizontal: 12),
          alignment: AlignmentDirectional.centerStart,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: const TextStyle(fontSize: 12, color: AppColors.inkMuted, height: 1.3)),
              child,
            ],
          ),
        ),
      ),
    );
  }
}

class _CityBox extends StatelessWidget {
  const _CityBox({required this.label, required this.city, required this.height, required this.onTap});

  final String label;
  final City? city;
  final double height;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return _FieldBox(
      label: label,
      height: height,
      onTap: onTap,
      child: Text(
        city?.name ?? S.chooseCity,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(
          fontSize: 17,
          height: 1.35,
          color: city == null ? AppColors.inkMuted : AppColors.ink,
          fontWeight: city == null ? FontWeight.w500 : FontWeight.w700,
        ),
      ),
    );
  }
}

/// Travel date: "اليوم", "بكرة" or the date. A tap offers today / tomorrow / another day.
class _DateBox extends StatelessWidget {
  const _DateBox({required this.date, required this.onChanged});

  final DateTime date;
  final ValueChanged<DateTime> onChanged;

  @override
  Widget build(BuildContext context) {
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final tomorrow = today.add(const Duration(days: 1));
    final label = Fmt.isSameDay(date, today)
        ? S.today
        : Fmt.isSameDay(date, tomorrow)
            ? S.tomorrow
            : Fmt.dayMonth(date);

    Future<void> pickOther() async {
      final picked = await showDatePicker(
        context: context,
        initialDate: date.isBefore(today) ? today : date,
        firstDate: today,
        // The backend accepts trips up to 60 days ahead (setting trips.max_days_ahead).
        lastDate: today.add(const Duration(days: 60)),
      );
      if (picked != null) onChanged(picked);
    }

    return _FieldBox(
      label: S.travelDate,
      onTap: () async {
        final choice = await showModalBottomSheet<int>(
          context: context,
          showDragHandle: true,
          builder: (context) => SafeArea(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                ListTile(
                  leading: const Icon(Icons.today_rounded, color: AppColors.navy),
                  title: const Text(S.today),
                  trailing: Fmt.isSameDay(date, today) ? const Icon(Icons.check_rounded, color: AppColors.navy) : null,
                  onTap: () => Navigator.pop(context, 0),
                ),
                ListTile(
                  leading: const Icon(Icons.event_rounded, color: AppColors.navy),
                  title: const Text(S.tomorrow),
                  trailing:
                      Fmt.isSameDay(date, tomorrow) ? const Icon(Icons.check_rounded, color: AppColors.navy) : null,
                  onTap: () => Navigator.pop(context, 1),
                ),
                ListTile(
                  leading: const Icon(Icons.calendar_month_rounded, color: AppColors.navy),
                  title: const Text(S.otherDate),
                  onTap: () => Navigator.pop(context, 2),
                ),
                const SizedBox(height: 8),
              ],
            ),
          ),
        );
        if (choice == 0) onChanged(today);
        if (choice == 1) onChanged(tomorrow);
        if (choice == 2) await pickOther();
      },
      child: Row(
        children: [
          const Icon(Icons.calendar_today_outlined, size: 15, color: AppColors.navy),
          const SizedBox(width: 6),
          Flexible(
            child: Text(label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.ink, height: 1.35)),
          ),
        ],
      ),
    );
  }
}

/// Seats with small − / + buttons inside the box.
class _SeatsBox extends StatelessWidget {
  const _SeatsBox({required this.seats, required this.onChanged});

  static const _max = 8;

  final int seats;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    Widget step(IconData icon, String tooltip, VoidCallback? onTap) => SizedBox(
          width: 30,
          height: 30,
          child: IconButton(
            tooltip: tooltip,
            onPressed: onTap,
            padding: EdgeInsets.zero,
            style: IconButton.styleFrom(
              backgroundColor: onTap == null ? Colors.transparent : AppColors.surface,
              foregroundColor: AppColors.navy,
              side: const BorderSide(color: AppColors.line),
            ),
            icon: Icon(icon, size: 16),
          ),
        );

    return _FieldBox(
      label: S.seats,
      child: Row(
        children: [
          Expanded(
            child: Text(S.seatsCount(seats),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.ink, height: 1.35)),
          ),
          step(Icons.remove_rounded, S.fewerSeats, seats > 1 ? () => onChanged(seats - 1) : null),
          const SizedBox(width: 4),
          step(Icons.add_rounded, S.moreSeats, seats < _max ? () => onChanged(seats + 1) : null),
        ],
      ),
    );
  }
}

/// Admin-chosen popular routes as small road signs; a tap fills the search form.
class _PopularRoutes extends ConsumerWidget {
  const _PopularRoutes();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final text = Theme.of(context).textTheme;
    final routes = ref.watch(popularRoutesProvider);

    return routes.when(
      loading: () => const Padding(
        padding: EdgeInsets.symmetric(vertical: 16),
        child: Center(child: CircularProgressIndicator()),
      ),
      error: (error, _) => Row(
        children: [
          Expanded(child: Text(errorMessage(error), style: text.bodyMedium?.copyWith(color: AppColors.inkMuted))),
          TextButton(onPressed: () => ref.invalidate(popularRoutesProvider), child: const Text(S.retry)),
        ],
      ),
      data: (list) => Wrap(
        spacing: 10,
        runSpacing: 10,
        children: [
          for (final route in list)
            Opacity(
              opacity: route.open ? 1 : 0.55,
              child: GestureDetector(
                onTap: () {
                  if (!route.open) {
                    offerReopenAlert(
                      context,
                      ref,
                      originCityId: route.origin.id,
                      destinationCityId: route.destination.id,
                      routeLabel: '${route.origin.name} ← ${route.destination.name}',
                      message: route.closureMessage,
                    );
                    return;
                  }
                  final current = ref.read(searchQueryProvider);
                  ref.read(searchQueryProvider.notifier).state =
                      current.copyWith(from: route.origin, to: route.destination);
                },
                // Compact road sign (brand identity v1): navy panel, white inset border.
                child: RoadSign(
                  compact: true,
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(route.origin.name),
                      const Padding(
                        padding: EdgeInsets.symmetric(horizontal: 6),
                        // Mirrors in RTL, so it points from the origin (right) to the destination (left).
                        child: Icon(Icons.arrow_forward_rounded, size: 15, color: AppColors.amber),
                      ),
                      Text(route.destination.name),
                      if (!route.open) ...[
                        const SizedBox(width: 6),
                        const Icon(Icons.lock_outline_rounded, size: 14, color: Colors.white),
                      ],
                    ],
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

/// "Your next trip" (spec P3): the nearest upcoming booking, one tap from its page.
class _NextTrip extends ConsumerWidget {
  const _NextTrip();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final next = ref.watch(bookingsProvider('upcoming')).valueOrNull?.firstOrNull;
    if (next == null) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: BookingCard(
        booking: next,
        heading: S.nextTrip,
        onTap: () => context.push('/bookings/${next.id}'),
      ),
    );
  }
}

/// A finished trip waiting for a rating (spec P9).
/// "The driver accepted your booking" — changes the passenger hasn't opened yet,
/// spotted by the live updates the moment the app is open.
class _LiveNotices extends ConsumerWidget {
  const _LiveNotices();

  static String _title(LiveNotice n) => switch ((n.kind, n.status)) {
        ('booking', 'accepted') => S.noticeBookingAccepted,
        ('booking', 'rejected') => S.noticeBookingRejected,
        ('booking', 'expired') => S.noticeBookingExpired,
        ('booking', _) => S.noticeBookingCancelled,
        ('parcel', 'accepted') => S.noticeParcelAccepted,
        ('parcel', 'rejected') => S.noticeParcelRejected,
        ('parcel', 'expired') => S.noticeParcelExpired,
        ('parcel', 'picked_up') => S.noticeParcelPickedUp,
        ('parcel', 'delivered') => S.noticeParcelDelivered,
        _ => S.noticeParcelCancelled,
      };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final notices = ref.watch(liveUpdatesProvider.select((s) => s.notices));
    if (notices.isEmpty) return const SizedBox.shrink();
    final text = Theme.of(context).textTheme;

    return Column(
      children: [
        for (final n in notices)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Material(
              color: n.status == 'accepted' || n.status == 'delivered' || n.status == 'picked_up'
                  ? AppColors.navyTint
                  : AppColors.dangerTint,
              borderRadius: BorderRadius.circular(16),
              child: InkWell(
                borderRadius: BorderRadius.circular(16),
                onTap: () {
                  ref.read(liveUpdatesProvider.notifier).dismiss(n);
                  context.push(n.route);
                },
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(14, 12, 6, 12),
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(_title(n), style: text.titleMedium),
                            Text(S.noticeOpen, style: text.bodySmall?.copyWith(color: AppColors.navyDark)),
                          ],
                        ),
                      ),
                      IconButton(
                        tooltip: S.close,
                        icon: const Icon(Icons.close_rounded, size: 20, color: AppColors.inkMuted),
                        onPressed: () => ref.read(liveUpdatesProvider.notifier).dismiss(n),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
      ],
    );
  }
}
