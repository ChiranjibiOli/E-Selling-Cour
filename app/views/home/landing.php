<?php

require_once __DIR__ . '/../../config/database.php';

$featuredCourses = [];
$stats = ['students' => 0, 'courses' => 0, 'instructors' => 0, 'enrollments' => 0];

function landing_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function landing_price(mixed $price): string
{
    return 'Rs. ' . number_format((float) $price, 0);
}

function landing_thumbnail(array $course): string
{
    $publicRoot = defined('PUBLIC_PATH') ? PUBLIC_PATH : dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public';
    $thumbnailPath = 'assets/images/course-placeholder.svg';

    if (!empty($course['thumbnail'])) {
        $candidate = 'assets/uploads/course_thumbnails/' . basename((string) $course['thumbnail']);
        $fullPath = $publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate);
        if (is_file($fullPath)) {
            $thumbnailPath = $candidate;
        }
    }

    return $thumbnailPath;
}

$featuredSql = "
    SELECT c.id, c.title, c.slug, c.short_description, c.thumbnail, c.price,
           c.level, c.duration, c.language, u.full_name AS instructor_name
    FROM courses c
    INNER JOIN users u ON c.instructor_id = u.id
    WHERE c.status = 'published'
    ORDER BY c.is_featured DESC, c.created_at DESC
    LIMIT 3
";

$featuredResult = $conn->query($featuredSql);
while ($featuredResult && $row = $featuredResult->fetch_assoc()) {
    $featuredCourses[] = $row;
}

$statQueries = [
    'students' => "SELECT COUNT(*) AS total FROM users WHERE role = 'student' AND status = 'active'",
    'courses' => "SELECT COUNT(*) AS total FROM courses WHERE status = 'published'",
    'instructors' => "SELECT COUNT(*) AS total FROM users WHERE role = 'instructor' AND status = 'active'",
    'enrollments' => "SELECT COUNT(*) AS total FROM enrollments WHERE status = 'active'",
];

foreach ($statQueries as $key => $query) {
    $result = $conn->query($query);
    $stats[$key] = $result ? (int) ($result->fetch_assoc()['total'] ?? 0) : 0;
}
?>
<link rel="stylesheet" href="assets/css/navbars/public-navbar.css?v=14">
<link rel="stylesheet" href="assets/css/pages/public/landing.css?v=20">
<link rel="stylesheet" href="assets/css/components/footer.css?v=10">

<style>
.knowledge-4d-stage{position:relative;min-height:560px;display:grid;place-items:center;perspective:1400px;overflow:visible;isolation:isolate}
.knowledge-4d-stage::before{content:"";position:absolute;width:440px;height:440px;border-radius:50%;background:radial-gradient(circle,rgba(202,148,52,.22),rgba(202,148,52,.08) 42%,transparent 72%);filter:blur(12px);animation:ambientPulse 5s ease-in-out infinite}
.knowledge-4d-stage::after{content:"";position:absolute;left:50%;bottom:56px;width:270px;height:46px;transform:translateX(-50%);border-radius:50%;background:rgba(55,31,14,.23);filter:blur(20px);z-index:0;animation:shadowFloat 4.4s ease-in-out infinite}
.knowledge-4d-object{position:relative;z-index:2;width:min(420px,90%);transform-style:preserve-3d;will-change:transform;animation:bookHover 4.4s ease-in-out infinite;transition:transform .18s ease-out}
.knowledge-4d-object img{width:100%;display:block;object-fit:contain;filter:drop-shadow(0 30px 32px rgba(35,20,10,.34));mask-image:radial-gradient(ellipse 66% 76% at 50% 52%,#000 62%,rgba(0,0,0,.88) 78%,transparent 100%);-webkit-mask-image:radial-gradient(ellipse 66% 76% at 50% 52%,#000 62%,rgba(0,0,0,.88) 78%,transparent 100%)}
.knowledge-4d-cover{position:absolute;z-index:4;top:18.5%;left:50%;width:46%;transform:translateX(-50%) translateZ(48px);padding:18px 12px 16px;text-align:center;color:#d9a74f;text-shadow:0 2px 10px rgba(0,0,0,.58);font-family:Georgia,"Times New Roman",serif;font-size:clamp(.9rem,1.9vw,1.4rem);font-weight:600;letter-spacing:.16em;line-height:1.23;text-transform:uppercase;pointer-events:none}
.knowledge-4d-cover::before{content:"";position:absolute;inset:-12px -8px;border:1px solid rgba(220,171,85,.65);box-shadow:inset 0 0 0 2px rgba(91,49,21,.5),0 14px 22px rgba(0,0,0,.18);transform:translateZ(-2px)}
.knowledge-4d-cover small{display:block;margin-bottom:9px;font-family:Arial,sans-serif;font-size:.46rem;font-weight:900;letter-spacing:.25em;color:#b98436}
.knowledge-4d-cover span{display:block;margin-top:11px;font-family:Arial,sans-serif;font-size:.46rem;font-weight:900;letter-spacing:.18em;color:#a97836}
.knowledge-4d-light{position:absolute;z-index:5;inset:9% 20% 28% 20%;background:linear-gradient(108deg,transparent 28%,rgba(255,225,163,.38) 44%,rgba(255,255,255,.58) 49%,transparent 58%);transform:translateX(-145%) translateZ(70px);mix-blend-mode:screen;pointer-events:none;animation:coverShine 4.8s ease-in-out infinite}
.knowledge-4d-particles{position:absolute;z-index:1;inset:0;pointer-events:none}
.knowledge-4d-particles i{position:absolute;width:7px;height:7px;border-radius:50%;background:#d4a24e;box-shadow:0 0 14px rgba(212,162,78,.8);animation:particleFloat 6s ease-in-out infinite}
.knowledge-4d-particles i:nth-child(1){left:15%;top:20%;animation-delay:-1s}.knowledge-4d-particles i:nth-child(2){right:16%;top:28%;width:5px;height:5px;animation-delay:-3s}.knowledge-4d-particles i:nth-child(3){left:23%;bottom:24%;width:4px;height:4px;animation-delay:-4.4s}.knowledge-4d-particles i:nth-child(4){right:24%;bottom:18%;animation-delay:-2.1s}
.knowledge-4d-badge{position:absolute;z-index:6;right:2%;bottom:14%;padding:10px 14px;border:1px solid rgba(157,110,39,.28);border-radius:999px;background:rgba(255,249,237,.82);box-shadow:0 12px 34px rgba(42,28,16,.12);backdrop-filter:blur(12px);color:#6e4c1c;font-size:.68rem;font-weight:900;letter-spacing:.08em;text-transform:uppercase;transform:translateZ(80px)}
@keyframes bookHover{0%,100%{transform:translateY(0) rotateX(1deg) rotateY(-2deg)}50%{transform:translateY(-16px) rotateX(-1deg) rotateY(2deg)}}
@keyframes shadowFloat{0%,100%{transform:translateX(-50%) scale(1);opacity:.65}50%{transform:translateX(-50%) scale(.84);opacity:.42}}
@keyframes ambientPulse{50%{transform:scale(1.08);opacity:.8}}
@keyframes coverShine{0%,18%{transform:translateX(-145%) translateZ(70px)}56%,100%{transform:translateX(150%) translateZ(70px)}}
@keyframes particleFloat{0%,100%{transform:translate3d(0,0,0);opacity:.25}50%{transform:translate3d(0,-18px,20px);opacity:1}}
@media(max-width:980px){.knowledge-4d-stage{min-height:500px}.knowledge-4d-object{width:min(390px,88%)}}
@media(max-width:620px){.knowledge-4d-stage{min-height:430px}.knowledge-4d-object{width:min(310px,88%)}.knowledge-4d-cover{top:18%;width:47%;padding:12px 8px;font-size:.86rem}.knowledge-4d-badge{right:4%;bottom:8%;font-size:.58rem}.knowledge-4d-stage::after{bottom:40px;width:210px}}
@media(prefers-reduced-motion:reduce){.knowledge-4d-object,.knowledge-4d-stage::before,.knowledge-4d-stage::after,.knowledge-4d-light,.knowledge-4d-particles i{animation:none}.knowledge-4d-object{transform:none!important}}
</style>

<main class="landing-page">
    <section class="editorial-hero">
        <div class="container editorial-hero-grid">
            <div class="editorial-hero-copy">
                <span class="editorial-kicker">Learn from the best</span>
                <h1>Education<br>that <em>transforms</em><br>your life.</h1>
                <p>Handpicked courses from approved instructors, designed for real progress. Purchase once, complete payment verification, and keep lifetime access.</p>
                <div class="editorial-actions">
                    <a class="editorial-button editorial-button-gold" href="courses.php">Discover courses</a>
                    <a class="editorial-button editorial-button-light" href="how-it-works.php">How it works</a>
                </div>
            </div>

            <div class="knowledge-4d-stage" id="knowledge4dStage" aria-label="Interactive 4D Book of Knowledge held by a real hand">
                <div class="knowledge-4d-particles" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
                <div class="knowledge-4d-object" id="knowledge4dObject">
                    <img src="data:image/webp;base64,UklGRmYoAABXRUJQVlA4IFooAADQCAGdASqNATQDPnk8mkokoyWpIfHpYSAPCWduwNWKOwcx4/A8NzhHH3Dyzpt45F6P8NJwOoXIutz+on+xf/bq3epH/pegDzWujM9KT1x/65UQFYHpzfpFiEnee68Z5FE9sDTecn2K6J/ET+vf6/9p/gG/mf+c5g+eOIxcm0LHSeZz3BYb5jwuU9EXitfod3jlUAtQDkPBc42Oi4dEVHZ8sIXYsO/N1WdroyXdPL205T3dbdpiJsSoW/Xb0WlKgppv/l5uflz6MzQaTrDWbeWnEchygfat31T/uQWEBu24VwJA9wAA+5CC6ygq+NtSiAdkZZDL0yfIKPcIxfYcIrPTVg3L1zXxeA/8Ng0dESiGPRHKACfBe27EOIc8MkdZ3gwFNHntaNFhlOgJuSPcST4o2W6r2z4taTk/Xw/4ktObcyFg21/gI7Mxt9NLA249OSssyXjwBgm2niEHO2fLNn3nZNr4D8a/DZJRtZwShiVuZvvzcgkbtCtloIIhsMuHKFZBFzC1egYB0g9puvjQeOM8sv3o6mHDmwmO6rHFda1+Hq372/prcApFIXYD+sD5w/qirIlo9hUoZs8NLr1NE4DojGG4uq5dnnsXTfoRAd89w06QQhv+bzz+RJH9dZu670MEcY+p0Fk+7zoXXj5AarxZSJ1eMlqkjISEl5dnONX3a8ZHwI8N9NzDcy4Bzj/zJ+kew8n9DYIOhIrarMEtukoiNerAOR5TaCxMctA2vpWKxRhBg8Sa+7WybTaXv2sRrNfC3/HvEhxN0Lh4Gx7UdScUiT7WtJCheUd/BQ3zDTBBYENEQBZitG+6gR2XQO2nGVuXJctxNiFbvQQCqFZZDA3HWagAq0wt8xk2jPzjzvWID2PRpGgna2FtlHsmPkABjcY8Z5U10Kq2hGbkXEGn3pfwIWaY3otOylM8RlSdUgs9UioKNsL33fG3eaztyO1RMbh7XPFCqIaVEtBcBxtKg60u+Dbxv/4/cjspshHOQEjeZobKzubMNEZL3nQlGUxylWTEj7f0OZtaxNHi43dRJZo8ip4XErr62d7n9dO3be+LB8pMj8OO6vOmPhCiVEZGJlF8N+SDI1/BAdUKi86U7aGwhhLE9ZhDh8PR75rkxSBOkBqoUFHjrE689jHKvZ8srp4lHxM7hIJQrU2LiDyCivJfzs4KL0KPs7z7bGaLbwRO0vrcOLdTX/aJTIMeMQy8l6eRssE+BXAizK+toEDLARjTDMUlgk9hOvxVqQdXSNj5wHnbMj3u/NQGU3uvFhrpRe3hSSiygUqzGdGfPvFptah0XNmRs1zaShpQ6kq+XBfBFEFeFDuarCzWL5THUtKHNK+O4b7yzXBxCD65j1p60SmNooYGEYGyHrMlNhAfSfrr9xGspf6LdFNvQVnFYCEtYdGxAyjKLiddPNkEDnAGCV+UOQoi/m65tuoDlEKi3xTyBqAnOWRHV16khzBHZFNqK0LFpMP87zzsEduiae/rNq+l23J8B5c+q2ZGMPagHn1w6MwQJCoG70Mcc6DQzbWhjpus/gR8TU6wNswng4Bm1XgPhAwvnMDGTRqSUGBkXz2ODMy3RB1ngoAAsbpae+sP1VLbn9MKm01uHA2gjP87oKzQwo161OqURyQGNR18dE38T9pnr7Ev04WVAC9caWt+2KtY7I6aagwd5wKfVcUeLU+IcrWxijwqpq1NsrXbn1EsnjcrWDA9pvkp59ZhKmXqg7xM/g8a1fjixwwKzjxdzAwcKJFXbdBgOwGDQFrl68CRdsK1z9wuyzQf1LgttvfRzNzEgPoK030sGoNZxPgN/7UxLEWQ1XrVf7dP9E7BWptrSciDULmYdS+UjzUbQMgAhoNrm/C/MfLjRXFRj8mOqgPaRZKHZeSwyQfTLfR/l+/r/cbUrfDHZRJzxPbjWEGvtJrkcfcOrIFkzdDN1ETJlp9SiouUYV3VQe4UR8P2cfdCcOsJWijUuFE99gJPzvYHgA8m8jxsZRogisAwOywpv4mN/asYVH27Ru3OCAPqhKAgU/DRPqQ2iPiYvWdhxsACEVl6NDw6oMT5fnPxxUzgD6ZP0JFfe3Asc/qIRlLgVAEbhnbMnQIB1uo64p86paqBIDnZMpFLXaLtm4eGrbhwTggSHXbNeGUhqA0CQb5A5ULfRyJQDrZONmZ2mS0Z6HBLrZfdHXe9Vff197kM4x1QANaftyXaPC3d6AUp0TjNQ3m17sCFvpQwJd6xBcqQkBoDmlLqxqrJZw4jvChyPDKghQM/7JWtGeb1YmlqYHecAlcfvjw1Awucf4Tsby6mqH69ItSalPIHv5HuC+h4abFOW8kwIICOYzWqz76f4TONRcOnC1B7jz3mfyhR1Nqmz/7yuet/zu3HDjpjJQErT/5PcA3khj+Qs/PEhCM8qI9JdEoKifzTD6v/qOh0Gy++Q0HGsO9NW/hzDAD/zYDGzp1TWLIzCyNM0MsU+Az9mjapUrcDwACXdU/mgTFc3gadjys8CO4LC1yLlASvbSNUzu9DLm0hZfs3YtraTpOQf9U6ZSNbUQUN4eFEAog7fAxr/8DaupNM3cBwez4/Hp4z+WgEkVyZxJACn/BLMfFx4Mdqm248J1AjDBaIBTGXptWN0G43FoAygMErKvzaGiKRMDS0yfYYx88aLsuDoTPV093cxLW+CUlJTH6Z+g/j9pmW/x8Ixzx40avKmqklI0TEQi2GwXFsGmAwmCVHBCcS50hW99/O2wd9AmBq84FYrUrqvqkKToN0Han2U4cy6jn+FqmNgo9WMx4KWIqgQ/oVs3tCH8Pwfd2pBlyat0SfPotc9YV4E2s5RxFE5cFtfrL+PyG/PKAA/uoke/7n1F1Gwfd2xW274xT87Oc/IWbuGzpJE6Lub+1IGYajfslR0WvoOQroPAtkzSQxFUPeSYc5CRHKMx9JsPEishYlcRfUaCS/p6YxmZnMIkip7BUqVftyyMeAwAe6x3slZkYGWLOARGTgaQdjdUdt+9yzVi0Sh7cavrNga59lUafPG4kHrytG9UANAvQAAh9hzPU8jo4Vfa2tulr8jL/LlO1l9eiy4KXj+qyC5S50bwC3u/oZJMh8pt6oi3ZZ84UKPE68EmeGsvsW08iJK5XJvgYAyUdD2KRBrkM/JTFtEr3Phi9H2eluWVOyXVFU+AfW2p77rqRlWBiC5lxbu7666GK2NhAFDX4VQRC7Iis9YYLb5WEiLVj03avU+PcJKLqvxlusyID8D9nKKUwVvP5y7Pg1D6Bi6n0Gj9DBWBSvvRx353yHZBhtNN16/HKciE6o38nfZBxll4UMe9YLplJrG/CsNHrHcHxwsOw5goxKXvohyCKZS5dqEcg+51QA0MWDVYAAAJXV0RUhehFLf0Y1jBKNB5Ki9FvRwghRaVInQFrhYmssiDfltF0/Al5UlG5Zh65zpFqQOrQPkKjMeh1eGdgGRoHrO8GYhLu5fOWglUT/CgEACcLwjU3C9wsQ+2SiEwDd45SwnoqH+sZzlpOuiT3YqkhOeSIF1ZWuXv9/wz64zcf/uQIIYMPS9IqNf3XMSU1URbw+mYAA/CVP3VXsnRPSqWLZ/PEWciBM2Rak4mORTZ6Nd20dG0a0w4VV6pl/Wr0YcDe7FYCrFmIrX/VSFn8LPhWTyf6jgSw3BwAVNAPOEPF4Cp5TY2+FaCvh1juytT2xJCPzpRU8zCF4QRcAJ3bjsuQM80iTOMC6n3WG0L1bDki/LUN+JiLyoxPYgrdUdQN/xOy1bDKCif8VzGQHFq5SEpLlN9nxitvFFbBRNntb7U9lXD0rcGp7T+27mLxnXsHuUvaMn5C8gFJt6mpOUWAaBbk3bxzWChPf3YJQuHI/jQhgqxtfbYCaMKamquYi9MOCN/8OueODlRS59p7gQEB+i8DkGOS9Ab+N66I/YcHsBpK2cUm/NJ3OEiOL8GZBLhxTg3zj9nzcpbPHC+cgOLpF7WB0XcWjTfuZi+Xu3KhDNcXcst5kUgmG5j++8cMYOneOhe429czIT6o/simTtpsPiT29LerdjNjLCH2oEyeQI882rXM3v0dow8HQ0Qm3TGjJIiU72qKxdORy/n8SD1P2utl5L6TpDe3ByW2UCZ0atJzGQ4CfqpvP9BS+Xv/SD32FrMqqmcju4LfCdAQ8o7I5xKcdcHxKxfB8R5fJZEeoH6efbm6V8XdC6A84TXD42LlJKH1vRJSFwGpKuqBoukC55w3W8wXjncfeAr3ZQGXMf1jig5R7I5lzRLC20KHQSjwI3veZw25QgKZMMdcvYWOebBbmsF8NmrgONEUVuTDdqDRSy3iZJtlEE2y+Bxgeb4uFPUUHsBD8yvdGhoSa88Ybk5JwxaRLxEjpiwz4UjUO4hBdISl99ouR5ol0MVcouJQBvsPqtR2i/vplNUUs7wEzpwgdaR9UUOh/cdXm8XJAtLdogm/JpbsO8CodVdZT/lCQ0Pc1eXVtDrbU0/3kbWKhp2PVGUMEsfD7ebho4yE7q+G4Pp+HS8+fRm87YeKBrApjUqyHRKfXGeXb+hiBhcwZtQz7YxEDG5QUKhm/MQnzXzpfh3AbEtEd+AWUw2otEI0SK+NsRld8VJICSsCPR+39NHL7J16taiEPCWlnhNykCU9SytGkDWNXrlPOR/G9zoY/pYrunaOD/fm+H9ahs63aKKmXgISXh/ygdtbOzCPlAC2cbOSeH0hpwbdWsCnl5E+i/nCbzm9GSb2H9jremyMpZGMtKEWwekBbbzWn+k3Y9zggWlkm4XS7bgOFzSfDk6ZujGP4Bi75MVDe6ezKl7CETzOS6IIKlbu/umW6w8ZOQDIMvj4h5iFC6KOXpjXPFcbS9zO1HwKYory+VWHlTZqCeXgfh250qfaZ8vAkULl3Ar66C1CRXAowT78eTwVBDn/YziGjWshC5WiXmLQ00z8Vnv/2DJfAM5XrlgMSSoyesmhPtvvSu2Zg4I0dKAk2NvHQ1KIzRtIRyUZON0eh++54Yjz1QtCUao5B1Ry1M5d2oOOg8flYIpYXdE2c3vTkH8NhKB9iErBsRFTsc4atFzIW104XZRqlACEKZzEhSxqvOLtWzHdvrbi1VaxFO6HIUMEKczJIwORSIErqjW/48IoonZ8wC8agNR/2VtsZEFOxN20MMw6TD2BYSPy4iEuc5j/n/SWse3U/nH4DCqPrV5ffz+H+RvEXPiqPgxEUkQPYb5yDwgNjayFxK1LVi212ocqp5yrl5yKeDa+4deyZU1SSB6BQJ40HdWTgQOWadNgV8PcKYN/ujWizBZwTpueNCRJcGhiDESM/CvqUMoVMWzApys9XME6xg0xAauUIFmbrE1Py+2+j4hBDWjWmn4G3ryRo4Vqg7fSvtrVA4FvvP5awf0VTSk9/oKsF/W7nRQYZpWvhuQsWRnSuF9ozvaqcfSguL2YmXaiwF8kAoGjE0CcL1+0wcoXOiQTY9zLHAb2ZmPzOqIiK1IjSem46qJ4Ur8g6bcs6DHSBw2NnDEPDQeL2V8PQr6ttQBfpBuIv4ASTr7HHOi3ts2aNIfokitUbq1+gEm7FDosTWirmTfxu13E8zUTb1ReFNacFABZwaW8s3ibxz5idz362PgSvHdcdXaraVfH3/oez+lKQrueJOJjlmgBnoxgwebiKM2QSAvXeYOOK8R8xahENx3tDkAhc1FLY1UMV9H61DC6x2VG2mFg4s1rpKCvzySAQYz1QH5miHdUU8i6xRJGtyEDytuWaenqjx29jAJQSLF2CbeXEaqWQwQyWKj6R5pe1uKf77YMf0pZAhGJ7AwxrTP3atW8NhVrM1DsMP188LsUz7ComifDxmjbHgDb6sqBOIXNr3bcRhpR/dXLFRaeyDRL+Mpe4ocuuz1269I9K0zf3sivtIQANfIbum1fGnk/HT5Mjaq9AtDChfqCbFtmn2weuUUYnQn7KsHwQLbQlfZaA0T+lKF/xOU86/QofsUJeXp6VPTH1/WEsyxe3P/Bczn4mT0vHiSRTu9fWslEEEScwNN+qxFnbP1tPisi/aVRrk0Zv4RCHtg/CayeoVrhnVJIjdTFGoSH8qKhswpH9AXLyEtm55b+gfyve2TmeFRibTktth09jOQnmVQ3/v+phN2QHTRBrxOZs7fXPNY+g+deh6ewUEF9jMZF7ptxiAZloJ9ddXlcJd4o/tHPV9ee4ma9ovPfmXDCCz8NHlzeRejMjWHkbdXlHFF5UzL+JRih+nMTrqvj2fuWKdiGPf0kucmMmwsH5YiJYWwatowZKtRAO5PoO+Y8Vp4vi/zj1zK0X0UwjYyzP+J1JoEkrAD5E8zsh2vo8mBD/EHCHkfMg5e1nb8wAgn9SKwT7XGscwQNhklZf2d8ODMSSDxuZGvbnKWmMQ08HvaWGm+UChn0AcxZn3hu9b1zyTy1K7ahhQcyDjzVMTqSemjeakSEELI2Okz5rRM/OcYs2lvWduXVnxKrsx+bzHlQc/SiwWql34MkBYqMuApgIjZWAMZpZ3Orxnoeh1VMlNVbLXCL2RoT9oYzP91zxibI6DDxuPl2ZZAoYQBefVxft9gIm9dDtI8gxmTVp0D84uKtWoIzrIoNgmJQqRkeUidWneJLT9pN4bY14E+coyOF3X6LKLeM51w4WVGryljIwPbSoB+UXxhO6cbNcbLRaI6JiuYYx/PiMdmEOQEWBNHi0hjbR5FM+MSxlSgDPQSerNh6NsL4EyBzW5n2YrHxL6+BrsGmCQ3WbAWq2Pu5byMMBoSmOuSFB0xSzD45pzjiwOmqtcoKNXPHy1+8fA0lLNN90OFNzxCgz9/llkRmLANDPzyZrBvrUiqerS4Vx/g6N5+VSEaoxgRJweqtQVU6dLeKsQL0d19TORx3VzZvBk4251pD3/GV/9ct2DsYQgt9noNV5b9z59xqcPI9SrXhhW+nkr4ozE47u16iR9FWpGR+Kk6Znp6OqlsA2iFyNqQtaL710nWZeACqmg8clSR8O1wh89tYGlrXjjm4TAszIIYw45xhgyJRp59fDOhJGLBaLEhMx8Ed8Bre3pYBqejNUlBgL/sJ52Vkwk7i0LX76RJjsSqCP6RLsCKUNQrnFa2CEmQtB2bg/ssUhzA7oFUmqSSxgFMxJAM1OIQ7SZxmDB4uy/k25MoZ+zuxvO1yDYOaxG8pj7Fohb4RH1cTAucwXHHBB0roKG9wOfnnpfoAUkNEFHCd/676w63tXkxQwnlLC7iCEDnV66MJrkvZTsnXrU0Ef7H0A6tzad5hGU5NFfuE+3UZYmdYfJGByQ6w+nf3qLen1LY+WVzSMcxN+whrSG6rHsCjC0vNVWAZKO9nNdLqcSdf8zdC/Dj3Yf8N/EpcHapz4Q4HF4dNlhbHZzFsvNBHgi20ZhhmyjNgHwJn/amhQ7+NOfSnsSnW6LOcPbRMw0cP0xKMg85LWl5Xo6n1chQurJ7+djZ23oElpmkez5L9rH0qRz7adxCpEEMPeoPQ0nzr36iumF3OGCPh5ATcB7hupEeUfijJxXSJNMWrAH2wfnvXh2Zk1D7GXfkvAyB8E5MSZnfVGXjghlDgS2lLhec9umx3qk9Uw66t+uNwiXO2teIMyAmRAFkcYspRx3qT5MPimqkeO7+0bKlExSd9bSHnMuZfW+k1r5CuOvmVExparbbPACZjCq2fu3JccdZY2qVaCgGLODoCDCnrAzv35jKgr3tO+bWfnRIC1JpTp0ZcYqEhYz2kdf0xjuFUcFSeMStpnN1Bgm03BKiGmwoN2lN9Ovs4tBwk9Bmby9b2389K4lqwnUL7K81Fi1dtHJJT8M2kNc6pjVWmunfgoS73L5mfv5yHNEUEgqcg/FemSxHtY3YcdWZmFOBYGqVN05kdWv8wGi2h0A2objbg7FCHk5Ys7XA+YmCyfKkFrFy4cWI6ozJ0ruseusJiBORt58TqRLyaFjlk3qS1nwO5GczSS0fvZLZ1DiADBsD/N6917a3nWn1IbXIr4jvjhEj636znfzh1BoQsGvOYyXiTKeGrU3zd9aNyneBY8rXcoQqSQm9i1zAMg8jdXHrHKEKqdq+p8fsVG9xbIlNMXxZMNsdhRLpBTx64e0C3t/7lF+PY8pqkb5iwhair/6+FUe/uOmAG/YrunlGSG1tdlGrvMhLn0TT1EKpDVEvgan8iTRD4flryaNaU9uKjX8nE+AqGfGekPhQA06b6hEuV77r89O8Tk4r5ITOICQeuWScPN0Tm9zBWwkTQDvzbK2G9wAicdHrf5FbEvB11kOHyXW6S3UxDAZ7ejJonazGJIkLp7t6yGNdsYLmK3QffWvcarHEC6omC03XZ2eWhcm3dhHWgwP7tapTRzPK+jUpjniWRD9/bY9AupOeXoRT3QkSrBf3qN3aBKxgvKxhZtIdYhD2isWrPu9cPOjg7fMs7QrmVItJ4fWeyE1RZDKAIKpa7TZuCzJbK7+g80/ovfK0f3h/xZulCr+A3CsVLFcN37ESHAvck6LgUhA5jTC4JIue8PNUXPNNXOfI+5FWTzhlDv3VCq98qoaNTSucjt2PzWL/pu0eBCmVZVTwUKYDDubJDpXpRMqA3PalFZYiR+AfrulCyT0V17HaW4JDEStU1mSremtLqRryMujSaYymFtKfDVe+u1qbcG65HNCMYdLta7gdhR3OqLCK2cyJj3TwZrPLH3DEuN/mu3hPDEVxSiIv1xmiKr8FQVoRORqLpDR1IID0ARdyx0hJ53wOR3FmXPrayRJBPRVt9Kn8QJv739WeVqhVlGmdijWDy/DWFCfbxBOX3vAnjIa8KO6vAQu4ya+JZLVTb1+zIueIq6e1DWO1onInY1HgNzKd5lsbR4VwwHJuHUiAxl37Jbm3YB2/uzCQC0GBR2f5CquYd2+ArqUGvK0HaFYHrRKuvrp9529UY0x2KDLDsbIfEhMagw3z/YX4UzmLGKQJDVDHZR/KDdSOS4CbtzYguNclSVwo+xLgGZuGAoSbH3avzNd3z9CP6Z6jedaH8OR6m4TvYHwFNqLG8mTp/kBRMJiOtA6ZfZni4qyzERgBrHyJLGiOifbpgNHUJH0JJvzJSlrvRMkwYVZTvz5LBFcOT7tBg2LYNxq6g2QUJbMRgLvl4lyxFrym43upz7Mwnqs14cejH6D34cPPKs+dXdnxuwEc8tYUXMrHhQu8MaNKykBXYM2hIc0eZJaMPG7hBACe84tc8wKFVNlVawgK+VFdNYR404I2suSgr5c7XBW2j/xrbjzTWHmW5B1g4W0ycA2P/oIpvuNlQ1faYsu1xoOBthS2s0zxzn/Zfk06sBtFvD1kUGvRLrxx5N9z4S82jahZjc5a4H8KGAXQOSgNhEfAzpRu8I0mpRY687fGbh0HdirdKmRkxrHI+bsD2R9jrqcSasbCXnkWNIu/1Uzx4NbTQla984EFTG2Dfzif3sfCM5LgnKVxcar3tv/OvBPN+lA2ZFD8iz9DLlGjwuQ3jRJoWq5/MV1xCeWF9pR+zbMaqa00D2Iw3D4UPvGWH9d/7GeC2/J8RlZD1Ta39uRXCk7TQURgNlmkWALUtO4i4lJ8bJ9W5fyDVFG5rGm9z2J6U5nJbvSAX7e6gA4fiY8AqhLMXzFYBW0YZfWS0kZH3JHEZaXxISTtV8nmVAfcDoQHc8stc+u5Q/Fkkcvxb7d0cluG0ZF/zbsSsksvXymSp2acohRypvQRJzYTW9UaRx4fXr8IJvfn8RkblxWWSzg+vKpaklXHsklLmRNMz3DwB6qeSqa8KQnzR05tB0v3+oW5O0erHsYTOjcisJsMR0wiZCWV1P0xN653wRi53itl+Fe48jjafZbNm2+ljVDwf4+KWlToYLKtnUNw9FmPWOUMNwBGymJHM708GYh58KFOX3QNijL62BfbXyHPh4HD8UD1ErZyUsx0/o6+alR/TEXyoGLFghpg4Pbsg7Ptn+gvU119veMvotNdVTPRsMvROkC5wPQi6lmqeJnZRh0ewZCp19V/9bKB9pavk4JLnDb866LbPRK8kPg0MQjgnoBNr9V2Fv73eTf1KIWvCmQpN4lIZ5t5vy62tcs3J8w4YSrc2gzO6TtriFOIlSVYQaYXP1AUPuO+F3BOuOhaun8U1Kd4woudz/cdexb0N0oMRL1CasOdY2KtgwAG+Wj2wdUE3DdgaFxzOXgl/HwzjIa5/GFAdtyvVWuFdyqtvnb3T63w+BYTCX6Oe9fTQnHziQOHljUw7RRzsGv+ih6+W49yp+10bCxqfpm3mfnHeAOy/JZun3cxVNQAcrIZBQf4yiImDxyUK5j4q5pS2/UIHMhdtGMPgtl7it1lD1HkcQTFiPGpkEkwqU5ucWErAduxitrtyKz+PC6BZ4Tf54yGIimTGb9772H2wbECWEDlscYUfs+Y56JbHIn44jQiVgnaaG0PcV+IXsCeUDsACmutUbWB8nAHMayvOrg+O0WBFQGL97fddIHTiIuRWBG+OIlFazKdkAWZ4KGOyqQdVVpkT28HfIUE0A4qlpM4GIhnITCp3lMUZjNlUvMHemjhT10KZjAUaPzSwJ7pYZfYYLmHDgJNVpUEgt6s1spCCfuUvbZNnERcV/NwO9M8i024v3UVyoyn/WR4Fd+0sPzZVdg33ChwtoKqpQWW6lBloH5ppmMR4bEMt8bkfEQLDNDm6znfA7ban6p4co7FknmKjZ3yF5YJlXxHemFINvE8zwu7WcUo/icWmSLcb62tTHnqPojm0JAmHu/UyiVRbqevQzmWa7b2c2HUncXr4JBpwhF9X8/Y7qN92ZHxlWJprXQEICCR6JewupPNPy6lNeqMGUhl2EoNhkwWoHA0is+RAmlJR2RO6eBFc9k5D72/msTKHAeKNChCbsqRi8wGf62ZE9KaOm3R/phHa723yVKruO9bQXQ4QtnT5yRdajbwPMWIRAiQJC73aiyvebrjk/MnnCVXhtlqtCY2XiKdIgzXVyz23FFkh/UNj4HA8Nw/cRsgvPiO1dEO742KFAyiQUgzFHbuCTRAPk7vNPUplBUD4UX/fQVZoUxFXxHcZXQyOm+DoVwiHPEt91TaF7z3UkzKsbjK8OHyNbxTHsDw01nUyPYSujbL7BXoxN1QmfF7JRAKDBIqfT5l2bJoiicxZveCi+OKOdaUOcqN6vZ0EKjSNsv07JjtEPN2+Yt0rqp7Wh2Y3TAZcKYRXxkpkGuka7eyio5wyISCkyckEQWV0mQLVVvGRf065R5vHD+n51CngoQMLi+fgKDDO3qOGqdyvYHN1UJVOQGO29Gnd2YoHUC/FF+2sGdyiEC3XCFAHYFAWrNkAUNo1NAChc0LTkYuDFEG1CozH3dkrqoK09r+yB0Xs3bvGPL6DR96Mv89ZrXwrIepvOPCVEUzZoPiKkg6/a7MpxwRyYx8v4rWoXjOdhTz1ucQVO6YOOXsSjnSJvwwzq1qmDwzRUthf0jH8Q5J+5+Z1rirK58Hx1t4+h7cSGojcKcWkkClg7F3tdQSod0l5KjO3pJdog+YkfaHgicQwhFm1fvKffBjJhs0HMvJZPxLj+xsvj5Yg79LoQpNZDYHtpMmv/xUElTRt/9AQlYQB5gbxIpDyMgv13UdFV8WRTVtn9m9/t5XvoLCFevbfPQlUwhcmCFbMAXl6QmnwX3b+4sWa957mmalzinq4DZHXaHd77JKF0b4imVRIjdIF7Z5+ZajoMM6aeofQ/RRuOJBCKUqdv7KgH90sVKpVeUKi78oXhqOrCcSjb8TW2P3aRHBruXDUjEXMR4KMNYHZ+s7NOeHlYXXpP1QuD0jbKV/U6C5OMvgbNvz3NphjDjqcldpU62fDY7/lCGd8MZEo2qHePduGaZLGP6xWgdg8uXHdoynAAvs/7WLsoJFbNTgC1L6q1QA5s1Co2Tt1Air+uW3swelHceMJk+HL4xNJtNxlQt3SVhbRpL9YkvndrFfaU5BABVvSbTzezv2DgRC+szCs4wqlTNQjFX8N7QthVudoXFNWaEjW2+U5BdDH9uwRq5cKGRZcgqc2MsufG6GHW/uyKWILKZkNLPu6Q00KoeMbfHPa5T9ri1J++HGreLYSz16wo7sNqNKheeTRZ5EfS9DhIug+d/tXuflBvPi9loOUq7r92snvZ3lD84qYSCW3mslRm5ZgtUOszRFp+9SZGV9hn6GwRk94FcCXU9fAzbfJ5uI43aQW0/wm/s02coRvikpAATwxt2JyDF920FkOElTaWzmKmVMk6gM5VSSHBkh77M0QoQzzpxMj/GtsmDA+8fBdB7ZRK65mtlMWdLf6vIC7v7DDPDXtcqsMtEOnAxp//BJOk7s/QVkHzjYYkdoTp70QcShLFtbCY885i0ifeKBpXCJ+d5w1CkcBrsnrPLTYpll9UkTH2r85Y8XosTqIPwha8fecejsChsYfJFWjpZBF1VETlSbJjlvoU0SWXmjP5B8Zp6m/Hk02PRg0Rc4+OveHkWCufwnzi18Q0hi0TBBwcPg8J09olTloWF4etVvjV79pgKfyrH0al1qAzQyX8jVgBY7w7bGRhFNmg8zCg2iIg4XgtPXQFH0zYn+R0lmkmNXoH4KcyRm1o50yRPhD9OismVkkE1lQ/tBVSiA0bnTtwERJyQVnplzHLi73gGWFRdqXd1ZFzxOYQvJol4ejVRQ37DWVlN5PaRNwTpXQ2uIeXEDdQrlsKavc1ickfh+9HFQE2XWzasK1VvOPBtDoeXaWgacy7b5cKA8zfKtxkjXz8a8IcsXIgEf2hUdttuRd25xxKX+4jIwlIL3Rf4X1l5sB/94UcDLFaEUeqJpkCy5n1kPctw8F/ybnp9PuFqPN1Ks5Erc4POpWK/wyMpvYeD82d5VaYybVHc6DSWfCHj6KIn1IlhUufK4Jw2f4RDM6Q2o2ZRnSX3nR43oQVw2BZxjLDThkJC2qB4ofcCo2voYTfXCjVztdNeDDtZPMwJT1VWsKGLcKyXglpY+KRNNQLSPNE6odNB8rQoclJclaEE2KV2ESouTUlrcuXhARBDsSTL358N6r+XVdzZ5hsgPwZXDRn6AKy9s+dDnvlPQplsaSANmiTVKd11dqlX0TCKCJeLAFIpVJLEUWBmEngAWlC2D6VqvK5eCDIRu7uQYF6VvqLUAeCpOtwrvkw+fzRIgLYP58wdlRfdINeJMnTp2yMQrF3AuBHAnuZB/xMs1okzhWarVLiHkyEpLQHe5gdc0EfvQRMxG0mYNaAspgR9LHRJuQixr9FC0n+ICqEbSS9dh/NWPXdbSaPrH7tcfk6zLMOe9jFi6xtAzHi2rjujM9L8Ch68Or2GqtbJbxlw0zp73YvffUuoF8Pzn/zd2nqv8WsUhhyhFY2x7eNI8M5Yo58A/VQDZ3TA85T26+Yc9UCyEHUuGv+r1NinEP9hH4AXksCLKM8S1ljfJZ4SDFY0MGl+r2I9+AAC/LydRLsEn+XXfG7HzdSH30IpP9Ja3NjA7Se7MEDQ880qBFeM8WuVprI0GWWCxVRdAZZY9k77b8anIouV1lJk8CfwGDlOX+ICisFafhYkrl0jUFx6MSwz4x55ECrIJQ8TjDzqu93rvzgg0mOYT6buvbNrPsGh8xuHpMVSXkIEmqupVi+ZUBsFqaLNwk/JPBX5ppdPtUbIQyyCYgX9Vm1m/d/iPj6TD6RKAMl22kxFNf6/lqzy+kkLack5smrPlJOWnV60UOpO4KDSuKluEW3Hi53wH9CeTvaQjeK7u6/dIVqwAFEh0Nfj11DHPr44bUiYOQA5YntGSdT1WHPH9/51NazKd+J1ALEL6cI5ERwjyGKO77n2U8iK4SemMKWad5HqF/qY6N5rsgvlnkNTRCDLAAA=" alt="A real hand holding a book">
                    <div class="knowledge-4d-cover"><small>COURSEHUB EDITION</small>THE BOOK OF<br>KNOWLEDGE<span>LEARN · BUILD · GROW</span></div>
                    <div class="knowledge-4d-light" aria-hidden="true"></div>
                </div>
                <div class="knowledge-4d-badge">Move your mouse</div>
            </div>
        </div>
    </section>

    <section class="editorial-courses">
        <div class="container">
            <div class="editorial-section-heading">
                <h2>Featured learning,<br>presented like a collection.</h2>
                <p>Explore approved courses built for practical learning and lifetime access.</p>
            </div>
            <div class="editorial-course-list">
                <?php if ($featuredCourses): ?>
                    <?php foreach ($featuredCourses as $index => $course): ?>
                        <article class="editorial-course-card editorial-tone-<?php echo ($index % 3) + 1; ?>" style="--stack-index: <?php echo $index; ?>;">
                            <div class="editorial-course-copy">
                                <span class="editorial-course-number"><?php echo str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT); ?> · <?php echo landing_h(strtoupper((string) $course['level'])); ?></span>
                                <h3><?php echo landing_h($course['title']); ?></h3>
                                <p><?php echo landing_h($course['short_description']); ?></p>
                                <div class="editorial-course-meta"><span><?php echo landing_h($course['instructor_name']); ?></span><span><?php echo landing_price($course['price']); ?></span></div>
                                <a class="editorial-button editorial-button-dark" href="course-details.php?slug=<?php echo urlencode((string) $course['slug']); ?>">View course</a>
                            </div>
                            <a class="editorial-course-art" href="course-details.php?slug=<?php echo urlencode((string) $course['slug']); ?>" aria-label="View <?php echo landing_h($course['title']); ?>">
                                <img src="<?php echo landing_h(landing_thumbnail($course)); ?>" alt="<?php echo landing_h($course['title']); ?>">
                                <span><?php echo landing_h(strtoupper(substr((string) $course['title'], 0, 1))); ?></span>
                            </a>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <article class="editorial-empty-state"><span>Courses are being prepared</span><h3>Published courses will appear here.</h3><p>Explore the full catalogue or return after instructors publish approved courses.</p><a class="editorial-button editorial-button-dark" href="courses.php">Open courses</a></article>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="editorial-directory">
        <div class="container">
            <div class="editorial-directory-intro"><h2>Everything important has its own page.</h2><p>Students can move from discovery to browsing, payment guidance, and account creation without dead navigation.</p></div>
            <div class="editorial-directory-grid">
                <article><span>Courses</span><h3>Browse and filter</h3><p>Search, sort, change view, and filter by category and level.</p><a href="courses.php">Open courses</a></article>
                <article><span>Process</span><h3>Understand access</h3><p>See how payment verification and lifetime access work.</p><a href="how-it-works.php">See process</a></article>
                <article><span>Instructors</span><h3>Teach with structure</h3><p>Create course content, submit it for review, and manage enrolled students.</p><a href="register.php?role=instructor">Become instructor</a></article>
                <article><span>Account</span><h3>Continue learning</h3><p>Sign in to access purchases, progress, and notifications.</p><a href="login.php">Log in</a></article>
            </div>
        </div>
    </section>

    <section class="editorial-stats">
        <div class="container editorial-stats-grid">
            <div><strong><?php echo number_format($stats['students']); ?></strong><span>Students</span></div>
            <div><strong><?php echo number_format($stats['courses']); ?></strong><span>Courses</span></div>
            <div><strong><?php echo number_format($stats['instructors']); ?></strong><span>Instructors</span></div>
            <div><strong><?php echo number_format($stats['enrollments']); ?></strong><span>Enrollments</span></div>
        </div>
    </section>
</main>

<script>
(function () {
    const stage = document.getElementById('knowledge4dStage');
    const object = document.getElementById('knowledge4dObject');
    if (!stage || !object || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    stage.addEventListener('pointermove', function (event) {
        const rect = stage.getBoundingClientRect();
        const x = (event.clientX - rect.left) / rect.width - 0.5;
        const y = (event.clientY - rect.top) / rect.height - 0.5;
        object.style.animation = 'none';
        object.style.transform = 'translateY(-8px) rotateX(' + (-y * 12).toFixed(2) + 'deg) rotateY(' + (x * 16).toFixed(2) + 'deg)';
    });

    stage.addEventListener('pointerleave', function () {
        object.style.transform = '';
        object.style.animation = '';
    });
})();
</script>
