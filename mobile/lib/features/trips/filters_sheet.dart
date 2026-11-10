import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../config/config_repository.dart';
import 'trip_repository.dart';

/// Filters for the results list (spec P4). Returns the new filters, or null if dismissed.
Future<SearchFilters?> showFiltersSheet(BuildContext context, SearchFilters current) {
  return showModalBottomSheet<SearchFilters>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    backgroundColor: AppColors.surface,
    showDragHandle: true,
    builder: (_) => _FiltersSheet(initial: current),
  );
}

class _FiltersSheet extends ConsumerStatefulWidget {
  const _FiltersSheet({required this.initial});

  final SearchFilters initial;

  @override
  ConsumerState<_FiltersSheet> createState() => _FiltersSheetState();
}

class _FiltersSheetState extends ConsumerState<_FiltersSheet> {
  static const _times = {
    'morning': S.timeMorning,
    'afternoon': S.timeAfternoon,
    'evening': S.timeEvening,
    'night': S.timeNight,
  };

  static const _ratings = [null, 3.0, 4.0, 4.5];

  late Set<String> _timesOfDay = {...widget.initial.timesOfDay};
  late Set<String> _vehicleTypes = {...widget.initial.vehicleTypes};
  late bool _womenOnly = widget.initial.womenOnly;
  late double? _minRating = widget.initial.minRating;

  void _toggle(Set<String> set, String value) => setState(() => set.contains(value) ? set.remove(value) : set.add(value));

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final vehicleTypes = ref.watch(appConfigProvider).valueOrNull?.vehicleTypes ?? const <String, String>{};

    Widget chip(String label, bool selected, VoidCallback onTap) => FilterChip(
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
        );

    Widget section(String title, List<Widget> chips) => Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: text.titleSmall),
            const SizedBox(height: 8),
            Wrap(spacing: 8, runSpacing: 4, children: chips),
            const SizedBox(height: 18),
          ],
        );

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 0, 20, 16),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(child: Text(S.filters, style: text.titleLarge)),
              TextButton(
                onPressed: () => setState(() {
                  _timesOfDay = {};
                  _vehicleTypes = {};
                  _womenOnly = false;
                  _minRating = null;
                }),
                child: const Text(S.clearFilters),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Flexible(
            child: SingleChildScrollView(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  section(S.timeOfDay, [
                    for (final entry in _times.entries)
                      chip(entry.value, _timesOfDay.contains(entry.key), () => _toggle(_timesOfDay, entry.key)),
                  ]),
                  if (vehicleTypes.isNotEmpty)
                    section(S.vehicleType, [
                      for (final entry in vehicleTypes.entries)
                        chip(entry.value, _vehicleTypes.contains(entry.key), () => _toggle(_vehicleTypes, entry.key)),
                    ]),
                  section(S.minRating, [
                    for (final rating in _ratings)
                      chip(
                        rating == null ? S.anyRating : '$rating+',
                        _minRating == rating,
                        () => setState(() => _minRating = rating),
                      ),
                  ]),
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: Text(S.womenOnlyFilter, style: text.titleSmall),
                    value: _womenOnly,
                    onChanged: (value) => setState(() => _womenOnly = value),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 8),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(
              SearchFilters(
                timesOfDay: _timesOfDay,
                vehicleTypes: _vehicleTypes,
                womenOnly: _womenOnly,
                minRating: _minRating,
                sort: widget.initial.sort,
              ),
            ),
            child: const Text(S.applyFilters),
          ),
        ],
      ),
    );
  }
}
