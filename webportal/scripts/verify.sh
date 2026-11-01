#!/usr/bin/env bash
#
# The gate every change goes through before it is called done.
#
# php -l is not enough and php artisan test is not enough, and both gaps were
# found the hard way in this project:
#
#   php -l passes a file whose class cannot be loaded. An incompatible method
#   declaration, a wrong visibility, a dropped variadic: none of those is a parse
#   error, so the lint is clean and the page is dead. Three of those happened here
#   and each took down every page of both panels at once.
#
#   php artisan test cannot report the fault either. The class is loaded while the
#   application boots, so the fatal happens before the first test runs and the
#   suite dies printing no "Tests:" line at all. A green suite and a crashed suite
#   look identical if you only read the exit status.
#
# So this script does the two things that do work, in the order that survives:
# load every class in a separate process first, where the fault is an exit code
# rather than a corpse, and only then run the suite and insist on seeing its
# summary line.
#
#   bash scripts/verify.sh
#
set -uo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

FAILED=0

# Dicatat lebih dulu supaya langkah terakhir hanya bisa melaporkan error yang
# benar-benar ditulis selama pemeriksaan ini.
LOG_LINES_BEFORE=$(wc -l < storage/logs/laravel.log 2>/dev/null || echo 0)
export LOG_LINES_BEFORE

step() { printf '\n\033[1m%s\033[0m\n' "$1"; }
ok() { printf '  \033[32mOK\033[0m   %s\n' "$1"; }
bad() { printf '  \033[31mGAGAL\033[0m %s\n' "$1"; FAILED=1; }

# ---------------------------------------------------------------- lint
step "1. Sintaks setiap berkas"
LINT_BAD=0
while IFS= read -r file; do
    case "$(php -l "$file" 2>&1)" in
        *"No syntax errors"*) ;;
        *) LINT_BAD=1; printf '       %s\n' "$(php -l "$file" 2>&1 | head -1)" ;;
    esac
done < <(find app bootstrap config database routes tests -name '*.php' -not -path '*/vendor/*')
[ "$LINT_BAD" -eq 0 ] && ok "$(find app bootstrap config database routes tests -name '*.php' | wc -l) berkas" \
                      || bad "ada berkas yang tidak bisa di-parse"

# ------------------------------------------------------- kelas bisa dimuat
# Dijalankan di proses terpisah, dan lebih dulu, karena kelas yang rusak membuat
# aplikasi tidak bisa boot sama sekali. Di dalam suite, prosesnya ikut mati
# sebelum tes pertama dan tidak ada yang bisa melaporkannya.
step "2. Setiap kelas di app/ bisa dimuat"
CLASSES=$(php -r '
$out = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("app"));
foreach ($it as $f) {
    if (! $f->isFile() || $f->getExtension() !== "php") { continue; }
    $rel = substr($f->getPathname(), 4, -4);
    if (str_ends_with($rel, "Pest") || str_contains($rel, "Tests")) { continue; }
    $out[] = "App\\" . str_replace("/", "\\", $rel);
}
sort($out);
echo implode("\n", $out);
')

LOADER=$(cat <<'LOADER'
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$bad = [];
foreach (array_slice($argv, 1) as $class) {
    if (! class_exists($class) && ! interface_exists($class)
        && ! trait_exists($class) && ! enum_exists($class)) {
        $bad[] = $class;
    }
}
echo implode("\n", $bad);
LOADER
)

CLASS_COUNT=$(printf '%s\n' "$CLASSES" | grep -c . )

LOAD_OUT=$(php -r "$LOADER" $(printf '%s\n' "$CLASSES" | tr '\n' ' ') 2>&1)
LOAD_CODE=$?

if [ "$LOAD_CODE" -ne 0 ] || [ -n "$LOAD_OUT" ]; then
    bad "ada kelas yang tidak bisa dimuat"
    printf '%s\n' "$LOAD_OUT" | head -6 | sed 's/^/       /'
else
    ok "$CLASS_COUNT kelas"
fi

# ---------------------------------------------------------------- suite
# Baris "Tests:" adalah bukti bahwa suite selesai. Suite yang crash tidak
# mencetak apa pun, jadi absennya adalah kegagalan, bukan kelulusan.
step "3. Test suite"
SUITE=$(timeout 1800 php artisan test 2>&1)
SUITE_CODE=$?
SUMMARY=$(printf '%s' "$SUITE" | sed 's/\x1b\[[0-9;]*m//g' | grep -E '^\s+Tests:' | tail -1)

if [ -z "$SUMMARY" ]; then
    bad "suite tidak mencetak baris Tests: — kemungkinan crash"
    printf '%s\n' "$SUITE" | sed 's/\x1b\[[0-9;]*m//g' | tail -12 | sed 's/^/       /'
else
    printf '%s\n' "$SUMMARY" | sed 's/^/       /'

    # Read the summary rather than matching the marker Pest prints beside each
    # failure. The marker check passed on a run with 31 failing tests, so it is not
    # trusted here: character-matching a progress line fails silently, and a check
    # that quietly stops working is indistinguishable from one that never worked.
    #
    # The suite counts a warning as neither a pass nor a failure, so the check is for
    # the word "failed" and not for anything other than "passed".
    if printf '%s' "$SUMMARY" | grep -q 'failed'; then
        bad "$(printf '%s' "$SUMMARY" | sed -e 's/^ *//' -e 's/Tests: */Tests: /')"
    else
        ok "$(printf '%s' "$SUMMARY" | sed 's/^ *//')"
    fi
fi

# ---------------------------------------------------------------- HTTP
# Halaman yang harus hidup setelah perubahan apa pun. R-checking page here is
# what caught the class-loading faults after the suite had already been read as
# green.
step "4. Halaman publik menjawab"
HTTP_BAD=0
for path in /up /login/regist /admin/login /student/login; do
    CODE=$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "http://127.0.0.1$path" 2>/dev/null)
    if [ "$CODE" = "200" ]; then
        ok "$path"
    else
        bad "$path -> ${CODE:-tidak menjawab}"
        HTTP_BAD=1
    fi
done

# ---------------------------------------------------------------- log
# Hanya error yang tertulis selama pemeriksaan ini yang dihitung. Lognya menumpuk
# dari hari-hari, jadi "ada error di log" selalu benar dan tidak berarti apa pun;
# yang berarti adalah ada error baru selama pemeriksaan berjalan.
step "5. Tidak ada error baru selama pemeriksaan"
NEW_ERRORS=$(tail -n +"${LOG_LINES_BEFORE:-0}" storage/logs/laravel.log 2>/dev/null \
    | grep -E 'local\.(ERROR|CRITICAL|ALERT|EMERGENCY)' || true)

if [ -n "$NEW_ERRORS" ]; then
    bad "$(printf '%s\n' "$NEW_ERRORS" | wc -l) error baru tertulis selama pemeriksaan"
    printf '%s\n' "$NEW_ERRORS" | tail -4 | cut -c1-160 | sed 's/^/       /'
else
    ok "tidak ada error baru"
fi

printf '\n'
if [ "$FAILED" -eq 0 ]; then
    printf '\033[32mSEMUA LOKASI\033[0m — aman untuk continuou\n'
else
    printf '\033[31mADA MASALAH\033[0m — jangan dianggap selesai\n'
fi
exit "$FAILED"