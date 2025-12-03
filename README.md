# Campus Lost & Found — Stage 3/4 Setup

This repository contains a PHP + MySQL Lost & Found application with a modern card-based UI. Items are displayed in a grid of cards, and clicking a card opens a modal with full details and an inline claim submission form.

Quick setup (macOS)

1. Ensure MySQL and PHP are installed. On macOS you can use Homebrew:

   brew install mysql php

2. Start MySQL server and log in (adjust name/password as needed):

   # start mysql (Homebrew)
   brew services start mysql

   # log into mysql
   mysql -u root -p

3. From inside MySQL, run the schema and sample data:

   SOURCE /absolute/path/to/lost_and_found/create_tables.sql;
   SOURCE /absolute/path/to/lost_and_found/add_photo_column.sql;
   SOURCE /absolute/path/to/lost_and_found/add_claim_status.sql;
   SOURCE /absolute/path/to/lost_and_found/sample_data.sql;

   Replace /absolute/path/to/lost_and_found with the actual path on your machine (e.g. /Users/John/Desktop/lost_and_found).
   
   Notes:
   - `add_photo_column.sql` adds a photo field to the Item table for storing image URLs
   - `add_claim_status.sql` adds status tracking for claims and items (approval workflow)

4. Configure DB credentials in `db_connect.php` if needed (host, user, password).

5. Run a PHP built-in server for quick testing (from project folder):

   php -S localhost:8000

   Then open http://localhost:8000/view_items.php

Files added/edited
- `create_tables.sql` — creates DB and all tables
- `add_photo_column.sql` — adds photo column to Item table
- `sample_data.sql` — inserts a few records for testing
- `db_connect.php` — DB connection (uses utf8mb4)
- `index.php` — landing page with login/signup
- `login.php` — login form (name + email)
- `signup.php` — signup form (creates user account)
- `view_items.php` — card grid display with modal details and inline claim submission
- `add_item.php` — form to create items with photo URL support
- `search.php` — search by keyword and location with card grid results
- `css/style.css` — modern card-based styles with modal popups

Features
- **Card Grid Layout**: Items displayed as clickable cards with image/placeholder, title, and poster
- **Modal Popups**: Click any card to see full details (description, location) and submit a claim
- **Claim Approval Workflow**: 
  - Users submit claims with description and optional photo proof
  - Item owners see pending claim count badges on their items
  - Owners review all claims and approve/reject each one
  - Approved claims reveal contact info (email) for coordination
  - Multiple users can claim the same item; owner picks the best match
- **Item Status Tracking**: Items progress through states: Available → Claimed → Returned
- **Visual Indicators**: Status badges show item state (Available, Claimed, Returned)
- **Photo Support**: Items and claims can include photo URLs for proof/identification
- **Session-Based Auth**: Users sign up/login and their claims are automatically associated
- **Owner Controls**: Delete items, review claims, approve/reject claims, mark items as returned
- **Search**: Keyword search with same card grid display

Mid-project report screenshots / deliverables
- Show successful DB connection (a screenshot of running `mysql` and the `USE campus_lost_found;` + `SHOW TABLES;`).
- Inserted records: show `SELECT * FROM Item;`, `SELECT * FROM Claim;`, `SELECT * FROM User;` (screenshots).
- Show search working: search for a keyword and screenshot results.

Notes and next steps
- Advanced features (Stage 5) planned: claim approval workflow, auto-matching, returned status, file uploads, authentication.
- Security & production: sanitize inputs further, add CSRF protection, password/hash user auth. File uploads should be stored securely.
