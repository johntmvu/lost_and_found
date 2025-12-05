# Campus Lost & Found 

This repository contains a PHP + MySQL Lost & Found application with a modern card-based UI. Items are displayed in a grid of cards, and clicking a card opens a modal with full details and an inline claim submission form.

Quick setup (macOS)

1. Ensure MySQL and PHP are installed. On macOS you can use Homebrew:

   brew install mysql php

2. Start MySQL server and log in (adjust name/password as needed):

   # start mysql (Homebrew)
   brew services start mysql

   # log into mysql
   mysql -u root -p

3. From inside MySQL, run the schema and sample data

4. Configure DB credentials in `db_connect.php` if needed (host, user, password).

5. Run a PHP built-in server for quick testing (from project folder):

   php -S localhost:8000

   Then open http://localhost:8000/

