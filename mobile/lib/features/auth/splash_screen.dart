import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/road.dart';
import 'session_controller.dart';

/// First frame: the app name over a strip of road while the saved session is
/// restored. The router moves on as soon as the session is known.
class SplashScreen extends ConsumerStatefulWidget {
  const SplashScreen({super.key});

  @override
  ConsumerState<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends ConsumerState<SplashScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() => ref.read(sessionProvider.notifier).restore());
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      backgroundColor: AppColors.green,
      body: Column(
        children: [
          Expanded(
            child: Center(
              child: Text(
                S.appName,
                style: TextStyle(fontFamily: AppFonts.display, fontSize: 52, height: 1.2, color: Colors.white),
              ),
            ),
          ),
          SizedBox(height: 64, width: double.infinity, child: CustomPaint(painter: RoadPainter())),
          SizedBox(height: 48),
        ],
      ),
    );
  }
}
