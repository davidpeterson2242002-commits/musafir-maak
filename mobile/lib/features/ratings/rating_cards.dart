import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/storage/cache_store.dart';
import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../models/misc.dart';
import 'rating_repository.dart';

/// Ratings the person closed with ✕: not shown on the home screen again
/// (they stay reachable from the booking / trip page).
final dismissedRatingsProvider = NotifierProvider<DismissedRatings, Set<String>>(DismissedRatings.new);

class DismissedRatings extends Notifier<Set<String>> {
  static const _key = '${CacheKeys.user}dismissed_ratings';

  @override
  Set<String> build() {
    final raw = ref.read(cacheStoreProvider).read(_key)?.data;
    return raw is List ? raw.map((e) => '$e').toSet() : <String>{};
  }

  void dismissAll(Iterable<PendingRating> ratings) {
    state = {...state, ...ratings.map(keyOf)};
    ref.read(cacheStoreProvider).write(_key, state.toList());
  }

  static String keyOf(PendingRating r) => '${r.subjectType}:${r.subjectId}:${r.rateAs}';
}

/// One card for all the ratings waiting, swiped sideways, closable with ✕
/// (spec P9 / D15). [driverSide]: the driver rating passengers and senders;
/// otherwise the passenger rating the driver.
class RatingCards extends ConsumerStatefulWidget {
  const RatingCards({super.key, required this.driverSide});

  final bool driverSide;

  @override
  ConsumerState<RatingCards> createState() => _RatingCardsState();
}

class _RatingCardsState extends ConsumerState<RatingCards> {
  final _pages = PageController();
  int _page = 0;

  @override
  void dispose() {
    _pages.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final dismissed = ref.watch(dismissedRatingsProvider);
    final all = ref.watch(pendingRatingsProvider).valueOrNull ?? const <PendingRating>[];
    final list = all
        .where((r) => widget.driverSide ? !r.ratingDriver : r.ratingDriver)
        .where((r) => !dismissed.contains(DismissedRatings.keyOf(r)))
        .toList();
    if (list.isEmpty) return const SizedBox.shrink();
    final page = _page.clamp(0, list.length - 1);

    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Container(
        decoration: BoxDecoration(
          color: AppColors.amberTint,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: AppColors.line),
        ),
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 6, 4, 0),
              child: Row(
                children: [
                  const Icon(Icons.star_rounded, color: AppColors.amber, size: 20),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      list.length == 1 ? S.rateTitleOne : S.rateTitleMany(page + 1, list.length),
                      style: text.labelLarge?.copyWith(color: AppColors.inkMuted),
                    ),
                  ),
                  IconButton(
                    tooltip: S.close,
                    visualDensity: VisualDensity.compact,
                    icon: const Icon(Icons.close_rounded, size: 20, color: AppColors.inkMuted),
                    onPressed: () => ref.read(dismissedRatingsProvider.notifier).dismissAll(list),
                  ),
                ],
              ),
            ),
            SizedBox(
              height: 92,
              child: PageView.builder(
                controller: _pages,
                itemCount: list.length,
                onPageChanged: (i) => setState(() => _page = i),
                itemBuilder: (context, i) {
                  final r = list[i];
                  return InkWell(
                    onTap: () async {
                      await context.push('/rate', extra: r);
                      ref.invalidate(pendingRatingsProvider);
                    },
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(14, 0, 14, 10),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            widget.driverSide ? S.rateCustomer(r.rateeName) : S.ratePrompt(r.rateeName),
                            style: text.titleMedium,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          Text(r.summary, style: text.bodySmall, maxLines: 1, overflow: TextOverflow.ellipsis),
                          const SizedBox(height: 6),
                          Row(
                            children: [
                              for (var s = 0; s < 5; s++)
                                const Padding(
                                  padding: EdgeInsetsDirectional.only(end: 2),
                                  child: Icon(Icons.star_outline_rounded, size: 24, color: AppColors.amber),
                                ),
                              const Spacer(),
                              Text(S.tapToRate, style: text.labelMedium?.copyWith(color: AppColors.greenDark)),
                            ],
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
            if (list.length > 1)
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    for (var i = 0; i < list.length; i++)
                      AnimatedContainer(
                        duration: const Duration(milliseconds: 150),
                        margin: const EdgeInsets.symmetric(horizontal: 3),
                        width: i == page ? 16 : 6,
                        height: 6,
                        decoration: BoxDecoration(
                          color: i == page ? AppColors.amber : AppColors.line,
                          borderRadius: BorderRadius.circular(3),
                        ),
                      ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}
