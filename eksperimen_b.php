<?php
function trapmf(float $x, float $a, float $b, float $c, float $d): float {
    if ($x < $a || $x > $d) return 0.0;
    if ($x >= $b && $x <= $c) return 1.0;
    if ($x > $a && $x < $b) return ($b == $a) ? 1.0 : ($x - $a) / ($b - $a);
    if ($x > $c && $x < $d) return ($d == $c) ? 1.0 : ($d - $x) / ($d - $c);
    return 0.0;
}

function trimf(float $x, float $a, float $b, float $c): float {
    if ($x < $a || $x > $c) return 0.0;
    if (abs($x - $b) < 0.0000001) return 1.0;
    if ($x >= $a && $x < $b) return ($x - $a) / ($b - $a);
    if ($x > $b && $x <= $c) return ($c - $x) / ($c - $b);
    return 0.0;
}

function category(float $score): array {
    if ($score <= 33) return ["Tidak Layak", "danger", "Air tidak direkomendasikan untuk digunakan."];
    if ($score <= 66) return ["Waspada", "warning", "Air masih perlu pemantauan dan perbaikan pada parameter yang belum ideal."];
    return ["Sangat Layak", "success", "Air memenuhi kondisi ideal menurut model FIS Sugeno Orde-0 (Permenkes 2023)."];
}

function fmt(float $n, int $dec = 4): string {
    return number_format($n, $dec, '.', '');
}

function top_membership(array $sets): array {
    arsort($sets);
    $key = array_key_first($sets);
    return [$key, $sets[$key]];
}

$result = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $T  = floatval(str_replace(',', '.', $_POST["suhu"]));
    $C  = floatval(str_replace(',', '.', $_POST["kekeruhan"]));
    $pH = floatval(str_replace(',', '.', $_POST["ph"]));
    $DO = floatval(str_replace(',', '.', $_POST["do"]));

    // KEMBALI KE GRAFIK ASLI (SINKRON 100%)
    $mf = [
        "Suhu" => [
            "Dingin" => trapmf($T, 15, 15, 20, 25),
            "Sejuk"  => trimf($T, 20, 25, 30),
            "Hangat" => trapmf($T, 25, 30, 35, 35),
        ],
        "Kekeruhan" => [
            "Keruh"  => trapmf($C, 0, 0, 1.2, 1.6),
            "Sedang" => trapmf($C, 1.2, 1.6, 2.2, 2.6),
            "Jernih" => trapmf($C, 2.2, 2.6, 3, 3),
        ],
        "pH" => [
            "Asam"   => trapmf($pH, 4, 4, 4.5, 5.0),
            "Normal" => trapmf($pH, 4.5, 5.0, 9.0, 9.5), 
            "Basa"   => trapmf($pH, 9.0, 9.5, 10, 10),
        ],
        "DO" => [
            "Rendah"  => trapmf($DO, 0, 0, 3.5, 4.5),
            "Optimal" => trapmf($DO, 3.5, 4.5, 7.0, 8.0),
            "Tinggi"  => trapmf($DO, 7.0, 8.0, 10, 10),
        ],
    ];

    $suhuSets = ["Dingin", "Sejuk", "Hangat"];
    $kekeruhanSets = ["Keruh", "Sedang", "Jernih"]; 
    $phSets = ["Asam", "Normal", "Basa"];
    $doSets = ["Rendah", "Optimal", "Tinggi"];

    $activeRules = [];
    $ruleNo = 1;
    $sumAlphaZ = 0.0;
    $sumAlpha = 0.0;

    foreach ($suhuSets as $s) {
        foreach ($kekeruhanSets as $k) {
            foreach ($phSets as $p) {
                foreach ($doSets as $d) {
                    $alpha = min($mf["Suhu"][$s], $mf["Kekeruhan"][$k], $mf["pH"][$p], $mf["DO"][$d]);

                    // SKENARIO EXPERIMEN B: LOGIKA VETO DIHAPUS TOTAL!
                    if ($s === "Sejuk" && $k === "Jernih" && $p === "Normal" && $d === "Optimal") {
                        $z = 100;
                        $output = "Sangat Layak";
                        $reason = "IDEAL: Suhu Sejuk, Kekeruhan Jernih, pH Normal, dan DO Optimal.";
                        $type = "ideal";
                    } else {
                        $z = 50;
                        $output = "Waspada";
                        $reason = "TRANSISI / NON-VETO: Logika pengaman Veto dinonaktifkan pada eksperimen ini.";
                        $type = "transisi";
                    }

                    if ($alpha > 0) {
                        $sumAlpha += $alpha;
                        $sumAlphaZ += $alpha * $z;
                        $activeRules[] = [
                            "no" => $ruleNo,
                            "suhu" => $s,
                            "kekeruhan" => $k,
                            "ph" => $p,
                            "do" => $d,
                            "alpha" => $alpha,
                            "z" => $z,
                            "output" => $output,
                            "reason" => $reason,
                            "type" => $type,
                            "alpha_z" => $alpha * $z,
                            "min_detail" => "MIN(" . fmt($mf["Suhu"][$s]) . ", " . fmt($mf["Kekeruhan"][$k]) . ", " . fmt($mf["pH"][$p]) . ", " . fmt($mf["DO"][$d]) . ")"
                        ];
                    }
                    $ruleNo++;
                }
            }
        }
    }

    $score = ($sumAlpha > 0) ? ($sumAlphaZ / $sumAlpha) : 0;
    [$cat, $color, $recommendation] = category($score);

    $dominant = [];
    foreach ($mf as $var => $sets) {
        [$name, $val] = top_membership($sets);
        $dominant[$var] = ["name" => $name, "val" => $val];
    }

    $result = [
        "input" => compact("T", "C", "pH", "DO"),
        "mf" => $mf,
        "dominant" => $dominant,
        "activeRules" => $activeRules,
        "sumAlpha" => $sumAlpha,
        "sumAlphaZ" => $sumAlphaZ,
        "score" => $score,
        "category" => $cat,
        "color" => $color,
        "recommendation" => $recommendation,
        "hasVetoActive" => false, // Veto mati
        "idealActive" => false,
    ];
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EKSPERIMEN B | Tanpa Aturan Veto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.plot.ly/plotly-2.27.0.min.js"></script>
    <style>
        :root {
            --bg: #fff2f7;
            --primary: #d63384;
            --secondary: #f7b7c1;
            --dark: #4a1d3f;
            --muted: #8f698e;
        }
        body { background: var(--bg); font-family: system-ui, -apple-system, "Segoe UI", sans-serif; color:#111827; }
        .app-shell { width: min(98vw, 1760px); max-width: 1760px; margin: 0 auto; }
        .hero {
            background: linear-gradient(135deg, #ff922b 0%, #d63384 100%);
            color:white; border-radius: 24px; padding: 22px 28px;
            box-shadow: 0 18px 35px rgba(214, 51, 132, .18);
        }
        .hero .pill { background: rgba(255,255,255,.18); border:1px solid rgba(255,255,255,.28); color:#fff; }
        .cardx {
            border:0; border-radius:24px; background:#fff;
            box-shadow: 0 14px 32px rgba(15,23,42,.07);
            page-break-inside: avoid; break-inside: avoid;
        }
        .section-title { font-size: 1.05rem; font-weight: 800; margin:0; color: var(--dark); }
        .small-muted { color: var(--muted); font-size:.86rem; }
        .form-control { border-radius:14px; padding:11px 14px; }
        .btn { border-radius:14px; padding:11px 16px; font-weight:700; background: var(--primary); border-color: var(--primary); color: #fff; }
        .badge-lg { font-size:1rem; padding:.75rem 1rem; border-radius:999px; }
        .score-circle {
            width: 178px; height:178px; border-radius:50%;
            display:grid; place-items:center; margin:auto;
            background: conic-gradient(var(--primary) calc(var(--score) * 1%), rgba(247, 183, 193, 0.22) 0);
            position: relative;
        }
        .score-circle::after {
            content:""; position:absolute; inset:16px; border-radius:50%; background:#fff;
        }
        .score-inner { position:relative; z-index:2; text-align:center; }
        .score-number { font-size:2.4rem; font-weight:900; letter-spacing:-1px; }
        .timeline { position:relative; padding-left:28px; }
        .timeline::before { content:""; position:absolute; left:9px; top:4px; bottom:4px; width:2px; background:#dbeafe; }
        .step { position:relative; margin-bottom:18px; }
        .step::before { content:""; position:absolute; left:-24px; top:3px; width:18px; height:18px; background:#d63384; border:4px solid rgba(247, 183, 193, 0.5); border-radius:50%; }
        .formula {
            background:#0b1220; color:#eaf2ff; padding:16px; border-radius:16px;
            font-family: Consolas, Monaco, monospace; overflow:auto; font-size:.93rem;
            white-space: pre-wrap; line-height: 1.6;
        }
        .rule-card {
            border:1px solid #e5e7eb; border-radius:18px; padding:18px; background:#fff;
            display:flex; flex-direction:column; gap:12px;
        }
        .rule-card .rule-meta { display:flex; justify-content:space-between; align-items:flex-start; gap:0.75rem; flex-wrap:wrap; }
        .rule-card .rule-meta strong { font-size:1rem; }
        .rule-card .rule-body { display:flex; flex-direction:column; gap:0.5rem; line-height:1.5; }
        .rule-card p { margin-bottom:0; }
        .rule-card hr { margin: 0.75rem 0; border-color: #e5e7eb; }
        .cardx h2.section-title { margin-bottom: 1.5rem; }
        .explain-box { border-left: 6px solid #ff922b; background:#fffbf8; padding:18px; border-radius:18px; }
        .mf-card { background:#f9fafb; border:1px solid #e5e7eb; border-radius:18px; padding:15px; }
        .bar-wrap { height:10px; background:#e5e7eb; border-radius:999px; overflow:hidden; }
        .bar { height:100%; border-radius:999px; background:#1463ff; }
        .rule-card.transisi { border-left:6px solid #ff922b; }
        .rule-card.ideal { border-left:6px solid #198754; }
        .table th { white-space: nowrap; }
        #analysisDetails.collapsed { display: none; }
        .toggle-section { display: flex; justify-content: space-between; gap: 14px; align-items: center; }
        .toggle-section .section-title { margin-bottom: 0; }
        .result-summary .score-circle { width: 168px; height: 168px; }
        .result-summary .score-number { font-size: 2.6rem; }
        .cardx + .cardx { margin-top: 1.5rem; }
    </style>
</head>
<body>
<div class="app-shell p-3 p-md-4">
    <div class="hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <span class="badge pill mb-2 text-dark bg-warning">MATA KULIAH: EKSPERIMEN MINGGU 12</span>
                <h1 class="h3 fw-bold mb-2">EKSPERIMEN B: Variasi Komposisi Aturan (Tanpa VETO)</h1>
                <p class="mb-0 opacity-90">Skenario menonaktifkan aturan VETO (Hukuman Mutlak z=0) untuk menguji kebocoran klasifikasi air berbahaya.</p>
            </div>
            <div class="text-md-end">
                <div class="fw-bold">Metode</div>
                <div>Fuzzy Sugeno Orde-0</div>
            </div>
        </div>
    </div>

    <div class="main-layout">
        <div class="row gx-3 gy-4 align-items-stretch">
            <div class="<?= $result ? 'col-lg-5' : 'col-12' ?>">
                <div class="cardx p-4 sticky-panel form-card h-100">
                    <h2 class="section-title mb-1">Input Parameter</h2>
                    <p class="small-muted mb-3">Masukkan nilai aktual sesuai domain Permenkes 2023.</p>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Suhu (°C)</label>
                            <input type="number" step="0.01" min="15" max="35" name="suhu" class="form-control" required value="<?= htmlspecialchars($_POST['suhu'] ?? '25') ?>">
                            <div class="small-muted mt-1">Domain: 15–35 °C</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Kejernihan / Turbidity (m)</label>
                            <input type="number" step="0.01" min="0" max="5" name="kekeruhan" class="form-control" required value="<?= htmlspecialchars($_POST['kekeruhan'] ?? '2.8') ?>">
                            <div class="small-muted mt-1">Domain: 0–3 m (Aman > 1.6 m)</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">pH</label>
                            <input type="number" step="0.01" min="4" max="10" name="ph" class="form-control" required value="<?= htmlspecialchars($_POST['ph'] ?? '4.0') ?>">
                            <div class="small-muted mt-1">Domain: 4–10 (Uji Ekstrem pH Asam!)</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Dissolved Oxygen / DO (mg/L)</label>
                            <input type="number" step="0.01" min="0" max="10" name="do" class="form-control" required value="<?= htmlspecialchars($_POST['do'] ?? '6.0') ?>">
                            <div class="small-muted mt-1">Domain: 0–10 mg/L (Aman > 4 mg/L)</div>
                        </div>
                        <button class="btn btn-warning text-dark w-100">Jalankan Eksperimen B</button>
                    </form>
                </div>
            </div>
            <?php if ($result): ?>
            <div class="col-lg-7">
                <div class="result-summary cardx p-4 mb-0 h-100">
                    <div class="text-center mb-4">
                        <div class="score-circle mx-auto" style="--score:<?= max(0, min(100, $result['score'])) ?>;">
                            <div class="score-inner">
                                <div class="score-number"><?= number_format($result['score'], 2) ?></div>
                                <div class="small-muted">Skor Kelayakan</div>
                            </div>
                        </div>
                    </div>
                    
                    <h2 class="section-title mb-3">Ringkasan Analisis</h2>
                    <div class="mb-3">
                        <span class="badge text-bg-<?= $result['color'] ?> fs-6 px-3 py-2"><?= strtoupper($result['category']) ?></span>
                    </div>
                    
                    <p class="mb-3 lead text-secondary">
                        <?= $result['recommendation'] ?>
                    </p>
                    
                    <div class="explain-box mb-3">
                        <h3 class="h6 fw-bold mb-2">Detail Inferensi (Uji Kasus Bahaya)</h3>
                        <p class="mb-1">Skor akhir <strong><?= fmt($result['score'], 2) ?></strong> masuk ke dalam kategori mutlak <strong><?= strtoupper($result['category']) ?></strong>.</p>
                        <p class="mb-1 text-warning small mt-2"><i class="fw-bold">Catatan Eksperimen:</i> Logika VETO dimatikan. Perhatikan bagaimana air dengan pH rusak/asam tetap dinilai aman/waspada oleh mesin biasa.</p>
                    </div>

                    <div class="formula">
                        z* = Σ(αᵢ × cᵢ) / Σ(αᵢ)<br>
                        z* = <?= fmt($result['sumAlphaZ']) ?> / <?= fmt($result['sumAlpha']) ?><br>
                        z* = <?= fmt($result['score']) ?>
                    </div>
                </div>
            </div>

            <div class="toggle-section mb-3 mt-4">
                <button type="button" id="toggleProcessBtn" class="btn btn-outline-primary btn-lg w-100">Tampilkan Proses & Grafik Lengkap</button>
            </div>

            <div id="analysisDetails" class="collapsed">
                
                <div class="cardx p-4 mb-4">
                    <h2 class="section-title mb-3">Alur Analisis Fuzzy</h2>
                    <div class="timeline">
                        <div class="step">
                            <div class="fw-bold">1. Input diterima</div>
                            <div class="small-muted">Suhu <?= fmt($result['input']['T'], 2) ?>°C, Kejernihan <?= fmt($result['input']['C'], 2) ?> m, pH <?= fmt($result['input']['pH'], 2) ?>, DO <?= fmt($result['input']['DO'], 2) ?> mg/L.</div>
                        </div>
                        <div class="step">
                            <div class="fw-bold">2. Fuzzifikasi</div>
                            <div class="small-muted">Setiap input diubah menjadi derajat keanggotaan berdasarkan fungsi trapezoid dan segitiga.</div>
                        </div>
                        <div class="step">
                            <div class="fw-bold">3. Evaluasi Rule</div>
                            <div class="small-muted">Sistem mengevaluasi <?= count($result['activeRules']) ?> rule aktif dan menentukan firing strength dengan operator MIN.</div>
                        </div>
                        <div class="step">
                            <div class="fw-bold">4. Defuzzifikasi</div>
                            <div class="small-muted">Skor akhir dihitung dengan Weighted Average Sugeno Orde-0.</div>
                        </div>
                    </div>
                </div>

                <div class="cardx p-4 mb-4">
                    <h2 class="section-title mb-3">Grafik Permukaan Keputusan (3D Control Surface)</h2>
                    <p class="small-muted mb-3">Lihat bagaimana jurang ungu (Veto) menghilang dan rata menjadi daratan hijau karena aturan pengaman dimatikan.</p>
                    <div id="surface3d" style="width: 100%; height: 400px; border-radius: 12px; overflow: hidden; background: #f9fafb;"></div>
                </div>

                <div class="cardx p-4 mb-4">
                    <h2 class="section-title mb-3">Visualisasi Fungsi Keanggotaan (MF)</h2>
                    <div class="row g-3">
                        <div class="col-lg-6"><div style="background:#f9fafb; padding:12px; border-radius:12px; height:180px;"><canvas id="mfSuhu"></canvas></div></div>
                        <div class="col-lg-6"><div style="background:#f9fafb; padding:12px; border-radius:12px; height:180px;"><canvas id="mfKekeruhan"></canvas></div></div>
                        <div class="col-lg-6"><div style="background:#f9fafb; padding:12px; border-radius:12px; height:180px;"><canvas id="mfPH"></canvas></div></div>
                        <div class="col-lg-6"><div style="background:#f9fafb; padding:12px; border-radius:12px; height:180px;"><canvas id="mfDO"></canvas></div></div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="cardx p-4 h-100 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h2 class="section-title mb-0">Rumus yang Digunakan</h2>
                                <span class="badge text-bg-dark">Sugeno</span>
                            </div>
                            <div class="formula flex-grow-1">
Triangular MF:
μ(x) = max(min((x-a)/(b-a), (c-x)/(c-b)), 0)

Trapezoidal MF:
μ(x) = max(min((x-a)/(b-a), 1, (d-x)/(d-c)), 0)
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="cardx p-4 h-100 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h2 class="section-title mb-0">Defuzzifikasi</h2>
                                <span class="badge text-bg-secondary">Weighted Average</span>
                            </div>
                            <div class="formula flex-grow-1">
Firing Strength:
αᵢ = MIN(μSuhu, μKekeruhan, μpH, μDO)

Nilai output:
z* = Σ(αᵢ × cᵢ) / Σ(αᵢ)
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cardx p-4 mb-4 mt-4">
                    <h2 class="section-title mb-3">Ringkasan Rule Aktif</h2>
                    <div class="row g-3 mt-3">
                        <?php foreach ($result['activeRules'] as $r): ?>
                            <div class="col-md-6">
                                <div class="rule-card <?= $r['type'] ?> h-100">
                                    <div class="rule-meta">
                                        <strong>Rule <?= $r['no'] ?></strong>
                                        <span class="badge <?= $r['z'] == 0 ? 'text-bg-danger' : ($r['z'] == 100 ? 'text-bg-success' : 'text-bg-warning') ?>">z = <?= $r['z'] ?></span>
                                    </div>
                                    <div class="rule-body">
                                        <p class="small mb-0">IF Suhu=<strong><?= $r['suhu'] ?></strong> AND Kejernihan=<strong><?= $r['kekeruhan'] ?></strong> AND pH=<strong><?= $r['ph'] ?></strong> AND DO=<strong><?= $r['do'] ?></strong></p>
                                        <p class="small-muted mb-0">α = <?= $r['min_detail'] ?> = <strong><?= fmt($r['alpha']) ?></strong></p>
                                        <p class="small mb-0">α × z = <?= fmt($r['alpha']) ?> × <?= $r['z'] ?> = <strong><?= fmt($r['alpha_z']) ?></strong></p>
                                    </div>
                                    <hr>
                                    <p class="small-muted mb-0"><?= $r['reason'] ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="cardx p-4 mb-4">
                    <h2 class="section-title mb-3">Tabel Perhitungan Tanpa Veto</h2>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>Rule</th>
                                    <th>Anteseden</th>
                                    <th>α</th>
                                    <th>Output</th>
                                    <th>cᵢ</th>
                                    <th>αᵢ × cᵢ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($result['activeRules'] as $r): ?>
                                <tr>
                                    <td><?= $r['no'] ?></td>
                                    <td>Suhu=<?= $r['suhu'] ?>, Kejer=<?= $r['kekeruhan'] ?>, pH=<?= $r['ph'] ?>, DO=<?= $r['do'] ?></td>
                                    <td><?= $r['min_detail'] ?> = <?= fmt($r['alpha']) ?></td>
                                    <td><?= $r['output'] ?></td>
                                    <td><?= $r['z'] ?></td>
                                    <td><?= fmt($r['alpha_z']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <script>
                function fuzzyEngineJS(T, C, pH, DO) {
                    const t_trap = (x,a,b,c,d) => x<a||x>d?0:(x>=b&&x<=c?1:(x>a&&x<b?(x-a)/(b-a):(d-x)/(d-c)));
                    const t_tri = (x,a,b,c) => x<a||x>c?0:(x===b?1:(x>a&&x<b?(x-a)/(b-a):(c-x)/(c-b)));

                    let mfS = { "Dingin": t_trap(T, 15, 15, 20, 25), "Sejuk": t_tri(T, 20, 25, 30), "Hangat": t_trap(T, 25, 30, 35, 35) };
                    let mfC = { "Keruh": t_trap(C, 0, 0, 1.2, 1.6), "Sedang": t_trap(C, 1.2, 1.6, 2.2, 2.6), "Jernih": t_trap(C, 2.2, 2.6, 3, 3) };
                    let mfP = { "Asam": t_trap(pH, 4, 4, 4.5, 5.0), "Normal": t_trap(pH, 4.5, 5.0, 9.0, 9.5), "Basa": t_trap(pH, 9.0, 9.5, 10, 10) };
                    let mfD = { "Rendah": t_trap(DO, 0, 0, 3.5, 4.5), "Optimal": t_trap(DO, 3.5, 4.5, 7.0, 8.0), "Tinggi": t_trap(DO, 7.0, 8.0, 10, 10) };

                    let sumAlphaZ = 0; let sumAlpha = 0;
                    for(let s in mfS) {
                        for(let k in mfC) {
                            for(let p in mfP) {
                                for(let d in mfD) {
                                    let alpha = Math.min(mfS[s], mfC[k], mfP[p], mfD[d]);
                                    
                                    // EKSPERIMEN B DI JS SURFACE: MATIKAN VETO JURANG 0
                                    let z = 50;
                                    if (s==="Sejuk" && k==="Jernih" && p==="Normal" && d==="Optimal") z = 100;
                                    
                                    if(alpha > 0) { sumAlpha += alpha; sumAlphaZ += alpha*z; }
                                }
                            }
                        }
                    }
                    return sumAlpha > 0 ? (sumAlphaZ / sumAlpha) : 0;
                }

                function render3D() {
                    const xSuhu = [], yDo = [];
                    for(let i=15; i<=35; i+=1) xSuhu.push(i);
                    for(let i=0; i<=10; i+=0.5) yDo.push(i);
                    const zMatrix = [];
                    for(let j=0; j<yDo.length; j++){
                        let row = [];
                        for(let i=0; i<xSuhu.length; i++){
                            row.push(fuzzyEngineJS(xSuhu[i], <?= $result['input']['C'] ?>, <?= $result['input']['pH'] ?>, yDo[j]));
                        }
                        zMatrix.push(row);
                    }
                    Plotly.newPlot('surface3d', [{ z: zMatrix, x: xSuhu, y: yDo, type: 'surface', colorscale: 'Viridis' }], { margin: {l: 0, r: 0, b: 0, t: 0}, scene: { xaxis: {title: 'Suhu'}, yaxis: {title: 'DO'}, zaxis: {title: 'Skor'} } }, {displayModeBar: false});
                }

                const trapmf = (x, a, b, c, d) => x<a||x>d?0:(x>=b&&x<=c?1:(x>a&&x<b?(x-a)/(b-a):(d-x)/(d-c)));
                const trimf = (x, a, b, c) => x<a||x>c?0:(Math.abs(x - b) < 0.0001?1:(x>=a&&x<b?(x-a)/(b-a):(c-x)/(c-b)));
                function linspace(a, b, n = 200) { const out = []; const step = (b - a) / (n - 1); for (let i = 0; i < n; i++) out.push(a + step * i); return out; }

                const verticalMarkerPlugin = {
                    id: 'verticalMarker',
                    afterDraw: (chart, args, options) => {
                        if (!options || !options.xValue) return;
                        const ctx = chart.ctx; const xScale = chart.scales[options.xScale || 'x']; const yScale = chart.scales[options.yScale || 'y']; const xPixel = xScale.getPixelForValue(options.xValue);
                        ctx.save(); ctx.beginPath(); ctx.strokeStyle = options.color || '#dc3545'; ctx.lineWidth = 2; ctx.setLineDash([5, 5]); ctx.moveTo(xPixel, yScale.top); ctx.lineTo(xPixel, yScale.bottom); ctx.stroke();
                        if (options.label) { ctx.fillStyle = options.color || '#dc3545'; ctx.font = 'bold 12px sans-serif'; ctx.textAlign = 'center'; ctx.fillText(String(options.label), xPixel, yScale.top - 8); }
                        ctx.restore();
                    }
                };
                Chart.register(verticalMarkerPlugin);

                function createMFChart(canvasId, xMin, xMax, xCurrent, sets) {
                    const xs = linspace(xMin, xMax, 240); const dataSets = [];
                    Object.entries(sets).forEach(([name, cfg]) => {
                        dataSets.push({ label: name, data: xs.map(x => ({ x: x, y: cfg.fn(x) })), borderColor: cfg.color, backgroundColor: 'transparent', tension: 0, pointRadius: 0, borderWidth: 2 });
                    });
                    const canvas = document.getElementById(canvasId);
                    if (!canvas) return null;
                    if (canvas._mfChart) { try { canvas._mfChart.destroy(); } catch (e) {} }
                    canvas._mfChart = new Chart(canvas.getContext('2d'), { type: 'line', data: { datasets: dataSets }, options: { responsive: true, maintainAspectRatio: false, animation: false, scales: { x: { type: 'linear', min: xMin, max: xMax }, y: { min: 0, max: 1.1 } }, plugins: { verticalMarker: { xValue: xCurrent, label: Number(xCurrent).toFixed(2) } } } });
                }

                function renderAllMF() {
                    createMFChart('mfSuhu', 15, 35, <?= $result['input']['T'] ?>, { 'Dingin': { color: '#1463ff', fn: (x) => trapmf(x, 15, 15, 20, 25) }, 'Sejuk': { color: '#198754', fn: (x) => trimf(x, 20, 25, 30) }, 'Hangat': { color: '#fd7e14', fn: (x) => trapmf(x, 25, 30, 35, 35) } });
                    createMFChart('mfKekeruhan', 0, 3, <?= $result['input']['C'] ?>, { 'Keruh': { color: '#dc3545', fn: (x) => trapmf(x, 0, 0, 1.2, 1.6) }, 'Sedang': { color: '#198754', fn: (x) => trapmf(x, 1.2, 1.6, 2.2, 2.6) }, 'Jernih': { color: '#1463ff', fn: (x) => trapmf(x, 2.2, 2.6, 3, 3) } });
                    createMFChart('mfPH', 4, 10, <?= $result['input']['pH'] ?>, { 'Asam': { color: '#dc3545', fn: (x) => trapmf(x, 4, 4, 4.5, 5.0) }, 'Normal': { color: '#198754', fn: (x) => trapmf(x, 4.5, 5.0, 9.0, 9.5) }, 'Basa': { color: '#dc3545', fn: (x) => trapmf(x, 9.0, 9.5, 10, 10) } });
                    createMFChart('mfDO', 0, 10, <?= $result['input']['DO'] ?>, { 'Rendah': { color: '#dc3545', fn: (x) => trapmf(x, 0, 0, 3.5, 4.5) }, 'Optimal': { color: '#198754', fn: (x) => trapmf(x, 3.5, 4.5, 7.0, 8.0) }, 'Tinggi': { color: '#1463ff', fn: (x) => trapmf(x, 7.0, 8.0, 10, 10) } });
                }

                const detailSection = document.getElementById('analysisDetails'); const toggleBtn = document.getElementById('toggleProcessBtn');
                if (toggleBtn && detailSection) { toggleBtn.addEventListener('click', () => { detailSection.classList.toggle('collapsed'); const isCollapsed = detailSection.classList.contains('collapsed'); toggleBtn.textContent = isCollapsed ? 'Tampilkan Proses & Grafik Lengkap' : 'Sembunyikan Detail'; if (!isCollapsed) { setTimeout(() => { renderAllMF(); render3D(); }, 150); } }); }
            </script>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>