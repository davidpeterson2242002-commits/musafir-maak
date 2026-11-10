import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_theme.dart';

/// A green highway signboard, as seen on Sudanese intercity roads: white
/// inset border, white lettering. The app's one signature element — used for
/// onboarding headings and route chips, nowhere else.
class RoadSign extends StatelessWidget {
  const RoadSign({super.key, required this.child, this.padding, this.compact = false});

  final Widget child;
  final EdgeInsetsGeometry? padding;

  /// Small version for chips (thinner border, tighter corners).
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final outer = compact ? 10.0 : 16.0;

    return Container(
      padding: EdgeInsets.all(compact ? 2.5 : 5),
      decoration: BoxDecoration(
        color: AppColors.green,
        borderRadius: BorderRadius.circular(outer),
      ),
      child: Container(
        padding: padding ?? EdgeInsets.symmetric(horizontal: compact ? 12 : 22, vertical: compact ? 7 : 18),
        decoration: BoxDecoration(
          border: Border.all(color: Colors.white, width: compact ? 1.2 : 2),
          borderRadius: BorderRadius.circular(outer - (compact ? 2.5 : 5)),
        ),
        child: DefaultTextStyle.merge(
          style: TextStyle(
            fontFamily: compact ? AppFonts.body : AppFonts.display,
            fontWeight: compact ? FontWeight.w700 : FontWeight.w400,
            fontSize: compact ? 14.5 : 30,
            height: 1.25,
            color: Colors.white,
          ),
          textAlign: TextAlign.center,
          child: child,
        ),
      ),
    );
  }
}

/// The signboard standing on two posts beside a strip of road. Sized by its parent.
class RoadScene extends StatelessWidget {
  const RoadScene({super.key, required this.sign, this.roadHeight = 56});

  final Widget sign;
  final double roadHeight;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisAlignment: MainAxisAlignment.end,
      children: [
        Padding(padding: const EdgeInsets.symmetric(horizontal: 36), child: sign),
        // Posts
        SizedBox(
          height: 46,
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(width: 7, color: AppColors.inkMuted),
              const SizedBox(width: 110),
              Container(width: 7, color: AppColors.inkMuted),
            ],
          ),
        ),
        SizedBox(
          height: roadHeight,
          width: double.infinity,
          child: const CustomPaint(painter: RoadPainter()),
        ),
      ],
    );
  }
}

/// Asphalt with an amber dashed centre line.
class RoadPainter extends CustomPainter {
  const RoadPainter();

  @override
  void paint(Canvas canvas, Size size) {
    canvas.drawRect(Offset.zero & size, Paint()..color = AppColors.asphalt);

    final dash = Paint()
      ..color = AppColors.amber
      ..strokeWidth = size.height * 0.09
      ..strokeCap = StrokeCap.butt;

    const dashWidth = 30.0;
    const gap = 22.0;
    final y = size.height / 2;
    for (double x = 10; x < size.width; x += dashWidth + gap) {
      canvas.drawLine(Offset(x, y), Offset(math.min(x + dashWidth, size.width), y), dash);
    }
  }

  @override
  bool shouldRepaint(covariant RoadPainter oldDelegate) => false;
}

/// The marker column beside the From / To fields: a ring at the origin, a
/// dashed line (the road), a filled pin at the destination.
class RouteMarker extends StatelessWidget {
  const RouteMarker({super.key, required this.height});

  final double height;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 18,
      height: height,
      child: const CustomPaint(painter: _RouteMarkerPainter()),
    );
  }
}

class _RouteMarkerPainter extends CustomPainter {
  const _RouteMarkerPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final cx = size.width / 2;
    const r = 6.0;
    final top = Offset(cx, size.height * 0.25);
    final bottom = Offset(cx, size.height * 0.75);

    final dash = Paint()
      ..color = AppColors.amber
      ..strokeWidth = 2.5;
    for (double y = top.dy + r + 4; y < bottom.dy - r - 4; y += 9) {
      canvas.drawLine(Offset(cx, y), Offset(cx, math.min(y + 5, bottom.dy - r - 2)), dash);
    }

    canvas.drawCircle(
      top,
      r - 1.25,
      Paint()
        ..color = AppColors.green
        ..style = PaintingStyle.stroke
        ..strokeWidth = 2.5,
    );
    canvas.drawCircle(bottom, r, Paint()..color = AppColors.green);
  }

  @override
  bool shouldRepaint(covariant _RouteMarkerPainter oldDelegate) => false;
}
