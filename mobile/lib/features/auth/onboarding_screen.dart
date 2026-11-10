import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/storage/cache_store.dart';
import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/road.dart';

/// Three stops along the road, each a highway sign: travel, trust, parcels (spec P1).
class OnboardingScreen extends ConsumerStatefulWidget {
  const OnboardingScreen({super.key});

  @override
  ConsumerState<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends ConsumerState<OnboardingScreen> {
  static const _pages = [
    (title: S.onboardTitle1, body: S.onboardBody1),
    (title: S.onboardTitle2, body: S.onboardBody2),
    (title: S.onboardTitle3, body: S.onboardBody3),
  ];

  final _controller = PageController();
  int _page = 0;

  bool get _isLast => _page == _pages.length - 1;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _finish() async {
    await ref.read(cacheStoreProvider).write(CacheKeys.onboardingSeen, true);
    if (mounted) context.go('/login');
  }

  void _next() {
    if (_isLast) {
      _finish();
    } else {
      _controller.nextPage(duration: const Duration(milliseconds: 280), curve: Curves.easeOut);
    }
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return Scaffold(
      backgroundColor: AppColors.surface,
      body: SafeArea(
        child: Column(
          children: [
            Align(
              alignment: AlignmentDirectional.centerEnd,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 8),
                child: TextButton(
                  onPressed: _isLast ? null : _finish,
                  child: Text(_isLast ? '' : S.skip),
                ),
              ),
            ),
            Expanded(
              child: PageView.builder(
                controller: _controller,
                itemCount: _pages.length,
                onPageChanged: (index) => setState(() => _page = index),
                itemBuilder: (context, index) {
                  final page = _pages[index];
                  return Column(
                    children: [
                      Expanded(
                        flex: 5,
                        child: Container(
                          color: AppColors.greenTint,
                          width: double.infinity,
                          child: RoadScene(sign: RoadSign(child: Text(page.title))),
                        ),
                      ),
                      Expanded(
                        flex: 3,
                        child: Padding(
                          padding: const EdgeInsets.fromLTRB(28, 28, 28, 0),
                          child: Text(
                            page.body,
                            style: text.bodyLarge?.copyWith(fontSize: 18.5),
                            textAlign: TextAlign.center,
                          ),
                        ),
                      ),
                    ],
                  );
                },
              ),
            ),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                for (var i = 0; i < _pages.length; i++)
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 200),
                    margin: const EdgeInsets.symmetric(horizontal: 4),
                    width: i == _page ? 26 : 8,
                    height: 8,
                    decoration: BoxDecoration(
                      color: i == _page ? AppColors.amber : AppColors.line,
                      borderRadius: BorderRadius.circular(4),
                    ),
                  ),
              ],
            ),
            Padding(
              padding: AppTheme.screenPadding.copyWith(top: 24, bottom: 8),
              child: FilledButton(onPressed: _next, child: Text(_isLast ? S.start : S.next)),
            ),
            TextButton(onPressed: _finish, child: const Text(S.haveAccount)),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
  }
}
