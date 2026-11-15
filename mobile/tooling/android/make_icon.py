# Generates the launcher icon: green highway sign with a white arrow and the amber dashed road line.
from PIL import Image, ImageDraw
import os, sys
out = sys.argv[1]
GREEN=(11,110,91,255); AMBER=(242,176,30,255); WHITE=(255,255,255,255); DARK=(7,78,64,255)

def foreground(size):
    # Adaptive icon foreground: 108dp canvas, content inside the 66dp safe zone.
    S=size*4
    im=Image.new('RGBA',(S,S),(0,0,0,0)); d=ImageDraw.Draw(im)
    c=S/2; u=S/108.0
    # road: amber dashes along the bottom-left → top-right diagonal, under the sign
    # sign plate (white border on green)
    w,h=50*u,38*u; x0,y0=c-w/2,c-h/2-5*u
    d.rounded_rectangle([x0,y0,x0+w,y0+h],radius=6*u,fill=WHITE)
    b=3*u
    d.rounded_rectangle([x0+b,y0+b,x0+w-b,y0+h-b],radius=4*u,fill=GREEN)
    # arrow pointing left (Arabic reading direction, toward the destination)
    ay=y0+h/2
    d.polygon([(x0+10*u,ay),(x0+22*u,ay-10*u),(x0+22*u,ay-4*u),(x0+w-10*u,ay-4*u),(x0+w-10*u,ay+4*u),(x0+22*u,ay+4*u),(x0+22*u,ay+10*u)],fill=WHITE)
    # post
    d.rectangle([c-2.5*u,y0+h,c+2.5*u,y0+h+11*u],fill=WHITE)
    # amber dashed road line at the bottom
    yy=y0+h+15*u
    for i in range(4):
        sx=c-21*u+i*12*u
        d.rounded_rectangle([sx,yy,sx+7*u,yy+3.4*u],radius=1.7*u,fill=AMBER)
    return im.resize((size,size),Image.LANCZOS)

def legacy(size):
    S=size*4
    bg=Image.new('RGBA',(S,S),(0,0,0,0)); d=ImageDraw.Draw(bg)
    d.rounded_rectangle([0,0,S-1,S-1],radius=S*0.22,fill=GREEN)
    fg=foreground(S)
    # legacy icons show the full 108dp canvas scaled into the square
    bg.alpha_composite(fg)
    return bg.resize((size,size),Image.LANCZOS)

dens={'mdpi':1,'hdpi':1.5,'xhdpi':2,'xxhdpi':3,'xxxhdpi':4}
for name,k in dens.items():
    dd=os.path.join(out,f'mipmap-{name}'); os.makedirs(dd,exist_ok=True)
    legacy(int(48*k)).save(os.path.join(dd,'ic_launcher.png'))
    foreground(int(108*k)).save(os.path.join(dd,'ic_launcher_foreground.png'))
os.makedirs(os.path.join(out,'mipmap-anydpi-v26'),exist_ok=True)
open(os.path.join(out,'mipmap-anydpi-v26','ic_launcher.xml'),'w').write('''<?xml version="1.0" encoding="utf-8"?>
<adaptive-icon xmlns:android="http://schemas.android.com/apk/res/android">
    <background android:drawable="@color/ic_launcher_background"/>
    <foreground android:drawable="@mipmap/ic_launcher_foreground"/>
</adaptive-icon>
''')
os.makedirs(os.path.join(out,'values'),exist_ok=True)
open(os.path.join(out,'values','ic_launcher_background.xml'),'w').write('<?xml version="1.0" encoding="utf-8"?>\n<resources><color name="ic_launcher_background">#0B6E5B</color></resources>\n')
legacy(512).save('/home/claude/icon_preview.png')
