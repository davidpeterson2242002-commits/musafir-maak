import 'package:flutter/material.dart';

/// The "Night Road" palette (brand identity v1, Nov 2026): navy like the road
/// at night, amber like its lights. Everything else stays quiet.
class AppColors {
  const AppColors._();

  /// Night navy — app bar, road signs, headings, selected states, outlines.
  static const Color navy = Color(0xFF15233F);
  static const Color navyDark = Color(0xFF0E1830);
  static const Color navyTint = Color(0xFFE2E7F0);

  /// Road amber — the ONE main button per screen, the route line, the
  /// companion line in the logo, small highlights. Never for text on light
  /// backgrounds (1.7:1); navy text on amber is fine (8.2:1).
  static const Color amber = Color(0xFFF2B01E);
  static const Color amberTint = Color(0xFFFCEFCB);

  /// Asphalt — the road in illustrations.
  static const Color asphalt = Color(0xFF263148);

  static const Color ink = Color(0xFF0B1220);
  static const Color inkMuted = Color(0xFF5A6072);
  static const Color line = Color(0xFFDEDAD2);
  static const Color background = Color(0xFFF4F2EE);
  static const Color surface = Color(0xFFFFFFFF);

  static const Color danger = Color(0xFFB9382C);
  static const Color dangerTint = Color(0xFFF8E5E2);
  static const Color success = Color(0xFF1E7F55);
}
