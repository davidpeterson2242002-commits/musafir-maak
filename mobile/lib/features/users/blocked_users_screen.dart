import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/widgets/common.dart';
import 'user_repository.dart';

/// People the user blocked, with unblock (profile → المحظورين).
class BlockedUsersScreen extends ConsumerWidget {
  const BlockedUsersScreen({super.key});

  Future<void> _unblock(BuildContext context, WidgetRef ref, int id) async {
    try {
      await ref.read(userRepositoryProvider).unblock(id);
      ref.invalidate(myBlocksProvider);
      if (context.mounted) showMessage(context, S.unblocked);
    } on ApiException catch (e) {
      if (context.mounted) showMessage(context, e.message);
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final text = Theme.of(context).textTheme;
    final blocks = ref.watch(myBlocksProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(S.blockedUsers, style: text.titleLarge),
        backgroundColor: AppColors.surface,
        scrolledUnderElevation: 0,
      ),
      body: blocks.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => LoadError(error: e, onRetry: () => ref.invalidate(myBlocksProvider)),
        data: (list) => list.isEmpty
            ? const EmptyState(icon: Icons.block_rounded, title: S.noBlockedUsers, body: S.noBlockedUsersBody)
            : ListView.separated(
                padding: const EdgeInsets.symmetric(vertical: 8),
                itemCount: list.length,
                separatorBuilder: (_, __) => const Divider(height: 1),
                itemBuilder: (context, i) {
                  final u = list[i];
                  return ListTile(
                    leading: AppAvatar(name: u.name, size: 40),
                    title: Text(u.name, style: text.titleMedium),
                    onTap: () => context.push('/users/${u.id}'),
                    trailing: TextButton(onPressed: () => _unblock(context, ref, u.id), child: const Text(S.unblock)),
                  );
                },
              ),
      ),
    );
  }
}
