import 'package:flutter/material.dart';

import '../strings.dart';
import '../theme/app_colors.dart';
import '../theme/app_theme.dart';

/// One required document photo: what to shoot and whether it's on the server yet.
class PhotoUploadTile extends StatelessWidget {
  const PhotoUploadTile({
    super.key,
    required this.title,
    required this.icon,
    required this.uploaded,
    required this.uploading,
    required this.onTap,
    this.help,
    this.enabled = true,
  });

  final String title;
  final String? help;
  final IconData icon;
  final bool uploaded;
  final bool uploading;
  final bool enabled;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final status = uploading ? S.uploading : (uploaded ? S.uploaded : help);

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: uploaded ? AppColors.greenTint : AppColors.surface,
        borderRadius: BorderRadius.circular(AppTheme.radius + 4),
        border: Border.all(color: uploaded ? AppColors.green : AppColors.line),
      ),
      child: Row(
        children: [
          Icon(uploaded ? Icons.check_circle_rounded : icon, color: AppColors.green, size: 28),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: text.titleSmall),
                if (status != null) Text(status, style: text.bodySmall),
              ],
            ),
          ),
          const SizedBox(width: 8),
          if (uploading)
            const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2.5, color: AppColors.green))
          else
            TextButton.icon(
              onPressed: enabled ? onTap : null,
              icon: const Icon(Icons.photo_camera_outlined, size: 20),
              label: Text(uploaded ? S.retakePhoto : S.takePhoto),
            ),
        ],
      ),
    );
  }
}
