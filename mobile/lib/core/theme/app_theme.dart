import 'package:flutter/material.dart';

import 'app_colors.dart';

/// Font families bundled in assets/fonts (see pubspec.yaml).
class AppFonts {
  const AppFonts._();

  static const String body = 'Tajawal';
  static const String display = 'Lalezar';
}

class AppTheme {
  const AppTheme._();

  static const double radius = 14;
  static const double buttonHeight = 54;
  static const EdgeInsets screenPadding = EdgeInsets.symmetric(horizontal: 20);

  static ThemeData light() {
    final scheme = ColorScheme.fromSeed(
      seedColor: AppColors.green,
      brightness: Brightness.light,
    ).copyWith(
      primary: AppColors.green,
      onPrimary: Colors.white,
      secondary: AppColors.amber,
      onSecondary: AppColors.ink,
      surface: AppColors.surface,
      onSurface: AppColors.ink,
      error: AppColors.danger,
      onError: Colors.white,
      outline: AppColors.line,
    );

    final buttonShape = RoundedRectangleBorder(borderRadius: BorderRadius.circular(radius));
    const buttonText = TextStyle(fontFamily: AppFonts.body, fontSize: 17, fontWeight: FontWeight.w700);

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: AppColors.background,
      fontFamily: AppFonts.body,
      textTheme: _textTheme(),
      splashFactory: InkRipple.splashFactory,
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.green,
          foregroundColor: Colors.white,
          disabledBackgroundColor: AppColors.line,
          disabledForegroundColor: AppColors.inkMuted,
          minimumSize: const Size.fromHeight(buttonHeight),
          shape: buttonShape,
          textStyle: buttonText,
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.green,
          minimumSize: const Size.fromHeight(buttonHeight),
          side: const BorderSide(color: AppColors.green, width: 1.5),
          shape: buttonShape,
          textStyle: buttonText,
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: AppColors.green,
          textStyle: buttonText.copyWith(fontSize: 15),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: AppColors.surface,
        indicatorColor: AppColors.greenTint,
        elevation: 0,
        height: 68,
        labelTextStyle: WidgetStateProperty.resolveWith(
          (states) => TextStyle(
            fontFamily: AppFonts.body,
            fontSize: 12.5,
            fontWeight: states.contains(WidgetState.selected) ? FontWeight.w700 : FontWeight.w500,
            color: states.contains(WidgetState.selected) ? AppColors.green : AppColors.inkMuted,
          ),
        ),
        iconTheme: WidgetStateProperty.resolveWith(
          (states) => IconThemeData(
            color: states.contains(WidgetState.selected) ? AppColors.green : AppColors.inkMuted,
          ),
        ),
      ),
      snackBarTheme: const SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: AppColors.ink,
        contentTextStyle: TextStyle(fontFamily: AppFonts.body, fontSize: 15, color: Colors.white),
      ),
      dividerTheme: const DividerThemeData(color: AppColors.line, thickness: 1, space: 1),
    );
  }

  static TextTheme _textTheme() {
    const ink = AppColors.ink;

    return const TextTheme(
      // Display: Lalezar, for the app name and screen titles only.
      displaySmall: TextStyle(fontFamily: AppFonts.display, fontSize: 34, height: 1.25, color: ink),
      headlineMedium: TextStyle(fontFamily: AppFonts.display, fontSize: 28, height: 1.3, color: ink),
      headlineSmall: TextStyle(fontFamily: AppFonts.display, fontSize: 23, height: 1.3, color: ink),
      // Everything else: Tajawal.
      titleLarge: TextStyle(fontSize: 20, fontWeight: FontWeight.w700, height: 1.4, color: ink),
      titleMedium: TextStyle(fontSize: 17, fontWeight: FontWeight.w700, height: 1.4, color: ink),
      titleSmall: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, height: 1.4, color: ink),
      bodyLarge: TextStyle(fontSize: 17, fontWeight: FontWeight.w400, height: 1.6, color: ink),
      bodyMedium: TextStyle(fontSize: 15.5, fontWeight: FontWeight.w400, height: 1.6, color: ink),
      bodySmall: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w400, height: 1.5, color: AppColors.inkMuted),
      labelLarge: TextStyle(fontSize: 15.5, fontWeight: FontWeight.w500, height: 1.3, color: ink),
      labelMedium: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w500, height: 1.3, color: AppColors.inkMuted),
    );
  }

  /// Decoration shared by every text field (kept here instead of a theme-level
  /// input decoration so it works unchanged across Flutter versions).
  static InputDecoration input({
    String? label,
    String? hint,
    String? errorText,
    Widget? prefixIcon,
    Widget? suffixIcon,
    String? helperText,
  }) {
    OutlineInputBorder border(Color color, [double width = 1]) => OutlineInputBorder(
          borderRadius: BorderRadius.circular(radius),
          borderSide: BorderSide(color: color, width: width),
        );

    return InputDecoration(
      labelText: label,
      hintText: hint,
      errorText: errorText,
      helperText: helperText,
      errorMaxLines: 3,
      helperMaxLines: 3,
      prefixIcon: prefixIcon,
      suffixIcon: suffixIcon,
      filled: true,
      fillColor: AppColors.surface,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      labelStyle: const TextStyle(color: AppColors.inkMuted, fontSize: 15.5),
      hintStyle: const TextStyle(color: AppColors.inkMuted, fontSize: 15.5),
      border: border(AppColors.line),
      enabledBorder: border(AppColors.line),
      focusedBorder: border(AppColors.green, 2),
      errorBorder: border(AppColors.danger),
      focusedErrorBorder: border(AppColors.danger, 2),
    );
  }
}
