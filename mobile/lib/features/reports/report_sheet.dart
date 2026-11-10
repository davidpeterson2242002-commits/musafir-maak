import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import '../config/config_repository.dart';
import '../users/user_repository.dart';

/// Report another user (spec 5.10), optionally about a booking, parcel, trip or chat.
/// Shows the server's confirmation when sent.
Future<void> showReportSheet(
  BuildContext context, {
  required int userId,
  String? subjectType,
  int? subjectId,
}) async {
  final message = await showModalBottomSheet<String>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    backgroundColor: AppColors.surface,
    showDragHandle: true,
    builder: (_) => _ReportSheet(userId: userId, subjectType: subjectType, subjectId: subjectId),
  );
  if (message != null && context.mounted) showMessage(context, message);
}

class _ReportSheet extends ConsumerStatefulWidget {
  const _ReportSheet({required this.userId, this.subjectType, this.subjectId});

  final int userId;
  final String? subjectType;
  final int? subjectId;

  @override
  ConsumerState<_ReportSheet> createState() => _ReportSheetState();
}

class _ReportSheetState extends ConsumerState<_ReportSheet> {
  final _details = TextEditingController();
  String? _reason;
  String? _error;
  bool _busy = false;

  @override
  void dispose() {
    _details.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    if (_reason == null) {
      setState(() => _error = S.chooseReason);
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final message = await ref.read(userRepositoryProvider).report(
            userId: widget.userId,
            reason: _reason!,
            details: _details.text,
            subjectType: widget.subjectType,
            subjectId: widget.subjectId,
          );
      if (mounted) Navigator.of(context).pop(message ?? S.done);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final reasons = ref.watch(appConfigProvider).valueOrNull?.reportReasons ?? const <String, String>{};

    return Padding(
      padding: EdgeInsets.fromLTRB(20, 0, 20, 16 + MediaQuery.viewInsetsOf(context).bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(S.reportReason, style: text.titleLarge),
          const SizedBox(height: 8),
          Flexible(
            child: SingleChildScrollView(
              child: Column(
                children: [
                  for (final entry in reasons.entries)
                    InkWell(
                      onTap: () => setState(() {
                        _reason = entry.key;
                        _error = null;
                      }),
                      borderRadius: BorderRadius.circular(10),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(vertical: 9),
                        child: Row(
                          children: [
                            Icon(
                              _reason == entry.key
                                  ? Icons.radio_button_checked_rounded
                                  : Icons.radio_button_unchecked_rounded,
                              color: _reason == entry.key ? AppColors.green : AppColors.inkMuted,
                            ),
                            const SizedBox(width: 10),
                            Expanded(child: Text(entry.value, style: text.bodyLarge?.copyWith(height: 1.4))),
                          ],
                        ),
                      ),
                    ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: _details,
                    maxLines: 3,
                    maxLength: 2000,
                    decoration: AppTheme.input(hint: S.reportDetails),
                  ),
                ],
              ),
            ),
          ),
          if (_error != null) ...[
            const SizedBox(height: 4),
            Text(_error!, style: const TextStyle(color: AppColors.danger, fontSize: 14.5)),
          ],
          const SizedBox(height: 10),
          PrimaryButton(label: S.sendReport, onPressed: _send, busy: _busy),
        ],
      ),
    );
  }
}
