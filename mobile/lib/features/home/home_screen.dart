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
import '../auth/session_controller.dart';
import '../bookings/booking_repository.dart';
import '../bookings/booking_widgets.dart';
import '../geo/city_picker.dart';
import '../geo/geo_repository.dart';
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
  // Start from the user's own city: most trips begin at home.
  final home = ref.read(currentUserProvider)?.city;
  return SearchQuery(from: home, date: DateTime.now());
});

/// Home tab (spec P3): where to, when, how many — plus one-tap popular routes.
class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final text = Theme.of(context).textTheme;
    final user = ref.watch(currentUserProvider);
    final firstName = (user?.name ?? '').trim().split(' ').first;

    return RefreshIndicator(
      color: AppColors.green,
      onRefresh: () async {
        ref.invalidate(popularRoutesProvider);
        ref.invalidate(bookingsProvider('upcoming'));
        ref.invalidate(pendingRatingsProvider);
        await ref.read(sessionProvider.notifier).refresh();
      },
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 28),
        children: [
          if (firstName.isNotEmpty)
            Text(S.hello(firstName), style: text.bodyLarge?.copyWith(color: AppColors.inkMuted)),
          Text(S.whereTo, style: text.displaySmall),
          const SizedBox(height: 16),
          const _RatePrompt(),
          const _NextTrip(),
          const _SearchCard(),
          const SizedBox(height: 28),
          Text(S.popularRoutes, style: text.titleMedium),
          const SizedBox(height: 12),
          const _PopularRoutes(),
        ],
      ),
    );
  }
}

class _SearchCard extends ConsumerWidget {
  const _SearchCard();

  static const _rowHeight = 60.0;

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
        context.push('/search');
      }
    }

    return Panel(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _KindToggle(
            kind: query.kind,
            onChanged: (kind) => notifier.state = query.copyWith(kind: kind),
          ),
          const SizedBox(height: 14),
          // From / To with the route marker on the leading side and swap on the trailing side.
          Row(
            children: [
              const RouteMarker(height: _rowHeight * 2 + 10),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  children: [
                    _CityRow(
                      label: S.from,
                      city: query.from,
                      height: _rowHeight,
                      onTap: () async {
                        final city = await pickCity(context, title: S.from, selected: query.from);
                        if (city != null) notifier.state = ref.read(searchQueryProvider).copyWith(from: city);
                      },
                    ),
                    const Divider(height: 10),
                    _CityRow(
                      label: S.to,
                      city: query.to,
                      height: _rowHeight,
                      onTap: () async {
                        final city = await pickCity(context, title: S.to, selected: query.to, exclude: query.from);
                        if (city != null) notifier.state = ref.read(searchQueryProvider).copyWith(to: city);
                      },
                    ),
                  ],
                ),
              ),
              IconButton(
                tooltip: S.swapCities,
                onPressed: () => notifier.state = query.swapped(),
                style: IconButton.styleFrom(
                  backgroundColor: AppColors.greenTint,
                  foregroundColor: AppColors.green,
                  fixedSize: const Size(44, 44),
                ),
                icon: const Icon(Icons.swap_vert_rounded),
              ),
            ],
          ),
          const Divider(height: 24),
          _DateRow(
            date: query.date,
            onChanged: (date) => notifier.state = query.copyWith(date: date),
          ),
          if (isTrip) ...[
            const Divider(height: 24),
            _SeatsRow(
              seats: query.seats,
              onChanged: (seats) => notifier.state = query.copyWith(seats: seats),
            ),
          ],
          const SizedBox(height: 16),
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
              color: selected ? AppColors.green : Colors.transparent,
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

class _CityRow extends StatelessWidget {
  const _CityRow({required this.label, required this.city, required this.height, required this.onTap});

  final String label;
  final City? city;
  final double height;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: SizedBox(
        height: height,
        width: double.infinity,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: text.labelMedium),
            Text(
              city?.name ?? S.chooseCity,
              style: text.titleLarge?.copyWith(
                color: city == null ? AppColors.inkMuted : AppColors.ink,
                fontWeight: city == null ? FontWeight.w500 : FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DateRow extends StatelessWidget {
  const _DateRow({required this.date, required this.onChanged});

  final DateTime date;
  final ValueChanged<DateTime> onChanged;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final tomorrow = today.add(const Duration(days: 1));
    final isToday = Fmt.isSameDay(date, today);
    final isTomorrow = Fmt.isSameDay(date, tomorrow);

    Widget quick(String label, bool selected, VoidCallback onTap) => Padding(
          padding: const EdgeInsetsDirectional.only(start: 8),
          child: ChoiceChip(
            label: Text(label),
            selected: selected,
            onSelected: (_) => onTap(),
            showCheckmark: false,
            selectedColor: AppColors.greenTint,
            backgroundColor: AppColors.surface,
            side: BorderSide(color: selected ? AppColors.green : AppColors.line),
            labelStyle: TextStyle(
              fontFamily: AppFonts.body,
              fontSize: 14.5,
              fontWeight: FontWeight.w700,
              color: selected ? AppColors.green : AppColors.inkMuted,
            ),
          ),
        );

    return Row(
      children: [
        Expanded(
          child: InkWell(
            borderRadius: BorderRadius.circular(10),
            onTap: () async {
              final picked = await showDatePicker(
                context: context,
                initialDate: date.isBefore(today) ? today : date,
                firstDate: today,
                // The backend accepts trips up to 60 days ahead (setting trips.max_days_ahead).
                lastDate: today.add(const Duration(days: 60)),
              );
              if (picked != null) onChanged(picked);
            },
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(S.travelDate, style: text.labelMedium),
                Row(
                  children: [
                    const Icon(Icons.calendar_today_outlined, size: 17, color: AppColors.green),
                    const SizedBox(width: 6),
                    Flexible(
                      child: Text(Fmt.dayMonth(date), style: text.titleMedium, overflow: TextOverflow.ellipsis),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
        quick(S.today, isToday, () => onChanged(today)),
        quick(S.tomorrow, isTomorrow, () => onChanged(tomorrow)),
      ],
    );
  }
}

class _SeatsRow extends StatelessWidget {
  const _SeatsRow({required this.seats, required this.onChanged});

  static const _max = 8;

  final int seats;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    Widget step(IconData icon, VoidCallback? onTap) => IconButton(
          onPressed: onTap,
          style: IconButton.styleFrom(
            fixedSize: const Size(44, 44),
            side: BorderSide(color: onTap == null ? AppColors.line : AppColors.green),
            foregroundColor: AppColors.green,
          ),
          icon: Icon(icon, size: 20),
        );

    return Row(
      children: [
        Expanded(child: Text(S.seats, style: text.titleMedium)),
        step(Icons.remove_rounded, seats > 1 ? () => onChanged(seats - 1) : null),
        SizedBox(
          width: 44,
          child: Text('$seats', style: text.titleLarge, textAlign: TextAlign.center),
        ),
        step(Icons.add_rounded, seats < _max ? () => onChanged(seats + 1) : null),
      ],
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
                    showMessage(context, route.closureMessage ?? S.routeClosed);
                    return;
                  }
                  final current = ref.read(searchQueryProvider);
                  ref.read(searchQueryProvider.notifier).state =
                      current.copyWith(from: route.origin, to: route.destination);
                },
                // Light chip (user feedback 2026-11-11: the solid green signs were too heavy).
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
                  decoration: BoxDecoration(
                    color: AppColors.surface,
                    borderRadius: BorderRadius.circular(999),
                    border: Border.all(color: AppColors.line),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(route.origin.name,
                          style: text.bodyMedium?.copyWith(color: AppColors.greenDark, fontWeight: FontWeight.w500)),
                      const Padding(
                        padding: EdgeInsets.symmetric(horizontal: 6),
                        // Mirrors in RTL, so it points from the origin (right) to the destination (left).
                        child: Icon(Icons.arrow_forward_rounded, size: 16, color: AppColors.amber),
                      ),
                      Text(route.destination.name,
                          style: text.bodyMedium?.copyWith(color: AppColors.greenDark, fontWeight: FontWeight.w500)),
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
class _RatePrompt extends ConsumerWidget {
  const _RatePrompt();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final pending = ref.watch(pendingRatingsProvider).valueOrNull?.firstOrNull;
    if (pending == null) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Material(
        color: AppColors.amberTint,
        borderRadius: BorderRadius.circular(18),
        child: InkWell(
          borderRadius: BorderRadius.circular(18),
          onTap: () async {
            await context.push('/rate', extra: pending);
            ref.invalidate(pendingRatingsProvider);
          },
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              children: [
                const Icon(Icons.star_rounded, color: AppColors.amber, size: 30),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(S.ratePrompt(pending.rateeName), style: Theme.of(context).textTheme.titleMedium),
                      Text(pending.summary, style: Theme.of(context).textTheme.bodySmall),
                    ],
                  ),
                ),
                const Icon(Icons.chevron_left_rounded, color: AppColors.ink, textDirection: TextDirection.ltr),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
