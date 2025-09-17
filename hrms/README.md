Simple HRMS Starter (inspired-by IceHRM layout) - Ready to upload to cPanel
-------------------------------------------------------------------------
This is a lightweight, original HRMS scaffold (PHP + MySQL) that implements:
- User login (simple session-based)
- Dashboard with example charts (Chart.js via CDN)
- Employees CRUD (list/add/edit/delete)
- Departments CRUD
- Attendance check-in / check-out
- Leaves request form (basic)
- Simple Reports page (CSV export of employees)
- SQL schema (install.sql) to create required tables

IMPORTANT: This is an original scaffold for learning / customization. It is NOT a copy
of IceHRM. You are free to customize and extend it.

Installation (cPanel / shared hosting):
1. Create a MySQL database and user in cPanel.
2. Edit config.php and set DB_HOST, DB_NAME, DB_USER, DB_PASS accordingly.
3. Import install.sql into your database (phpMyAdmin or command line).
4. Upload all files to the public_html (or a subfolder) via cPanel File Manager or FTP.
5. Open the site in browser and login:
   default admin credentials in install.sql: admin / admin123

Files included:
- index.php (routes to dashboard or login)
- login.php / logout.php
- dashboard.php
- employees.php (list + add)
- employee_edit.php
- departments.php
- attendance.php
- leaves.php
- reports.php
- config.php
- install.sql
- assets/ (css + simple JS)

Customize as needed. If you want, I can extend this to a full-featured system (roles, ACL,
nicer UI, PDF reports, email notifications, export/import, etc.) — tell me which features
you want next.
