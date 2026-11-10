import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/formatters.dart';
import '../../core/utils/phone.dart';
import '../../core/widgets/common.dart';
import '../../models/city.dart';
import '../../models/driver.dart';
import '../../models/driver_trip.dart';
import '../auth/session_controller.dart';
import '../geo/city_picker.dart';
import 'driver_repository.dart';
import 'driver_trip_repository.dart';

/// Post a trip (spec D7), or edit one that has no bookings yet. One scrolling
/// form in the order a driver thinks: where, when, which car, how much, where
/// to meet, bags, parcels.
class PostTripScreen extends ConsumerStatefulWidget {
  const PostTripScreen({super.key, this.trip});

  /// The trip to edit; null to post a new one.
  final DriverTrip? trip;

  @override
  ConsumerState<PostTripScreen> createState() => _PostTripScreenState();
}

class _PostTripScreenState extends ConsumerState<PostTripScreen> {
  final _price = TextEditingController();
  final _extraBag = TextEditingController();
  final _notes = TextEditingController();
  final _parcelSmall = TextEditingController();
  final _parcelMedium = TextEditingController();
  final _parcelLarge = TextEditingController();
  final List<TextEditingController> _pickups = [];
  final List<TextEditingController> _dropoffs = [];

  City? _from;
  City? _to;
  int? _vehicleId;
  DateTime? _date;
  TimeOfDay? _time;
  int? _seats;
  int _large = 1;
  int _small = 1;
  bool _parcels = false;
  int _maxParcels = 3;
  bool _womenOnly = false;
  bool _busy = false;
  Map<String, String> _errors = {};

  bool get _editing => widget.trip != null;

  @override
  void initState() {
    super.initState();
    final trip = widget.trip;

    if (trip == null) {
      _from = ref.read(currentUserProvider)?.city;
      _pickups.add(TextEditingController());
      _dropoffs.add(TextEditingController());
      return;
    }

    _from = City(id: trip.originId, name: trip.originName);
    _to = City(id: trip.destinationId, name: trip.destinationName);
    _vehicleId = trip.vehicleId;
    _date = DateTime(trip.departsAt.year, trip.departsAt.month, trip.departsAt.day);
    _time = TimeOfDay(hour: trip.departsAt.hour, minute: trip.departsAt.minute);
    _seats = trip.seatsTotal;
    _price.text = '${trip.pricePerSeat}';
    _large = trip.luggageLarge;
    _small = trip.luggageSmall;
    _extraBag.text = trip.extraBagPrice?.toString() ?? '';
    _parcels = trip.acceptsParcels;
    _parcelSmall.text = trip.parcelPrices['small']?.toString() ?? '';
    _parcelMedium.text = trip.parcelPrices['medium']?.toString() ?? '';
    _parcelLarge.text = trip.parcelPrices['large']?.toString() ?? '';
    _maxParcels = trip.maxParcels == 0 ? 3 : trip.maxParcels;
    _womenOnly = trip.womenOnly;
    _notes.text = trip.notes ?? '';
    _pickups.addAll(trip.pickupPoints.map((label) => TextEditingController(text: label)));
    _dropoffs.addAll(trip.dropoffPoints.map((label) => TextEditingController(text: label)));
    if (_pickups.isEmpty) _pickups.add(TextEditingController());
    if (_dropoffs.isEmpty) _dropoffs.add(TextEditingController());
  }

  @override
  void dispose() {
    for (final c in [_price, _extraBag, _notes, _parcelSmall, _parcelMedium, _parcelLarge, ..._pickups, ..._dropoffs]) {
      c.dispose();
    }
    super.dispose();
  }

  int? _number(TextEditingController c) => int.tryParse(Phone.westernDigits(c.text.trim()));

  List<String> _labels(List<TextEditingController> list) =>
      list.map((c) => c.text.trim()).where((label) => label.isNotEmpty).toList();

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final picked = await showDatePicker(
      context: context,
      initialDate: (_date != null && !_date!.isBefore(today)) ? _date! : today,
      firstDate: today,
      lastDate: today.add(const Duration(days: 60)),
    );
    if (picked != null) setState(() => _date = picked);
  }

  Future<void> _pickTime() async {
    final picked = await showTimePicker(context: context, initialTime: _time ?? const TimeOfDay(hour: 7, minute: 0));
    if (picked != null) setState(() => _time = picked);
  }

  Future<void> _submit(Vehicle vehicle, int seats) async {
    FocusScope.of(context).unfocus();
    final errors = <String, String>{};
    final price = _number(_price);
    final pickups = _labels(_pickups);
    final dropoffs = _labels(_dropoffs);
    final parcelPrices = {
      'small': _number(_parcelSmall),
      'medium': _number(_parcelMedium),
      'large': _number(_parcelLarge),
    };

    if (_from == null || _to == null) {
      errors['route'] = S.chooseBothCities;
    } else if (_from == _to) {
      errors['route'] = S.sameCity;
    }
    if (_date == null) errors['departs_at'] = S.fieldRequired;
    if (_time == null) errors['departs_at'] = S.timeRequired;
    if (price == null || price < 1) errors['price_per_seat'] = S.priceRequired;
    if (pickups.isEmpty) errors['pickup_points'] = S.pointRequired;
    if (dropoffs.isEmpty) errors['dropoff_points'] = S.pointRequired;
    if (_parcels && parcelPrices.values.any((p) => p == null || p < 1)) errors['parcels'] = S.parcelPricesRequired;

    setState(() => _errors = errors);
    if (errors.isNotEmpty) {
      showMessage(context, errors.values.first);
      return;
    }

    final draft = TripDraft(
      originCityId: _from!.id,
      destinationCityId: _to!.id,
      vehicleId: vehicle.id,
      departsAt: DateTime(_date!.year, _date!.month, _date!.day, _time!.hour, _time!.minute),
      seatsTotal: seats,
      pricePerSeat: price!,
      pickupPoints: pickups,
      dropoffPoints: dropoffs,
      luggageLarge: _large,
      luggageSmall: _small,
      extraBagPrice: _number(_extraBag),
      acceptsParcels: _parcels,
      parcelPrices: {
        for (final entry in parcelPrices.entries)
          if (entry.value != null) entry.key: entry.value!,
      },
      maxParcels: _maxParcels,
      womenOnly: _womenOnly,
      notes: _notes.text,
    );

    setState(() => _busy = true);
    try {
      final repo = ref.read(driverTripRepositoryProvider);
      final saved = _editing ? await repo.update(widget.trip!.id, draft) : await repo.create(draft);
      ref.invalidate(driverTripsProvider);
      ref.invalidate(driverTripProvider(saved.id));
      if (!mounted) return;
      showMessage(context, _editing ? S.tripUpdated : S.tripPublished);
      if (_editing) {
        context.pop();
      } else {
        context.pushReplacement('/driver/trips/${saved.id}');
      }
    } on ApiException catch (e) {
      if (!mounted) return;
      // Price above the route's maximum, time too soon, account frozen… the server says which.
      setState(() => _errors = e.fieldErrors);
      showMessage(context, e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final account = ref.watch(driverAccountProvider);

    return Scaffold(
      backgroundColor: AppColors.surface,
      appBar: AppBar(
        title: Text(_editing ? S.editTrip : S.postTrip, style: text.titleLarge),
        backgroundColor: AppColors.surface,
        scrolledUnderElevation: 0,
      ),
      body: account.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => LoadError(error: error, onRetry: () => ref.invalidate(driverAccountProvider)),
        data: (account) {
          final vehicles = (account?.vehicles ?? const <Vehicle>[]).where((v) => v.isApproved).toList();
          if (vehicles.isEmpty) {
            return const EmptyState(icon: Icons.directions_car_filled_outlined, title: S.noApprovedVehicle, body: '');
          }

          final vehicle = vehicles.firstWhere((v) => v.id == _vehicleId, orElse: () => vehicles.first);
          final seats = (_seats ?? vehicle.seats).clamp(1, vehicle.seats).toInt();

          Widget section(String title) => Padding(
                padding: const EdgeInsets.only(top: 24, bottom: 10),
                child: Text(title, style: text.titleMedium),
              );

          Widget error(String key) => _errors[key] == null
              ? const SizedBox.shrink()
              : Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Text(_errors[key]!, style: const TextStyle(color: AppColors.danger, fontSize: 13.5)),
                );

          Widget numberField(TextEditingController controller, String label, {String? errorKey}) => TextField(
                controller: controller,
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9٠-٩]'))],
                decoration: AppTheme.input(label: label, errorText: errorKey == null ? null : _errors[errorKey]),
              );

          return SafeArea(
            child: Column(
              children: [
                Expanded(
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
                    children: [
                      // A posted trip keeps its route; to change it, cancel and post again.
                      IgnorePointer(
                        ignoring: _editing,
                        child: Opacity(
                          opacity: _editing ? 0.6 : 1,
                          child: Column(
                            children: [
                              CityField(
                                label: S.from,
                                value: _from,
                                exclude: _to,
                                pickerTitle: S.from,
                                onChanged: (city) => setState(() => _from = city),
                              ),
                              const SizedBox(height: 14),
                              CityField(
                                label: S.to,
                                value: _to,
                                exclude: _from,
                                pickerTitle: S.to,
                                onChanged: (city) => setState(() => _to = city),
                              ),
                            ],
                          ),
                        ),
                      ),
                      error('route'),
                      const SizedBox(height: 14),
                      Row(
                        children: [
                          Expanded(
                            child: _PickerField(
                              label: S.departureDate,
                              value: _date == null ? null : Fmt.dayMonth(_date!),
                              icon: Icons.calendar_today_outlined,
                              onTap: _pickDate,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: _PickerField(
                              label: S.departureTime,
                              value: _time == null ? null : Fmt.time(DateTime(2000, 1, 1, _time!.hour, _time!.minute)),
                              icon: Icons.schedule_rounded,
                              onTap: _pickTime,
                            ),
                          ),
                        ],
                      ),
                      error('departs_at'),
                      if (vehicles.length > 1) ...[
                        section(S.tripVehicle),
                        Wrap(
                          spacing: 8,
                          runSpacing: 4,
                          children: [
                            for (final v in vehicles)
                              ChoiceChip(
                                label: Text('${v.name} — ${v.plateNumber}'),
                                selected: v.id == vehicle.id,
                                onSelected: (_) => setState(() {
                                  _vehicleId = v.id;
                                  _seats = null;
                                }),
                                showCheckmark: false,
                                selectedColor: AppColors.greenTint,
                                backgroundColor: AppColors.surface,
                                side: BorderSide(color: v.id == vehicle.id ? AppColors.green : AppColors.line),
                                labelStyle: TextStyle(
                                  fontFamily: AppFonts.body,
                                  fontSize: 14.5,
                                  fontWeight: FontWeight.w700,
                                  color: v.id == vehicle.id ? AppColors.green : AppColors.inkMuted,
                                ),
                              ),
                          ],
                        ),
                      ] else ...[
                        const SizedBox(height: 14),
                        Text('${S.tripVehicle}: ${vehicle.typeLabel} — ${vehicle.name} — ${vehicle.plateNumber}', style: text.bodySmall),
                      ],
                      const SizedBox(height: 18),
                      _Counter(
                        label: S.seatsOffered,
                        value: seats,
                        min: 1,
                        max: vehicle.seats,
                        onChanged: (value) => setState(() => _seats = value),
                      ),
                      const SizedBox(height: 16),
                      numberField(_price, S.pricePerSeat, errorKey: 'price_per_seat'),
                      section(S.pickupPoints),
                      Text(S.pickupPointsHelp, style: text.bodySmall),
                      const SizedBox(height: 8),
                      _PointsEditor(controllers: _pickups, onChanged: () => setState(() {})),
                      error('pickup_points'),
                      section(S.dropoffPoints),
                      Text(S.dropoffPointsHelp, style: text.bodySmall),
                      const SizedBox(height: 8),
                      _PointsEditor(controllers: _dropoffs, onChanged: () => setState(() {})),
                      error('dropoff_points'),
                      section(S.luggagePerPassenger),
                      _Counter(label: S.largeBags, value: _large, min: 0, max: 5, onChanged: (v) => setState(() => _large = v)),
                      const SizedBox(height: 10),
                      _Counter(label: S.smallBags, value: _small, min: 0, max: 5, onChanged: (v) => setState(() => _small = v)),
                      const SizedBox(height: 14),
                      numberField(_extraBag, S.extraBagPriceLabel),
                      const SizedBox(height: 10),
                      SwitchListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text(S.acceptParcelsSwitch, style: text.titleSmall),
                        value: _parcels,
                        onChanged: (value) => setState(() => _parcels = value),
                      ),
                      if (_parcels) ...[
                        Text(S.parcelPricesLabel, style: text.bodySmall),
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            Expanded(child: numberField(_parcelSmall, S.priceSmall)),
                            const SizedBox(width: 8),
                            Expanded(child: numberField(_parcelMedium, S.priceMedium)),
                            const SizedBox(width: 8),
                            Expanded(child: numberField(_parcelLarge, S.priceLarge)),
                          ],
                        ),
                        error('parcels'),
                        const SizedBox(height: 12),
                        _Counter(
                          label: S.maxParcelsLabel,
                          value: _maxParcels,
                          min: 1,
                          max: 50,
                          onChanged: (v) => setState(() => _maxParcels = v),
                        ),
                      ],
                      SwitchListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text(S.womenOnlySwitch, style: text.titleSmall),
                        value: _womenOnly,
                        onChanged: (value) => setState(() => _womenOnly = value),
                      ),
                      const SizedBox(height: 6),
                      TextField(
                        controller: _notes,
                        maxLines: 3,
                        maxLength: 1000,
                        decoration: AppTheme.input(hint: S.tripNotes).copyWith(counterText: ''),
                      ),
                    ],
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
                  child: PrimaryButton(
                    label: _editing ? S.saveChanges : S.publishTrip,
                    busy: _busy,
                    onPressed: () => _submit(vehicle, seats),
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

/// A read-only field that opens a picker (date, time).
class _PickerField extends StatelessWidget {
  const _PickerField({required this.label, required this.value, required this.icon, required this.onTap});

  final String label;
  final String? value;
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppTheme.radius),
      child: InputDecorator(
        isEmpty: value == null,
        decoration: AppTheme.input(label: label, suffixIcon: Icon(icon, size: 20, color: AppColors.green)),
        child: value == null
            ? null
            : Text(
                value!,
                style: const TextStyle(fontSize: 15.5, fontWeight: FontWeight.w500, color: AppColors.ink),
                overflow: TextOverflow.ellipsis,
              ),
      ),
    );
  }
}

class _Counter extends StatelessWidget {
  const _Counter({required this.label, required this.value, required this.min, required this.max, required this.onChanged});

  final String label;
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
        Expanded(child: Text(label, style: text.titleSmall)),
        step(Icons.remove_rounded, value > min ? () => onChanged(value - 1) : null),
        SizedBox(width: 44, child: Text('$value', style: text.titleLarge, textAlign: TextAlign.center)),
        step(Icons.add_rounded, value < max ? () => onChanged(value + 1) : null),
      ],
    );
  }
}

/// A short list of place names with add / remove.
class _PointsEditor extends StatelessWidget {
  const _PointsEditor({required this.controllers, required this.onChanged});

  static const _max = 10;

  final List<TextEditingController> controllers;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (var i = 0; i < controllers.length; i++)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: controllers[i],
                    maxLength: 150,
                    textInputAction: TextInputAction.next,
                    decoration: AppTheme.input(hint: S.pointHint).copyWith(counterText: ''),
                  ),
                ),
                if (controllers.length > 1)
                  IconButton(
                    tooltip: S.delete,
                    onPressed: () {
                      controllers.removeAt(i).dispose();
                      onChanged();
                    },
                    icon: const Icon(Icons.close_rounded, color: AppColors.inkMuted),
                  ),
              ],
            ),
          ),
        if (controllers.length < _max)
          TextButton.icon(
            onPressed: () {
              controllers.add(TextEditingController());
              onChanged();
            },
            icon: const Icon(Icons.add_rounded, size: 20),
            label: const Text(S.addPoint),
          ),
      ],
    );
  }
}
