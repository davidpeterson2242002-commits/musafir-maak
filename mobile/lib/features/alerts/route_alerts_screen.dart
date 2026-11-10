import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../core/widgets/common.dart';
import '../../models/city.dart';
import '../../models/misc.dart';
import '../geo/city_picker.dart';
import '../trips/trip_widgets.dart';
import 'route_alert_repository.dart';

/// Route alerts (spec P12): "tell me when a driver posts a trip on this route".
class RouteAlertsScreen extends ConsumerWidget {
  const RouteAlertsScreen({super.key});

  Future<void> _add(BuildContext context, WidgetRef ref) async {
    final created = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppColors.surface,
      showDragHandle: true,
      builder: (_) => const _AddAlertSheet(),
    );
    if (created ?? false) ref.invalidate(routeAlertsProvider);
  }

  Future<void> _delete(BuildContext context, WidgetRef ref, RouteAlert alert) async {
    try {
      await ref.read(routeAlertRepositoryProvider).delete(alert.id);
      ref.invalidate(routeAlertsProvider);
      if (context.mounted) showMessage(context, S.alertDeleted);
    } on ApiException catch (e) {
      if (context.mounted) showMessage(context, e.message);
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final text = Theme.of(context).textTheme;
    final alerts = ref.watch(routeAlertsProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(S.routeAlerts, style: text.titleLarge),
        backgroundColor: AppColors.surface,
        scrolledUnderElevation: 0,
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _add(context, ref),
        backgroundColor: AppColors.green,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.add_rounded),
        label: const Text(S.addAlert),
      ),
      body: alerts.when(
        skipLoadingOnRefresh: true,
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => LoadError(error: error, onRetry: () => ref.invalidate(routeAlertsProvider)),
        data: (list) {
          if (list.isEmpty) {
            return const EmptyState(
              icon: Icons.notifications_none_rounded,
              title: S.alertsEmptyTitle,
              body: S.alertsEmptyBody,
            );
          }

          return ListView.separated(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
            itemCount: list.length,
            separatorBuilder: (_, __) => const SizedBox(height: 10),
            itemBuilder: (context, index) {
              final alert = list[index];
              return Panel(
                padding: const EdgeInsetsDirectional.fromSTEB(16, 10, 4, 10),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          RouteTitle(from: alert.originName, to: alert.destinationName, style: text.titleMedium),
                          const SizedBox(height: 2),
                          Text(
                            alert.date == null ? S.anyDate : Fmt.dayMonth(alert.date!),
                            style: text.bodySmall,
                          ),
                        ],
                      ),
                    ),
                    if (!alert.active) const Tag(S.alertEnded, color: AppColors.inkMuted, background: AppColors.background),
                    IconButton(
                      tooltip: S.delete,
                      onPressed: () => _delete(context, ref, alert),
                      icon: const Icon(Icons.delete_outline_rounded, color: AppColors.danger),
                    ),
                  ],
                ),
              );
            },
          );
        },
      ),
    );
  }
}

class _AddAlertSheet extends ConsumerStatefulWidget {
  const _AddAlertSheet();

  @override
  ConsumerState<_AddAlertSheet> createState() => _AddAlertSheetState();
}

class _AddAlertSheetState extends ConsumerState<_AddAlertSheet> {
  City? _from;
  City? _to;
  DateTime? _date;
  bool _busy = false;
  String? _error;

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final picked = await showDatePicker(
      context: context,
      initialDate: _date ?? today,
      firstDate: today,
      lastDate: today.add(const Duration(days: 60)),
    );
    if (picked != null) setState(() => _date = picked);
  }

  Future<void> _save() async {
    if (_from == null || _to == null) {
      setState(() => _error = S.chooseBothCities);
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(routeAlertRepositoryProvider).create(
            originCityId: _from!.id,
            destinationCityId: _to!.id,
            date: _date,
          );
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return Padding(
      padding: EdgeInsets.fromLTRB(20, 0, 20, 16 + MediaQuery.viewInsetsOf(context).bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(S.addAlert, style: text.titleLarge),
          const SizedBox(height: 16),
          CityField(
            label: S.from,
            value: _from,
            exclude: _to,
            pickerTitle: S.from,
            onChanged: (city) => setState(() => _from = city),
          ),
          const SizedBox(height: 14),
          CityField(
            label: S.to,
            value: _to,
            exclude: _from,
            pickerTitle: S.to,
            onChanged: (city) => setState(() => _to = city),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () => setState(() => _date = null),
                  style: _date == null ? OutlinedButton.styleFrom(backgroundColor: AppColors.greenTint) : null,
                  child: const Text(S.anyDate),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: OutlinedButton(
                  onPressed: _pickDate,
                  style: _date != null ? OutlinedButton.styleFrom(backgroundColor: AppColors.greenTint) : null,
                  child: Text(_date == null ? S.specificDate : Fmt.dayMonth(_date!), overflow: TextOverflow.ellipsis),
                ),
              ),
            ],
          ),
          if (_error != null) ...[
            const SizedBox(height: 10),
            Text(_error!, style: const TextStyle(color: AppColors.danger, fontSize: 14.5)),
          ],
          const SizedBox(height: 16),
          PrimaryButton(label: S.saveAlert, onPressed: _save, busy: _busy),
        ],
      ),
    );
  }
}
