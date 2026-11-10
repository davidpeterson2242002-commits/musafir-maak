import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../core/widgets/common.dart';
import '../../models/chat.dart';
import '../reports/report_sheet.dart';
import '../trips/trip_widgets.dart';
import 'user_repository.dart';

/// Another user's public profile (spec P13): rating, reviews, report and block.
class PublicProfileScreen extends ConsumerWidget {
  const PublicProfileScreen({super.key, required this.userId});

  final int userId;

  Future<void> _toggleBlock(BuildContext context, WidgetRef ref, PublicProfile profile) async {
    final repo = ref.read(userRepositoryProvider);
    try {
      if (profile.blockedByMe) {
        await repo.unblock(userId);
        if (context.mounted) showMessage(context, S.unblocked);
      } else {
        final ok = await confirmSheet(
          context,
          title: S.blockConfirm(profile.user.name),
          body: S.blockBody,
          confirmLabel: S.block,
          destructive: true,
        );
        if (!ok) return;
        await repo.block(userId);
        if (context.mounted) showMessage(context, S.blocked);
      }
      ref.invalidate(publicProfileProvider(userId));
    } on ApiException catch (e) {
      if (context.mounted) showMessage(context, e.message);
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final text = Theme.of(context).textTheme;
    final loaded = ref.watch(publicProfileProvider(userId));

    return Scaffold(
      appBar: AppBar(
        title: Text(S.profile, style: text.titleLarge),
        backgroundColor: AppColors.surface,
        scrolledUnderElevation: 0,
      ),
      body: loaded.when(
        skipLoadingOnRefresh: true,
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => LoadError(error: error, onRetry: () => ref.invalidate(publicProfileProvider(userId))),
        data: (profile) {
          final user = profile.user;
          final reviews = profile.isDriver ? profile.driverReviews : profile.passengerReviews;

          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
            children: [
              Panel(
                child: Column(
                  children: [
                    AppAvatar(photoUrl: user.photoUrl, name: user.name, size: 88),
                    const SizedBox(height: 10),
                    Text(user.name, style: text.headlineSmall, textAlign: TextAlign.center),
                    if (user.verified) ...[const SizedBox(height: 2), const VerifiedBadge()],
                    const SizedBox(height: 12),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                      children: [
                        if (profile.isDriver)
                          _Stat(
                            label: S.asDriver,
                            child: RatingLabel(average: profile.driverRatingAvg ?? 0, count: profile.driverRatingCount),
                          ),
                        _Stat(
                          label: S.asPassenger,
                          child: RatingLabel(average: user.ratingAvg, count: user.ratingCount, emptyLabel: S.noRatingsYet),
                        ),
                        if (profile.isDriver)
                          _Stat(label: S.driver, child: Text(S.tripsDone(profile.driverTrips), style: text.titleSmall)),
                      ],
                    ),
                    if (user.memberSince != null) ...[
                      const SizedBox(height: 10),
                      Text('${S.memberSince} ${Fmt.monthYear(user.memberSince!)}', style: text.bodySmall),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 20),
              Text(S.reviews, style: text.titleMedium),
              const SizedBox(height: 10),
              if (reviews.isEmpty)
                Text(S.noReviews, style: text.bodyMedium?.copyWith(color: AppColors.inkMuted))
              else
                for (final review in reviews)
                  Padding(padding: const EdgeInsets.only(bottom: 10), child: _ReviewCard(review: review)),
              const SizedBox(height: 16),
              OutlinedButton.icon(
                onPressed: () => showReportSheet(context, userId: userId),
                icon: const Icon(Icons.flag_outlined, size: 20),
                label: const Text(S.reportUser),
              ),
              const SizedBox(height: 4),
              TextButton(
                onPressed: () => _toggleBlock(context, ref, profile),
                style: TextButton.styleFrom(foregroundColor: AppColors.danger),
                child: Text(profile.blockedByMe ? S.unblock : S.block),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  const _Stat({required this.label, required this.child});

  final String label;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        child,
        const SizedBox(height: 2),
        Text(label, style: Theme.of(context).textTheme.bodySmall),
      ],
    );
  }
}

class _ReviewCard extends StatelessWidget {
  const _ReviewCard({required this.review});

  final Review review;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return Panel(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              // Stars always read left-to-right.
              Directionality(
                textDirection: TextDirection.ltr,
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    for (var i = 1; i <= 5; i++)
                      Icon(
                        i <= review.stars ? Icons.star_rounded : Icons.star_outline_rounded,
                        size: 18,
                        color: i <= review.stars ? AppColors.amber : AppColors.line,
                      ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Expanded(child: Text(review.raterName ?? '', style: text.titleSmall, overflow: TextOverflow.ellipsis)),
              if (review.createdAt != null) Text(Fmt.monthYear(review.createdAt!), style: text.bodySmall),
            ],
          ),
          if ((review.comment ?? '').trim().isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(review.comment!, style: text.bodyMedium),
          ],
          if (review.tags.isNotEmpty) ...[
            const SizedBox(height: 8),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                for (final tag in review.tags) Tag(tag, color: AppColors.ink, background: AppColors.background),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
