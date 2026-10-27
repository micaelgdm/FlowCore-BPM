$ErrorActionPreference = 'Stop'

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$secretFile = Join-Path $projectRoot '.env.flowable'
$javaHome = Join-Path $projectRoot '.tools\jdk-17.0.20.1+1'
$maven = Join-Path $projectRoot '.tools\apache-maven-3.10.0\bin\mvn.cmd'

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

Push-Location $PSScriptRoot
try {
    & $maven -B spring-boot:run
    exit $LASTEXITCODE
}
finally {
    Pop-Location
}
