from pathlib import Path
import struct
p = Path(__file__).resolve().parent / 'public'
b = (p / 'favicon.png').read_bytes()
(p / 'favicon.ico').write_bytes(struct.pack('<HHH', 0, 1, 1) + struct.pack('<BBBBHHII', 32, 32, 0, 0, 1, 32, len(b), 22) + b)
for name in ('apple-touch-icon.png', 'apple-touch-icon-precomposed.png'):
    (p / name).write_bytes(b)
