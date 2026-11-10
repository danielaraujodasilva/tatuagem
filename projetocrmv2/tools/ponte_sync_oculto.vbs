' ==========================================================
' ponte_sync_oculto.vbs - sincroniza o feed da ponte do WhatsApp
'
' POR QUE EXISTE
' A ponte Baileys grava dados/conversas.jsonl. Este launcher roda,
' sem janela, o ponte_sync.php que copia o feed para as tabelas do
' CRM (whatsapp_conversations / whatsapp_messages).
'
' Consumido por: tools/ponte_sync.php
' ==========================================================
Option Explicit

Dim shell, fso, root, logDir, command
Set shell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")

root = "C:\xampp\htdocs\site\projetocrmv2"
logDir = root & "\data"

If Not fso.FolderExists(logDir) Then
    fso.CreateFolder(logDir)
End If

command = "cmd.exe /d /c """"C:\xampp\php\php.exe"" """ & root & "\tools\ponte_sync.php"" >> """ & logDir & "\ponte_sync.log"" 2>&1"""

shell.CurrentDirectory = root
shell.Run command, 0, True

WScript.Quit 0
