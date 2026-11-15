import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

/// Private photo links look like `…/files/{expires}/{sig}/{disk}/{path}`. The part after the
/// signature never changes for a photo, so it is the disk-cache key: a photo is downloaded once,
/// even though its link is re-signed every hour.
String imageCacheKey(String url) => RegExp(r'/files/\d+/[^/]+/(.+)$').firstMatch(url)?.group(1) ?? url;

/// The server's resized copy (160, 400 or 800 px wide) of a private photo.
String thumbUrl(String url, int width) => url.replaceFirstMapped(
      RegExp(r'(/files/\d+/[^/]+/)(?!w\d+/)'),
      (m) => '${m[1]}w$width/',
    );

/// Network photo with a disk cache and, when [thumb] is given, the server's small copy —
/// a car photo in a list is ~30 KB instead of ~300 KB on slow mobile data.
class AppImage extends StatelessWidget {
  const AppImage(
    this.url, {
    super.key,
    this.width,
    this.height,
    this.fit = BoxFit.cover,
    this.thumb,
    this.fallback,
  });

  final String url;
  final double? width;
  final double? height;
  final BoxFit fit;

  /// 160, 400 or 800: asks the server for a resized copy.
  final int? thumb;
  final Widget? fallback;

  @override
  Widget build(BuildContext context) {
    final src = thumb == null ? url : thumbUrl(url, thumb!);
    final empty = fallback ?? Container(width: width, height: height, color: AppColors.background);
    return CachedNetworkImage(
      imageUrl: src,
      cacheKey: imageCacheKey(src),
      width: width,
      height: height,
      fit: fit,
      memCacheWidth: thumb,
      fadeInDuration: const Duration(milliseconds: 150),
      placeholder: (_, __) => Container(width: width, height: height, color: AppColors.background),
      errorWidget: (_, __, ___) => empty,
    );
  }
}
