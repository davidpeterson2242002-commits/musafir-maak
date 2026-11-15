import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/strings.dart';
import '../../core/widgets/common.dart';
import 'route_alert_repository.dart';

/// "This route is closed for now — want us to tell you when it opens?"
///
/// Asks first, then saves a reopen alert (it also shows the admin which closed
/// routes people want). Returns true when the alert was saved.
Future<bool> offerReopenAlert(
  BuildContext context,
  WidgetRef ref, {
  required int originCityId,
  required int destinationCityId,
  required String routeLabel,
  String? message,
  bool asDriver = false,
}) async {
  final ok = await confirmSheet(
    context,
    title: S.routeClosedNamed(routeLabel),
    body: '${message ?? S.routeClosedTitle}\n\n${asDriver ? S.reopenAskDriver : S.reopenAsk}',
    confirmLabel: S.notifyWhenOpen,
  );
  if (!ok || !context.mounted) return false;
  return saveReopenAlert(context, ref, originCityId: originCityId, destinationCityId: destinationCityId, asDriver: asDriver);
}

/// Saves the alert straight away (the button is already on screen).
Future<bool> saveReopenAlert(
  BuildContext context,
  WidgetRef ref, {
  required int originCityId,
  required int destinationCityId,
  bool asDriver = false,
}) async {
  try {
    await ref.read(routeAlertRepositoryProvider).createReopen(
          originCityId: originCityId,
          destinationCityId: destinationCityId,
          asDriver: asDriver,
        );
    ref.invalidate(routeAlertsProvider);
    if (context.mounted) showMessage(context, S.reopenAlertSaved);
    return true;
  } on ApiException catch (e) {
    if (context.mounted) showMessage(context, e.message);
    return false;
  }
}
