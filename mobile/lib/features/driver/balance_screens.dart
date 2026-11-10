import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/api/api_exception.dart';
import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/formatters.dart';
import '../../core/utils/phone.dart';
import '../../core/widgets/common.dart';
import '../../models/driver_money.dart';
import '../trips/trip_widgets.dart';
import 'driver_money_repository.dart';
import 'driver_repository.dart';

/// Balance and statement (spec D13): what's owed, by when, payments in
/// review, and every fee and payment line.
class BalanceScreen extends ConsumerWidget {
  const BalanceScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final text = Theme.of(context).textTheme;
    final balance = ref.watch(driverBalanceProvider);
    final ledger = ref.watch(ledgerProvider);
    final payments = ref.watch(settlementsProvider).valueOrNull ?? const <SettlementPayment>[];

    void reload() {
      ref.invalidate(driverBalanceProvider);
      ref.invalidate(ledgerProvider);
      ref.invalidate(settlementsProvider);
      ref.invalidate(driverAccountProvider);
    }

    return Scaffold(
      appBar: AppBar(
        title: Text(S.balanceTitle, style: text.titleLarge),
        backgroundColor: AppColors.surface,
        scrolledUnderElevation: 0,
      ),
      body: balance.when(
        skipLoadingOnRefresh: true,
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => LoadError(error: error, onRetry: reload),
        data: (balance) {
          final owes = balance.balanceOwed > 0;

          return RefreshIndicator(
            color: AppColors.green,
            onRefresh: () async => reload(),
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
              children: [
                Panel(
                  color: balance.frozen || balance.overdue ? AppColors.dangerTint : null,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(S.balanceOwed, style: text.labelMedium),
                      Text(
                        owes ? Fmt.money(balance.balanceOwed) : S.nothingOwed,
                        style: text.displaySmall?.copyWith(color: owes ? AppColors.ink : AppColors.green),
                      ),
                      if (owes && balance.settlementDueAt != null)
                        Text(
                          balance.overdue ? S.overdue : S.dueOn(Fmt.dayMonth(balance.settlementDueAt!)),
                          style: text.titleSmall?.copyWith(color: balance.overdue ? AppColors.danger : AppColors.ink),
                        ),
                      if (owes && !balance.frozen && balance.freezesAt != null)
                        Text(S.freezesOn(Fmt.dayTime(balance.freezesAt!)), style: text.bodySmall),
                      if (balance.frozen) Text(S.frozenBanner, style: text.bodySmall?.copyWith(color: AppColors.danger)),
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 6,
                        runSpacing: 6,
                        children: [
                          Tag(S.settleEvery(balance.cycleDays), color: AppColors.ink, background: AppColors.background),
                          if (balance.freeBookingsRemaining > 0)
                            Tag(S.freeBookingsLeft(balance.freeBookingsRemaining)),
                          if (balance.walletBalance > 0)
                            Tag('${S.walletBalance}: ${Fmt.money(balance.walletBalance)}'),
                          if (balance.pendingPayments > 0)
                            Tag(
                              S.pendingPayment(Fmt.money(balance.pendingPayments)),
                              icon: Icons.hourglass_top_rounded,
                              color: AppColors.ink,
                              background: AppColors.amberTint,
                            ),
                        ],
                      ),
                      if (owes) ...[
                        const SizedBox(height: 14),
                        FilledButton(
                          onPressed: () async {
                            await context.push('/driver/pay');
                            reload();
                          },
                          child: const Text(S.payBalance),
                        ),
                      ],
                    ],
                  ),
                ),
                if (payments.isNotEmpty) ...[
                  const SizedBox(height: 22),
                  Text(S.myPayments, style: text.titleMedium),
                  const SizedBox(height: 8),
                  for (final payment in payments.take(5))
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Panel(
                        padding: const EdgeInsets.all(12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Expanded(child: Text('${Fmt.money(payment.amount)} — ${payment.method}', style: text.titleSmall)),
                                Tag(
                                  payment.statusLabel,
                                  color: switch (payment.status) {
                                    'confirmed' => AppColors.green,
                                    'rejected' => AppColors.danger,
                                    _ => AppColors.ink,
                                  },
                                  background: switch (payment.status) {
                                    'confirmed' => AppColors.greenTint,
                                    'rejected' => AppColors.dangerTint,
                                    _ => AppColors.amberTint,
                                  },
                                ),
                              ],
                            ),
                            if (payment.createdAt != null) Text(Fmt.dayTime(payment.createdAt!), style: text.bodySmall),
                            if ((payment.rejectReason ?? '').isNotEmpty)
                              Text(payment.rejectReason!, style: text.bodySmall?.copyWith(color: AppColors.danger)),
                          ],
                        ),
                      ),
                    ),
                ],
                const SizedBox(height: 22),
                Text(S.ledger, style: text.titleMedium),
                const SizedBox(height: 8),
                ledger.when(
                  skipLoadingOnRefresh: true,
                  loading: () => const Padding(padding: EdgeInsets.all(16), child: Center(child: CircularProgressIndicator())),
                  error: (error, _) => Text(errorMessage(error), style: text.bodyMedium),
                  data: (entries) => entries.isEmpty
                      ? Text(S.noLedger, style: text.bodyMedium?.copyWith(color: AppColors.inkMuted))
                      : Panel(
                          padding: const EdgeInsets.symmetric(horizontal: 14),
                          child: Column(
                            children: [
                              for (var i = 0; i < entries.length; i++) ...[
                                if (i > 0) const Divider(),
                                _LedgerRow(entry: entries[i]),
                              ],
                            ],
                          ),
                        ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _LedgerRow extends StatelessWidget {
  const _LedgerRow({required this.entry});

  final LedgerEntry entry;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    // Positive = a charge (more owed); negative = a payment or refund.
    final charge = entry.amount > 0;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(entry.label, style: text.titleSmall),
                if ((entry.note ?? '').isNotEmpty) Text(entry.note!, style: text.bodySmall, maxLines: 2),
                if (entry.createdAt != null) Text(Fmt.dayTime(entry.createdAt!), style: text.bodySmall),
              ],
            ),
          ),
          Ltr(
            '${charge ? '+' : '−'}${Fmt.number(entry.amount.abs())}',
            style: text.titleSmall?.copyWith(color: charge ? AppColors.ink : AppColors.green),
          ),
        ],
      ),
    );
  }
}

/// Report a payment (spec D14): pick how you paid, see where to pay, enter the
/// amount and reference, attach the receipt.
class PayScreen extends ConsumerStatefulWidget {
  const PayScreen({super.key});

  @override
  ConsumerState<PayScreen> createState() => _PayScreenState();
}

class _PayScreenState extends ConsumerState<PayScreen> {
  final _amount = TextEditingController();
  final _reference = TextEditingController();
  int? _methodId;
  String? _receiptPath;
  bool _busy = false;
  bool _amountPrefilled = false;
  Map<String, String> _errors = {};

  @override
  void dispose() {
    _amount.dispose();
    _reference.dispose();
    super.dispose();
  }

  Future<void> _pickReceipt() async {
    try {
      // Usually a screenshot from the banking app, so the gallery is the default.
      final file = await ImagePicker().pickImage(source: ImageSource.gallery, maxWidth: 1600, maxHeight: 1600, imageQuality: 85);
      if (file != null && mounted) setState(() => _receiptPath = file.path);
    } catch (_) {
      // Permission denied: nothing picked.
    }
  }

  Future<void> _submit(PaymentMethod? method) async {
    FocusScope.of(context).unfocus();
    final errors = <String, String>{};
    final amount = int.tryParse(Phone.westernDigits(_amount.text.trim()));

    if (method == null) errors['payment_method_id'] = S.chooseMethodFirst;
    if (amount == null || amount < 1) errors['amount'] = S.amountRequired;
    if ((method?.requiresReference ?? false) && _reference.text.trim().isEmpty) {
      errors['reference_number'] = S.referenceRequired;
    }
    if (_receiptPath == null) errors['receipt'] = S.receiptRequired;

    setState(() => _errors = errors);
    if (errors.isNotEmpty) return;

    setState(() => _busy = true);
    try {
      await ref.read(driverMoneyRepositoryProvider).submitPayment(
            methodId: method!.id,
            amount: amount!,
            receiptPath: _receiptPath!,
            reference: _reference.text,
          );
      if (!mounted) return;
      showMessage(context, S.paymentSent);
      context.pop();
    } on ApiException catch (e) {
      if (!mounted) return;
      // "A payment is already in review", "reference already used"…
      setState(() => _errors = e.fieldErrors);
      showMessage(context, e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final methods = ref.watch(paymentMethodsProvider);
    final owed = ref.watch(driverBalanceProvider).valueOrNull?.balanceOwed;

    if (!_amountPrefilled && owed != null && owed > 0) {
      _amountPrefilled = true;
      _amount.text = '$owed';
    }

    return Scaffold(
      backgroundColor: AppColors.surface,
      appBar: AppBar(
        title: Text(S.payTitle, style: text.titleLarge),
        backgroundColor: AppColors.surface,
        scrolledUnderElevation: 0,
      ),
      body: methods.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => LoadError(error: error, onRetry: () => ref.invalidate(paymentMethodsProvider)),
        data: (methods) {
          if (methods.isEmpty) {
            return const EmptyState(icon: Icons.account_balance_outlined, title: S.noPaymentMethods, body: '');
          }
          final method = methods.where((m) => m.id == _methodId).firstOrNull;

          return SafeArea(
            child: Column(
              children: [
                Expanded(
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
                    children: [
                      Text(S.choosePaymentMethod, style: text.titleSmall),
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 8,
                        runSpacing: 4,
                        children: [
                          for (final m in methods)
                            ChoiceChip(
                              label: Text(m.name),
                              selected: m.id == _methodId,
                              onSelected: (_) => setState(() => _methodId = m.id),
                              showCheckmark: false,
                              selectedColor: AppColors.greenTint,
                              backgroundColor: AppColors.surface,
                              side: BorderSide(color: m.id == _methodId ? AppColors.green : AppColors.line),
                              labelStyle: TextStyle(
                                fontFamily: AppFonts.body,
                                fontSize: 14.5,
                                fontWeight: FontWeight.w700,
                                color: m.id == _methodId ? AppColors.green : AppColors.inkMuted,
                              ),
                            ),
                        ],
                      ),
                      if (_errors['payment_method_id'] != null)
                        Text(_errors['payment_method_id']!, style: const TextStyle(color: AppColors.danger, fontSize: 13.5)),
                      if (method != null) ...[
                        const SizedBox(height: 12),
                        _MethodDetails(method: method),
                      ],
                      const SizedBox(height: 18),
                      TextField(
                        controller: _amount,
                        keyboardType: TextInputType.number,
                        inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9٠-٩]'))],
                        decoration: AppTheme.input(label: S.amountPaid, errorText: _errors['amount']),
                      ),
                      const SizedBox(height: 14),
                      Directionality(
                        textDirection: TextDirection.ltr,
                        child: TextField(
                          controller: _reference,
                          textAlign: TextAlign.left,
                          decoration: AppTheme.input(hint: S.referenceNumber, errorText: _errors['reference_number']),
                        ),
                      ),
                      const SizedBox(height: 14),
                      InkWell(
                        onTap: _pickReceipt,
                        borderRadius: BorderRadius.circular(AppTheme.radius),
                        child: Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(AppTheme.radius),
                            border: Border.all(color: _errors['receipt'] != null ? AppColors.danger : AppColors.line),
                          ),
                          child: Row(
                            children: [
                              Container(
                                width: 64,
                                height: 64,
                                clipBehavior: Clip.antiAlias,
                                decoration: BoxDecoration(color: AppColors.greenTint, borderRadius: BorderRadius.circular(10)),
                                child: _receiptPath == null
                                    ? const Icon(Icons.receipt_long_outlined, color: AppColors.green)
                                    : Image.file(File(_receiptPath!), fit: BoxFit.cover, cacheWidth: 200),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(S.receiptPhoto, style: text.titleSmall),
                                    Text(
                                      _errors['receipt'] ?? S.receiptHelp,
                                      style: text.bodySmall?.copyWith(color: _errors['receipt'] != null ? AppColors.danger : null),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
                  child: PrimaryButton(label: S.sendPayment, busy: _busy, onPressed: () => _submit(method)),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

/// Where to send the money for the chosen method.
class _MethodDetails extends StatelessWidget {
  const _MethodDetails({required this.method});

  final PaymentMethod method;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final number = method.accountNumber;

    return Panel(
      color: AppColors.background,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if ((method.accountName ?? '').isNotEmpty) ...[
            Text(S.accountName, style: text.labelMedium),
            Text(method.accountName!, style: text.titleSmall),
            const SizedBox(height: 8),
          ],
          if ((number ?? '').isNotEmpty) ...[
            Text(S.accountNumber, style: text.labelMedium),
            Row(
              children: [
                Expanded(child: Ltr(number!, style: text.titleLarge?.copyWith(letterSpacing: 1))),
                TextButton.icon(
                  onPressed: () async {
                    await Clipboard.setData(ClipboardData(text: number));
                    if (context.mounted) showMessage(context, S.copied);
                  },
                  icon: const Icon(Icons.copy_rounded, size: 18),
                  label: const Text(S.copyNumber),
                ),
              ],
            ),
          ],
          if ((method.instructions ?? '').isNotEmpty) Text(method.instructions!, style: text.bodyMedium),
          if (method.agentLocations.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(S.agentLocations, style: text.labelMedium),
            for (final place in method.agentLocations) Text('• $place', style: text.bodyMedium),
          ],
        ],
      ),
    );
  }
}
