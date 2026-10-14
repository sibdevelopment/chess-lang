#!/bin/zsh

cd /Applications/XAMPP/xamppfiles/htdocs/chess-academy || exit 1
clear
echo "Archer LMS server is starting..."
echo "Keep this Terminal window open while using the LMS."
echo "Open: http://localhost:8000/login"
echo
exec /Applications/XAMPP/xamppfiles/bin/php artisan serve --host=127.0.0.1 --port=8000
