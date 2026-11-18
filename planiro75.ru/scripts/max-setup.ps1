#Requires -Version 5.1
<#
.SYNOPSIS
  Синхронизация webhook MAX с Planner (после смены токена или ручной настройки в PowerShell).

.DESCRIPTION
  1) Удаляет все старые подписки webhook в MAX (частая причина bad_secret).
  2) Создаёт одну подписку с нужными update_types и secret.
  3) Печатает команду для сохранения того же secret в БД Planner (PHP на сервере).

.PARAMETER Token
  Токен бота из business.max.ru → Чат-боты → Интеграция.

.PARAMETER WebhookUrl
  Полный HTTPS URL, например: https://planiro75.ru/public/api/max_webhook.php

.PARAMETER Secret
  Секрет 5–256 символов [a-zA-Z0-9_-]. Если не задан — генерируется (32 hex).

.PARAMETER PhpExe
  Путь к php.exe на сервере (необязательно). Если задан вместе с ProjectRoot — вызовет cron для записи secret в БД.

.PARAMETER ProjectRoot
  Корень проекта planner на сервере, например C:\www\planner

.EXAMPLE
  .\scripts\max-setup.ps1 -Token "YOUR_TOKEN" -WebhookUrl "https://planiro75.ru/public/api/max_webhook.php"

.EXAMPLE
  .\scripts\max-setup.ps1 -Token "..." -WebhookUrl "..." -PhpExe "C:\php\php.exe" -ProjectRoot "C:\www\planner"
#>
param(
    [Parameter(Mandatory = $true)]
    [string]$Token,

    [Parameter(Mandatory = $true)]
    [string]$WebhookUrl,

    [string]$Secret = "",

    [string]$PhpExe = "",

    [string]$ProjectRoot = "",

    [int]$OrgId = 1
)

$ErrorActionPreference = "Stop"
$ApiBase = "https://platform-api.max.ru"
$UpdateTypes = @(
    "message_created",
    "message_callback",
    "bot_started",
    "bot_added"
)

function Write-Step([string]$Message) {
    Write-Host "==> $Message" -ForegroundColor Cyan
}

function Invoke-MaxApi {
    param(
        [string]$Method,
        [string]$Path,
        [object]$Body = $null
    )
    $uri = "$ApiBase$Path"
    $headers = @{
        Authorization = ($Token -replace '\s+', '')
        Accept        = "application/json"
    }
    $params = @{
        Method      = $Method
        Uri         = $uri
        Headers     = $headers
        ErrorAction = "Stop"
    }
    if ($null -ne $Body) {
        $params.ContentType = "application/json; charset=utf-8"
        $params.Body = ($Body | ConvertTo-Json -Compress -Depth 6)
    }
    try {
        return Invoke-RestMethod @params
    }
    catch {
        $resp = $_.Exception.Response
        $detail = $_.Exception.Message
        if ($resp) {
            $reader = New-Object System.IO.StreamReader($resp.GetResponseStream())
            $detail = $reader.ReadToEnd()
            $reader.Close()
        }
        throw "MAX API $Method $Path failed: $detail"
    }
}

$Token = $Token -replace '\s+', ''
$WebhookUrl = $WebhookUrl.Trim()

if ($WebhookUrl -notmatch '^https://') {
    throw "WebhookUrl должен начинаться с https://"
}

if ($Secret -eq "") {
    $bytes = New-Object byte[] 16
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    $Secret = -join ($bytes | ForEach-Object { '{0:x2}' -f $_ })
    Write-Step "Сгенерирован новый secret ($($Secret.Length) симв.)"
}
elseif ($Secret -notmatch '^[a-zA-Z0-9_-]{5,256}$') {
    throw "Secret: только A-Z, a-z, 0-9, _ и -, длина 5–256 (правила MAX API)."
}

Write-Step "Список текущих подписок"
$list = Invoke-MaxApi -Method GET -Path "/subscriptions"
$subs = @()
if ($list.subscriptions) {
    $subs = @($list.subscriptions)
}
elseif ($list -is [array]) {
    $subs = @($list)
}
Write-Host "Найдено подписок: $($subs.Count)"

foreach ($sub in $subs) {
    $url = ""
    if ($sub -is [hashtable] -or $sub -is [pscustomobject]) {
        $url = [string]($sub.url)
    }
    if ($url -eq "") { continue }
    Write-Step "Удаление подписки: $url"
    $enc = [uri]::EscapeDataString($url)
    Invoke-MaxApi -Method DELETE -Path "/subscriptions?url=$enc" | Out-Null
}

Write-Step "Регистрация webhook"
$body = @{
    url          = $WebhookUrl
    update_types = $UpdateTypes
    secret       = $Secret
}
$created = Invoke-MaxApi -Method POST -Path "/subscriptions" -Body $body
if ($created.success -eq $false) {
    throw "MAX вернул success=false: $($created.message)"
}

Write-Host ""
Write-Host "Готово в MAX:" -ForegroundColor Green
Write-Host "  URL:    $WebhookUrl"
Write-Host "  Secret: $Secret  (длина $($Secret.Length))"
Write-Host "  Типы:   $($UpdateTypes -join ', ')"
Write-Host ""

if ($PhpExe -ne "" -and $ProjectRoot -ne "") {
    $cron = Join-Path $ProjectRoot "public\cron\max_webhook_register.php"
    if (-not (Test-Path $cron)) {
        throw "Не найден: $cron"
    }
    Write-Step "Запись secret и токена в БД через PHP"
    & $PhpExe $cron "--org=$OrgId" "--secret=$Secret"
    if ($LASTEXITCODE -ne 0) {
        throw "PHP cron завершился с кодом $LASTEXITCODE"
    }
    Write-Host "БД Planner обновлена." -ForegroundColor Green
}
else {
    Write-Host "Дальше на СЕРВЕРЕ (чтобы secret совпал с БД):" -ForegroundColor Yellow
    Write-Host "  1) Сохраните токен в MAX мониторинг или config.local.php / PLANNER_MAX_TOKEN"
    Write-Host "  2) Выполните:"
    Write-Host "     php public/cron/max_webhook_register.php --org=$OrgId --secret=$Secret"
    Write-Host "  Или в админке: MAX мониторинг → поле secret → вставить secret → Сохранить → Зарегистрировать webhook"
    Write-Host ""
    Write-Host "  Диагностика:"
    Write-Host "     php public/cron/max_diagnose.php --org=$OrgId"
}

Write-Host ""
Write-Host "Проверка: напишите боту /help и смотрите var/log/max-webhook.log" -ForegroundColor Gray
