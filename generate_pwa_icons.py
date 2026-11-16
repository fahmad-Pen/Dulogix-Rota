from pathlib import Path
import struct, zlib
root = Path(__file__).resolve().parent / 'public'
src = (root / 'favicon.png').read_bytes()
assert src[:8] == b'\x89PNG\r\n\x1a\n'
pos = 8
payload = bytearray()
while pos < len(src):
    length = int.from_bytes(src[pos:pos+4], 'big')
    typ = src[pos+4:pos+8]
    data = src[pos+8:pos+8+length]
    pos += length + 12
    if typ == b'IHDR':
        width, height, depth, color, comp, filt, interlace = struct.unpack('>IIBBBBB', data)
    if typ == b'IDAT':
        payload.extend(data)
assert depth == 8 and color == 6 and interlace == 0
raw = zlib.decompress(payload)
stride = width * 4
rows = []
prev = bytearray(stride)
off = 0
for _ in range(height):
    mode = raw[off]
    off += 1
    scan = bytearray(raw[off:off+stride])
    off += stride
    for i in range(stride):
        a = scan[i-4] if i >= 4 else 0
        b = prev[i]
        c = prev[i-4] if i >= 4 else 0
        if mode == 1: scan[i] = (scan[i] + a) & 255
        elif mode == 2: scan[i] = (scan[i] + b) & 255
        elif mode == 3: scan[i] = (scan[i] + ((a+b)//2)) & 255
        elif mode == 4:
            p = a+b-c
            v = min((a,b,c), key=lambda x: abs(p-x))
            scan[i] = (scan[i]+v) & 255
        elif mode != 0: raise ValueError('PNG filter')
    rows.append(scan)
    prev = scan

def chunk(typ, data):
    return struct.pack('>I', len(data)) + typ + data + struct.pack('>I', zlib.crc32(typ+data)&0xffffffff)

for size in (192, 512):
    output = bytearray()
    for y in range(size):
        row = rows[y*height//size]
        output.append(0)
        for x in range(size):
            i = (x*width//size)*4
            output.extend(row[i:i+4])
    ihdr = struct.pack('>IIBBBBB', size, size, 8, 6, 0, 0, 0)
    png = src[:8] + chunk(b'IHDR', ihdr) + chunk(b'IDAT', zlib.compress(output, 9)) + chunk(b'IEND', b'')
    (root / f'pwa-icon-{size}.png').write_bytes(png)
