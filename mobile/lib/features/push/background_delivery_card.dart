import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:permission_handler/permission_handler.dart';

import '../../core/storage/cache_store.dart';
import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import 'push_service.dart';

/// Many phones (Tecno, Infinix, Xiaomi, Oppo, Samsung with battery saver) stop notifications
/// for an app swiped away from recents. This one-time card asks Android not to put the app to
/// sleep and points to the app's settings for "Autostart". Hidden once allowed or closed.
final backgroundDeliveryCardProvider = NotifierProvider<BackgroundDeliveryCard, bool>(BackgroundDeliveryCard.new);

class BackgroundDeliveryCard extends Notifier<bool> {
  static const _key = 'push.background_card_done'; // per phone, survives logout

  @override
  bool build() {
    if (ref.watch(pushServiceProvider) is NoPushService) return false;
    if (ref.read(cacheStoreProvider).read(_key) != null) return false;
    Future(() async {
      if (await Permission.ignoreBatteryOptimizations.isGranted) close();
    });
    return true;
  }

  void close() {
    ref.read(cacheStoreProvider).write(_key, true);
    state = false;
  }

  Future<void> allow() async {
    final status = await Permission.ignoreBatteryOptimizations.request();
    if (status.isGranted) close();
  }
}

class BackgroundDeliveryCardView extends ConsumerWidget {
  const BackgroundDeliveryCardView({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (!ref.watch(backgroundDeliveryCardProvider)) return const SizedBox.shrink();
    final text = Theme.of(context).textTheme;
    final card = ref.read(backgroundDeliveryCardProvider.notifier);

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.fromLTRB(14, 10, 6, 12),
      decoration: BoxDecoration(
        color: AppColors.amberTint,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.amber.withValues(alpha: 0.5)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.notifications_active_outlined, color: AppColors.ink),
              const SizedBox(width: 8),
              Expanded(child: Text(S.bgDeliveryTitle, style: text.titleMedium)),
              IconButton(onPressed: card.close, icon: const Icon(Icons.close_rounded, size: 20), tooltip: S.close),
            ],
          ),
          Padding(
            padding: const EdgeInsets.only(left: 8),
            child: Text(S.bgDeliveryBody, style: text.bodyMedium?.copyWith(color: AppColors.inkMuted)),
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            children: [
              FilledButton(onPressed: card.allow, child: const Text(S.bgDeliveryAllow)),
              OutlinedButton(onPressed: openAppSettings, child: const Text(S.bgDeliverySettings)),
            ],
          ),
        ],
      ),
    );
  }
}
