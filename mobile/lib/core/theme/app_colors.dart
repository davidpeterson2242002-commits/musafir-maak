import 'package:flutter/material.dart';

/// The palette. Green is the colour of Sudanese highway signboards; amber is
/// the road's centre line. Everything else stays quiet.
class AppColors {
  const AppColors._();

  /// Signboard green — primary actions, signs, selected states.
  static const Color green = Color(0xFF0B6E5B);
  static const Color greenDark = Color(0xFF084C40);
  static const Color greenTint = Color(0xFFE3F1EC);

  /// Road-marking amber — the route line and small highlights. Never for text on white.
  static const Color amber = Color(0xFFF2B01E);
  static const Color amberTint = Color(0xFFFDF3D7);

  /// Asphalt — the road in illustrations.
  static const Color asphalt = Color(0xFF2C3A38);

  static const Color ink = Color(0xFF16302B);
  static const Color inkMuted = Color(0xFF5C6F6A);
  static const Color line = Color(0xFFD8E2DE);
  static const Color background = Color(0xFFF3F6F4);
  static const Color surface = Color(0xFFFFFFFF);

  static const Color danger = Color(0xFFC2412D);
  static const Color dangerTint = Color(0xFFFBE9E5);
  static const Color success = Color(0xFF1E8E5A);
}
