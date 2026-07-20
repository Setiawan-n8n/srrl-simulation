(function () {
    'use strict';

    var NS = 'http://www.w3.org/2000/svg';
    var CFG = window.SIMULATION_CONFIG || {};

    // ---- Layout constants (mode skema generik) ----
    var TRACK_SPACING = 70; // jarak antar jalur (px, satuan viewBox)
    var TRACK_Y_START = 90; // Y jalur pertama
    var X_EDGE_BARAT = 10;
    var X_TRUNK_BARAT = 170;
    var X_WESEL_BARAT = 280;
    var X_STATION_LEFT = 280;
    var X_STATION_RIGHT = 920;
    var X_WESEL_TIMUR = 920;
    var X_TRUNK_TIMUR = 1030;
    var X_EDGE_TIMUR = 1190;
    var X_HOME = (X_STATION_LEFT + X_STATION_RIGHT) / 2;

    // Nilai default (dipakai kalau API belum sempat mengirim 'settings',
    // mis. saat memuat data pertama kali). Nilai SEBENARNYA yang dipakai
    // simulasi datang dari server tiap loadData() -- lihat state.settings
    // -- dan bisa diatur lewat admin panel (App\Filament\Pages\
    // SimulationSettings) tanpa perlu ubah kode ini.
    var DEFAULT_SETTINGS = {
        approach_minutes: 4,   // menit animasi masuk/keluar
        dwell_static_minutes: 3,   // menit KA "muncul" statis sebelum berangkat (tanpa jam datang)
        arrival_only_dwell_minutes: 15, // FALLBACK: dipakai hanya kalau baris "kedatangan saja" tidak
                                         // punya pasangan baris "Dinas Rangkaian" yang bisa ditautkan
                                         // (lihat findCompanionDeparture()) -- kalau pasangannya
                                         // ketemu, waktu berangkat pasangan itulah yang dipakai,
                                         // bukan tebakan menit ini. 15 menit dipilih berdasarkan
                                         // data nyata: seluruh pasangan kedatangan->keberangkatan
                                         // yang berhasil ditautkan di jadwal SGU 15 Juli 2026
                                         // jaraknya <=30 menit -- 45 menit (nilai lama) jauh lebih
                                         // panjang dari kenyataan operasional itu.
    };

    var ZOOM_MIN = 0.5;
    var ZOOM_MAX = 3;
    var ZOOM_STEP = 0.25;

    // Ukuran ikon kereta (dipakai juga oleh getPhase() untuk menghitung titik
    // berhenti presisi di ujung peron, jadi tidak boleh hanya lokal ke drawTrain()).
    var TRAIN_W_REAL = 76, TRAIN_H_REAL = 24;
    var TRAIN_W_GENERIC = 46, TRAIN_H_GENERIC = 16;

    var KATEGORI_COLOR = {
        penumpang: '#3ca7f6',
        komuter: '#2dd4bf',
        barang: '#f97316',
        dinas: '#94a3b8',
        langsir: '#94a3b8',
        lainnya: '#c084fc',
    };

    var state = {
        data: null,
        realMode: false,
        trackGeomByCode: {},
        trackIdToCode: {},
        clockMin: 0,
        playing: false,
        speed: 1,
        timer: null,
        zoom: 1,
        // Parameter waktu animasi aktif, diisi ulang dari API tiap
        // loadData() (lihat DEFAULT_SETTINGS di atas & applySettings()).
        settings: DEFAULT_SETTINGS,
        // row.id (baris "kedatangan saja") -> baris "Dinas Rangkaian"
        // pasangannya yang ditemukan (atau tidak ada kuncinya kalau tidak
        // ada pasangan). Dibangun sekali per loadData() oleh
        // linkCompanionRows(). Dipakai getPhase() supaya baris kedatangan
        // itu "mengambil alih" animasi keberangkatan pasangannya --
        // bukan menebak durasi parkir dengan arrival_only_dwell_minutes.
        linkedDeparture: {},
        // row.id (baris "Dinas Rangkaian") yang sudah "diserap" ke baris
        // kedatangan pasangannya di atas -- baris ini sendiri TIDAK PERNAH
        // digambar terpisah (lihat getPhase()), supaya tidak ada 2 ikon
        // kereta di titik yang sama untuk 1 rangkaian yang sama.
        absorbedRowIds: {},
        // Kunci pasangan tabrakan yang terakhir kali dilaporkan (popup +
        // log), dipakai supaya popup tidak muncul berulang tiap tick
        // selama simulasi tetap pause di tabrakan yang sama. Direset ke
        // null begitu kedua kereta itu sudah tidak lagi overlap, sehingga
        // tabrakan baru (pasangan lain, atau pasangan sama di waktu lain)
        // tetap akan terdeteksi.
        lastNotifiedCollisionKey: null,
        // id baris jadwal yang sedang ditandai bertabrakan pada tick ini,
        // dipakai drawTrain() untuk memberi highlight visual (lihat class
        // .train-collision di simulation.css).
        collisionRowIds: [],
        // Set pasangan jalur yang BERTETANGGA langsung di ladder wesel
        // (berbagi rel/lead sebelum bercabang sendiri-sendiri), diisi dari
        // data.track_adjacencies (lihat TrackAdjacencySeeder & komentar di
        // migrasi track_adjacencies). Key: "<kodeA>|<kodeB>|<sisi>" dan
        // "<kodeB>|<kodeA>|<sisi>" (dua arah), value: true. Dipakai
        // findCollision() supaya "konflik ladder wesel" hanya dicek untuk
        // pasangan jalur yang memang benar-benar bertetangga secara fisik
        // (diverifikasi dari gambar emplasemen), bukan sekadar tebakan
        // "sisi yang sama" -- itu heuristik lama yang jauh lebih berisik
        // (puluhan false alarm/hari, lihat catatan di reportCollision()).
        // Kalau kosong (mis. stasiun selain SGU yang belum dipetakan),
        // konflik ladder wesel otomatis tidak pernah terdeteksi -- lebih
        // aman daripada menebak.
        trackAdjacency: {},
    };

    // ---------------------------------------------------------------------
    // Utilities
    // ---------------------------------------------------------------------
    function el(tag, attrs, parent) {
        var e = document.createElementNS(NS, tag);
        for (var k in attrs) {
            if (Object.prototype.hasOwnProperty.call(attrs, k)) {
                e.setAttribute(k, attrs[k]);
            }
        }
        if (parent) parent.appendChild(e);
        return e;
    }

    function timeToMin(t) {
        if (!t) return null;
        var parts = t.split(':');
        return parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
    }

    function fmtClock(min) {
        min = ((Math.floor(min) % 1440) + 1440) % 1440;
        var h = Math.floor(min / 60);
        var m = min % 60;
        return (h < 10 ? '0' + h : h) + ':' + (m < 10 ? '0' + m : m);
    }

    function lerp(a, b, t) {
        t = Math.max(0, Math.min(1, t));
        return a + (b - a) * t;
    }

    function trackY(i) {
        return TRACK_Y_START + i * TRACK_SPACING;
    }

    function fmtKm(km) {
        if (km == null || isNaN(km)) return '-';
        var whole = Math.floor(km);
        var meter = Math.round((km - whole) * 1000);
        var meterStr = String(meter);
        while (meterStr.length < 3) meterStr = '0' + meterStr;
        return whole + '+' + meterStr;
    }

    /**
     * Sudut arah (derajat, konvensi SVG: 0 = ke kanan/+x, positif = searah
     * jarum jam karena sumbu-y menghadap bawah) dari titik `a` ke titik `b`
     * pada polyline. Dipakai buat memutar badan ikon kereta supaya SEJAJAR
     * garis track pada tikungan/wesel (lihat drawTrain()), bukan cuma
     * ikut posisi x/y-nya saja sementara badannya tetap datar.
     */
    function angleBetween(a, b) {
        return Math.atan2(b.y - a.y, b.x - a.x) * 180 / Math.PI;
    }

    /**
     * Cari titik (x,y,km,angle) pada polyline `points` (terurut menaik
     * menurut x, dari ujung barat ke ujung timur jalur) untuk suatu nilai x
     * tertentu, dengan interpolasi linear di antara dua titik terdekat.
     * Inilah yang membuat posisi kereta SELALU berada di atas titik
     * koordinat jalur (bukan sekadar garis lurus sembarang) -- kalau suatu
     * jalur digambar dengan lengkungan/tikungan (seperti jalur VI menjelang
     * wesel 273 di data SGU), animasi akan tetap mengikuti bentuknya persis
     * karena y, km, DAN angle-nya semua diinterpolasi dari titik-titik asli
     * segmen yang sedang dilalui -- tidak pernah "memotong" ke segmen lain
     * atau meninggalkan polyline aslinya.
     */
    function samplePointAtX(points, x) {
        if (!points || !points.length) return { x: x, y: 0, km: null, angle: 0 };
        var first = points[0], last = points[points.length - 1];
        if (x <= first.x) {
            var nextA = points.length > 1 ? points[1] : first;
            return { x: first.x, y: first.y, km: first.km, angle: angleBetween(first, nextA) };
        }
        if (x >= last.x) {
            var prevB = points.length > 1 ? points[points.length - 2] : last;
            return { x: last.x, y: last.y, km: last.km, angle: angleBetween(prevB, last) };
        }
        for (var i = 0; i < points.length - 1; i++) {
            var a = points[i], b = points[i + 1];
            if (x >= a.x && x <= b.x) {
                var t = (b.x === a.x) ? 0 : (x - a.x) / (b.x - a.x);
                return {
                    x: x,
                    y: lerp(a.y, b.y, t),
                    km: (a.km != null && b.km != null) ? lerp(a.km, b.km, t) : null,
                    angle: angleBetween(a, b),
                };
            }
        }
        return last;
    }

    function trainDims() {
        return state.realMode
            ? { w: TRAIN_W_REAL, h: TRAIN_H_REAL }
            : { w: TRAIN_W_GENERIC, h: TRAIN_H_GENERIC };
    }

    /**
     * Titik berhenti (x) supaya SISI DEPAN kereta segaris dengan ujung
     * peron, tergantung arah datangnya kereta:
     * - datang dari barat (bergerak ke arah timur/+x): depan kereta = sisi
     *   timur ikon -> berhenti dengan sisi timur ikon pas di peronEastX.
     * - datang dari timur (bergerak ke arah barat/-x): depan kereta = sisi
     *   barat ikon -> berhenti dengan sisi barat ikon pas di peronWestX.
     * Kalau data peron belum ada utk jalur ini, fallback ke titik tengah
     * jalur supaya tidak error.
     */
    function stopXFor(geom, asalSide) {
        var w = trainDims().w;
        if (geom.peronWestX == null || geom.peronEastX == null) {
            return (geom.baratX + geom.timurX) / 2;
        }
        return asalSide === 'timur' ? (geom.peronWestX + w / 2) : (geom.peronEastX - w / 2);
    }

    /**
     * Geometri per-jalur, dinormalisasi supaya getPhase() bisa dipakai sama
     * baik untuk mode skema generik maupun mode "denah asli" (real mode).
     * - points: polyline titik koordinat (x,y,km) sepanjang jalur, dari
     *   ujung barat ke ujung timur -- inilah "titik-titik koordinat track"
     *   yang wajib diikuti kereta (lihat samplePointAtX()).
     * - baratX/baratY, timurX/timurY: ujung terluar jalur (tempat kereta
     *   muncul & menghilang -- SELALU titik pertama/terakhir dari points,
     *   tidak pernah di tengah).
     * - peronWestX/peronEastX: batas peron (dipakai stopXFor()).
     * Pada mode denah asli, nilainya diambil dari track.diagram_path hasil
     * digitisasi presisi (lihat TrackSeeder::seedJalurSguPresisi()).
     */
    function buildTrackGeom(track, index, isReal) {
        if (isReal && track.diagram_path) {
            var dp = track.diagram_path;
            var points = (dp.points || []).map(function (p) {
                return { x: p[0], y: p[1], km: p[2] };
            });
            if (!points.length) {
                points = [
                    { x: dp.west_entry[0], y: dp.west_entry[1], km: null },
                    { x: dp.east_entry[0], y: dp.east_entry[1], km: null },
                ];
            }
            var siding = null;
            if (dp.siding && dp.siding.points && dp.siding.points.length) {
                siding = {
                    forRelasiCode: dp.siding.for_relasi_code || null,
                    points: dp.siding.points.map(function (p) {
                        return { x: p[0], y: p[1], km: p[2] };
                    }),
                };
            }
            return {
                y: dp.y,
                points: points,
                baratX: points[0].x,
                baratY: points[0].y,
                timurX: points[points.length - 1].x,
                timurY: points[points.length - 1].y,
                peronWestX: dp.dwell_start_x,
                peronEastX: dp.dwell_end_x,
                peronYTop: dp.peron_y_top,
                peronYBottom: dp.peron_y_bottom,
                siding: siding,
            };
        }
        var y = trackY(index);
        return {
            y: y,
            points: [{ x: X_EDGE_BARAT, y: y, km: null }, { x: X_EDGE_TIMUR, y: y, km: null }],
            baratX: X_EDGE_BARAT,
            baratY: y,
            timurX: X_EDGE_TIMUR,
            timurY: y,
            peronWestX: X_HOME - 60,
            peronEastX: X_HOME + 60,
            peronYTop: null,
            peronYBottom: null,
            siding: null,
        };
    }

    // ---------------------------------------------------------------------
    // Data loading
    // ---------------------------------------------------------------------
    /**
     * Buang baris jadwal duplikat (mis. akibat data seed yang sempat
     * ter-reseed beberapa kali di sisi server sehingga ada baris kembar
     * dengan no_ka + jam + jalur yang sama persis). Kunci dedup sengaja
     * TIDAK memakai id baris (karena baris duplikat punya id berbeda-beda),
     * melainkan kombinasi kolom yang secara logis harus unik per KA.
     */
    function dedupeJadwal(jadwal) {
        var seen = {};
        var out = [];
        jadwal.forEach(function (row) {
            var key = [
                row.no_ka, row.urutan, row.jam_datang, row.jam_berangkat,
                row.track ? row.track.code : '',
                row.asal ? row.asal.code : '', row.tujuan ? row.tujuan.code : '',
            ].join('|');
            if (!seen[key]) {
                seen[key] = true;
                out.push(row);
            }
        });
        return out;
    }

    /**
     * Nilai settings.* aktif, diambil dari API (App\Models\SimulationSetting,
     * bisa diubah lewat admin panel) dengan fallback ke DEFAULT_SETTINGS
     * kalau field-nya tidak ada di response (mis. API versi lama).
     */
    function applySettings(settings) {
        settings = settings || {};
        state.settings = {
            approach_minutes: settings.approach_minutes != null ? settings.approach_minutes : DEFAULT_SETTINGS.approach_minutes,
            dwell_static_minutes: settings.dwell_static_minutes != null ? settings.dwell_static_minutes : DEFAULT_SETTINGS.dwell_static_minutes,
            arrival_only_dwell_minutes: settings.arrival_only_dwell_minutes != null ? settings.arrival_only_dwell_minutes : DEFAULT_SETTINGS.arrival_only_dwell_minutes,
        };
    }

    /**
     * Cari baris "Dinas Rangkaian <Nama>" yang merupakan kelanjutan NYATA
     * dari baris `row` (baris "kedatangan saja") -- yaitu baris lain di
     * JALUR YANG SAMA, berkategori 'dinas', hanya punya jam berangkat
     * (bukan jam datang), namanya memuat nama KA `row` (pola penamaan
     * "Dinas Rangkaian <Nama>" ini konsisten dipakai TrainCategorizer &
     * data jadwal KAI), dan berangkat SESUDAH `row` datang DALAM
     * MAX_COMPANION_GAP_MIN MENIT. Batas jarak waktu ini sengaja dipasang
     * karena nama layanan seperti "Gayabaru Malam Selatan" atau "Argo
     * Wilis" bisa MUNCUL LAGI beberapa jam kemudian untuk keberangkatan
     * lain di hari yang sama (jadwal pulang-pergi) -- tanpa batas ini,
     * kedatangan pagi bisa salah tertaut ke baris "Dinas Rangkaian"
     * bernama sama yang sebetulnya milik keberangkatan malam (sudah
     * diverifikasi terjadi pada data asli sebelum batas ini ditambahkan:
     * jarak pasangan yang BENAR semuanya <=30 menit, sedangkan yang salah
     * berjarak 300+ menit). Kalau ada beberapa kandidat yang lolos, dipilih
     * yang jam berangkatnya PALING DEKAT.
     *
     * @param {object} row
     * @param {object[]} allRows
     * @param {Object.<number, boolean>} [claimed] id baris keberangkatan yang
     *   sudah "dipakai" kedatangan lain -- lihat linkCompanionRows().
     */
    var MAX_COMPANION_GAP_MIN = 60;

    function findCompanionDeparture(row, allRows, claimed) {
        if (!row.jam_datang || row.jam_berangkat) return null;
        var trackCode = row.track ? row.track.code : null;
        if (!trackCode) return null;
        var namaLower = (row.nama_ka || '').toLowerCase();
        if (!namaLower) return null;
        var datMin = timeToMin(row.jam_datang);

        var best = null;
        var bestBer = null;
        allRows.forEach(function (r) {
            if (r.id === row.id) return;
            if (claimed && claimed[r.id]) return;
            if (r.kategori !== 'dinas') return;
            if (!r.jam_berangkat || r.jam_datang) return;
            if (!r.track || r.track.code !== trackCode) return;
            var rNama = (r.nama_ka || '').toLowerCase();
            if (rNama.indexOf(namaLower) === -1) return;
            var berMin = timeToMin(r.jam_berangkat);
            if (berMin < datMin) return;
            if (berMin - datMin > MAX_COMPANION_GAP_MIN) return;
            if (best === null || berMin < bestBer) {
                best = r;
                bestBer = berMin;
            }
        });
        return best;
    }

    /**
     * Bangun state.linkedDeparture & state.absorbedRowIds untuk seluruh
     * jadwal yang baru dimuat -- lihat komentar pada kedua field itu di
     * `state` dan pada findCompanionDeparture() di atas. Ini yang membuat
     * getPhase() tidak lagi perlu menebak arrival_only_dwell_minutes untuk
     * kasus yang datanya sebenarnya sudah lengkap (ada baris "Dinas
     * Rangkaian"-nya), sehingga jauh lebih akurat terhadap jadwal asli.
     *
     * Baris kedatangan diproses dari yang PALING PAGI dulu, dan begitu
     * sebuah baris keberangkatan "diklaim" oleh satu kedatangan, baris itu
     * dicoret dari daftar kandidat kedatangan lain (`claimed`) -- supaya
     * 2 kedatangan berbeda tidak pernah berebut 1 baris "Dinas Rangkaian"
     * yang sama.
     */
    function linkCompanionRows(jadwal) {
        var linked = {};
        var absorbed = {};
        var claimed = {};

        var arrivalsOnly = (jadwal || []).filter(function (row) {
            return row.jam_datang && !row.jam_berangkat;
        }).slice().sort(function (a, b) {
            return timeToMin(a.jam_datang) - timeToMin(b.jam_datang);
        });

        arrivalsOnly.forEach(function (row) {
            var companion = findCompanionDeparture(row, jadwal, claimed);
            if (companion) {
                linked[row.id] = companion;
                absorbed[companion.id] = true;
                claimed[companion.id] = true;
            }
        });

        state.linkedDeparture = linked;
        state.absorbedRowIds = absorbed;
    }

    /**
     * Bangun state.trackAdjacency dari data.track_adjacencies (lihat
     * ScheduleController::index() & TrackAdjacencySeeder). Disimpan dua
     * arah supaya lookup-nya tidak peduli urutan (A,B) vs (B,A).
     */
    function buildTrackAdjacency(list) {
        var map = {};
        (list || []).forEach(function (a) {
            if (!a.track_a || !a.track_b || !a.side) return;
            map[a.track_a + '|' + a.track_b + '|' + a.side] = true;
            map[a.track_b + '|' + a.track_a + '|' + a.side] = true;
        });
        state.trackAdjacency = map;
    }

    function tracksAreAdjacent(codeA, codeB, side) {
        return !!state.trackAdjacency[codeA + '|' + codeB + '|' + side];
    }

    function loadData(tanggal, stasiun) {
        var url = new URL(CFG.apiUrl, window.location.origin);
        if (tanggal) url.searchParams.set('tanggal', tanggal);
        if (stasiun) url.searchParams.set('stasiun', stasiun);

        return fetch(url.toString())
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.jadwal) data.jadwal = dedupeJadwal(data.jadwal);
                state.data = data;
                state.realMode = !!(data.stasiun && data.stasiun.diagram_svg_path);
                state.trackGeomByCode = {};
                state.trackIdToCode = {};
                data.tracks.forEach(function (t, i) {
                    state.trackGeomByCode[t.code] = buildTrackGeom(t, i, state.realMode);
                    state.trackIdToCode[t.id] = t.code;
                });
                applySettings(data.settings);
                linkCompanionRows(data.jadwal);
                buildTrackAdjacency(data.track_adjacencies);
                return data;
            });
    }

    // ---------------------------------------------------------------------
    // Train phase / position calculation
    // ---------------------------------------------------------------------
    function getPhase(row, t) {
        // Baris "Dinas Rangkaian" yang sudah ditautkan sebagai kelanjutan
        // baris kedatangan lain (lihat linkCompanionRows()) TIDAK PERNAH
        // digambar sendiri -- ceritanya sudah diambil alih sepenuhnya oleh
        // baris kedatangan pasangannya di bawah, supaya tidak ada 2 ikon
        // untuk 1 rangkaian yang sama.
        if (state.absorbedRowIds && state.absorbedRowIds[row.id]) {
            return { visible: false };
        }

        var trackCode = row.track ? row.track.code : null;
        var geom = trackCode !== undefined ? state.trackGeomByCode[trackCode] : undefined;
        if (!geom) {
            return { visible: false };
        }

        var APPROACH_MIN = state.settings.approach_minutes;
        var DWELL_STATIC = state.settings.dwell_static_minutes;
        var ARRIVAL_ONLY_DWELL = state.settings.arrival_only_dwell_minutes;

        var dat = timeToMin(row.jam_datang);
        var ber = timeToMin(row.jam_berangkat);
        var asalSide = row.asal ? row.asal.side : 'barat';
        var tujuanSide = row.tujuan ? row.tujuan.side : 'barat';
        var isThrough = !dat && row.jam_datang_ket === 'Ls';

        function edgeX(side) { return side === 'timur' ? geom.timurX : geom.baratX; }

        // Titik kereta "berhenti" (front-aligned ke ujung peron sesuai arah
        // datang) selalu dihitung dari asalSide baris ini, supaya posisi
        // berhenti konsisten baik saat fase masuk maupun fase keluar.
        var stopX = stopXFor(geom, asalSide);
        var stopPt = samplePointAtX(geom.points, stopX);

        // Bungkus hasil lerp-di-sumbu-X supaya SELALU dibaca ulang dari
        // polyline titik koordinat jalur (bukan interpolasi Y bebas) --
        // inilah yang menjamin kereta tidak pernah keluar dari titik-titik
        // track. Jalur VI sendiri sekarang MEMANG melengkung ke atas di
        // titik koordinatnya (lihat points[] jalur VI di
        // sgu_geometri_presisi.json, ditelusuri langsung dari garis piksel
        // pada img/emplasemen/sgu.png resolusi asli) menjelang ujung timur
        // -- jadi KA yang keluar/masuk lewat sisi timur jalur VI otomatis
        // mengikuti tikungan itu tanpa logika khusus, karena samplePointAtX
        // membaca y & km langsung dari titik-titik polyline tsb.
        //
        // `useSiding`/geom.siding di bawah ini TIDAK dipakai jalur normal
        // manapun saat ini (geom.siding.forRelasiCode sengaja null) --
        // datanya (sepur ke arah "Ke BY Sgu" via wesel 261/282) sempat
        // dikira jadi rute KA "Dinas Rangkaian ... SB", ternyata salah (KA
        // tsb tetap di jalur utama, lihat catatan di
        // sgu_geometri_presisi.json). Disimpan sebagai referensi kalau
        // suatu saat ada KA yang terkonfirmasi benar-benar lewat sepur ini.
        function alongTrack(xa, xb, progress, useSiding) {
            var x = lerp(xa, xb, progress);
            if (useSiding && geom.siding && geom.siding.forRelasiCode) {
                var jx = geom.siding.points[0].x;
                if (x >= jx) return samplePointAtX(geom.siding.points, x);
            }
            return samplePointAtX(geom.points, x);
        }

        // Apakah baris jadwal ini harus dianimasikan lewat sepur menyimpang
        // (bukan lurus ke ujung jalur)? Cocok kalau jalur ini punya
        // geom.siding DENGAN forRelasiCode terisi (saat ini tidak ada) DAN
        // kode relasi (asal utk kedatangan / tujuan utk keberangkatan) sama
        // dengan siding.forRelasiCode. Sengaja hanya dipakai utk baris
        // "sekali jalan" (hanya jam datang ATAU hanya jam berangkat) karena
        // itulah pola dinas rangkaian/langsir -- KA yang benar2 transit
        // (jam datang & berangkat sama-sama ada, mis. "Sri Tanjung" yang
        // relasinya memuat kode sama tapi cuma
        // singgah) TETAP lewat jalur utama seperti biasa.
        function sidingMatches(code) {
            return !!(geom.siding && code && geom.siding.forRelasiCode === code);
        }

        function sidingOuterX() {
            var pts = geom.siding.points;
            return pts[pts.length - 1].x;
        }

        // Kasus: lewat langsung tanpa berhenti (hanya BER, DAT = "Ls")
        if (isThrough && ber !== null) {
            var winStart = ber - APPROACH_MIN;
            var winEnd = ber + APPROACH_MIN;
            if (t < winStart || t > winEnd) return { visible: false };
            var progress = (t - winStart) / (winEnd - winStart);
            var pThrough = alongTrack(edgeX(asalSide), edgeX(tujuanSide), progress);
            return {
                visible: true,
                x: pThrough.x, y: pThrough.y, km: pThrough.km, angle: pThrough.angle,
                phase: 'through',
                sideActive: t < ber ? asalSide : tujuanSide,
                trackCode: trackCode,
            };
        }

        // Kasus: datang & berangkat sama-sama ada (transit / berhenti)
        if (dat !== null && ber !== null) {
            var enterStart = dat - APPROACH_MIN;
            var leaveEnd = ber + APPROACH_MIN;
            if (t < enterStart || t > leaveEnd) return { visible: false };

            if (t < dat) {
                var p1 = (t - enterStart) / APPROACH_MIN;
                var pIn = alongTrack(edgeX(asalSide), stopX, p1);
                return { visible: true, x: pIn.x, y: pIn.y, km: pIn.km, angle: pIn.angle, phase: 'masuk', sideActive: asalSide, trackCode: trackCode };
            }
            if (t <= ber) {
                return { visible: true, x: stopPt.x, y: stopPt.y, km: stopPt.km, angle: stopPt.angle, phase: 'berhenti', trackCode: trackCode };
            }
            var p2 = (t - ber) / APPROACH_MIN;
            var pOut = alongTrack(stopX, edgeX(tujuanSide), p2);
            return { visible: true, x: pOut.x, y: pOut.y, km: pOut.km, angle: pOut.angle, phase: 'keluar', sideActive: tujuanSide, trackCode: trackCode };
        }

        // Kasus: hanya jam datang (tidak ada jam berangkat tercatat di baris
        // ini -- biasanya karena rangkaiannya dipindah/dilangsir dan dicatat
        // sebagai baris "Dinas Rangkaian" terpisah).
        if (dat !== null) {
            var enterStart2 = dat - APPROACH_MIN;
            var viaSidingIn = sidingMatches(row.asal ? row.asal.code : null);
            var enterX = viaSidingIn ? sidingOuterX() : edgeX(asalSide);

            // Kalau ketemu baris "Dinas Rangkaian" pasangannya (lihat
            // linkCompanionRows()), pakai jam berangkat & sisi tujuan
            // ASLI-nya -- jauh lebih akurat daripada menebak durasi parkir,
            // dan otomatis mencegah "tabrakan" dengan kereta berikutnya
            // yang datang ke jalur yang sama sebelum rangkaian ini benar2
            // pergi (persis kasus KA 8/Bima vs KA 12/Turangga di jalur VI).
            var companion = state.linkedDeparture[row.id];
            if (companion) {
                var comBer = timeToMin(companion.jam_berangkat);
                var comTujuanSide = companion.tujuan ? companion.tujuan.side : tujuanSide;
                var comLeaveEnd = comBer + APPROACH_MIN;
                if (t < enterStart2 || t > comLeaveEnd) return { visible: false };
                if (t < dat) {
                    var pc1 = (t - enterStart2) / APPROACH_MIN;
                    var pInC = alongTrack(enterX, stopX, pc1, viaSidingIn);
                    return { visible: true, x: pInC.x, y: pInC.y, km: pInC.km, angle: pInC.angle, phase: 'masuk', sideActive: asalSide, trackCode: trackCode };
                }
                if (t <= comBer) {
                    return { visible: true, x: stopPt.x, y: stopPt.y, km: stopPt.km, angle: stopPt.angle, phase: 'berhenti', trackCode: trackCode };
                }
                var viaSidingOutC = sidingMatches(companion.tujuan ? companion.tujuan.code : null);
                var exitXC = viaSidingOutC ? sidingOuterX() : edgeX(comTujuanSide);
                var pc2 = (t - comBer) / APPROACH_MIN;
                var pOutC = alongTrack(stopX, exitXC, pc2, viaSidingOutC);
                return { visible: true, x: pOutC.x, y: pOutC.y, km: pOutC.km, angle: pOutC.angle, phase: 'keluar', sideActive: comTujuanSide, trackCode: trackCode };
            }

            // Tidak ada pasangan yang cocok ditemukan -- fallback: KA
            // ditampilkan masuk & berhenti selama arrival_only_dwell_minutes
            // (bisa diatur di admin panel), lalu dianggap sudah dipindah ke
            // dipo sehingga tidak menumpuk terus di daftar.
            var dwellEnd2 = dat + ARRIVAL_ONLY_DWELL;
            if (t < enterStart2 || t > dwellEnd2) return { visible: false };
            if (t < dat) {
                var p3 = (t - enterStart2) / APPROACH_MIN;
                var pIn2 = alongTrack(enterX, stopX, p3, viaSidingIn);
                return { visible: true, x: pIn2.x, y: pIn2.y, km: pIn2.km, angle: pIn2.angle, phase: 'masuk', sideActive: asalSide, trackCode: trackCode };
            }
            return { visible: true, x: stopPt.x, y: stopPt.y, km: stopPt.km, angle: stopPt.angle, phase: 'berhenti', trackCode: trackCode };
        }

        // Kasus: hanya jam berangkat (mis. dinas rangkaian berangkat dari jalur ini)
        if (ber !== null) {
            var dwellStart = ber - DWELL_STATIC;
            var leaveEnd2 = ber + APPROACH_MIN;
            if (t < dwellStart || t > leaveEnd2) return { visible: false };
            if (t <= ber) {
                return { visible: true, x: stopPt.x, y: stopPt.y, km: stopPt.km, angle: stopPt.angle, phase: 'berhenti', trackCode: trackCode };
            }
            var p4 = (t - ber) / APPROACH_MIN;
            var viaSidingOut = sidingMatches(row.tujuan ? row.tujuan.code : null);
            var exitX = viaSidingOut ? sidingOuterX() : edgeX(tujuanSide);
            var pOut2 = alongTrack(stopX, exitX, p4, viaSidingOut);
            return { visible: true, x: pOut2.x, y: pOut2.y, km: pOut2.km, angle: pOut2.angle, phase: 'keluar', sideActive: tujuanSide, trackCode: trackCode };
        }

        return { visible: false };
    }

    function computeSignalStates(t) {
        // key: "<trackCode>|<side>" -> boolean merah
        var red = {};
        if (!state.data) return red;

        state.data.jadwal.forEach(function (row) {
            var ph = getPhase(row, t);
            if (!ph.visible) return;
            if (ph.phase === 'masuk' || ph.phase === 'keluar' || ph.phase === 'through') {
                var key = ph.trackCode + '|' + ph.sideActive;
                red[key] = true;
                if (ph.phase === 'through') {
                    // tandai kedua sisi selama lintas langsung
                    var otherSide = ph.sideActive === 'barat' ? 'timur' : 'barat';
                    red[ph.trackCode + '|' + otherSide] = true;
                }
            }
        });

        return red;
    }

    // ---------------------------------------------------------------------
    // Collision detection
    // ---------------------------------------------------------------------
    /**
     * Baris "Dinas Rangkaian <Nama>" adalah rangkaian YANG SAMA dengan KA
     * <Nama> yang baru datang -- cuma dicatat sebagai baris jadwal terpisah
     * karena polanya "datang lalu dilangsir" (lihat komentar di
     * JadwalImporter/getPhase kasus "hanya jam datang"). Pasangan ini WAJAR
     * tumpang tindih di titik berhenti yang sama sesaat, jadi TIDAK dihitung
     * sebagai tabrakan -- kalau tidak, popup akan muncul puluhan kali per
     * hari untuk kejadian yang sebetulnya normal.
     */
    function isCompanionPair(a, b) {
        var an = (a.nama_ka || '').toLowerCase();
        var bn = (b.nama_ka || '').toLowerCase();
        if (a.kategori === 'dinas' && bn && an.indexOf(bn) !== -1) return true;
        if (b.kategori === 'dinas' && an && bn.indexOf(an) !== -1) return true;
        return false;
    }

    /**
     * Cari pasangan kereta BERBEDA (bukan pasangan "dinas rangkaian" di
     * atas) yang berada di jalur yang sama DAN posisi ikonnya tumpang
     * tindih secara visual pada tick `t` ini. `entries` adalah daftar
     * {row, ph} kereta yang sedang visible (dari loop renderDynamic()).
     * Mengembalikan objek tabrakan pertama yang ditemukan, atau null.
     */
    function findCollision(entries) {
        var byTrack = {};
        entries.forEach(function (e) {
            var code = e.ph.trackCode;
            if (!code) return;
            (byTrack[code] = byTrack[code] || []).push(e);
        });

        var w = trainDims().w;
        var found = null;

        // 1) Tabrakan di JALUR YANG SAMA (dua kereta menumpuk di satu
        // trackCode -- kasus asli "KA 8 vs KA 12 di jalur VI").
        Object.keys(byTrack).forEach(function (code) {
            if (found) return;
            var list = byTrack[code];
            for (var i = 0; i < list.length && !found; i++) {
                for (var j = i + 1; j < list.length; j++) {
                    var a = list[i], b = list[j];
                    if (a.row.id === b.row.id) continue;
                    if (isCompanionPair(a.row, b.row)) continue;
                    if (Math.abs(a.ph.x - b.ph.x) < w) {
                        found = { type: 'track', code: code, a: a, b: b };
                        break;
                    }
                }
            }
        });
        if (found) return found;

        // 2) Konflik di LADDER WESEL (persimpangan) -- dua kereta di JALUR
        // PERON BERBEDA, tapi kedua-duanya sedang masuk/keluar/lewat
        // langsung ('masuk'/'keluar'/'through', bukan 'berhenti') di SISI
        // (barat/timur) yang SAMA pada saat yang sama, dan posisi ikonnya
        // secara visual saling dekat. Semua jalur peron di satu stasiun
        // bertemu jadi satu ladder wesel bersama menjelang tiap ujung
        // emplasemen (lihat renderStaticGeneric()/diagram wesel real),
        // jadi dua kereta yang sama-sama melintasi ladder itu bisa
        // berebut resource fisik yang sama meskipun jalur peron TUJUAN
        // akhirnya berbeda -- ini TIDAK tertangkap pengecekan #1 di atas
        // karena itu hanya membandingkan kereta pada trackCode yang SAMA.
        // Kasus nyata: KA 2628 (lewat langsung di jalur V) & KA 33 (masuk
        // ke jalur VI) yang sama-sama berada di ujung timur emplasemen
        // sekitar pukul 05:24 -- dua jalur peron berbeda, tapi konvergen
        // di ladder wesel sisi timur yang sama pada waktu yang sama.
        //
        // Pengecekan pasangan jalurnya DIBATASI ke yang sudah terverifikasi
        // BERTETANGGA langsung di ladder (state.trackAdjacency, lihat
        // TrackAdjacencySeeder & buildTrackAdjacency()) -- bukan sekadar
        // "sisi yang sama" (heuristik pertama yang dicoba, ternyata
        // menghasilkan 25-46 false alarm/hari karena SEMUA jalur di
        // stasiun dianggap bisa saling konflik, padahal cuma jalur yang
        // benar-benar bertetangga fisik yang berbagi persimpangan sama).
        // Kalau data adjacency-nya kosong (stasiun belum dipetakan),
        // pengecekan ini otomatis tidak pernah menyala -- default aman.
        var transitional = entries.filter(function (e) { return e.ph.phase !== 'berhenti'; });
        for (var m = 0; m < transitional.length && !found; m++) {
            for (var n = m + 1; n < transitional.length; n++) {
                var ta = transitional[m], tb = transitional[n];
                if (ta.row.id === tb.row.id) continue;
                if (ta.ph.trackCode === tb.ph.trackCode) continue;
                if (ta.ph.sideActive !== tb.ph.sideActive) continue;
                if (!tracksAreAdjacent(ta.ph.trackCode, tb.ph.trackCode, ta.ph.sideActive)) continue;
                if (isCompanionPair(ta.row, tb.row)) continue;
                if (Math.abs(ta.ph.x - tb.ph.x) < w) {
                    found = {
                        type: 'throat',
                        code: ta.ph.trackCode + '/' + tb.ph.trackCode,
                        side: ta.ph.sideActive,
                        a: ta, b: tb,
                    };
                    break;
                }
            }
        }

        return found;
    }

    /** Kunci stabil untuk satu pasangan tabrakan, dipakai anti-spam popup. */
    function collisionKey(found) {
        var ids = [found.a.row.id, found.b.row.id].sort(function (x, y) { return x - y; });
        return found.code + '|' + ids[0] + '|' + ids[1];
    }

    function detectCollisions(t, entries) {
        var found = findCollision(entries);

        if (!found) {
            state.lastNotifiedCollisionKey = null;
            state.collisionRowIds = [];
            return;
        }

        state.collisionRowIds = [found.a.row.id, found.b.row.id];

        var key = collisionKey(found);
        if (key === state.lastNotifiedCollisionKey) return;
        state.lastNotifiedCollisionKey = key;

        reportCollision(t, found);
    }

    function reportCollision(t, found) {
        var a = found.a.row, b = found.b.row;
        var km = found.a.ph.km != null ? found.a.ph.km : found.b.ph.km;
        var stasiunName = (state.data.stasiun && state.data.stasiun.name) || 'this station';

        var message;
        if (found.type === 'throat') {
            // Konflik ladder wesel: dua jalur peron berbeda, tapi
            // konvergen di persimpangan yang sama pada sisi yang sama.
            // SENGAJA TIDAK menghentikan simulasi (lihat bawah) -- topologi
            // sambungan wesel antar-jalur belum didigitisasi presisi (lihat
            // catatan pada SignalWeselSeeder::seedSguPresisi(): field
            // track_from_id/track_to_id pada tabel wesels saat ini masih
            // disamakan dengan jalurnya sendiri, belum memetakan crossover
            // sungguhan), jadi deteksi ini masih berupa PERINGATAN DINI
            // berbasis heuristik (sisi + kedekatan posisi), bukan kepastian
            // sepenuhnya seperti tabrakan di jalur yang sama. Tetap
            // dihighlight & dicatat di log supaya bisa ditinjau, tapi tidak
            // memaksa berhenti supaya tidak jadi terlalu sering (mirip
            // masalah dwell 45 menit yang sudah diperbaiki sebelumnya).
            var sideLabel = found.side === 'timur' ? 'east' : 'west';
            message = 'At ' + fmtClock(t) + ', train ' + (a.no_ka || '-') + ' (' + (a.nama_ka || '-') +
                ', Track ' + found.a.ph.trackCode + ')' +
                ' and train ' + (b.no_ka || '-') + ' (' + (b.nama_ka || '-') +
                ', Track ' + found.b.ph.trackCode + ')' +
                ' are converging on the same switch ladder at the ' + sideLabel + ' end of ' + stasiunName +
                (km != null ? ', near KM ' + fmtKm(km) : '') +
                '. Different platform tracks, but crossing the same junction at the same time' +
                ' (advisory -- switch crossover layout not fully mapped yet, simulation not paused).';
        } else {
            pause();
            message = 'At ' + fmtClock(t) + ', train ' + (a.no_ka || '-') + ' (' + (a.nama_ka || '-') + ')' +
                ' and train ' + (b.no_ka || '-') + ' (' + (b.nama_ka || '-') + ')' +
                ' are both occupying Track ' + found.code + ' at ' + stasiunName +
                (km != null ? ', near KM ' + fmtKm(km) : '') + '.';
        }

        // Modal pop-up yang mengharuskan diklik "OK" hanya untuk tabrakan
        // JALUR YANG SAMA (found.type==='track') -- itu satu-satunya kasus
        // yang sudah dipastikan lewat pause(). Konflik ladder wesel
        // (throat) sudah dihighlight visual + dicatat di log di bawah,
        // tapi TIDAK memunculkan modal supaya tidak mengganggu terus-
        // menerus (bisa terjadi puluhan kali/hari, lihat komentar di atas).
        if (found.type !== 'throat') {
            showCollisionModal(message);
        }

        postAccidentLog({
            stasiun: (state.data.stasiun && state.data.stasiun.code) || null,
            tanggal: state.data.tanggal,
            clock_time: fmtClock(t),
            track_code: found.type === 'throat' ? (found.a.ph.trackCode + '/' + found.b.ph.trackCode) : found.code,
            km_position: km,
            train_a_no_ka: a.no_ka,
            train_a_nama: a.nama_ka,
            train_b_no_ka: b.no_ka,
            train_b_nama: b.nama_ka,
            detail: message,
        });
    }

    function showCollisionModal(message) {
        var modal = document.getElementById('collisionModal');
        var msgEl = document.getElementById('collisionModalMessage');
        if (!modal || !msgEl) return;
        msgEl.textContent = message;
        modal.hidden = false;
    }

    function hideCollisionModal() {
        var modal = document.getElementById('collisionModal');
        if (modal) modal.hidden = true;
    }

    function postAccidentLog(payload) {
        var url = CFG.accidentLogUrl;
        if (!url) return;
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        }).catch(function (err) {
            console.error('Failed to save accident log:', err);
        });
    }

    function bindCollisionModal() {
        var btn = document.getElementById('btnCollisionDismiss');
        if (btn) btn.addEventListener('click', hideCollisionModal);
    }

    // ---------------------------------------------------------------------
    // Rendering
    // ---------------------------------------------------------------------
    function renderStatic() {
        var svg = document.getElementById('stationSvg');
        svg.innerHTML = '';

        var tracks = (state.data && state.data.tracks) || [];
        var stasiunMeta = (state.data && state.data.stasiun) || {};

        var defs = el('defs', {}, svg);
        var glow = el('filter', { id: 'glow', x: '-60%', y: '-60%', width: '220%', height: '220%' }, defs);
        el('feGaussianBlur', { stdDeviation: '2.2', result: 'blur' }, glow);
        var merge = el('feMerge', {}, glow);
        el('feMergeNode', { in: 'blur' }, merge);
        el('feMergeNode', { in: 'SourceGraphic' }, merge);

        if (state.realMode) {
            renderStaticReal(svg, stasiunMeta);
        } else {
            renderStaticGeneric(svg, tracks, stasiunMeta);
        }

        renderWesels();
        renderPeronHover();
        updatePageTitle();
    }

    // ---------------------------------------------------------------------
    // Tooltip KM (mengikuti ujung panah mouse) -- dipakai sinyal, wesel, dan
    // label KM statis lain. Satu div #kmTooltip dipakai ulang untuk semua.
    // ---------------------------------------------------------------------
    function bindKmTooltip(node, text) {
        var tip = document.getElementById('kmTooltip');
        if (!tip) return;
        node.addEventListener('mouseenter', function (evt) {
            tip.textContent = text;
            tip.hidden = false;
            positionTooltip(evt);
        });
        node.addEventListener('mousemove', positionTooltip);
        node.addEventListener('mouseleave', function () {
            tip.hidden = true;
        });

        function positionTooltip(evt) {
            tip.style.left = (evt.clientX + 14) + 'px';
            tip.style.top = (evt.clientY + 10) + 'px';
        }
    }

    /** Mode denah asli: gambar emplasemen sungguhan sebagai latar + overlay sinyal/kereta. */
    function renderStaticReal(svg, stasiunMeta) {
        svg.setAttribute('viewBox', stasiunMeta.diagram_viewbox || '0 0 1200 520');

        var bgLayer = el('g', { id: 'layer-background' }, svg);
        var bgUrl = (CFG.assetBase || '').replace(/\/$/, '') + '/' + stasiunMeta.diagram_svg_path;
        var vb = (stasiunMeta.diagram_viewbox || '0 0 1200 520').split(/\s+/);
        var img = el('image', {
            x: vb[0] || 0, y: vb[1] || 0, width: vb[2] || 1200, height: vb[3] || 520,
            preserveAspectRatio: 'xMidYMid meet',
        }, bgLayer);
        img.setAttributeNS('http://www.w3.org/1999/xlink', 'href', bgUrl);
        img.setAttribute('href', bgUrl);

        el('g', { id: 'layer-track' }, svg);
        el('g', { id: 'layer-peron' }, svg);
        el('g', { id: 'layer-station' }, svg);
        el('g', { id: 'layer-signal' }, svg);
        el('g', { id: 'layer-wesel' }, svg);
        el('g', { id: 'layer-train' }, svg);
    }

    /** Mode skema generik (dipakai stasiun yang belum punya gambar asli). */
    function renderStaticGeneric(svg, tracks, stasiunMeta) {
        var n = Math.max(tracks.length, 1);
        var topY = trackY(0) - 35;
        var botY = trackY(n - 1) + 35;
        var vbHeight = Math.max(520, botY + 70);
        svg.setAttribute('viewBox', '0 0 1200 ' + vbHeight);

        var trunkY = (trackY(0) + trackY(n - 1)) / 2;

        var g = el('g', { id: 'layer-track' }, svg);
        el('g', { id: 'layer-peron' }, svg);
        el('g', { id: 'layer-station' }, svg);
        el('g', { id: 'layer-signal' }, svg);
        el('g', { id: 'layer-wesel' }, svg);
        el('g', { id: 'layer-train' }, svg);

        tracks.forEach(function (tr, i) {
            var y = trackY(i);

            el('line', { x1: X_EDGE_BARAT, y1: trunkY, x2: X_TRUNK_BARAT, y2: trunkY, class: 'track-line' }, g);
            el('path', {
                d: 'M ' + X_TRUNK_BARAT + ' ' + trunkY + ' C ' + (X_TRUNK_BARAT + 60) + ' ' + trunkY + ', ' + (X_WESEL_BARAT - 40) + ' ' + y + ', ' + X_WESEL_BARAT + ' ' + y,
                class: 'track-line',
            }, g);
            el('line', { x1: X_WESEL_BARAT, y1: y, x2: X_WESEL_TIMUR, y2: y, class: 'track-line' }, g);
            el('path', {
                d: 'M ' + X_WESEL_TIMUR + ' ' + y + ' C ' + (X_WESEL_TIMUR + 40) + ' ' + y + ', ' + (X_TRUNK_TIMUR - 60) + ' ' + trunkY + ', ' + X_TRUNK_TIMUR + ' ' + trunkY,
                class: 'track-line',
            }, g);
            el('line', { x1: X_TRUNK_TIMUR, y1: trunkY, x2: X_EDGE_TIMUR, y2: trunkY, class: 'track-line' }, g);

            el('text', { x: X_WESEL_BARAT - 26, y: y + 4, class: 'track-label', 'text-anchor': 'end' }, g).textContent = tr.code;
            el('text', { x: X_WESEL_TIMUR + 26, y: y + 4, class: 'track-label' }, g).textContent = tr.code;
        });

        var stationLayer = document.getElementById('layer-station');
        el('rect', {
            x: X_STATION_LEFT, y: topY, width: (X_STATION_RIGHT - X_STATION_LEFT), height: (botY - topY),
            class: 'station-box', rx: 10, fill: '#17233a', opacity: 0.35,
        }, stationLayer);
        el('text', { x: (X_STATION_LEFT + X_STATION_RIGHT) / 2, y: topY - 12, class: 'station-box-label', 'text-anchor': 'middle' }, stationLayer)
            .textContent = 'STATION ' + (stasiunMeta.name || '').toUpperCase() + (stasiunMeta.code ? ' (' + stasiunMeta.code + ')' : '');

        el('text', { x: X_EDGE_BARAT, y: botY + 28, class: 'side-label' }, stationLayer).textContent =
            '← ' + (stasiunMeta.arah_barat_label || 'West Side');
        el('text', { x: X_EDGE_TIMUR, y: botY + 28, class: 'side-label', 'text-anchor': 'end' }, stationLayer).textContent =
            (stasiunMeta.arah_timur_label || 'East Side') + ' →';
    }

    /**
     * Render simbol wesel ke #layer-wesel, dipakai kedua mode (generik &
     * denah asli). Posisinya statis (tidak berubah tiap tick), jadi cukup
     * digambar sekali di renderStatic(). Tiap simbol dipasangi tooltip yang
     * menampilkan kode & KM chainage saat kursor mouse diarahkan ke atasnya.
     */
    function renderWesels() {
        var weselLayer = document.getElementById('layer-wesel');
        if (!weselLayer) return;
        weselLayer.innerHTML = '';
        (state.data.wesels || []).forEach(function (w) {
            if (w.pos_x == null || w.pos_y == null) return;
            var grp = el('g', { class: 'wesel-node' }, weselLayer);
            el('rect', { x: w.pos_x - 4, y: w.pos_y - 4, width: 8, height: 8, class: 'wesel-mark', transform: 'rotate(45 ' + w.pos_x + ' ' + w.pos_y + ')' }, grp);
            bindKmTooltip(grp, 'Switch ' + w.code + (w.posisi_km != null ? ' · KM ' + fmtKm(w.posisi_km) : ''));
        });
    }

    /**
     * Hover peron/platform: kotak abu-abu (RGB 233,233,233) pada gambar asli,
     * batasnya (peronWestX/peronEastX/peronYTop/peronYBottom) sudah
     * didigitisasi lewat analisis warna piksel (lihat
     * TrackSeeder::seedJalurSguPresisi()). Digambar transparan, cuma
     * menampilkan tooltip nama+panjang peron saat kursor diarahkan ke
     * kotaknya -- tidak mengubah tampilan visual gambar latar.
     * Hanya aktif di mode denah asli; mode generik tidak punya batas-Y
     * peron (peronYTop/peronYBottom = null) sehingga otomatis dilewati.
     */
    function renderPeronHover() {
        var layer = document.getElementById('layer-peron');
        if (!layer) return;
        layer.innerHTML = '';
        var tracks = (state.data && state.data.tracks) || [];
        tracks.forEach(function (t) {
            var geom = state.trackGeomByCode[t.code];
            if (!geom || geom.peronWestX == null || geom.peronEastX == null ||
                geom.peronYTop == null || geom.peronYBottom == null) {
                return;
            }
            var grp = el('g', { class: 'peron-node' }, layer);
            el('rect', {
                x: geom.peronWestX,
                y: geom.peronYTop,
                width: geom.peronEastX - geom.peronWestX,
                height: geom.peronYBottom - geom.peronYTop,
                class: 'peron-hover-mark',
            }, grp);
            var panjang = t.panjang_peron_m != null ? (Math.round(t.panjang_peron_m) + ' m') : '';
            bindKmTooltip(grp, 'Platform ' + t.name + (panjang ? ' · ' + panjang : ''));
        });
    }

    function updatePageTitle() {
        var meta = (state.data && state.data.stasiun) || {};
        var h1 = document.getElementById('pageTitle');
        if (h1 && meta.name) {
            h1.textContent = 'Train Journey Simulation — ' + meta.name + ' Station' + (meta.code ? ' (' + meta.code + ')' : '');
        }
    }

    function renderDynamic() {
        var t = state.clockMin;
        var redSignals = computeSignalStates(t);

        // Sinyal
        var signalLayer = document.getElementById('layer-signal');
        signalLayer.innerHTML = '';
        (state.data.signals || []).forEach(function (s) {
            if (s.pos_x == null || s.pos_y == null) return;
            var trackCode = state.trackIdToCode[s.track_id];
            // Sinyal tingkat stasiun (track_id kosong, mis. kotak 51-55/71-74
            // yang belum terpetakan ke satu jalur spesifik) tidak punya kunci
            // okupansi jalur -- ditampilkan statis hijau (belum ada logika
            // interlocking penuh, lihat catatan pada seeder).
            var key = trackCode + '|' + s.side;
            var isRed = trackCode ? !!redSignals[key] : false;
            var grp = el('g', { class: 'signal-node' }, signalLayer);
            el('line', { x1: s.pos_x, y1: s.pos_y - 14, x2: s.pos_x, y2: s.pos_y + 6, stroke: '#4b5b7d', 'stroke-width': 2 }, grp);
            el('circle', {
                cx: s.pos_x, cy: s.pos_y - 16, r: state.realMode ? 10 : 6,
                class: 'signal-dot',
                fill: isRed ? '#ef4444' : '#33d17a',
                filter: 'url(#glow)',
            }, grp);
            bindKmTooltip(grp, 'Signal ' + s.code + (s.posisi_km != null ? ' · KM ' + fmtKm(s.posisi_km) : '') + ' · ' + (isRed ? 'danger' : 'clear'));
        });

        // Kereta
        var trainLayer = document.getElementById('layer-train');
        trainLayer.innerHTML = '';
        var atStation = [];
        var upcoming = [];
        var visibleEntries = [];

        (state.data.jadwal || []).forEach(function (row) {
            // Baris "Dinas Rangkaian" yang sudah diserap ke baris
            // kedatangan pasangannya (lihat linkCompanionRows()) tidak
            // perlu tampil sendiri di panel "Upcoming" juga -- ceritanya
            // sudah terwakili lewat baris kedatangan aslinya.
            if (state.absorbedRowIds && state.absorbedRowIds[row.id]) return;

            var ph = getPhase(row, t);
            if (ph.visible) {
                visibleEntries.push({ row: row, ph: ph });
                if (ph.phase === 'berhenti') atStation.push(row);
            }
            var dat = timeToMin(row.jam_datang);
            var ber = timeToMin(row.jam_berangkat);
            var nextT = dat !== null ? dat : ber;
            if (nextT !== null && nextT >= t && nextT <= t + 30) {
                upcoming.push(row);
            }
        });

        // Deteksi tabrakan SEBELUM menggambar, supaya drawTrain() sudah
        // tahu (lewat state.collisionRowIds) kereta mana yang perlu
        // dihighlight merah pada tick ini.
        detectCollisions(t, visibleEntries);

        visibleEntries.forEach(function (entry) {
            drawTrain(trainLayer, entry.row, entry.ph);
        });

        renderSidePanels(atStation, upcoming);
        document.getElementById('clockReadout').textContent = fmtClock(t);
        document.getElementById('timeSlider').value = t;
    }

    function drawTrain(layer, row, ph) {
        var color = KATEGORI_COLOR[row.kategori] || KATEGORI_COLOR.lainnya;
        // Mode denah asli pakai satuan pt (viewBox jauh lebih besar), tapi
        // jarak antar jalur juga tidak seragam -- ukuran kereta dibuat kecil
        // & proporsional terhadap tebal garis rel, bukan sekadar dikalikan.
        var dims = trainDims();
        var w = dims.w, h = dims.h;
        var angle = ph.angle || 0;

        // Badan kereta diputar supaya SEJAJAR arah track persis di titik
        // (ph.x, ph.y) itu -- bukan cuma ikut posisi x/y-nya tapi tetap
        // datar. Urutan transform: pindah ke titik tengah kereta dulu,
        // putar di situ (jadi porosnya pas di tengah badan kereta, bukan
        // sudut kiri-atas), baru geser lagi supaya rect w x h yang digambar
        // dari (0,0) jadi center-nya di titik itu. `angle` dibaca langsung
        // dari kemiringan segmen polyline jalur yang sedang dilalui (lihat
        // samplePointAtX()/angleBetween()), jadi otomatis mengikuti
        // tikungan/wesel apa pun tanpa perlu logika khusus per-jalur.
        var transform = 'translate(' + ph.x + ',' + ph.y + ')' +
            (angle ? ' rotate(' + angle.toFixed(2) + ')' : '') +
            ' translate(' + (-w / 2) + ',' + (-h / 2) + ')';
        var isColliding = state.collisionRowIds.indexOf(row.id) !== -1;
        var grp = el('g', { class: isColliding ? 'train-node train-collision' : 'train-node', transform: transform }, layer);
        el('rect', { width: w, height: h, rx: state.realMode ? 6 : 5, fill: color }, grp);
        var label = String(row.no_ka || '').slice(0, 7);
        var text = el('text', { x: w / 2, y: h / 2 + 1 }, grp);
        text.textContent = label;
        if (state.realMode) text.setAttribute('font-size', 13);
        var title = el('title', {}, grp);
        title.textContent = row.no_ka + ' - ' + row.nama_ka + ' (' + (row.relasi_raw || '-') + ')';

        // Label KM di atas kereta yang sedang berhenti (posisi dibaca dari
        // titik koordinat jalur yang sama dipakai untuk menempatkan kereta,
        // lihat samplePointAtX() -- bukan angka terpisah/kira-kira). Ukuran
        // fontnya dibesarkan di mode denah asli (viewBox jauh lebih besar
        // dari mode skema, sama seperti label no-KA di atas) supaya
        // benar-benar terbaca, bukan cuma beberapa piksel di layar.
        if (ph.phase === 'berhenti' && ph.km != null) {
            var kmText = el('text', {
                x: w / 2, y: -6, class: 'train-km-label', 'text-anchor': 'middle',
            }, grp);
            if (state.realMode) kmText.setAttribute('font-size', 13);
            kmText.textContent = 'KM ' + fmtKm(ph.km);
        }
    }

    function trainMeta(row) {
        var jam = row.jam_datang || row.jam_berangkat || '-';
        var arah = row.jam_datang ? ('from ' + (row.asal ? row.asal.code : '-')) : ('to ' + (row.tujuan ? row.tujuan.code : '-'));
        return jam + ' &middot; Track ' + (row.track ? row.track.code : '-') + ' &middot; ' + arah;
    }

    function renderSidePanels(atStation, upcoming) {
        var cur = document.getElementById('currentTrains');
        var up = document.getElementById('upcomingTrains');

        if (!state.data.punya_jadwal) {
            cur.innerHTML = '<p class="muted">Train schedule not yet available for this station.</p>';
            up.innerHTML = '';
            return;
        }

        if (atStation.length === 0) {
            cur.innerHTML = '<p class="muted">No trains currently stopped.</p>';
        } else {
            cur.innerHTML = atStation.map(function (row) {
                return '<div class="train-card"><span class="no-ka">' + row.no_ka + '</span><span class="nama">' + row.nama_ka + '</span>' +
                    '<div class="meta">' + trainMeta(row) + '</div></div>';
            }).join('');
        }

        upcoming.sort(function (a, b) {
            var ta = timeToMin(a.jam_datang || a.jam_berangkat);
            var tb = timeToMin(b.jam_datang || b.jam_berangkat);
            return ta - tb;
        });

        if (upcoming.length === 0) {
            up.innerHTML = '<p class="muted">None in the next 30 minutes.</p>';
        } else {
            up.innerHTML = upcoming.slice(0, 8).map(function (row) {
                return '<div class="train-card"><span class="no-ka">' + row.no_ka + '</span><span class="nama">' + row.nama_ka + '</span>' +
                    '<div class="meta">' + trainMeta(row) + '</div></div>';
            }).join('');
        }
    }

    // ---------------------------------------------------------------------
    // Playback control
    // ---------------------------------------------------------------------
    function tick() {
        state.clockMin += state.speed / 12; // dipanggil tiap 5 detik nyata -> speed menit/detik * (1/12)
        if (state.clockMin >= 1440) state.clockMin = 0;
        renderDynamic();
    }

    function play() {
        if (state.playing || !state.data.punya_jadwal) return;
        state.playing = true;
        document.getElementById('btnPlay').innerHTML = '&#10074;&#10074; Pause';
        state.timer = setInterval(tick, 200);
    }

    function pause() {
        state.playing = false;
        document.getElementById('btnPlay').innerHTML = '&#9654; Start';
        clearInterval(state.timer);
    }

    // ---------------------------------------------------------------------
    // Zoom
    // ---------------------------------------------------------------------
    function applyZoom() {
        var svg = document.getElementById('stationSvg');
        svg.style.width = (state.zoom * 100) + '%';
        document.getElementById('zoomReadout').textContent = Math.round(state.zoom * 100) + '%';
    }

    function bindZoomControls() {
        document.getElementById('btnZoomIn').addEventListener('click', function () {
            state.zoom = Math.min(ZOOM_MAX, state.zoom + ZOOM_STEP);
            applyZoom();
        });
        document.getElementById('btnZoomOut').addEventListener('click', function () {
            state.zoom = Math.max(ZOOM_MIN, state.zoom - ZOOM_STEP);
            applyZoom();
        });
        document.getElementById('btnZoomReset').addEventListener('click', function () {
            state.zoom = 1;
            applyZoom();
        });
    }

    function setControlsEnabled(enabled) {
        ['btnPlay', 'btnReset', 'speedSelect', 'timeSlider'].forEach(function (id) {
            var elm = document.getElementById(id);
            if (elm) elm.disabled = !enabled;
        });
        var notice = document.getElementById('noJadwalNotice');
        if (notice) notice.hidden = enabled;
    }

    function bindControls() {
        document.getElementById('btnPlay').addEventListener('click', function () {
            if (state.playing) pause(); else play();
        });

        document.getElementById('btnReset').addEventListener('click', function () {
            pause();
            state.clockMin = 0;
            renderDynamic();
        });

        document.getElementById('speedSelect').addEventListener('change', function (e) {
            state.speed = parseFloat(e.target.value);
        });

        document.getElementById('timeSlider').addEventListener('input', function (e) {
            pause();
            state.clockMin = parseInt(e.target.value, 10);
            renderDynamic();
        });

        document.getElementById('tanggalSelect').addEventListener('change', function (e) {
            pause();
            var tanggal = e.target.value;
            var url = new URL(window.location.href);
            url.searchParams.set('tanggal', tanggal);
            window.location.href = url.toString();
        });

        bindZoomControls();
    }

    // ---------------------------------------------------------------------
    // Init
    // ---------------------------------------------------------------------
    function init() {
        bindControls();
        bindCollisionModal();
        loadData(CFG.tanggal, CFG.stasiun).then(function (data) {
            renderStatic();
            applyZoom();
            setControlsEnabled(!!data.punya_jadwal);
            renderDynamic();
        }).catch(function (err) {
            console.error('Failed to load schedule data:', err);
            document.getElementById('currentTrains').innerHTML =
                '<p class="muted">Failed to load data. Make sure migrations &amp; seeders have been run.</p>';
        });
    }

    document.addEventListener('DOMContentLoaded', init);

    // expose for potential debugging/testing
    window.__simulation = {
        getPhase: getPhase, timeToMin: timeToMin, fmtClock: fmtClock, state: state,
        findCollision: findCollision, isCompanionPair: isCompanionPair,
        applySettings: applySettings, linkCompanionRows: linkCompanionRows,
        findCompanionDeparture: findCompanionDeparture,
        buildTrackAdjacency: buildTrackAdjacency, tracksAreAdjacent: tracksAreAdjacent,
    };
})();
