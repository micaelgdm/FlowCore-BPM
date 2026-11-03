$ErrorActionPreference = 'Stop'

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$secretFile = Join-Path $projectRoot '.env.flowable'
$javaHome = Join-Path $projectRoot '.tools\jdk-17.0.20.1+1'
$maven = Join-Path $projectRoot '.tools\apache-maven-3.10.0\bin\mvn.cmd'
$phpCandidates = @('C:\xamp85\php\php.exe', 'C:\xampp\php\php.exe')
$php = $phpCandidates | Where-Object { Test-Path $_ } | Select-Object -First 1

if (-not $php) {
    throw 'PHP não encontrado. Verifique C:\xamp85\php\php.exe.'
}

if (-not (Test-Path $secretFile)) {
    throw 'Arquivo .env.flowable ausente. Crie-o com FLOWABLE_REST_USER e FLOWABLE_REST_PASSWORD.'
}

Get-Content $secretFile | ForEach-Object {
    if ($_ -match '^([A-Z0-9_]+)=(.*)$') {
        [Environment]::SetEnvironmentVariable($Matches[1], $Matches[2], 'Process')
    }
}

$env:JAVA_HOME = $javaHome
$env:PATH = (Join-Path $javaHome 'bin') + ';' + $env:PATH

$scheduler = Start-Process -FilePath $php `
    -ArgumentList @('artisan', 'schedule:work') `
    -WorkingDirectory $projectRoot `
    -WindowStyle Hidden `
    -PassThru

Push-Location $PSScriptRoot
try {
    & $maven -B spring-boot:run
    exit $LASTEXITCODE
}
finally {
    Pop-Location
    if ($scheduler -and -not $scheduler.HasExited) {
        Stop-Process -Id $scheduler.Id -Force
    }
}
