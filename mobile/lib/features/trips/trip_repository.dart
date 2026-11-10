import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/utils/formatters.dart';
import '../../models/trip.dart';
import '../home/home_screen.dart';

enum TripSort {
  earliest,
  cheapest,
  rating;
}

/// Filters and sort on the results screen (spec P4).
class SearchFilters {
  const SearchFilters({
    this.timesOfDay = const {},
    this.vehicleTypes = const {},
    this.womenOnly = false,
    this.minRating,
    this.maxPrice,
    this.sort = TripSort.earliest,
  });

  /// night / morning / afternoon / evening (Sudan time).
  final Set<String> timesOfDay;
  final Set<String> vehicleTypes;
  final bool womenOnly;
  final double? minRating;
  final int? maxPrice;
  final TripSort sort;

  /// How many filters are on (the sort is not a filter).
  int get activeCount =>
      (timesOfDay.isEmpty ? 0 : 1) +
      (vehicleTypes.isEmpty ? 0 : 1) +
      (womenOnly ? 1 : 0) +
      (minRating == null ? 0 : 1) +
      (maxPrice == null ? 0 : 1);

  SearchFilters withSort(TripSort value) => SearchFilters(
        timesOfDay: timesOfDay,
        vehicleTypes: vehicleTypes,
        womenOnly: womenOnly,
        minRating: minRating,
        maxPrice: maxPrice,
        sort: value,
      );

  Map<String, dynamic> toQuery() => {
        if (timesOfDay.isNotEmpty) 'time_of_day': timesOfDay.toList(),
        if (vehicleTypes.isNotEmpty) 'vehicle_types': vehicleTypes.toList(),
        if (womenOnly) 'women_only': 1,
        if (minRating != null) 'min_rating': minRating,
        if (maxPrice != null) 'max_price': maxPrice,
        'sort': sort.name,
      };
}

final searchFiltersProvider = StateProvider<SearchFilters>((ref) => const SearchFilters());

final tripRepositoryProvider = Provider<TripRepository>((ref) => TripRepository(ref.watch(apiClientProvider)));

/// Results for the current search form + filters. Re-runs when either changes.
final searchResultsProvider = FutureProvider.autoDispose<SearchResult>((ref) {
  final query = ref.watch(searchQueryProvider);
  final filters = ref.watch(searchFiltersProvider);
  return ref.watch(tripRepositoryProvider).search(query, filters);
});

final tripDetailsProvider = FutureProvider.autoDispose.family<TripDetails, int>(
  (ref, id) => ref.watch(tripRepositoryProvider).details(id),
);

class TripRepository {
  TripRepository(this._api);

  final ApiClient _api;

  Future<SearchResult> search(SearchQuery query, SearchFilters filters) async {
    final json = await _api.get('trips/search', query: {
      'origin_city_id': query.from!.id,
      'destination_city_id': query.to!.id,
      'date': Fmt.isoDate(query.date),
      'seats': query.kind == SearchKind.trip ? query.seats : 1,
      'kind': query.kind.name,
      ...filters.toQuery(),
    });
    return SearchResult.fromJson(Map<String, dynamic>.from(json as Map));
  }

  Future<TripDetails> details(int id) async {
    final json = await _api.get('trips/$id');
    return TripDetails.fromJson(Map<String, dynamic>.from(json as Map));
  }
}
