param(
    [string]$VpsHost = "160.187.144.147",
    [int]$SshPort = 22,
    [string]$SshUser = "root",
    [int]$LocalPort = 3307,
    [int]$RemoteMysqlPort = 3306
)

Write-Host "Opening MySQL tunnel: 127.0.0.1:$LocalPort -> $VpsHost:localhost:$RemoteMysqlPort"
Write-Host "Keep this terminal open while Laravel uses the VPS database."

ssh -N -L "$LocalPort`:127.0.0.1`:$RemoteMysqlPort" -p $SshPort "$SshUser@$VpsHost"
