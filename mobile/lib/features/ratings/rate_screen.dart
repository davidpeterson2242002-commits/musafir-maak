import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import '../../models/misc.dart';
import '../config/config_repository.dart';
import 'rating_repository.dart';

/// Rate a finished trip (spec P9): stars, quick tags, optional comment.
class RateScreen extends ConsumerStatefulWidget {
  const RateScreen({super.key, required this.subject});

  final PendingRating subject;

  @override
  ConsumerState<RateScreen> createState() => _RateScreenState();
}

class _RateScreenState extends ConsumerState<RateScreen> {
  final _comment = TextEditingController();
  final Set<String> _tags = {};
  int _stars = 0;
  bool _busy = false;

  @override
  void dispose() {
    _comment.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    if (_stars == 0) {
      showMessage(context, S.chooseStars);
      return;
    }

    setState(() => _busy = true);
    try {
      await ref.read(ratingRepositoryProvider).rate(
            widget.subject,
            stars: _stars,
            comment: _comment.text,
            tags: _tags.toList(),
          );
      ref.invalidate(pendingRatingsProvider);
      if (!mounted) return;
      showMessage(context, S.ratingSent);
      context.pop();
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final config = ref.watch(appConfigProvider).valueOrNull;
    final tags = (widget.subject.ratingDriver ? config?.driverRatingTags : config?.passengerRatingTags) ?? const <String, String>{};

    return Scaffold(
      backgroundColor: AppColors.surface,
      appBar: AppBar(backgroundColor: AppColors.surface, scrolledUnderElevation: 0),
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(20, 0, 20, 16),
                children: [
                  Text(
                    widget.subject.ratingDriver ? S.rateTitle(widget.subject.rateeName) : S.rateCustomer(widget.subject.rateeName),
                    style: text.headlineMedium,
                  ),
                  const SizedBox(height: 4),
                  Text(widget.subject.summary, style: text.bodyMedium?.copyWith(color: AppColors.inkMuted)),
                  const SizedBox(height: 24),
                  // Stars read left-to-right everywhere: 1 on the left, 5 on the right.
                  Directionality(
                    textDirection: TextDirection.ltr,
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        for (var i = 1; i <= 5; i++)
                          IconButton(
                            onPressed: () => setState(() => _stars = i),
                            iconSize: 46,
                            padding: const EdgeInsets.all(4),
                            icon: Icon(
                              i <= _stars ? Icons.star_rounded : Icons.star_outline_rounded,
                              color: i <= _stars ? AppColors.amber : AppColors.line,
                            ),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),
                  if (tags.isNotEmpty)
                    Wrap(
                      spacing: 8,
                      runSpacing: 4,
                      alignment: WrapAlignment.center,
                      children: [
                        for (final entry in tags.entries)
                          FilterChip(
                            label: Text(entry.value),
                            selected: _tags.contains(entry.key),
                            onSelected: (on) => setState(() => on ? _tags.add(entry.key) : _tags.remove(entry.key)),
                            showCheckmark: false,
                            selectedColor: AppColors.greenTint,
                            backgroundColor: AppColors.surface,
                            side: BorderSide(color: _tags.contains(entry.key) ? AppColors.green : AppColors.line),
                            labelStyle: TextStyle(
                              fontFamily: AppFonts.body,
                              fontSize: 14.5,
                              fontWeight: FontWeight.w700,
                              color: _tags.contains(entry.key) ? AppColors.green : AppColors.inkMuted,
                            ),
                          ),
                      ],
                    ),
                  const SizedBox(height: 20),
                  TextField(
                    controller: _comment,
                    maxLines: 4,
                    maxLength: 1000,
                    decoration: AppTheme.input(hint: S.rateComment),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
              child: PrimaryButton(label: S.sendRating, onPressed: _send, busy: _busy),
            ),
          ],
        ),
      ),
    );
  }
}
