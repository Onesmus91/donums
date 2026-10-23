# Donums Admin Guide

Sign in at `/admin` (or `/account/login`). The sidebar only shows what your role allows.

## Who can do what
Only the **Super Admin** can create staff, change anyone's access, edit settings, view the audit log, delete courses/sessions/applicants, or see System & security. Everyone else is a normal member unless you grant a role in **Users & roles**:

| Role | Can access |
|---|---|
| Instructor | Only their own courses (build, publish, view learners) |
| Recruiter | Careers & ATS: vacancies, applicants, CVs, interviews, reports |
| Moderator | Community moderation queue, reports, locking/pinning |
| Host | Create and run their own live sessions |
| Support | Customer requests, partners, deliveries, payments, shared mailboxes |

Adding staff: *Users & roles → Add a staff member*. They get an email to set their own password. Remove access by unticking the role.

## Mail (the shared workspace)
- Pick a **mailbox** at the top-left; folders are Inbox, Starred, Sent, Drafts, Scheduled, Outbox, Archive and Trash. New mail is fetched when you open a mailbox and every 5 minutes by the cron job.
- **Compose** supports rich text, attachments, Cc/Bcc, saved replies, signatures, a branded or plain letterhead, **Send later**, and autosaved drafts. Replies keep the conversation thread.
- **Outbox** shows anything queued or failed with the exact error and a *Send now* button. Failed messages are retried automatically up to 4 times.
- **Create request** turns an email into a customer request in the CRM.
- **Templates** edits the wording of every automatic email (welcome, receipts, interview invites, reminders…) and your own saved replies. *Restore default* undoes edits.
- **Contacts & Campaigns:** newsletters go **only** to contacts marked *Subscribed*. People subscribe via the sign-up tick-box or the footer form; imported lists require you to confirm consent. Every campaign email has a one-click unsubscribe. Campaigns are released gradually (default 25 per 5 minutes) to protect your sender reputation. Use *Send a test to me* first.
- **Mailbox settings:** enter each mailbox's password and servers, then *Save, then test connection*. Run the **Deliverability** check and fix any red items — this is what decides inbox vs spam.

## Academy
*Academy → New course.* Add details and a cover image, then use the **Content** tab to add sections and lessons (video link/upload, reading, download, quiz). Tick *Free preview* on a lesson to let visitors sample it. Set status to **Published** when ready. Price 0 = free instant enrolment; any price = learners get your payment instructions, submit their M-Pesa/bank code, and you verify it under **Payments** (access and a receipt email follow immediately). Certificates are issued automatically on completion and can be verified at `/learning/verify`.
Tip: for long videos, upload them as *Unlisted* on YouTube and paste the link — it is faster and cheaper than hosting video files.

## Careers & ATS
- **Vacancies:** create, approve employer submissions, close or feature. Fill in *required skills*, *minimum experience* and *accepted qualifications* — they drive each applicant's **fit score** (a guide, never a decision).
- **Pipeline:** drag candidates between stages (no email sent). Open a profile to read the CV, rate, tag, add notes, write a **scorecard**, **schedule an interview** (with an emailed invitation) or **move stage with a message**. Rejections are sent with a respectful template you can edit.
- **Applicants** supports search, filters, bulk moves and CSV export; **Reports** shows funnel, sources and time-to-hire. Candidates track their own progress at `/jobs/status`.
- Data protection: *Delete permanently* (Super Admin) removes an applicant and their CV.

## Community
New members' first posts wait in **Community → Approval queue**; approving trusts that member. Reported content appears under **Reports**. You can pin, lock, hide and delete from any discussion page. Categories are managed by the Super Admin.

## Live (webinars)
- **Start instant session:** one click opens your host room immediately; share the page link and people join from any browser (set *Anyone with the link* or *Registered attendees only*).
- **Schedule a webinar:** title, time, seats, price, access, agenda, speakers. Free sessions register instantly; paid sessions release the joining link when you verify the payment. Registrants get reminders (1 day and 1 hour before) and a follow-up with feedback link automatically (needs the cron job).
- **In the room (host):** *Start webinar*, invite people **on stage** from the People tab (up to 6 on camera), admit from the waiting room, mute/remove, launch **polls**, mark Q&A answered, post announcements, share your screen, and press **Record** to save a copy to your computer (choose “This tab” and tick “Share tab audio”). *End for all* finishes the session.
- Afterwards: open the session page for attendance, minutes watched, Q&A, poll results, feedback and CSV export. Paste a YouTube/Vimeo/MP4 link into *Recording link* to publish a replay.
- **Broadcast mode (100+ viewers and social media):** set *Delivery mode = Broadcast* on the session (after the media server is connected under **Live → Streaming**; see `media-server/README.md`). In the room open the **Broadcast** tab: *Go live to attendees*, tick destinations, *Start social stream*. Attendees watch the single program feed; guests you invite on stage are included in it automatically.

## Routine
- Daily: Requests (New), Payments to verify, Mail, Applicants (Applied), Community queue.
- Weekly: System & security health; download a backup (nightly backups are automatic).
- Always: keep two-step verification on for the Super Admin account.
