# Abn School — WordPress Theme (Complete School Management System)

A complete school management system built **inside a WordPress theme** — no
separate school plugin required, and no Elementor or WooCommerce needed.
Students, fees, attendance, exams, timetable, homework, staff, ID cards,
WhatsApp reminders, notices, admission inquiries, and parent & student
portals are all part of the theme itself.

> **Which file do I upload?**
> Upload **`abn-school.zip`** as the theme (Appearance → Themes → Add New →
> Upload Theme). That zip IS the theme.
> If you downloaded the complete package (`abn-school-complete.zip`), unzip
> it first and upload the **inner** `abn-school.zip` — WordPress rejects the
> bundle itself with *"The theme is missing the style.css stylesheet"*.

- School: **Abn School**
- Phone / WhatsApp: **[Insert Phone Number]** — add the real number in
  **Abn School → Settings** (it is a placeholder until you do).
- Email: **[Insert Email]** — add the real email in Settings.
- Address: **[Insert Address]** — add the real address in Settings.
- Palette: Deep emerald `#0C3B2E`, warm gold `#C9A227`, ivory `#FAF6EE`,
  charcoal `#1F2937`.

## What is inside

- **Public website** — Home, About, Admissions (with a working inquiry
  form), Classes & Courses, Teachers, Announcements, Contact. All pages are
  normal WordPress pages: edit their text in **Pages**. Class fees, teacher
  names and announcements come live from Settings / staff records / notices.
- **Dashboard** — KPIs (students, fees collected this month, pending fees,
  attendance today), fee-collection bar chart (real data), attendance donut.
- **Students & Admissions** — add/edit/delete, search + class filter,
  admission form, and linking each student to a parent account and a
  student account for the portal.
- **Fees & Challans** — create challans, record payments (Mark Paid),
  printable challans/receipts, fee reports on the dashboard.
- **Attendance** — mark per class per day (Present / Absent / Leave).
- **Exams & Report Cards** — exams, marks entry per subject, printable
  report cards with grades.
- **Timetable** — weekly timetable, editable per class.
- **Homework Diary** — homework per class / subject / due date.
- **Staff** — teachers, roles, office staff, with teacher-login accounts
  and assigned classes.
- **ID Cards** — printable student ID cards (single + whole class).
- **WhatsApp Reminders** — one-tap wa.me fee reminders to parents (no API
  key). Works once the school WhatsApp number is set in Settings.
- **Notices & Announcements** — publish notices from the admin; they show
  on the Announcements page and in every portal.
- **Admission Inquiries** — inquiries from the Admissions page form are
  listed in the admin for follow-up.
- **Parent & Student Portals** — front-end page `/portal/` with the
  shortcode `[abn_school_portal]`. Parents see ONLY their own children;
  students see ONLY their own record: attendance, fees, homework, exam
  results, timetable and announcements. Teachers see the staff portal
  limited to their assigned classes.
- **Settings** — school name, logo upload, phone, WhatsApp, emails,
  address, academic year, monthly fee defaults, demo data tools, and a
  **Run Setup Again** button.

## Install — 10-minute checklist

Install WordPress first (LocalWP is easiest — free from localwp.com; or
XAMPP). Create a site with PHP 8. Then:

**1. Upload the theme zip and activate it — that is the whole install.**
- **Upload:** Appearance → Themes → Add New → Upload Theme → choose
  `abn-school.zip` → Install → Activate.
- **Or file-copy (recommended on a PC):** unzip `abn-school.zip` and copy
  the `abn-school` folder into `wp-content/themes/` (in LocalWP:
  right-click the site → Reveal in Explorer/Finder →
  `app/public/wp-content/themes/`). Then go to **Appearance → Themes**
  and Activate **Abn School**.

**Activating the theme does everything, automatically — no extra clicks:**
1. Creates the school settings, site title and pretty permalinks.
2. Creates the Teacher, Parent and Student roles.
3. Creates the Home, About, Admissions, Classes, Teachers,
   Announcements, Contact and School Portal pages and sets Home as the
   front page.
4. Seeds demo data (24 students, staff, fees, attendance, one exam,
   timetable, homework, 3 notices, demo portal accounts).

That is it — the system is ready. Open the site: **Home** shows the school
website; **Abn School** in the WordPress admin is the management system.

**2. Check the setup.** Go to **Abn School → Settings** — the top card
shows the one-click setup status (last run, every step). All steps should
say OK. If anything failed, click **Run Setup Again** (safe — nothing is
duplicated).

**3. Add your real details (2 minutes).** In **Abn School → Settings**:
school name, logo, phone, WhatsApp, emails, address, academic year.
Check the monthly fee defaults — they are DEMO values (Nursery–KG
Rs. 2,000, Primary Rs. 2,500, Middle Rs. 3,500, Matric Rs. 4,500) and
must be confirmed for your school.

**4. Look around.** Dashboard KPIs, Students list, Fees, Attendance,
Exams, Timetable, Homework, Staff, ID Cards, Notices, Admission
Inquiries, and the public site (Home, Classes, Teachers, Announcements,
Contact) plus the Portal page.

**5. Try the portals.** Log in as a parent or student account (created in
Users, or the demo accounts supplied separately with this delivery) and
open `/portal/` — parents see only their own children.

**6. Replace the demo data.** When you are ready, use **Clear Demo Data**
in Settings to remove all demo students, fees, attendance, exams, staff,
homework, demo notices and demo accounts — then add your real students
and staff.

## Requirements

- WordPress 6.0+ (tested on 7.1), PHP 7.4+ (tested on 8.3).
- No plugins required. No Elementor, no WooCommerce, no school-management
  plugin — the theme is the whole system.
- Recommended PHP extensions: `mbstring`, `curl`, `xml`, `gd`, `zip`,
  `sqlite3` (only if using the SQLite database plugin instead of MySQL).
- On activation the theme sets the site title to the school name and
  pretty permalinks (`/%postname%/`) so `/admissions/`, `/classes/`,
  `/teachers/`, `/announcements/`, `/contact/` and `/portal/` work.

## Data storage

All school data is stored as WordPress custom post types + postmeta
(`abn_student`, `abn_fee`, `abn_attendance`, `abn_exam`, `abn_mark`,
`abn_homework`, `abn_staff`, `abn_notice`, `abn_inquiry`; timetable in the
`abn_timetable` option; settings in the `abn_settings` option).
No custom database tables, no separate school plugin.

## Roles & permissions

- **Administrator** — everything.
- **School Teacher** — Abn School menu with Dashboard, Attendance, Exams,
  Timetable and Homework, limited to the classes assigned to them in
  Staff (set the teacher's login account + assigned classes there).
- **Parent** — front-end portal only; sees only the children linked to
  their account (link them in Students → Edit Student → Parent Account).
  Parents are redirected from wp-admin to the portal.
- **Student** — front-end portal only; sees only their own record (link
  them in Students → Edit Student → Student Account).

Create parent/student/teacher accounts in **Users → Add New** with the
matching role, then link them from Students / Staff.

## Demo data (seeded on activation)

- 24 students, Nursery–Class 10, with father name, roll no, admission no,
  phone (demo numbers).
- Fee records: previous month paid for all; current month paid for 2 of 3
  students, pending for the rest. Monthly fees: Nursery–KG Rs. 2,000,
  Primary Rs. 2,500, Middle Rs. 3,500, Matric Rs. 4,500 (DEMO — confirm
  in Settings).
- Attendance for the last 5 school days; one First Term exam with marks
  for Classes 6–10; a weekly timetable; 6 homework entries; 8 staff
  members; 3 demo notices.
- Demo portal accounts (parent linked to the first two students, student
  linked to the first student, teacher linked to the Mathematics teacher
  for Classes 6–8). Their usernames are shown in the admin (Students /
  Staff lists); passwords are supplied separately with this delivery and
  are NOT written in this file.

## Still needed from the school

- Real school address, phone / WhatsApp number and email (Settings).
- School logo (Settings → School Logo, or Appearance → Customize →
  Site Identity).
- Real fee structure if it differs from the demo defaults above.
- Real student/staff data (replace the demo data — clear it first).
