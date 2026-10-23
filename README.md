# verzeilberg-2.0

My personal website, [verzeilberg.nl](https://verzeilberg.nl), rebuilt in Symfony 8.1. It replaces the old Laminas site: a blog, events, an agenda, my runs from Strava and my Steam library, plus an admin to manage all of it. The site itself is in Dutch.

## Features

**Public site**
- Blog with categories, search, image carousels, YouTube videos, an RSS feed and comments, with optional moderation
- Events with a map and `.ics` downloads, and an agenda with iCal feeds
- Running stats from Strava, and a games page from the Steam library with my own reviews
- Checklists that visitors or users fill in
- Contact form, sitemap and `robots.txt`; old Laminas URLs redirect to the new pages

**Accounts**
- Registration with email confirmation, followed by admin approval
- Two-step login: a code by email (the default) or an authenticator app, with backup codes and trusted devices
- Profiles with an avatar, a public or private page, friends, and email notifications for replies and friend requests (users can switch these off)

**Admin**
- Content management for the blog, events, agendas (with Google Calendar sync), menus, media and checklists
- Users, roles, permissions, and route permissions for giving non-admins access to admin pages
- A mailbox (IMAP/SMTP) inside the admin
- A status page that checks PHP, the database, mail, background jobs and folder permissions, and shows recent errors from the log

**Security and privacy**
- Login throttling, rate limits and a honeypot on the public forms, and CSRF tokens on every form
- Passwords and tokens for IMAP, Strava and Google are stored encrypted
- Old personal data is deleted automatically: contact messages and emails after six months, unhandled registrations after 30 days

## Tech stack

- PHP 8.4, with the extensions `pdo_mysql`, `intl`, `mbstring`, `sodium`, `gd` and `imap`
- Symfony 8.1, Doctrine ORM 3, Twig, AssetMapper (no Node build step)
- MySQL 8
- Bootstrap 5 and Font Awesome
- `scheb/2fa-bundle` for two-step login, KnpPaginator for paging
- PHPUnit for the tests

## Getting started

```bash
git clone git@github.com:verzeilberg/verzeilberg-2.0.git
cd verzeilberg-2.0
bin/setup.sh --env=dev
```

`bin/setup.sh` installs the packages, asks for the settings, creates the database, runs the migrations, sets folder permissions and offers to create an admin account. You can run it again safely: every step checks what is already there.

### Settings

Settings go in `.env.local`, which is not in git. `.env` only holds empty defaults. To see which settings are needed, which are still missing and where to get them:

```bash
bin/check-env.sh            # development
bin/check-env.sh --env=prod # production
```

The most important ones:

| Setting | What it's for |
| --- | --- |
| `DATABASE_URL` | The MySQL database |
| `APP_SECRET` | Also encrypts the stored passwords and tokens. Never change it once the site is live, or those values can't be read any more |
| `MAILER_DSN` | Sending mail. Every login needs a code by email, so without mail nobody can log in |
| `DEFAULT_URI` | The site's address, used for links in mails sent by commands |
| `STRAVA_*`, `STEAM_*`, `GOOGLE_*`, `GOOGLE_MAPS_API_KEY` | The integrations; the site works without them |

Notification mails (new comments, registrations, contact messages) go to `app.notification_email` in `config/services.yaml`.

### Admin account

```bash
php bin/console app:user:create-admin
```

This creates an administrator, or gives an existing account admin rights and a new password.

## Tests

The tests need their own database. Put its `DATABASE_URL` in `.env.test.local`, then run:

```bash
php bin/phpunit
```

Each test runs inside a database transaction that is rolled back afterwards, so the test database stays clean. Mail is never sent in tests.

## Deployment

**A server with shell access:** after updating the code, run

```bash
bin/update.sh --backup
```

This pulls the code, installs the packages, backs up the database, runs the migrations, compiles the assets, clears the cache and fixes the permissions.

**An FTP-only server** (the current hosting):

```bash
bin/build-release.sh                 # add --with-uploads the first time
```

This builds a ready-to-upload copy in `release/`, with production packages, compiled assets and the settings from `.env.prod.local`. Upload its contents over FTP, then delete `var/cache/prod/` on the server.

There is no shell on that server, so database changes go through phpMyAdmin. Create the SQL locally with `php bin/console doctrine:migrations:migrate --write-sql` and run it there. The admin status page (`/admin/status`) shows whether the database is up to date and whether mail works.

Uploaded files (`public/uploads/`) are not in git. Copy them to the server over FTP.

### Background jobs

Two jobs run by themselves, so they need no cron:
- **Mail:** mails go out right after the page has been sent to the visitor.
- **Privacy purge:** old personal data is deleted once a day.

If the hosting offers cron jobs, add these for the syncs:

```cron
*/15 * * * * php /path/to/bin/console app:agenda:sync-google --quiet
0 * * * *    php /path/to/bin/console app:steam:sync --quiet
```

Both can also be started by hand from the admin.

## Console commands

| Command | What it does |
| --- | --- |
| `app:user:create-admin` | Create an administrator, or reset one |
| `app:steam:sync` | Update the Steam library: playtime, store details and achievements |
| `app:agenda:sync-google` | Update the agendas that are linked to a Google calendar |
| `app:strava:create-token` | Create a Strava access token from an authorization code |
| `app:privacy:purge` | Delete old personal data (this also runs daily by itself) |
| `app:checklist:resync-answers` | Rebuild the stored answers of all checklist items |
| `app:agenda:import-legacy` | Import the agendas from the old site's database (only needed once) |

## Project layout

```
assets/       JavaScript and CSS (AssetMapper; core/, components/, modules/<feature>/)
bin/          console, and the setup/update/release scripts
config/       Symfony configuration
migrations/   Doctrine migrations
src/          Controllers, entities, forms, services, security
templates/    Twig templates (admin/ for the admin, email/ for mails)
tests/        PHPUnit tests
```

## License

Copyright © Sander Verzeilberg. All rights reserved. The code is public to read, but not licensed for reuse.
