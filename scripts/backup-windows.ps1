<#
  Backup do AlpDesk no Laragon (Windows): banco de dados + imagens dos chamados,
  com cópia automática para o Google Drive (rclone).

  Rodar na mão (PowerShell, na pasta do projeto):
      powershell -NoProfile -ExecutionPolicy Bypass -File scripts\backup-windows.ps1

  Os arquivos ficam em C:\laragon\backups\alpdesk (FORA do projeto, para nunca irem ao Git).
  O histórico de execuções fica em C:\laragon\backups\alpdesk\backup.log

  Restaurar (em um banco de TESTE, pelo terminal do Laragon / cmd):
      1. Extraia o db_AAAA-MM-DD_HHMM.zip  ->  db_AAAA-MM-DD_HHMM.sql
      2. mysql -u root alpdesk_teste < db_AAAA-MM-DD_HHMM.sql
#>
param(
    [string]$AppDir         = (Split-Path -Parent $PSScriptRoot),
    [string]$BackupDir      = 'C:\laragon\backups\alpdesk',
    [int]   $KeepDays       = 14,
    [string]$Remote         = 'gdrive:AlpDesk-Backups',
    [int]   $RemoteKeepDays = 30,
    [string]$Rclone         = 'rclone'
)

$ErrorActionPreference = 'Stop'
New-Item -ItemType Directory -Force -Path $BackupDir | Out-Null
$log = Join-Path $BackupDir 'backup.log'

function Log([string]$msg) {
    $linha = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')  $msg"
    Write-Output $linha
    Add-Content -Path $log -Value $linha -Encoding UTF8
}

$sql = $null
try {
    # 1. Lê as credenciais do banco no .env (a senha não fica escrita neste arquivo)
    $cfg = @{}
    foreach ($l in Get-Content (Join-Path $AppDir '.env') -Encoding UTF8) {
        if ($l -match '^\s*([A-Z0-9_]+)\s*=\s*(.*)$') {
            $cfg[$Matches[1]] = $Matches[2].Trim().Trim('"')
        }
    }
    $dbHost = if ($cfg['DB_HOST']) { $cfg['DB_HOST'] } else { '127.0.0.1' }
    $dbPort = if ($cfg['DB_PORT']) { $cfg['DB_PORT'] } else { '3306' }
    if (-not $cfg['DB_DATABASE'] -or -not $cfg['DB_USERNAME']) {
        throw 'DB_DATABASE ou DB_USERNAME não encontrado no .env'
    }

    # 2. Acha o mysqldump.exe do Laragon (pega a versão mais nova instalada)
    $dump = Get-ChildItem 'C:\laragon\bin\mysql' -Recurse -Filter 'mysqldump.exe' -ErrorAction SilentlyContinue |
        Sort-Object FullName -Descending | Select-Object -First 1
    if (-not $dump) { throw 'mysqldump.exe não encontrado em C:\laragon\bin\mysql' }

    # 3. Exporta o banco. --result-file grava direto no arquivo (evita o PowerShell
    #    mudar a codificação do texto). --single-transaction não trava o sistema.
    $stamp = Get-Date -Format 'yyyy-MM-dd_HHmm'
    $sql   = Join-Path $BackupDir "db_$stamp.sql"
    $env:MYSQL_PWD = $cfg['DB_PASSWORD']
    & $dump.FullName --single-transaction --no-tablespaces --default-character-set=utf8mb4 `
        -h $dbHost -P $dbPort -u $cfg['DB_USERNAME'] "--result-file=$sql" $cfg['DB_DATABASE']
    if ($LASTEXITCODE -ne 0) { throw "mysqldump falhou (código $LASTEXITCODE). O MySQL do Laragon está ligado?" }

    Compress-Archive -Path $sql -DestinationPath (Join-Path $BackupDir "db_$stamp.zip") -CompressionLevel Optimal
    Remove-Item $sql
    $sql = $null
    Log "Banco exportado: db_$stamp.zip"

    # 4. Compacta as imagens dos chamados (se houver alguma)
    $img = Join-Path $AppDir 'storage\app\private\task-images'
    if ((Test-Path $img) -and (Get-ChildItem $img -File -Recurse | Select-Object -First 1)) {
        Compress-Archive -Path $img -DestinationPath (Join-Path $BackupDir "imagens_$stamp.zip") -CompressionLevel Optimal
        Log "Imagens compactadas: imagens_$stamp.zip"
    }

    # 5. Apaga os backups locais mais velhos que $KeepDays dias
    Get-ChildItem -Path (Join-Path $BackupDir '*') -Include 'db_*.zip', 'imagens_*.zip' |
        Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$KeepDays) } |
        Remove-Item

    # 6. Envia para o Google Drive e apaga lá o que passou de $RemoteKeepDays dias
    if ($Remote) {
        # Sem rclone no PATH (instalado sem winget)? Usa o que estiver em C:\laragon\bin\rclone
        if (-not (Get-Command $Rclone -ErrorAction SilentlyContinue) -and (Test-Path 'C:\laragon\bin\rclone\rclone.exe')) {
            $Rclone = 'C:\laragon\bin\rclone\rclone.exe'
        }
        & $Rclone copy $BackupDir $Remote --include 'db_*.zip' --include 'imagens_*.zip'
        if ($LASTEXITCODE -ne 0) { throw "rclone copy falhou (código $LASTEXITCODE)" }
        & $Rclone delete $Remote --min-age "${RemoteKeepDays}d" --include 'db_*.zip' --include 'imagens_*.zip'
        if ($LASTEXITCODE -ne 0) { throw "rclone delete falhou (código $LASTEXITCODE)" }
        Log "Cópia enviada para $Remote"
    }

    Log 'Backup concluído.'
    exit 0
}
catch {
    Log "ERRO: $($_.Exception.Message)"
    exit 1
}
finally {
    Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
    if ($sql -and (Test-Path $sql)) { Remove-Item $sql -ErrorAction SilentlyContinue }
}
