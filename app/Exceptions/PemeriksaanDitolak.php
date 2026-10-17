<?php

namespace App\Exceptions;

use RuntimeException;

// Dilempar di dalam transaksi pemeriksaan agar seluruh perubahan di-rollback.
class PemeriksaanDitolak extends RuntimeException
{
}
