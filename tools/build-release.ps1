param([switch]$Force)
$ErrorActionPreference = 'Stop'
$builder = Join-Path $PSScriptRoot 'build_release.py'
$builderArgs = @($builder)
if ($Force) { $builderArgs += '--force' }
& python @builderArgs
if ($LASTEXITCODE -ne 0) { throw 'Release packaging failed.' }
