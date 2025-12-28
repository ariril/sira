Param(
  [string]$Root = "c:\laravelproject\TA\sira"
)

$ErrorActionPreference = 'Stop'

$searchRoots = @('app','routes','resources','config','database','tests')
$excludePaths = @(
  (Join-Path $Root 'vendor'),
  (Join-Path $Root 'storage'),
  (Join-Path $Root 'bootstrap\\cache')
)

function Should-ExcludePath {
  param([Parameter(Mandatory=$true)][string]$Path)
  foreach ($p in $excludePaths) {
    if ($Path.StartsWith($p, [System.StringComparison]::OrdinalIgnoreCase)) {
      return $true
    }
  }
  return $false
}

$haystackFiles = @()
foreach ($sr in $searchRoots) {
  $dir = Join-Path $Root $sr
  if (-not (Test-Path -LiteralPath $dir)) { continue }

  $haystackFiles += Get-ChildItem -LiteralPath $dir -Recurse -File -Include *.php,*.blade.php
}

$haystackFiles = $haystackFiles | Where-Object { -not (Should-ExcludePath -Path $_.FullName) }

function Find-FirstHit {
  param(
    [Parameter(Mandatory=$true)][string]$Pattern,
    [Parameter(Mandatory=$true)][string]$ExcludeFile
  )

  foreach ($hf in $haystackFiles) {
    if ($hf.FullName -eq $ExcludeFile) { continue }

    $m = Select-String -LiteralPath $hf.FullName -SimpleMatch -Pattern $Pattern -List -ErrorAction SilentlyContinue
    if ($m) {
      return $m
    }
  }

  return $null
}

function Get-NamespaceClass {
  param([Parameter(Mandatory=$true)][string]$Path)

  $content = Get-Content -LiteralPath $Path -Raw
  $ns = [regex]::Match($content, '(?m)^namespace\s+([^;]+);')
  $cl = [regex]::Match($content, '(?m)^(?:final\s+|abstract\s+)?class\s+([A-Za-z0-9_]+)')

  if (-not $cl.Success) {
    return $null
  }

  $namespace = if ($ns.Success) { $ns.Groups[1].Value.Trim() } else { '' }
  $fqcn = if ($namespace) { $namespace + '\' + $cl.Groups[1].Value } else { $cl.Groups[1].Value }

  [pscustomobject]@{
    Path = $Path
    Class = $cl.Groups[1].Value
    Namespace = $namespace
    FQCN = $fqcn
    Content = $content
  }
}

$targetsRel = @(
  'app\\Http\\Controllers',
  'app\\Models',
  'app\\Services',
  'app\\Http\\Requests'
)

$targets = $targetsRel | ForEach-Object { Join-Path $Root $_ }
$files = @()
foreach ($t in $targets) {
  if (Test-Path -LiteralPath $t) {
    $files += Get-ChildItem -LiteralPath $t -Recurse -Filter *.php -File
  }
}
$files = $files | Sort-Object FullName

$results = foreach ($f in $files) {
  $meta = Get-NamespaceClass -Path $f.FullName
  if (-not $meta) { continue }

  $class = $meta.Class
  $fqcn = $meta.FQCN
  $isController = $f.FullName -like '*\\Controllers\\*'

  $patterns = @(
    ('use ' + $fqcn + ';'),
    ($class + '::class'),
    $fqcn
  )

  if ($isController) {
    $patterns += @($class + '@')
  }

  $hit = $null
  foreach ($p in $patterns) {
    $m = Find-FirstHit -Pattern $p -ExcludeFile $f.FullName
    if ($m) {
      $hit = $m
      break
    }
  }

  $trim = $meta.Content.Trim()
  $isEmpty = ($trim.Length -lt 120) -or ($meta.Content -match '(?s)^\s*<\?php\s*(?:declare\(strict_types=1\);\s*)?(?:namespace\s+[^;]+;\s*)?(?:use\s+[^;]+;\s*)*class\s+[A-Za-z0-9_]+\s*\{\s*\}\s*$')

  $fileRel = ($f.FullName.Substring($Root.Length + 1) -replace '\\', '/')
  $evidence = if ($hit) {
    $relPath = ($hit.Path -replace '^' + [regex]::Escape($Root + '\\'), '') -replace '\\', '/'
    ($relPath + ':' + $hit.LineNumber)
  } else {
    ''
  }

  [pscustomobject]@{
    File = $fileRel
    Class = $class
    FQCN = $fqcn
    Used = [bool]$hit
    Evidence = $evidence
    EmptyOrStub = $isEmpty
  }
}

$results | ConvertTo-Json -Depth 3
