Set shell = CreateObject("WScript.Shell")
shell.Run "powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File ""C:\xampp\htdocs\site\codex\start-codex-bridge.ps1"" -Watch", 0, False
