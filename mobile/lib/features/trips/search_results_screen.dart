import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/formatters.dart';
import '../../core/widgets/common.dart';
import '../../models/trip.dart';
import '../alerts/route_alert_repository.dart';
import '../home/home_screen.dart';
import 'filters_sheet.dart';
import 'trip_repository.dart';
import 'trip_widgets.dart';

/// Search results (spec P4): trips on the chosen route and day, with filters,
/// sort, nearby days when the day is empty, and a route alert.
class SearchResultsScreen extends ConsumerWidget {
  const SearchResultsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final text = Theme.of(context).textTheme;
    final query = ref.watch(searchQueryProvider);
    final filters = ref.watch(searchFiltersProvider);
    final results = ref.watch(searchResultsProvider);

    // Opened without a search (e.g. app restored on this route): go back home.
    if (query.from == null || query.to == null) {
      return const Scaffold(body: SizedBox.shrink());
    }

    final parcelMode = query.kind == SearchKind.parcel;

    return Scaffold(
      appBar: AppBar(
        backgroundColor: AppColors.surface,
        scrolledUnderElevation: 0,
        titleSpacing: 0,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            RouteTitle(from: query.from!.name, to: query.to!.name, style: text.titleMedium),
            Text(
              parcelMode ? '${S.parcel} — ${Fmt.dayMonth(query.date)}' : '${Fmt.dayMonth(query.date)} — ${S.seatsCount(query.seats)}',
              style: text.bodySmall,
            ),
          ],
        ),
      ),
      body: Column(
        children: [
          const OfflineBanner(),
          _Toolbar(filters: filters),
          Expanded(
            child: results.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (error, _) => LoadError(error: error, onRetry: () => ref.invalidate(searchResultsProvider)),
              data: (result) {
                if (result.closed) {
                  return EmptyState(
                    icon: Icons.block_rounded,
                    title: S.routeClosedTitle,
                    body: result.closureMessage ?? S.routeClosed,
                  );
                }
                if (result.trips.isEmpty) {
                  return _NoTrips(result: result, filtered: filters.activeCount > 0);
                }

                return RefreshIndicator(
                  color: AppColors.green,
                  onRefresh: () async => ref.invalidate(searchResultsProvider),
                  child: ListView.separated(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                    itemCount: result.trips.length + 1,
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (context, index) {
                      if (index == 0) {
                        return Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 4),
                          child: Text(S.tripsFound(result.trips.length), style: text.labelMedium),
                        );
                      }
                      final trip = result.trips[index - 1];
                      return TripCard(
                        trip: trip,
                        parcelMode: parcelMode,
                        onTap: () => context.push('/trips/${trip.id}'),
                      );
                    },
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

/// Sort choice + filters button.
class _Toolbar extends ConsumerWidget {
  const _Toolbar({required this.filters});

  final SearchFilters filters;

  static const _sorts = {
    TripSort.earliest: S.sortEarliest,
    TripSort.cheapest: S.sortCheapest,
    TripSort.rating: S.sortRating,
  };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final notifier = ref.read(searchFiltersProvider.notifier);

    return Container(
      color: AppColors.surface,
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 10),
      child: Row(
        children: [
          Expanded(
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  for (final entry in _sorts.entries)
                    Padding(
                      padding: const EdgeInsetsDirectional.only(end: 8),
                      child: ChoiceChip(
                        label: Text(entry.value),
                        selected: filters.sort == entry.key,
                        onSelected: (_) => notifier.state = filters.withSort(entry.key),
                        showCheckmark: false,
                        selectedColor: AppColors.greenTint,
                        backgroundColor: AppColors.surface,
                        side: BorderSide(color: filters.sort == entry.key ? AppColors.green : AppColors.line),
                        labelStyle: TextStyle(
                          fontFamily: AppFonts.body,
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                          color: filters.sort == entry.key ? AppColors.green : AppColors.inkMuted,
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
          TextButton.icon(
            onPressed: () async {
              final updated = await showFiltersSheet(context, filters);
              if (updated != null) notifier.state = updated;
            },
            icon: const Icon(Icons.tune_rounded, size: 20),
            label: Text(filters.activeCount == 0 ? S.filters : '${S.filters} (${filters.activeCount})'),
          ),
        ],
      ),
    );
  }
}

/// Empty day: nearby days that do have trips, and "alert me" (spec P4, P12).
class _NoTrips extends ConsumerStatefulWidget {
  const _NoTrips({required this.result, required this.filtered});

  final SearchResult result;
  final bool filtered;

  @override
  ConsumerState<_NoTrips> createState() => _NoTripsState();
}

class _NoTripsState extends ConsumerState<_NoTrips> {
  bool _busy = false;
  bool _alertSet = false;

  Future<void> _createAlert() async {
    final query = ref.read(searchQueryProvider);
    setState(() => _busy = true);
    try {
      await ref.read(routeAlertRepositoryProvider).create(
            originCityId: query.from!.id,
            destinationCityId: query.to!.id,
            date: query.date,
          );
      ref.invalidate(routeAlertsProvider);
      if (!mounted) return;
      setState(() => _alertSet = true);
      showMessage(context, S.alertCreated);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final nearby = widget.result.nearbyDates;

    return ListView(
      padding: const EdgeInsets.fromLTRB(24, 40, 24, 24),
      children: [
        Center(
          child: Container(
            width: 84,
            height: 84,
            decoration: const BoxDecoration(color: AppColors.greenTint, shape: BoxShape.circle),
            child: const Icon(Icons.event_busy_rounded, size: 38, color: AppColors.green),
          ),
        ),
        const SizedBox(height: 20),
        Text(S.noTripsTitle, style: text.titleLarge, textAlign: TextAlign.center),
        const SizedBox(height: 8),
        Text(
          widget.filtered ? S.noTripsFiltered : S.noTripsBody,
          style: text.bodyMedium?.copyWith(color: AppColors.inkMuted),
          textAlign: TextAlign.center,
        ),
        if (nearby.isNotEmpty) ...[
          const SizedBox(height: 28),
          Text(S.nearbyDays, style: text.titleSmall),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final day in nearby)
                ActionChip(
                  backgroundColor: AppColors.surface,
                  side: const BorderSide(color: AppColors.green),
                  label: Text(
                    '${Fmt.dayMonth(day.date)} — ${S.tripsFound(day.trips)}',
                    style: const TextStyle(
                      fontFamily: AppFonts.body,
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: AppColors.green,
                    ),
                  ),
                  onPressed: () {
                    final current = ref.read(searchQueryProvider);
                    ref.read(searchQueryProvider.notifier).state = current.copyWith(date: day.date);
                  },
                ),
            ],
          ),
        ],
        const SizedBox(height: 28),
        if (_alertSet)
          const Center(child: Tag(S.alertCreated, icon: Icons.notifications_active_rounded))
        else
          OutlinedButton.icon(
            onPressed: _busy ? null : _createAlert,
            icon: const Icon(Icons.notifications_none_rounded, size: 20),
            label: const Text(S.alertMe),
          ),
      ],
    );
  }
}
