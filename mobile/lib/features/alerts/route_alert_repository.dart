import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/utils/formatters.dart';
import '../../models/misc.dart';

final routeAlertRepositoryProvider = Provider<RouteAlertRepository>(
  (ref) => RouteAlertRepository(ref.watch(apiClientProvider)),
);

final routeAlertsProvider = FutureProvider.autoDispose<List<RouteAlert>>(
  (ref) => ref.watch(routeAlertRepositoryProvider).list(),
);

class RouteAlertRepository {
  RouteAlertRepository(this._api);

  final ApiClient _api;

  Future<List<RouteAlert>> list() async {
    final json = await _api.get('route-alerts');
    return (json as List).map((e) => RouteAlert.fromJson(Map<String, dynamic>.from(e as Map))).toList();
  }

  /// [date] null = notify for any date on this route.
  Future<RouteAlert> create({required int originCityId, required int destinationCityId, DateTime? date}) async {
    final json = await _api.post('route-alerts', body: {
      'origin_city_id': originCityId,
      'destination_city_id': destinationCityId,
      if (date != null) 'date': Fmt.isoDate(date),
    });
    return RouteAlert.fromJson(Map<String, dynamic>.from(json as Map));
  }

  Future<void> delete(int id) => _api.delete('route-alerts/$id');
}
