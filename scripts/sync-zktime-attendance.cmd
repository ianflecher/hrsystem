@echo off
cd /d D:\GitHub\hris
C:\xampp\php\php.exe artisan attendance:sync-zktime >> storage\logs\zktime-sync.log 2>&1
