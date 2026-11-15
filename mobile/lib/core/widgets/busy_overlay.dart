import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import '../strings.dart';
import '../theme/app_colors.dart';

/// Covers the app with a light barrier and a spinner while any user action is
/// talking to the server ([busy] > 0), so a tap is never met with silence and
/// can't be repeated by accident. Appears after a short delay so instant
/// actions don't flash.
class BusyOverlay extends StatefulWidget {
  const BusyOverlay({super.key, required this.busy, required this.child});

  final ValueListenable<int> busy;
  final Widget child;

  @override
  State<BusyOverlay> createState() => _BusyOverlayState();
}

class _BusyOverlayState extends State<BusyOverlay> {
  static const _delay = Duration(milliseconds: 150);
  static const _slowAfter = Duration(seconds: 6);

  Timer? _showTimer;
  Timer? _slowTimer;
  bool _visible = false;
  bool _slow = false;

  @override
  void initState() {
    super.initState();
    widget.busy.addListener(_changed);
  }

  @override
  void didUpdateWidget(BusyOverlay old) {
    super.didUpdateWidget(old);
    if (old.busy != widget.busy) {
      old.busy.removeListener(_changed);
      widget.busy.addListener(_changed);
    }
  }

  void _changed() {
    if (widget.busy.value > 0) {
      _showTimer ??= Timer(_delay, () => mounted ? setState(() => _visible = true) : null);
      _slowTimer ??= Timer(_slowAfter, () => mounted ? setState(() => _slow = true) : null);
    } else {
      _showTimer?.cancel();
      _slowTimer?.cancel();
      _showTimer = null;
      _slowTimer = null;
      if (_visible || _slow) setState(() => _visible = _slow = false);
    }
  }

  @override
  void dispose() {
    widget.busy.removeListener(_changed);
    _showTimer?.cancel();
    _slowTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        widget.child,
        if (_visible)
          Positioned.fill(
            child: AbsorbPointer(
              child: ColoredBox(
                color: Colors.black.withValues(alpha: 0.12),
                child: Center(
                  child: Material(
                    color: AppColors.surface,
                    elevation: 6,
                    borderRadius: BorderRadius.circular(16),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 18),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const SizedBox(
                            width: 24,
                            height: 24,
                            child: CircularProgressIndicator(strokeWidth: 2.6, color: AppColors.green),
                          ),
                          const SizedBox(width: 14),
                          Text(
                            _slow ? S.busySlow : S.busy,
                            style: Theme.of(context).textTheme.titleMedium,
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
      ],
    );
  }
}
