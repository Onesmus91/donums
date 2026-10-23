# Donums — Launch Guide

This is the complete, upgraded Donums platform. It replaces the previous site. Your existing data (requests, partners, listings, vacancies, applications, courses, mail history and mailbox settings) is **migrated automatically on first load** — nothing is deleted.

## Before you start (10 minutes)

1. **Back up the current site.** In DirectAdmin → File Manager, compress `public_html` to a zip and download it.
2. **Rotate your secrets (important).** In the old site, `/data` had no access rule, so `mail_accounts.json` (mailbox passwords in plain text) and `admin.json` (admin password hash) were very likely downloadable by anyone who guessed the address. Treat them as exposed:
   - Change the password of every mailbox listed in `data/mail_accounts.json` in DirectAdmin (Email Accounts), **then** enter the new passwords in the new Admin → Mail → Mailbox settings.
   - Change the Super Admin password after first sign-in and turn on two-step verification (Admin → System & security).
3. Confirm your host runs **PHP 8.1 or newer** with SQLite (standard). DirectAdmin → Select PHP Version.

## Upload

1. In File Manager, open `public_html`. **Keep these three items; do not delete them:** the `data/` folder, the `assets/uploads/` folder, and `config.php`.
2. Delete everything else in `public_html` (old `admin/`, `api/`, `app/`, `forum/`, `jobs/`, `learning/`, `webinars/`, `*.php`, etc. — full list in `3-LEGACY-FILES-TO-DELETE.txt`).
3. Upload the contents of the new `public_html` folder (zip, then *Extract*). Let it overwrite `config.php` only if you have not changed it; otherwise keep yours.
4. Visit `https://donums.co.ke/`. The first request creates the database and imports your old data (takes a few seconds).
5. Visit `https://donums.co.ke/admin` and sign in with your **existing admin password** (it was migrated). If you were never given one, the site shows a one-time “Create the Super Admin” page.

## Configure (15 minutes)

| Where | What to do |
|---|---|
| Admin → Mail → Mailbox settings | Re-enter each mailbox password. Press **Save, then test connection** — both Incoming and Outgoing must say connected. Default host is `da33.host-ww.net` (ports 993/465 SSL). |
| Admin → Settings | Enter your M-Pesa Paybill/Till and bank details (shown to paying learners and webinar attendees). |
| Admin → System & security | Turn on two-step verification. Copy the **cron** line it shows into DirectAdmin → Cron Jobs (every 5 minutes). Review health checks. |
| Admin → Mail → Mailbox settings → Deliverability | Run the domain check. Fix SPF / DKIM / DMARC (DirectAdmin → Email Accounts → *Email Authentication*) so your email reaches inboxes. |
| `.htaccess` | Once SSL is active, uncomment the three “Force HTTPS” lines at the top. |
| Admin → Users & roles | Add staff and give each person only the access they need (Instructor, Recruiter, Moderator, Host, Support). Only you can grant access. |

## Strongly recommended: move private data out of the web folder

Create a folder **above** `public_html`, e.g. `/home/YOURUSER/donums_private`, move the contents of `public_html/data/` into it, then in `config.php` set:

```php
'data_dir' => '/home/YOURUSER/donums_private',
```

The site works either way (the in-web `data/` folder is locked by `.htaccess`), but a folder outside the web root is safer.

## Live streaming and 100+ attendees (optional second step)

The interactive room works immediately. To serve 100+ viewers and stream to YouTube/Facebook/LinkedIn, follow `media-server/README.md` (rent a small VPS, run the installer, connect it under Admin → Live → Streaming). Do a private rehearsal before the first public event.

## After migration is verified

Admin → System & security lists old data files and old code files. Download a copy for your records, then delete them (they contained passwords and personal data in readable form).

## What to test on your live server (I could not test against your actual host)

- [ ] Send a test email from Admin → Mail to your own address; reply to it; confirm both arrive.
- [ ] Register a learner, enrol in the free course, finish it, open the certificate.
- [ ] Apply for a vacancy with a PDF CV; open the CV from the applicant profile.
- [ ] Upload a webinar cover photo (Admin → Live → edit session) and confirm it shows on the public page.
- [ ] Start an **instant session** (Admin → Live), open the link on your phone, and check video both ways. If it connects on Wi-Fi but not on mobile data, add a TURN server (Admin → Settings → ICE servers).
- [ ] Open the site on a phone and tablet.
