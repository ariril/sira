Param(
  [string]$Root = "c:\laravelproject\TA\sira",
  [string]$InputJson = "c:\laravelproject\TA\sira\tools\audit-usage.json",
  [string]$OutputMd = "c:\laravelproject\TA\sira\tools\audit-usage.md"
)

$ErrorActionPreference = 'Stop'

$prefix = ($Root -replace '\\','/')
if (-not $prefix.EndsWith('/')) { $prefix += '/' }

function To-RelPath([string]$p) {
  if ([string]::IsNullOrWhiteSpace($p)) { return $p }
  $p2 = $p -replace '\\','/'
  if ($p2.StartsWith($prefix, [System.StringComparison]::OrdinalIgnoreCase)) {
    return $p2.Substring($prefix.Length)
  }
  return $p2
}

function To-Link([string]$path, [int]$line) {
  $rp = To-RelPath $path
  if ($line -gt 0) {
    return "[$rp]($rp#L$line)"
  }
  return "[$rp]($rp)"
}

function Find-ClassLine([string]$filePath, [string]$className) {
  $abs = Join-Path $Root ($filePath -replace '/','\\')
  if (-not (Test-Path -LiteralPath $abs)) { return 0 }
  $m = Select-String -LiteralPath $abs -Pattern ("class " + $className) -SimpleMatch -List -ErrorAction SilentlyContinue
  if ($m) { return [int]$m.LineNumber }
  return 0
}

$items = Get-Content -Raw -LiteralPath $InputJson | ConvertFrom-Json

$lines = New-Object System.Collections.Generic.List[string]
$lines.Add('# Audit pemakaian file')
$lines.Add('')
$lines.Add(('Generated: ' + (Get-Date -Format "yyyy-MM-dd HH:mm:ss")))
$lines.Add(('Total files: ' + $items.Count))
$lines.Add(('Unused (no references found): ' + (@($items | Where-Object { -not $_.Used }).Count)))
$lines.Add(('Empty/Stub: ' + (@($items | Where-Object { $_.EmptyOrStub }).Count)))
$lines.Add('')
$lines.Add('| File | Status | Bukti referensi (contoh) | Rekomendasi | Risiko |')
$lines.Add('|---|---|---|---|---|')

foreach ($it in ($items | Sort-Object File)) {
  $status = if ($it.Used) { 'dipakai' } else { 'tidak (belum ditemukan)' }

  $evidence = ''
  if ($it.Evidence) {
    $m = [regex]::Match($it.Evidence, '^(.*):(\d+)$')
    if ($m.Success) {
      $evidence = To-Link $m.Groups[1].Value ([int]$m.Groups[2].Value)
    } else {
      $evidence = To-RelPath $it.Evidence
    }
  } else {
    $defLine = Find-ClassLine -filePath $it.File -className $it.Class
    $evidence = if ($defLine -gt 0) { ('Def: ' + (To-Link $it.File $defLine)) } else { 'Def: ' + (To-Link $it.File 0) }
  }

  $recommendation = 'keep'
  $risk = 'low'

  if (-not $it.Used) {
    $recommendation = if ($it.EmptyOrStub) { 'kandidat hapus (butuh verifikasi manual)' } else { 'kandidat review (mungkin belum terhubung)' }
    $risk = 'medium'
  }

  $fileLink = To-Link $it.File 0
  $lines.Add("| $fileLink | $status | $evidence | $recommendation | $risk |")
}

$lines | Set-Content -LiteralPath $OutputMd -Encoding utf8
Write-Output ("Wrote: " + (To-RelPath $OutputMd))
