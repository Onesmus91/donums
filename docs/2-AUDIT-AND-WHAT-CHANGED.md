# Audit findings and what was done

## 1. What I found in the uploaded site

**Security (fix first — these were live risks)**
- `/data` had **no access protection**. It held `mail_accounts.json` (mailbox passwords in plain text), `admin.json` (admin password hash), a 125 KB mail log, customer requests and job applications. The README claimed a rule blocked it; no such rule existed.
- `data/private-cv/.htacess` is misspelled, so CVs were not protected either.
- Mailbox passwords were stored unencrypted; the three admin tools each had their own sign-in code.
- No CSRF protection on forms; no rate limiting on sign-in; file uploads were not type-checked by content.

**Product gaps (what you described)**
- **Learning:** one 5 KB page and a JSON file. No accounts, no lesson player, no progress, quizzes, reviews or certificates; no payments.
- **Jobs / ATS:** a list page and a form; admin had a status dropdown and nothing else — no pipeline, interviews, scorecards, candidate tracking or emails.
- **Forum:** a topic list only — no accounts, replies, moderation or voting.
- **Webinars:** a record with a “join link” field. No room, registration, chat, or scheduling logic.
- **Mail:** a basic send/receive log built on the PHP IMAP extension (removed from PHP 8.4), with no queue, drafts, attachments, templates, campaigns or tracking.
- **Admin:** three separate pages with different styling; one 38 KB file held most logic.
- **Design & copy:** body text forced to 11 px; inconsistent pages; generic wording (“Select a service. Send your requirement.”); 20 MB and 7 MB images on public pages.

## 2. What the new platform contains

One foundation (SQLite database, accounts and roles, one design system, one admin console), with your data migrated automatically.

**Donums Academy (Coursera-style)** — course catalogue with search, filters and ratings; landing pages (outcomes, syllabus with free previews, instructor, FAQ, reviews); video (YouTube/Vimeo/MP4 upload), reading, downloadable-file and **quiz** lessons (single/multi-answer, pass mark, explanations, retakes); progress tracking and resume; **verifiable certificates** with a public verification page; reviews and ratings; course discussions; free instant enrolment; **paid enrolment** with M-Pesa/bank instructions, transaction-code submission and admin verification; course builder with sections, drag-order, learners and CSV export; instructor role limited to their own courses.

**Donums Careers + ATS** — public job board with filters and Google-compatible job markup; applications with CV upload, screening questions and a transparent **fit score** (skills 50 / experience 25 / qualification 15 / completeness 10); candidate **status tracking** by reference; **drag-and-drop pipeline** (Applied → Screening → Shortlisted → Interview → Assessment → Offer → Hired / Rejected); applicant profile with CV viewer, notes, ratings, tags, **interview scheduling with email invites**, **scorecards**, duplicate detection, talent pool, bulk actions, CSV export, reports (funnel, sources, time-to-hire); employer vacancy submission with approval; stage-change emails; GDPR-style permanent deletion.

**Community forum** — categories, threads, replies (with one level of nesting), voting, accepted answers, reputation, following + email/in-app notifications, search, member profiles, reporting, locking/pinning/hiding, **moderation queue** for new members, spam traps and rate limits, XSS-safe formatting.

**Donums Live (webinars)** — **instant sessions** and **scheduled webinars**; free and **paid** (payment-verified) registration; personal join links; calendar (.ics) files; automatic day-before and one-hour reminders and post-event follow-up; a browser **live room** with host camera/mic, screen sharing, invite-to-stage, waiting room, live chat, moderated Q&A, polls, hand-raising, mute/remove, announcements, attendance and minutes-watched tracking, local recording, feedback and CSV exports. No third-party meeting software is involved.

**Professional mail system** — shared mailbox workspace (inbox, starred, sent, drafts, scheduled, outbox, archive, trash), search, bulk actions, threading, **safe rendering of hostile email** (scripts and tracking images blocked), attachments, rich-text composer with branded letterhead, signatures, saved replies, scheduled send, autosaved drafts, reply / reply-all / forward, convert-email-to-request, **a delivery queue with retries**, encrypted mailbox passwords, a built-in IMAP/SMTP engine (no PHP imap extension needed), **campaigns** to opted-in contacts with merge tags, open/click tracking, one-click unsubscribe, throttled sending, contacts with import/export and consent control, editable templates for every automatic email, and a **DNS deliverability checker** (SPF/DKIM/DMARC).

**Admin (Super Admin controlled)** — one console. Only the Super Admin creates staff or grants access; staff see only the modules their role allows (Instructor, Recruiter, Moderator, Host, Support). Audit log, two-step verification, nightly database backup, downloadable backups, health checks, payment verification, requests/partners/deliveries/listings CRM.

**Public site** — rewritten copy and a consistent design; readable 16 px type; services, quotation form with confirmation emails, request tracking (now requires the contact used), partner applications, legal pages rewritten, SEO (sitemap, structured data), PWA/offline page; images optimised from 42 MB to under 2 MB.

## 3. How it was verified

I ran the system against a copy of your real data and drove it end to end: **more than 200 automated checks, all passing** — sign-up, lockout, password reset, role restrictions, course creation → enrolment → quiz → certificate → verification, paid enrolment → verification, job application with CV upload → fit score → interview → rejection email → pipeline drag, forum moderation and XSS attempts, webinar registration (free and paid), the room API, and **two real Chromium browsers exchanging live WebRTC video, chat and Q&A**. Mail was tested against a real IMAP server (Dovecot) and an SMTP server: receiving, attachments, sanitising hostile HTML, replying with threading, Bcc, Unicode subjects, queue retries, campaigns, tracking links, tampered-link refusal, and unsubscribe. I also viewed the pages as rendered screenshots and fixed the issues I saw.

## 4. Live streaming and 100+ attendees (added in this version)

The room now has two delivery modes, chosen per session:

* **Interactive room** — browser-to-browser, no server. Host plus up to 6 on camera, ~25 viewers.
* **Broadcast** — the host's mixed program feed goes to a **media server you own** (open-source MediaMTX on a VPS) which serves **100+ viewers** and, through a restream helper, **pushes the same feed live to YouTube, Facebook, LinkedIn and any RTMP destination**. Attendees still chat, ask questions, vote in polls, raise their hands and can be invited on stage. See `media-server/README.md`.

Tested: a browser publishing to the media server; 50 simultaneous real WebRTC viewers all receiving live video (media-server CPU about 5%); 120 simulated attendees polling the website (30 requests/second, no errors, 39 ms at the 95th percentile); a restream verified to arrive as H.264 + AAC, 1280×720, at two RTMP endpoints; signed-token security on every publish and watch request; stream keys never visible to browsers.

## 5. Honest limits — read this

1. **You need a VPS for Broadcast mode** (about US$20–40/month for 100 viewers). It cannot run on shared PHP hosting. The installer and instructions are in `media-server/`; `install.sh` could not be run on a real VPS in my environment, so run a private rehearsal before your first public event.
2. **Not tested against the real YouTube/Facebook/LinkedIn.** Those platforms need your accounts, live-streaming enabled and valid stream keys. The stream they receive was verified to be in the format they require, but I could not push to them. Facebook and LinkedIn also expire or restrict keys; test each once.
3. **“100 attendees” means 100 watching and interacting** (chat, Q&A, polls, hand-raise), with the host plus up to 5 guests on camera — the standard webinar format. A meeting where all 100 people have their cameras on needs a different, much heavier architecture (an SFU) and is not what this builds.
4. **Social platforms run 10–30 seconds behind.** Viewers on the website itself see about 1–3 seconds of delay.
5. **Host's connection and tab:** the host's computer mixes and uploads the program feed (about 2.5 Mbps up). Keep the room tab in front.
6. **Firewalls.** Some corporate/mobile networks block direct video. Add a TURN server (Admin → Settings → ICE servers) if viewers report black screens.
7. **Payments are manual-verified** (M-Pesa Paybill/Till code or bank reference). No automatic M-Pesa (Daraja) or card gateway yet — needs your Safaricom/gateway credentials.
8. **Not tested on your live host.** Tested on PHP 8.3 with SQLite. Run the checklist in the Launch Guide.
9. Not built: SCORM packages, assignments/peer review, drip-release, multi-language, native mobile apps, two-way flag sync to the mail server. Open-rate tracking is approximate.
