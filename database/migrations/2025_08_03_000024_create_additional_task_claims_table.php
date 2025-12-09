<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('additional_task_claims', function (Blueprint $table) {
            $table->id();

            // Tugas & siapa yang mengklaim
            $table->foreignId('additional_task_id')
                ->constrained('additional_tasks')
                ->cascadeOnDelete();

            $table->foreignId('user_id') // pegawai pemilik klaim
            ->constrained('users')
                ->cascadeOnDelete();

            // Status siklus klaim (diperluas)
            // active: sedang dipegang user
            // submitted: hasil tugas dikirim, menunggu validasi
            // validated: diverifikasi oleh sistem/penilai awal
            // approved: disetujui oleh atasan
            // rejected: ditolak oleh atasan
            // completed: selesai
            // cancelled: user batal (dalam/di luar tenggat)
            // auto_unclaim: dilepas otomatis oleh sistem (mis. kadaluarsa)
            $table->enum('status', ['active','submitted','validated','approved','rejected','completed','cancelled','auto_unclaim'])
                ->default('active')
                ->index();

            // Timestamps per kejadian
            $table->timestamp('claimed_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Batas maksimal cancel utk klaim ini (per-klaim, bukan global)
            $table->timestamp('cancel_deadline_at')->nullable()->index();

            // Alasan/bukti saat cancel (opsional)
            $table->string('cancel_reason')->nullable();

            // Kebijakan sanksi bila melanggar tenggat atau pelanggaran lain
            // none  : tidak ada sanksi
            // percent: potongan remunerasi dalam persen (0–100)
            // amount : potongan nominal rupiah
            $table->enum('penalty_type', ['none', 'percent', 'amount'])
                ->default('none');

            // Nilai kebijakan sanksi (mis. 15.00 untuk 15% atau 50000.00 untuk Rp 50.000)
            $table->decimal('penalty_value', 12, 2)->default(0);

            // Realisasi sanksi (jika diterapkan)
            $table->boolean('penalty_applied')->default(false)->index();
            $table->timestamp('penalty_applied_at')->nullable();
            $table->decimal('penalty_amount', 14, 2)->nullable(); // jumlah potongan yang benar-benar dikenakan
            $table->string('penalty_note')->nullable();

            $table->string('result_file_path')->nullable();
            $table->text('result_note')->nullable();
            // Pelanggaran
            $table->boolean('is_violation')->default(false)->index();

            $table->timestamps();

            $table->unsignedTinyInteger('is_active')->storedAs("CASE WHEN status = 'active' THEN 1 ELSE 0 END");
            // Hanya boleh ada satu baris (task, is_active=1)
            $table->unique(['additional_task_id', 'is_active'], 'uniq_task_single_active');

            $table->index(['additional_task_id', 'user_id'], 'idx_task_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('additional_task_claims');
    }
};
