# Sistem Pendukung Keputusan Kualitas Air (FIS Sugeno Orde-0)

Repositori ini berisi prototipe sistem pendukung keputusan untuk klasifikasi kelayakan kualitas air pemandian umum berdasarkan **Permenkes No. 2 Tahun 2023**.

## Struktur Proyek
- `index.php`: Program baseline sistem FIS Sugeno Orde-0 dengan Logika Veto.
- `eksperimen_a.php`: Variasi *Membership Function* (Segitiga Murni).
- `eksperimen_b.php`: Simulasi tanpa Logika VETO.
- `eksperimen_c.php`: Variasi defuzzifikasi menggunakan *Winner-Takes-All*.

## Deskripsi
Sistem ini menggunakan logika fuzzy untuk merepresentasikan ketidakpastian parameter kualitas air (Suhu, Kejernihan, pH, dan DO) dan dilengkapi dengan mekanisme Logika Veto Mutlak untuk menjamin keselamatan publik.
