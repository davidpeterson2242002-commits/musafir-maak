import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/formatters.dart';
import '../../core/widgets/common.dart';
import '../../models/booking.dart';
import '../../models/trip.dart';
import '../../models/user.dart';
import '../auth/session_controller.dart';
import '../home/home_screen.dart';
import '../trips/trip_repository.dart';
import '../trips/trip_widgets.dart';
import 'booking_repository.dart';

/// Booking request (spec P6): seats, pickup point, extra bags, a note — and
/// the ID step first if the passenger isn't verified yet.
class BookingRequestScreen extends ConsumerStatefulWidget {
  const BookingRequestScreen({super.key, required this.tripId});

  final int tripId;

  @override
  ConsumerState<BookingRequestScreen> createState() => _BookingRequestScreenState();
}

class _BookingRequestScreenState extends ConsumerState<BookingRequestScreen> {
  final _message = TextEditingController();
  int? _seats;
  int? _pickupPointId;
  int _extraBags = 0;
  bool _withFamily = false;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _message.dispose();
    super.dispose();
  }

  /// none / rejected: documents must be sent before the server accepts a booking.
  bool get _needsId {
    final status = ref.read(currentUserProvider)?.idStatus;
    return status == IdStatus.none || status == IdStatus.rejected;
  }

  Future<void> _submit(Trip trip, int seats) async {
    FocusScope.of(context).unfocus();
    setState(() => _error = null);

    if (_needsId && !await _verifyIdentity()) return;

    setState(() => _busy = true);
    try {
      final booking = await _create(trip, seats);
      _afterCreated(booking.id);
    } on ApiException catch (e) {
      // The server is the judge of ID rules (e.g. admin requires approval first).
      if (e.code == 'id_verification_required' && await _verifyIdentity()) {
        try {
          final booking = await _create(trip, seats);
          _afterCreated(booking.id);
          return;
        } on ApiException catch (again) {
          if (mounted) setState(() => _error = again.message);
          return;
        }
      }
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<Booking> _create(Trip trip, int seats) => ref.read(bookingRepositoryProvider).create(
        trip.id,
        seats: seats,
        pickupPointId: _pickupPointId ?? (trip.pickupPoints.isEmpty ? null : trip.pickupPoints.first.id),
        extraBags: _extraBags,
        includesFamily: _withFamily,
        message: _message.text,
      );

  void _afterCreated(int bookingId) {
    if (!mounted) return;
    ref.invalidate(bookingsProvider);
    ref.invalidate(tripDetailsProvider(widget.tripId));
    showMessage(context, S.requestSent);
    // Replace this form with the booking page: "back" returns to the trip.
    context.pushReplacement('/bookings/$bookingId');
  }

  /// Opens the ID step; true when documents were sent (or already allow booking).
  Future<bool> _verifyIdentity() async {
    final ok = await context.push<bool>('/identity', extra: true);
    return ok ?? false;
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final details = ref.watch(tripDetailsProvider(widget.tripId));
    final user = ref.watch(currentUserProvider);

    return Scaffold(
      backgroundColor: AppColors.surface,
      appBar: AppBar(
        title: Text(S.bookingRequest, style: text.titleLarge),
        backgroundColor: AppColors.surface,
        scrolledUnderElevation: 0,
      ),
      body: details.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => LoadError(error: error, onRetry: () => ref.invalidate(tripDetailsProvider(widget.tripId))),
        data: (details) {
          final trip = details.trip;
          final maxSeats = trip.seatsAvailable < 1 ? 1 : trip.seatsAvailable;
          final requested = ref.read(searchQueryProvider).seats;
          final seats = (_seats ?? requested).clamp(1, maxSeats).toInt();
          final pickupId = _pickupPointId ?? (trip.pickupPoints.isEmpty ? null : trip.pickupPoints.first.id);
          final askFamily = trip.womenOnly && user?.gender != Gender.female;
          final idMissing = user?.idStatus == IdStatus.none || user?.idStatus == IdStatus.rejected;
          final total = trip.pricePerSeat * seats + (trip.extraBagPrice ?? 0) * _extraBags;

          return SafeArea(
            child: Column(
              children: [
                Expanded(
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
                    children: [
                      RouteTitle(from: trip.originName, to: trip.destinationName, style: text.headlineSmall),
                      Text(Fmt.dayTime(trip.departsAt), style: text.bodyMedium?.copyWith(color: AppColors.inkMuted)),
                      const SizedBox(height: 20),
                      if (idMissing) ...[
                        const Panel(
                          color: AppColors.amberTint,
                          padding: EdgeInsets.all(12),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Icon(Icons.badge_outlined, color: AppColors.ink),
                              SizedBox(width: 10),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(S.idNeededTitle, style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
                                    Text(S.idNeededBody, style: TextStyle(fontSize: 13.5, height: 1.5)),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 20),
                      ],
                      _Stepper(
                        label: S.seats,
                        value: seats,
                        min: 1,
                        max: maxSeats,
                        onChanged: (value) => setState(() => _seats = value),
                      ),
                      if (trip.pickupPoints.isNotEmpty) ...[
                        const Divider(height: 32),
                        Text(S.pickupPoint, style: text.titleMedium),
                        const SizedBox(height: 4),
                        for (final point in trip.pickupPoints)
                          InkWell(
                            onTap: () => setState(() => _pickupPointId = point.id),
                            borderRadius: BorderRadius.circular(10),
                            child: Padding(
                              padding: const EdgeInsets.symmetric(vertical: 9),
                              child: Row(
                                children: [
                                  Icon(
                                    point.id == pickupId
                                        ? Icons.radio_button_checked_rounded
                                        : Icons.radio_button_unchecked_rounded,
                                    color: point.id == pickupId ? AppColors.green : AppColors.inkMuted,
                                  ),
                                  const SizedBox(width: 10),
                                  Expanded(child: Text(point.label, style: text.bodyLarge?.copyWith(height: 1.4))),
                                ],
                              ),
                            ),
                          ),
                      ],
                      if (trip.extraBagPrice != null) ...[
                        const Divider(height: 32),
                        _Stepper(
                          label: S.extraBags,
                          help: '${S.luggageAllowance(trip.luggageLarge, trip.luggageSmall)}. ${S.extraBag(Fmt.money(trip.extraBagPrice!))}',
                          value: _extraBags,
                          min: 0,
                          max: 10,
                          onChanged: (value) => setState(() => _extraBags = value),
                        ),
                      ],
                      if (askFamily) ...[
                        const Divider(height: 32),
                        CheckboxListTile(
                          value: _withFamily,
                          onChanged: (value) => setState(() => _withFamily = value ?? false),
                          title: Text(S.withFamily, style: text.titleMedium),
                          subtitle: Text(S.withFamilyHelp, style: text.bodySmall),
                          contentPadding: EdgeInsets.zero,
                          controlAffinity: ListTileControlAffinity.leading,
                        ),
                      ],
                      const Divider(height: 32),
                      TextField(
                        controller: _message,
                        maxLines: 3,
                        maxLength: 500,
                        decoration: AppTheme.input(hint: S.messageToDriver),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.fromLTRB(20, 12, 20, 12),
                  decoration: const BoxDecoration(border: Border(top: BorderSide(color: AppColors.line))),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Row(
                        children: [
                          Expanded(child: Text(S.total, style: text.titleMedium)),
                          Text(Fmt.money(total), style: text.titleLarge?.copyWith(color: AppColors.green)),
                        ],
                      ),
                      Text(S.payDriverDirectly, style: text.bodySmall),
                      if (_error != null) ...[
                        const SizedBox(height: 8),
                        Text(_error!, style: const TextStyle(color: AppColors.danger, fontSize: 14.5)),
                      ],
                      const SizedBox(height: 10),
                      PrimaryButton(
                        label: idMissing ? S.verifyThenBook : S.sendRequest,
                        busy: _busy,
                        onPressed: () => _submit(trip, seats),
                      ),
                    ],
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

class _Stepper extends StatelessWidget {
  const _Stepper({
    required this.label,
    required this.value,
    required this.min,
    required this.max,
    required this.onChanged,
    this.help,
  });

  final String label;
  final String? help;
  final int value;
  final int min;
  final int max;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    Widget step(IconData icon, VoidCallback? onTap) => IconButton(
          onPressed: onTap,
          style: IconButton.styleFrom(
            fixedSize: const Size(44, 44),
            side: BorderSide(color: onTap == null ? AppColors.line : AppColors.green),
            foregroundColor: AppColors.green,
          ),
          icon: Icon(icon, size: 20),
        );

    return Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: text.titleMedium),
              if (help != null) Text(help!, style: text.bodySmall),
            ],
          ),
        ),
        step(Icons.remove_rounded, value > min ? () => onChanged(value - 1) : null),
        SizedBox(width: 44, child: Text('$value', style: text.titleLarge, textAlign: TextAlign.center)),
        step(Icons.add_rounded, value < max ? () => onChanged(value + 1) : null),
      ],
    );
  }
}
