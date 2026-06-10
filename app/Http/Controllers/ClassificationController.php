<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Mahasiswa;

class ClassificationController extends Controller
{
    public function index()
    {
        $totalTraining = Mahasiswa::count();

        return view('klasifikasi', compact('totalTraining'));
    }

    public function predict(Request $request)
    {
        // 1. Validasi Input
        $request->validate([
            'ipk' => 'required|numeric',
            'kehadiran' => 'required|numeric',
            'sks_lulus' => 'required|numeric',
            'status_kerja' => 'required',
            'algoritma' => 'required' // Tambahan untuk memilih algoritma
        ]);

        $total = Mahasiswa::count();

        if ($total == 0) {
            return redirect()->back()
                ->with('error', 'Data training tidak ditemukan.');
        }

        $algoritma = $request->algoritma;
        $hasil = '';
        $probYa = 0;
        $probTidak = 0;

        /*
        |--------------------------------------------------------------------------
        | 1. NAIVE BAYES (BAWAAN)
        |--------------------------------------------------------------------------
        */
        if ($algoritma == 'naive_bayes') {
            $totalYa = Mahasiswa::where('tepat_waktu', 'Ya')->count();
            $totalTidak = Mahasiswa::where('tepat_waktu', 'Tidak')->count();

            $pYa = $totalYa / $total;
            $pTidak = $totalTidak / $total;

            $ipkTinggi = $request->ipk >= 3;
            $hadirTinggi = $request->kehadiran >= 80;
            $sksTinggi = $request->sks_lulus >= 110;

            $ipkYa = Mahasiswa::where('tepat_waktu', 'Ya')->where('ipk', $ipkTinggi ? '>=' : '<', 3)->count();
            $ipkTidak = Mahasiswa::where('tepat_waktu', 'Tidak')->where('ipk', $ipkTinggi ? '>=' : '<', 3)->count();

            $hadirYa = Mahasiswa::where('tepat_waktu', 'Ya')->where('kehadiran', $hadirTinggi ? '>=' : '<', 80)->count();
            $hadirTidak = Mahasiswa::where('tepat_waktu', 'Tidak')->where('kehadiran', $hadirTinggi ? '>=' : '<', 80)->count();

            $sksYa = Mahasiswa::where('tepat_waktu', 'Ya')->where('sks_lulus', $sksTinggi ? '>=' : '<', 110)->count();
            $sksTidak = Mahasiswa::where('tepat_waktu', 'Tidak')->where('sks_lulus', $sksTinggi ? '>=' : '<', 110)->count();

            $kerjaYa = Mahasiswa::where('tepat_waktu', 'Ya')->where('status_kerja', $request->status_kerja)->count();
            $kerjaTidak = Mahasiswa::where('tepat_waktu', 'Tidak')->where('status_kerja', $request->status_kerja)->count();

            // Laplace Smoothing
            $pIpkYa = ($ipkYa + 1) / ($totalYa + 2);
            $pIpkTidak = ($ipkTidak + 1) / ($totalTidak + 2);

            $pHadirYa = ($hadirYa + 1) / ($totalYa + 2);
            $pHadirTidak = ($hadirTidak + 1) / ($totalTidak + 2);

            $pSksYa = ($sksYa + 1) / ($totalYa + 2);
            $pSksTidak = ($sksTidak + 1) / ($totalTidak + 2);

            $pKerjaYa = ($kerjaYa + 1) / ($totalYa + 2);
            $pKerjaTidak = ($kerjaTidak + 1) / ($totalTidak + 2);

            $probYa = $pYa * $pIpkYa * $pHadirYa * $pSksYa * $pKerjaYa;
            $probTidak = $pTidak * $pIpkTidak * $pHadirTidak * $pSksTidak * $pKerjaTidak;

            $hasil = $probYa > $probTidak ? 'Ya' : 'Tidak';
        }
        
        /*
        |--------------------------------------------------------------------------
        | 2. DECISION TREE (DITAMBAHKAN)
        |--------------------------------------------------------------------------
        */
        else if ($algoritma == 'decision_tree') {
            // Menerapkan model Decision Tree dengan membagi dataset 
            // menggunakan aturan percabangan pada atribut IPK, Kehadiran, dan SKS.
            // Menghitung probabilitas empiris dari node daun pada database.
            
            $query = Mahasiswa::query();
            
            if ($request->ipk >= 3.0) {
                $query->where('ipk', '>=', 3.0);
                if ($request->kehadiran >= 80) {
                    $query->where('kehadiran', '>=', 80);
                } else {
                    $query->where('kehadiran', '<', 80);
                }
            } else {
                $query->where('ipk', '<', 3.0);
                if ($request->sks_lulus >= 110) {
                    $query->where('sks_lulus', '>=', 110);
                } else {
                    $query->where('sks_lulus', '<', 110);
                }
            }

            $totalNode = $query->count();
            $lulusNode = (clone $query)->where('tepat_waktu', 'Ya')->count();

            if ($totalNode > 0) {
                $probYa = $lulusNode / $totalNode;
            } else {
                // Fallback probabilitas keseluruhan jika node kosong
                $probYa = Mahasiswa::where('tepat_waktu', 'Ya')->count() / $total;
            }
            
            $probTidak = 1 - $probYa;
            $hasil = $probYa > 0.5 ? 'Ya' : 'Tidak';
        }

        /*
        |--------------------------------------------------------------------------
        | 3. RANDOM FOREST (DITAMBAHKAN)
        |--------------------------------------------------------------------------
        */
        else if ($algoritma == 'random_forest') {
            // Membangun ensemble dari beberapa Decision Tree
            // dan menentukan hasil akhir menggunakan majority voting.
            
            // Tree 1: Berdasarkan atribut IPK
            $t1_total = Mahasiswa::where('ipk', $request->ipk >= 3.0 ? '>=' : '<', 3.0)->count();
            $t1_ya = Mahasiswa::where('ipk', $request->ipk >= 3.0 ? '>=' : '<', 3.0)->where('tepat_waktu', 'Ya')->count();
            $p1_ya = $t1_total > 0 ? $t1_ya / $t1_total : 0;
            $vote1 = $p1_ya > 0.5 ? 1 : 0;

            // Tree 2: Berdasarkan atribut Kehadiran
            $t2_total = Mahasiswa::where('kehadiran', $request->kehadiran >= 80 ? '>=' : '<', 80)->count();
            $t2_ya = Mahasiswa::where('kehadiran', $request->kehadiran >= 80 ? '>=' : '<', 80)->where('tepat_waktu', 'Ya')->count();
            $p2_ya = $t2_total > 0 ? $t2_ya / $t2_total : 0;
            $vote2 = $p2_ya > 0.5 ? 1 : 0;

            // Tree 3: Berdasarkan atribut SKS Lulus
            $t3_total = Mahasiswa::where('sks_lulus', $request->sks_lulus >= 110 ? '>=' : '<', 110)->count();
            $t3_ya = Mahasiswa::where('sks_lulus', $request->sks_lulus >= 110 ? '>=' : '<', 110)->where('tepat_waktu', 'Ya')->count();
            $p3_ya = $t3_total > 0 ? $t3_ya / $t3_total : 0;
            $vote3 = $p3_ya > 0.5 ? 1 : 0;

            // Agregasi hasil dari semua tree (Voting dan rata-rata probabilitas)
            $suaraYa = $vote1 + $vote2 + $vote3;
            $suaraTidak = 3 - $suaraYa;
            
            $probYa = ($p1_ya + $p2_ya + $p3_ya) / 3;
            $probTidak = 1 - $probYa;
            $hasil = ($suaraYa > $suaraTidak) ? 'Ya' : 'Tidak';
        }

        /*
        |--------------------------------------------------------------------------
        | 4. LOGISTIC REGRESSION (DITAMBAHKAN)
        |--------------------------------------------------------------------------
        */
        else if ($algoritma == 'logistic_regression') {
            // Menerapkan model Logistic Regression
            // Melakukan proses iterasi (Gradient Descent) sederhana untuk mendapatkan bobot asli
            
            $data = Mahasiswa::all();
            $w0 = 0; $w1 = 0; $w2 = 0; $w3 = 0; $w4 = 0;
            $learning_rate = 0.001;
            $epochs = 10;
            
            // Proses Training
            for ($i = 0; $i < $epochs; $i++) {
                foreach ($data as $row) {
                    $x1 = $row->ipk;
                    $x2 = $row->kehadiran;
                    $x3 = $row->sks_lulus;
                    $x4 = ($row->status_kerja == 'Ya') ? 1 : 0;
                    $y_actual = ($row->tepat_waktu == 'Ya') ? 1 : 0;

                    $z = $w0 + ($w1 * $x1) + ($w2 * $x2) + ($w3 * $x3) + ($w4 * $x4);
                    $z = max(-100, min(100, $z)); 
                    $y_pred = 1 / (1 + exp(-$z));

                    $error = $y_actual - $y_pred;

                    // Update bobot
                    $w0 += $learning_rate * $error;
                    $w1 += $learning_rate * $error * $x1;
                    $w2 += $learning_rate * $error * $x2;
                    $w3 += $learning_rate * $error * $x3;
                    $w4 += $learning_rate * $error * $x4;
                }
            }

            // Proses Prediksi
            $input_x1 = $request->ipk;
            $input_x2 = $request->kehadiran;
            $input_x3 = $request->sks_lulus;
            $input_x4 = ($request->status_kerja == 'Ya') ? 1 : 0;

            $z_input = $w0 + ($w1 * $input_x1) + ($w2 * $input_x2) + ($w3 * $input_x3) + ($w4 * $input_x4);
            $z_input = max(-100, min(100, $z_input));
            
            $probYa = 1 / (1 + exp(-$z_input));
            $probTidak = 1 - $probYa;
            $hasil = ($probYa > 0.5) ? 'Ya' : 'Tidak';
        }

        return redirect('/')
            ->with('prediction', $hasil)
            ->with('prob_ya', $probYa)
            ->with('prob_tidak', $probTidak)
            ->with('algoritma_terpilih', str_replace('_', ' ', strtoupper($algoritma)));
    }
}