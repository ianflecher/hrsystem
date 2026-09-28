@echo off
cd /d D:\GitHub\hris
C:\xampp\php\php.exe artisan attendance:sync >> storage\logs\scanner-sync.log 2>&1
