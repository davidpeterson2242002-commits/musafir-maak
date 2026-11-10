import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api/api_exception.dart';
import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/formatters.dart';
import '../../core/utils/phone.dart';
import '../../core/widgets/common.dart';
import '../../models/user.dart';
import '../auth/session_controller.dart';
import '../config/config_repository.dart';
import 'profile_repository.dart';

/// "حسابي" tab (spec P11).
class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(currentUserProvider);
    if (user == null) return const SizedBox.shrink();

    final supportNumber = ref.watch(appConfigProvider).valueOrNull?.supportWhatsapp ?? '';

    return RefreshIndicator(
      color: AppColors.green,
      onRefresh: () => ref.read(sessionProvider.notifier).refresh(),
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 28),
        children: [
          _Header(user: user),
          const SizedBox(height: 16),
          _IdentityCard(status: user.idStatus),
          if (!user.isDriver) ...[
            const SizedBox(height: 12),
            _DriverCard(driverStatus: user.driverStatus),
          ],
          const SizedBox(height: 20),
          Panel(
            padding: EdgeInsets.zero,
            child: Column(
              children: [
                _MenuItem(
                  icon: Icons.edit_outlined,
                  label: S.editProfile,
                  onTap: () => context.push('/profile/edit'),
                ),
                const _MenuItem(icon: Icons.notifications_none_rounded, label: S.routeAlerts, soon: true),
                if (supportNumber.isNotEmpty)
                  _MenuItem(
                    icon: Icons.support_agent_rounded,
                    label: S.contactWhatsapp,
                    onTap: () => _openWhatsapp(context, supportNumber),
                  ),
                _MenuItem(
                  icon: Icons.description_outlined,
                  label: S.terms,
                  onTap: () => context.push('/page/terms'),
                ),
                _MenuItem(
                  icon: Icons.lock_outline_rounded,
                  label: S.privacy,
                  onTap: () => context.push('/page/privacy'),
                ),
                _MenuItem(
                  icon: Icons.block_rounded,
                  label: S.prohibitedItems,
                  onTap: () => context.push('/page/prohibited'),
                  last: true,
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          OutlinedButton.icon(
            onPressed: () => _logout(context, ref),
            icon: const Icon(Icons.logout_rounded, size: 20),
            label: const Text(S.logout),
          ),
          const SizedBox(height: 4),
          TextButton(
            onPressed: () => _deleteAccount(context, ref),
            style: TextButton.styleFrom(foregroundColor: AppColors.danger),
            child: const Text(S.deleteAccount),
          ),
        ],
      ),
    );
  }

  Future<void> _openWhatsapp(BuildContext context, String number) async {
    final digits = (Phone.normalize(number) ?? number).replaceAll(RegExp(r'[^0-9]'), '');
    final opened = await launchUrl(Uri.parse('https://wa.me/$digits'), mode: LaunchMode.externalApplication);
    if (!opened && context.mounted) showMessage(context, S.unknownError);
  }

  Future<void> _logout(BuildContext context, WidgetRef ref) async {
    final ok = await confirmSheet(context, title: S.logoutConfirm, confirmLabel: S.logout);
    if (ok) await ref.read(sessionProvider.notifier).signOut();
  }

  Future<void> _deleteAccount(BuildContext context, WidgetRef ref) async {
    final ok = await confirmSheet(
      context,
      title: S.deleteAccount,
      body: S.deleteAccountConfirm,
      confirmLabel: S.deleteAccountAction,
      destructive: true,
    );
    if (!ok) return;

    try {
      await ref.read(profileRepositoryProvider).deleteAccount();
      await ref.read(sessionProvider.notifier).accountDeleted();
    } on ApiException catch (e) {
      // e.g. "cancel your active bookings first" — the server explains what to do.
      if (context.mounted) showMessage(context, e.message);
    }
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.user});

  final User user;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return Row(
      children: [
        AppAvatar(photoUrl: user.photoUrl, name: user.name, size: 76),
        const SizedBox(width: 16),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(user.name ?? '', style: text.headlineSmall, maxLines: 1, overflow: TextOverflow.ellipsis),
              Ltr(Phone.local(user.phone), style: text.bodyMedium?.copyWith(color: AppColors.inkMuted)),
              const SizedBox(height: 4),
              Row(
                children: [
                  const Icon(Icons.star_rounded, size: 18, color: AppColors.amber),
                  const SizedBox(width: 3),
                  Text(
                    user.ratingCount > 0
                        ? '${user.ratingAvg.toStringAsFixed(1)} (${S.ratingsCount(user.ratingCount)})'
                        : S.noRatingsYet,
                    style: text.labelMedium,
                  ),
                  if (user.memberSince != null) ...[
                    const SizedBox(width: 10),
                    Flexible(
                      child: Text(
                        '${S.memberSince} ${Fmt.monthYear(user.memberSince!)}',
                        style: text.labelMedium,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }
}

/// ID verification status and the next step (spec 5.12). The upload flow itself is M8.
class _IdentityCard extends StatelessWidget {
  const _IdentityCard({required this.status});

  final IdStatus status;

  @override
  Widget build(BuildContext context) {
    final (icon, color, tint, title, body) = switch (status) {
      IdStatus.verified => (Icons.verified_rounded, AppColors.green, AppColors.greenTint, S.idVerified, null),
      IdStatus.pending => (Icons.hourglass_top_rounded, AppColors.ink, AppColors.amberTint, S.idPending, S.idPendingBody),
      IdStatus.rejected => (Icons.error_outline_rounded, AppColors.danger, AppColors.dangerTint, S.idRejected, S.idRejectedBody),
      IdStatus.none => (Icons.badge_outlined, AppColors.green, AppColors.greenTint, S.verifyId, S.verifyIdBody),
    };

    return _StatusCard(icon: icon, color: color, tint: tint, title: title, body: body);
  }
}

class _DriverCard extends StatelessWidget {
  const _DriverCard({required this.driverStatus});

  final String? driverStatus;

  @override
  Widget build(BuildContext context) {
    final pending = driverStatus == 'pending_review';

    return _StatusCard(
      icon: pending ? Icons.hourglass_top_rounded : Icons.directions_car_filled_outlined,
      color: AppColors.ink,
      tint: AppColors.surface,
      bordered: true,
      title: pending ? S.driverPending : S.becomeDriver,
      body: pending ? S.idPendingBody : S.becomeDriverBody,
      trailing: pending ? null : const _SoonTag(),
    );
  }
}

class _StatusCard extends StatelessWidget {
  const _StatusCard({
    required this.icon,
    required this.color,
    required this.tint,
    required this.title,
    this.body,
    this.trailing,
    this.bordered = false,
  });

  final IconData icon;
  final Color color;
  final Color tint;
  final String title;
  final String? body;
  final Widget? trailing;
  final bool bordered;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: tint,
        borderRadius: BorderRadius.circular(AppTheme.radius + 4),
        border: bordered ? Border.all(color: AppColors.line) : null,
      ),
      child: Row(
        children: [
          Icon(icon, color: color, size: 26),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: text.titleMedium?.copyWith(color: color)),
                if (body != null) Text(body!, style: text.bodySmall?.copyWith(color: AppColors.ink)),
              ],
            ),
          ),
          if (trailing != null) trailing!,
        ],
      ),
    );
  }
}

class _SoonTag extends StatelessWidget {
  const _SoonTag();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: AppColors.amberTint, borderRadius: BorderRadius.circular(20)),
      child: const Text(S.comingSoon, style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: AppColors.ink)),
    );
  }
}

class _MenuItem extends StatelessWidget {
  const _MenuItem({required this.icon, required this.label, this.onTap, this.soon = false, this.last = false});

  final IconData icon;
  final String label;
  final VoidCallback? onTap;
  final bool soon;
  final bool last;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return Column(
      children: [
        ListTile(
          onTap: soon ? null : onTap,
          contentPadding: const EdgeInsets.symmetric(horizontal: 16),
          leading: Icon(icon, color: soon ? AppColors.inkMuted : AppColors.green),
          title: Text(label, style: text.titleMedium?.copyWith(fontWeight: FontWeight.w500, color: soon ? AppColors.inkMuted : AppColors.ink)),
          trailing: soon
              ? const _SoonTag()
              : const Icon(Icons.chevron_left_rounded, color: AppColors.inkMuted, textDirection: TextDirection.ltr),
        ),
        if (!last) const Divider(indent: 56),
      ],
    );
  }
}
