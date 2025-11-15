<?php

namespace App\Enums;

/** Status usulan kriteria baru sebelum menjadi PerformanceCriteria. */
enum CriteriaProposalStatus: string
{
    case DRAFT     = 'draft';      // dibuat kepala unit tapi belum diajukan
    case PROPOSED  = 'proposed';   // diajukan ke Admin RS
    case REJECTED  = 'rejected';   // ditolak admin
    case PUBLISHED = 'published';  // disetujui & sudah dibuatkan PerformanceCriteria aktif
}
