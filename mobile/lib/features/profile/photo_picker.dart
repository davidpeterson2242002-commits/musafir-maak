import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/strings.dart';
import '../../core/theme/app_colors.dart';

/// Lets the user take or choose a profile photo. The image is shrunk and
/// re-compressed on the phone first (≈100–200 KB) so the upload is quick on
/// mobile data. Returns the file path, or null if cancelled.
Future<String?> pickProfilePhoto(BuildContext context, {bool preferFrontCamera = true}) async {
  final source = await showModalBottomSheet<ImageSource>(
    context: context,
    backgroundColor: AppColors.surface,
    showDragHandle: true,
    builder: (context) => SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          ListTile(
            leading: const Icon(Icons.photo_camera_outlined, color: AppColors.green),
            title: const Text(S.photoFromCamera),
            onTap: () => Navigator.of(context).pop(ImageSource.camera),
          ),
          ListTile(
            leading: const Icon(Icons.photo_library_outlined, color: AppColors.green),
            title: const Text(S.photoFromGallery),
            onTap: () => Navigator.of(context).pop(ImageSource.gallery),
          ),
          const SizedBox(height: 12),
        ],
      ),
    ),
  );
  if (source == null) return null;

  try {
    final file = await ImagePicker().pickImage(
      source: source,
      maxWidth: 1080,
      maxHeight: 1080,
      imageQuality: 78,
      preferredCameraDevice: preferFrontCamera ? CameraDevice.front : CameraDevice.rear,
    );
    return file?.path;
  } catch (_) {
    return null; // permission denied or no camera: nothing was picked
  }
}
