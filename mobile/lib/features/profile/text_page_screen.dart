import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/widgets/common.dart';
import '../config/config_repository.dart';

/// Terms, privacy policy and the prohibited-items list. The text comes from
/// the admin panel (`GET /config`) and is cached for offline reading.
class TextPageScreen extends ConsumerWidget {
  const TextPageScreen({super.key, required this.page});

  /// `terms`, `privacy`, `prohibited` or `driver-terms`.
  final String page;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final text = Theme.of(context).textTheme;
    final config = ref.watch(appConfigProvider);

    final title = switch (page) {
      'privacy' => S.privacy,
      'prohibited' => S.prohibitedItems,
      'driver-terms' => S.driverTerms,
      _ => S.terms,
    };

    return Scaffold(
      backgroundColor: AppColors.surface,
      appBar: AppBar(
        title: Text(title, style: text.titleLarge),
        backgroundColor: AppColors.surface,
        scrolledUnderElevation: 0,
      ),
      body: config.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => LoadError(error: error, onRetry: () => ref.invalidate(appConfigProvider)),
        data: (config) {
          if (page == 'prohibited') {
            final items = config.prohibitedItems;
            return ListView.separated(
              padding: const EdgeInsets.all(20),
              itemCount: items.length,
              separatorBuilder: (_, __) => const Divider(height: 24),
              itemBuilder: (context, index) => Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Padding(
                    padding: EdgeInsets.only(top: 3),
                    child: Icon(Icons.block_rounded, size: 20, color: AppColors.danger),
                  ),
                  const SizedBox(width: 12),
                  Expanded(child: Text(items[index], style: text.bodyLarge)),
                ],
              ),
            );
          }

          final body = switch (page) {
            'privacy' => config.privacy,
            'driver-terms' => config.driverTerms,
            _ => config.terms,
          };
          if (body.trim().isEmpty) {
            return LoadError(error: Object(), onRetry: () => ref.invalidate(appConfigProvider));
          }

          return SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: SelectableText(body, style: text.bodyLarge),
          );
        },
      ),
    );
  }
}
