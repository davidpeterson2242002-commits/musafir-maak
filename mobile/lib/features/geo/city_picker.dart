import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import '../../models/city.dart';
import 'geo_repository.dart';

/// Opens the city list (searchable, grouped by state) and returns the choice.
Future<City?> pickCity(BuildContext context, {String? title, City? selected, City? exclude}) {
  return showModalBottomSheet<City>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    backgroundColor: AppColors.surface,
    showDragHandle: true,
    builder: (_) => _CityPickerSheet(title: title ?? S.chooseCity, selected: selected, exclude: exclude),
  );
}

class _CityPickerSheet extends ConsumerStatefulWidget {
  const _CityPickerSheet({required this.title, this.selected, this.exclude});

  final String title;
  final City? selected;
  final City? exclude;

  @override
  ConsumerState<_CityPickerSheet> createState() => _CityPickerSheetState();
}

class _CityPickerSheetState extends ConsumerState<_CityPickerSheet> {
  String _query = '';

  /// Matches Arabic or English names, ignoring hamza forms and case, so
  /// "ام درمان" finds "أم درمان".
  static String _fold(String value) => value
      .toLowerCase()
      .replaceAll(RegExp('[أإآ]'), 'ا')
      .replaceAll('ة', 'ه')
      .replaceAll('ى', 'ي')
      .trim();

  bool _matches(City city) {
    if (_query.isEmpty) return true;
    final q = _fold(_query);
    return _fold(city.name).contains(q) ||
        _fold(city.nameEn ?? '').contains(q) ||
        _fold(city.stateName ?? '').contains(q);
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final cities = ref.watch(citiesProvider);

    return FractionallySizedBox(
      heightFactor: 0.9,
      child: Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 12),
              child: Text(widget.title, style: text.titleLarge),
            ),
            Padding(
              padding: AppTheme.screenPadding,
              child: TextField(
                textInputAction: TextInputAction.search,
                onChanged: (value) => setState(() => _query = value),
                decoration: AppTheme.input(
                  hint: S.searchCity,
                  prefixIcon: const Icon(Icons.search_rounded, color: AppColors.inkMuted),
                ),
              ),
            ),
            const SizedBox(height: 8),
            Expanded(
              child: cities.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, _) => LoadError(error: error, onRetry: () => ref.invalidate(citiesProvider)),
                data: (all) {
                  final list = all.where((c) => c != widget.exclude && _matches(c)).toList();
                  if (list.isEmpty) {
                    return Padding(
                      padding: const EdgeInsets.all(32),
                      child: Text(S.noCityFound, style: text.bodyLarge, textAlign: TextAlign.center),
                    );
                  }

                  return ListView.builder(
                    padding: const EdgeInsets.only(bottom: 16),
                    itemCount: list.length,
                    itemBuilder: (context, index) {
                      final city = list[index];
                      final isSelected = city == widget.selected;
                      final showState = city.stateName != null && city.stateName != city.name;

                      return ListTile(
                        contentPadding: const EdgeInsets.symmetric(horizontal: 20),
                        title: Text(
                          city.name,
                          style: text.titleMedium?.copyWith(fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500),
                        ),
                        subtitle: showState ? Text('ولاية ${city.stateName}', style: text.bodySmall) : null,
                        trailing: isSelected ? const Icon(Icons.check_rounded, color: AppColors.green) : null,
                        onTap: () => Navigator.of(context).pop(city),
                      );
                    },
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// A form row that looks like a text field and opens the city picker.
class CityField extends StatelessWidget {
  const CityField({
    super.key,
    required this.label,
    required this.value,
    required this.onChanged,
    this.errorText,
    this.exclude,
    this.pickerTitle,
  });

  final String label;
  final City? value;
  final ValueChanged<City> onChanged;
  final String? errorText;
  final City? exclude;
  final String? pickerTitle;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(AppTheme.radius),
      onTap: () async {
        FocusScope.of(context).unfocus();
        final city = await pickCity(context, title: pickerTitle, selected: value, exclude: exclude);
        if (city != null) onChanged(city);
      },
      child: InputDecorator(
        isEmpty: value == null,
        decoration: AppTheme.input(
          label: label,
          errorText: errorText,
          suffixIcon: const Icon(Icons.keyboard_arrow_down_rounded, color: AppColors.inkMuted),
        ),
        child: value == null
            ? null
            : Text(value!.name, style: const TextStyle(fontSize: 16.5, fontWeight: FontWeight.w500, color: AppColors.ink)),
      ),
    );
  }
}
