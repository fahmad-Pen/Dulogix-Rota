# IONOS Web Hosting: private Poppler `pdftotext` setup

## Purpose

Dulogix Performance PDF imports prefer Poppler `pdftotext -layout -nopgbrk` so DSP Weekly Summary and POD reports are extracted with the same command-line options in local Docker and IONOS production. The Composer `smalot/pdfparser` fallback remains available when Poppler cannot be run; its text layout may differ.

**Verified on IONOS SSH (3 November 2026):** ARM64 (`aarch64`), Debian glibc 2.36, home `/home/www`, shell execution allowed, `pdftotext version 22.12.0` ran successfully with `LD_LIBRARY_PATH`. **Not yet verified:** execution from the production PHP web process or an actual DSP PDF import.

## Local and production paths

| Environment | Executable | Libraries |
| --- | --- | --- |
| Docker (local) | `/usr/bin/pdftotext` (installed by `Dockerfile`) | System libraries |
| IONOS shared hosting | `/home/www/poppler-local/usr/bin/pdftotext` | `/home/www/poppler-local/usr/lib/aarch64-linux-gnu` |

`src/Controller/PerformanceReportReader.php` checks optional environment variables `PDFTOTEXT_BINARY` and `PDFTOTEXT_LIBRARY_PATH`, then the IONOS private path and common system paths. For the known IONOS path, it automatically scopes `LD_LIBRARY_PATH` to the subprocess. PDF imports retain the pure-PHP fallback if command-line extraction fails. No root or system-wide package installation is required.

## Reproduce the IONOS installation

Connect via IONOS SSH. **Only use these Debian 12 ARM64 package details on a compatible ARM64 host.** The server reported `uname -m = aarch64` and `ldd --version = 2.36`.

1. Create private directories and download the official Debian 12 ARM64 package index:

```bash
mkdir -p "$HOME/poppler-install" "$HOME/poppler-local"
cd "$HOME/poppler-install"
curl -fsSL https://deb.debian.org/debian/dists/bookworm/main/binary-arm64/Packages.xz -o Packages.xz
```

2. Download, SHA256-verify, and extract **`poppler-utils`, `libpoppler126`, `libnspr4`, and `libnss3`** from the indexed Debian repository. The script below resolves the current filenames and checksums from the downloaded index rather than guessing versioned URLs:

```bash
cd "$HOME/poppler-install"
python3 - <<'PY'
import hashlib
import lzma
import subprocess
import urllib.request
from pathlib import Path

index = lzma.open("Packages.xz", "rt").read()
wanted = ("poppler-utils", "libpoppler126", "libnspr4", "libnss3")
packages = {}
for block in index.split("\n\n"):
    fields = {}
    for line in block.splitlines():
        if line and not line[0].isspace() and ": " in line:
            key, value = line.split(": ", 1)
            fields[key] = value
    if fields.get("Package") in wanted and fields.get("Architecture") == "arm64":
        packages[fields["Package"]] = fields

for name in wanted:
    if name not in packages:
        raise RuntimeError("Package missing from index: " + name)
    entry = packages[name]
    dest = Path(entry["Filename"]).name
    url = "https://deb.debian.org/debian/" + entry["Filename"]
    urllib.request.urlretrieve(url, dest)
    actual = hashlib.sha256(Path(dest).read_bytes()).hexdigest()
    if actual != entry["SHA256"]:
        raise RuntimeError("SHA256 mismatch: " + name)
    subprocess.run(["dpkg-deb", "-x", dest, str(Path.home() / "poppler-local")], check=True)
    print("Verified and extracted:", name)
PY
```

The actual successful installation used `poppler-utils 22.12.0-2+deb12u2` and `libpoppler126 22.12.0-2+deb12u2`. These two packages must have matching versions. Package-index updates may require confirming their versions are still compatible.

3. Check missing libraries and execute:

```bash
LD_LIBRARY_PATH="$HOME/poppler-local/usr/lib/aarch64-linux-gnu" \
  ldd "$HOME/poppler-local/usr/bin/pdftotext" | grep 'not found' || true

LD_LIBRARY_PATH="$HOME/poppler-local/usr/lib/aarch64-linux-gnu" \
  "$HOME/poppler-local/usr/bin/pdftotext" -v
```

The expected working version at initial setup was `22.12.0`. If `ldd` reports other missing dependencies, resolve them before use. On this host the initially missing files were `libnss3.so`, `libsmime3.so`, `libplc4.so`, and `libnspr4.so`; `libnss3` and `libnspr4` supplied them.

## Verification after deploying Dulogix

1. Deploy the updated `PerformanceReportReader.php` through the normal release workflow; the private binary and libraries are **not in Git** and must remain installed in the hosting account.
2. Confirm the web PHP process can execute the private binary and access the libraries. **CLI `php -r` and SSH tests do not prove web PHP permissions or environment.** Avoid publicly accessible diagnostics; remove any temporary diagnostic script immediately after use.
3. Upload the actual Week 43 DSP Weekly Summary PDF and a POD PDF; verify complete, correct row counts and fields before trusting the import. The Week 43 sample contains 95 driver rows. Do not treat a successful `-v` check as an import test.
4. If the PDF importer falls back to Smalot or reports a table-format error, inspect the web PHP execution permissions, binary path, and library loading first. Do not silently accept partial driver imports.

## Troubleshooting

- `libpoppler.so.126: cannot open shared object file`: ensure `libpoppler126` was extracted and `LD_LIBRARY_PATH` points to the ARM64 library directory.
- `libnss3.so`, `libsmime3.so`, `libplc4.so`, or `libnspr4.so` missing: extract the Debian ARM64 `libnss3` and `libnspr4` packages.
- `Exec format error`: check `uname -m` and package architecture; this installation is ARM64, not x86-64.
- Works over SSH but not from website: investigate web PHP's `disable_functions`, filesystem permissions, process environment, and hosting restrictions. The reader silently attempts the PHP parser if Poppler fails.
- Different extraction across environments: compare `pdftotext -v`; local Alpine's Poppler version may differ from Debian 12's `22.12.0`, even with identical flags.

Do not commit downloaded Debian packages, extracted shared libraries, uploaded driver PDFs, or temporary diagnostic scripts.
