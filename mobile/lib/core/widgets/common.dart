import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../api/api_exception.dart';
import '../strings.dart';
import '../theme/app_colors.dart';
import '../theme/app_theme.dart';
import '../utils/formatters.dart';

/// Full-width primary button that shows a spinner (and ignores taps) while busy.
class PrimaryButton extends StatelessWidget {
  const PrimaryButton({super.key, required this.label, required this.onPressed, this.busy = false, this.icon});

  final String label;
  final VoidCallback? onPressed;
  final bool busy;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return FilledButton(
      onPressed: busy ? () {} : onPressed,
      child: busy
          ? const SizedBox(
              width: 22,
              height: 22,
              child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white),
            )
          : Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (icon != null) ...[Icon(icon, size: 20), const SizedBox(width: 8)],
                Flexible(child: Text(label, overflow: TextOverflow.ellipsis)),
              ],
            ),
    );
  }
}

/// White rounded panel used for grouped content.
class Panel extends StatelessWidget {
  const Panel({super.key, required this.child, this.padding = const EdgeInsets.all(16), this.color});

  final Widget child;
  final EdgeInsetsGeometry padding;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: padding,
      decoration: BoxDecoration(
        color: color ?? AppColors.surface,
        borderRadius: BorderRadius.circular(AppTheme.radius + 4),
        border: color == null ? Border.all(color: AppColors.line) : null,
      ),
      child: child,
    );
  }
}

/// Profile photo, or the first letter of the name on a green tint.
class AppAvatar extends StatelessWidget {
  const AppAvatar({super.key, this.photoUrl, this.name, this.size = 56});

  final String? photoUrl;
  final String? name;
  final double size;

  @override
  Widget build(BuildContext context) {
    final placeholder = Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: const BoxDecoration(color: AppColors.greenTint, shape: BoxShape.circle),
      child: Text(
        Fmt.initial(name),
        style: TextStyle(
          fontFamily: AppFonts.display,
          fontSize: size * 0.46,
          height: 1.2,
          color: AppColors.green,
        ),
      ),
    );

    if (photoUrl == null) return placeholder;

    return ClipOval(
      child: Image.network(
        photoUrl!,
        width: size,
        height: size,
        fit: BoxFit.cover,
        // Decode at display size: profile photos are tiny on screen and memory is tight on older phones.
        cacheWidth: (size * 2).round(),
        errorBuilder: (_, __, ___) => placeholder,
        loadingBuilder: (context, child, progress) => progress == null ? child : placeholder,
      ),
    );
  }
}

/// "هوية موثّقة" mark shown next to names.
class VerifiedBadge extends StatelessWidget {
  const VerifiedBadge({super.key, this.withLabel = true});

  final bool withLabel;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        const Icon(Icons.verified_rounded, size: 18, color: AppColors.green),
        if (withLabel) ...[
          const SizedBox(width: 4),
          const Text(
            S.idVerified,
            style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700, color: AppColors.green),
          ),
        ],
      ],
    );
  }
}

/// Empty screens say what will appear here and offer the next step.
class EmptyState extends StatelessWidget {
  const EmptyState({
    super.key,
    required this.icon,
    required this.title,
    required this.body,
    this.actionLabel,
    this.onAction,
  });

  final IconData icon;
  final String title;
  final String body;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 84,
              height: 84,
              decoration: const BoxDecoration(color: AppColors.greenTint, shape: BoxShape.circle),
              child: Icon(icon, size: 38, color: AppColors.green),
            ),
            const SizedBox(height: 20),
            Text(title, style: text.titleLarge, textAlign: TextAlign.center),
            const SizedBox(height: 8),
            Text(body, style: text.bodyMedium?.copyWith(color: AppColors.inkMuted), textAlign: TextAlign.center),
            if (actionLabel != null) ...[
              const SizedBox(height: 24),
              OutlinedButton(onPressed: onAction, child: Text(actionLabel!)),
            ],
          ],
        ),
      ),
    );
  }
}

/// Shown when a screen's data couldn't be loaded at all (nothing cached).
class LoadError extends StatelessWidget {
  const LoadError({super.key, required this.error, required this.onRetry});

  final Object error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final message = error is ApiException ? (error as ApiException).message : S.unknownError;
    final offline = error is ApiException && (error as ApiException).isOffline;

    return EmptyState(
      icon: offline ? Icons.wifi_off_rounded : Icons.error_outline_rounded,
      title: S.loadFailed,
      body: message,
      actionLabel: S.retry,
      onAction: onRetry,
    );
  }
}

/// Thin strip under the app bar while the last request failed for lack of a connection.
class OfflineBanner extends ConsumerWidget {
  const OfflineBanner({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final offline = ref.watch(offlineProvider);

    return AnimatedSize(
      duration: const Duration(milliseconds: 200),
      alignment: Alignment.topCenter,
      child: !offline
          ? const SizedBox(width: double.infinity)
          : Container(
              width: double.infinity,
              color: AppColors.amberTint,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: const Row(
                children: [
                  Icon(Icons.wifi_off_rounded, size: 18, color: AppColors.ink),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(S.offline, style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w500, color: AppColors.ink)),
                  ),
                ],
              ),
            ),
    );
  }
}

/// Numbers, phones and codes read left-to-right even inside Arabic text.
class Ltr extends StatelessWidget {
  const Ltr(this.text, {super.key, this.style});

  final String text;
  final TextStyle? style;

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.ltr,
      child: Text(text, style: style),
    );
  }
}

void showMessage(BuildContext context, String message) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message)));
}

/// The text to show for any thrown error.
String errorMessage(Object error) => error is ApiException ? error.message : S.unknownError;

/// Yes/no question in a bottom sheet (easier to reach than a centred dialog).
Future<bool> confirmSheet(
  BuildContext context, {
  required String title,
  String? body,
  required String confirmLabel,
  bool destructive = false,
}) async {
  final result = await showModalBottomSheet<bool>(
    context: context,
    backgroundColor: AppColors.surface,
    showDragHandle: true,
    builder: (context) {
      final text = Theme.of(context).textTheme;
      return SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(title, style: text.titleLarge),
              if (body != null) ...[
                const SizedBox(height: 8),
                Text(body, style: text.bodyMedium?.copyWith(color: AppColors.inkMuted)),
              ],
              const SizedBox(height: 20),
              FilledButton(
                style: destructive ? FilledButton.styleFrom(backgroundColor: AppColors.danger) : null,
                onPressed: () => Navigator.of(context).pop(true),
                child: Text(confirmLabel),
              ),
              const SizedBox(height: 8),
              TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text(S.cancel)),
            ],
          ),
        ),
      );
    },
  );
  return result ?? false;
}

/// Asks for a short piece of text in a bottom sheet. Returns null if dismissed
/// or shorter than [minLength].
Future<String?> askTextSheet(
  BuildContext context, {
  required String title,
  required String hint,
  required String action,
  String? warning,
  int minLength = 3,
}) {
  final controller = TextEditingController();

  return showModalBottomSheet<String>(
    context: context,
    isScrollControlled: true,
    backgroundColor: AppColors.surface,
    showDragHandle: true,
    builder: (sheet) => Padding(
      padding: EdgeInsets.fromLTRB(20, 0, 20, 16 + MediaQuery.viewInsetsOf(sheet).bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(title, style: Theme.of(sheet).textTheme.titleLarge),
          if (warning != null) ...[
            const SizedBox(height: 6),
            Text(warning, style: Theme.of(sheet).textTheme.bodyMedium?.copyWith(color: AppColors.danger)),
          ],
          const SizedBox(height: 12),
          TextField(
            controller: controller,
            autofocus: true,
            maxLines: 3,
            maxLength: 500,
            decoration: AppTheme.input(hint: hint),
          ),
          const SizedBox(height: 8),
          FilledButton(
            onPressed: () {
              final value = controller.text.trim();
              if (value.length >= minLength) Navigator.of(sheet).pop(value);
            },
            child: Text(action),
          ),
        ],
      ),
    ),
  );
}
