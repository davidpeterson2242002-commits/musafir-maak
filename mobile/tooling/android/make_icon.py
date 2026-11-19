# The launcher icons in ./icon and ic_stat_notify.xml are pre-rendered from the
# brand logo (visual identity v1, Nov 2026 — "Night Road": navy #15233F, amber #F2B01E).
#
# Logo mark, viewBox 0 0 100 100 (white on navy for the icon, companion line amber):
#   <path d="M22 71 C22 55 32 52 50 48 S78 42 78 22" fill="none" stroke-width="10" stroke-linecap="round"/>
#   <path d="M78 56 L78 86" fill="none" stroke-width="10" stroke-linecap="round"/>   (amber)
#   <circle cx="22" cy="81" r="8" fill="none" stroke-width="6"/>
#   <circle cx="78" cy="19" r="11"/>
#
# Adaptive icon: 108dp foreground, mark box 56dp centred (inside the 66dp safe circle),
# background colour #15233F (icon/values/ic_launcher_background.xml).
# Legacy icon: navy rounded square (22% radius) with the same mark.
# To regenerate, render the SVG above at each density (mdpi 1x … xxxhdpi 4x).
