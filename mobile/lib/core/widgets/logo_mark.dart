import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

/// The brand mark (visual identity v1): an open ring (departure), the road,
/// a solid dot (destination) and the companion line beside it.
/// On navy: white with an amber companion line. On light backgrounds: one colour.
class LogoMark extends StatelessWidget {
  const LogoMark({super.key, this.size = 96, this.color = Colors.white, this.companionColor = AppColors.amber});

  final double size;
  final Color color;
  final Color companionColor;

  @override
  Widget build(BuildContext context) {
    return SizedBox.square(
      dimension: size,
      child: CustomPaint(painter: _LogoMarkPainter(color, companionColor)),
    );
  }
}

class _LogoMarkPainter extends CustomPainter {
  _LogoMarkPainter(this.color, this.companionColor);

  final Color color;
  final Color companionColor;

  @override
  void paint(Canvas canvas, Size size) {
    // Drawn on a 100 x 100 grid, the same as the SVG logo.
    canvas.scale(size.width / 100, size.height / 100);
    final stroke = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 10
      ..strokeCap = StrokeCap.round
      ..color = color;

    final road = Path()
      ..moveTo(22, 71)
      ..cubicTo(22, 55, 32, 52, 50, 48)
      ..cubicTo(68, 44, 78, 42, 78, 22);
    canvas.drawPath(road, stroke);
    final companion = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 10
      ..strokeCap = StrokeCap.round
      ..color = companionColor;
    canvas.drawLine(const Offset(78, 56), const Offset(78, 86), companion);
    final ring = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 6
      ..color = color;
    canvas.drawCircle(const Offset(22, 81), 8, ring);
    canvas.drawCircle(const Offset(78, 19), 11, Paint()..color = color);
  }

  @override
  bool shouldRepaint(_LogoMarkPainter old) => old.color != color || old.companionColor != companionColor;
}
