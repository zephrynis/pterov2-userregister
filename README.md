# UserRegister 1.0.1

Native Pterodactyl Panel v2 extension for public signup with administrator-controlled email verification. Registration starts closed, and email verification is required by default.

## User flow

When registration is open, the login form shows **Create account**. With **Require email verification** on, the visitor enters an email address, follows a verification email, then supplies first name, last name, username, password, and password confirmation. No Panel account exists before verification.

With verification off, the visitor supplies email and all account details in one form and can sign in immediately after registration. No verification email is sent or pending verification request created. The Panel's normal account-created notification still follows its existing mail/queue configuration. First and last names follow the Panel's account validation rules. The success page links to the native login form. Signup grants no administrator privileges or server access.

The signup pages at `/extensions/userregister` work without JavaScript and use the Panel's stylesheet, theme tokens, and applied theme. They match the native login layout: heading above the card, Pterodactyl mark beside the form on desktop and above it on mobile, uppercase labels and buttons, and the native footer. Existing login, password reset, and two-factor authentication remain native.

## Installation and settings

1. Use Panel **2.0.0-beta.2** with SDK **2.0.0-beta.6**. This prerelease integration is deliberately constrained below SDK beta.7; retest before widening it.
2. Validate an unpacked runtime package with `php artisan p:extension:doctor /path/to/userregister`.
3. Install `release/userregister-1.0.1.pteroext` through Admin → Extensions, or `php artisan p:extension:install /path/to/userregister-1.0.1.pteroext --enable`.
4. Check the Panel's existing mail transport and queue worker. Verification email sends immediately; the Panel's normal account-created notification uses its existing queue.
5. Under Admin → Extensions → UserRegister settings, choose **Require email verification** (on by default), enable **Open public registration**, then reload the browser.

Settings include the **Require email verification** toggle (`require_email_verification`), verification lifetime (30 minutes by default, 5–1440 allowed), resend cooldown (60 seconds, 60–3600 allowed), maximum verification requests per email each hour (3, 1–10 allowed), and optional HTTP(S) terms/privacy URLs. Configured policies must be accepted in either signup mode. Only the registration-open switch is public frontend configuration; the server chooses and enforces the verification mode. No mail credentials are stored by this extension.

Existing installations continue requiring verification after upgrade; a missing verification setting means on. Changing the toggle takes effect on the next request. Turning it on rejects submissions from already-open direct registration forms and returns visitors to email-first signup. Turning it off redirects old email-request and inbox pages to direct signup; valid verification links already sent can still finish their verified flow until they expire. Successful direct registration invalidates old links for that email. Changing the toggle does not change existing accounts or mark unverified addresses as verified. No schema migration is needed for this setting.

Closing registration blocks both new requests and completion of pending requests. Existing Panel accounts remain usable. Disable the extension to remove its routes and login contribution after reload. Disabling or removing it retains accounts and extension data. Pending requests expire normally and can be removed by the cleanup command before disabling.

## Verification and abuse protection

- Pending requests live in `ext_userregister_requests`; no password is stored there.
- Links contain 256-bit random tokens. Only SHA-256 hashes are persisted. Opening a link exchanges it for a session and redirects to a clean URL; GET does not consume it.
- Completion rechecks expiry and uniqueness, uses native account validation and creation, and consumes the request in the same database transaction. Row locks and native unique indexes protect duplicate submissions and concurrent resends.
- Resending invalidates older links. A mail transport failure rolls back the rotation, preserving the previous link.
- Verification request responses are neutral for existing accounts, cooldowns, and email limits. Direct registration shows ordinary email/username validation errors. Limits are 5 POST attempts per IP per minute across both signup modes, plus the configurable email limit for verification requests. Use a shared Panel cache and correctly configured trusted proxies for consistent limits across replicas.
- Native web middleware supplies sessions and CSRF protection. Signup responses are private, not cached, and use `Referrer-Policy: no-referrer`.
- Raw verification tokens necessarily appear in the initial email/link URL. Avoid recording signup URL paths in reverse-proxy access logs. The extension does not log tokens, passwords, or mail transport exceptions.
- Cleanup runs hourly through the Panel scheduler. Run `php artisan userregister:prune` manually if needed. Expired links are rejected even if cleanup has not run.
- When required, email verification applies to signup. Subsequent email changes follow the Panel's native behavior; this extension does not add ongoing email verification.

## Development

Use Node.js 22.12+ and the committed lockfile. This directory is independent of the other extensions.

```sh
npm ci
npm run check
npm run test:backend
npm run package
```

`test:backend` uses the existing local Panel container's PHP/framework in a temporary directory. It explicitly replaces database, limiter, cache, session, notification, and queue settings with isolated test stores. It covers both signup modes, mode changes, duplicate accounts, native validation, rollback, and policy acceptance. It creates no hosted accounts and sends no mail. Set `PTERODACTYL_COMPOSE_FILE` to use a different Compose file. These tests use SQLite and real native account creation with fake notifications; they do not prove SMTP delivery or concurrent MariaDB locking.

`tests/browser-router.php` can serve a temporary copy inside the Panel container for browser QA with an isolated SQLite file, sessions/cache directory, and fake mail. It provides development fixture endpoints and must never be exposed publicly. It is excluded from release packages. Tests and scripts are not included in runtime archives.

`npm run package` checks types, frontend tests, and the SDK preset build, then creates a runtime archive with `extension.json` at its root. Run backend tests separately before release. Runtime packages contain PHP, migrations, views, and built assets; development dependencies and the vendored SDK are excluded. The SDK preset preserves bare React/SDK imports for the Panel import map.

## SDK provenance and compatibility

`vendor/sdk` is a development-only copy of `/app/packages/sdk` from the local running Panel on 2026-10-15, SDK **2.0.0-beta.6**, image revision `ea742d332f5861a81e502857446b8df9cb9f0254` (Panel **2.0.0-beta.2**). See `SDK_PROVENANCE.md`.

The documentation snapshot synced 2026-10-14 still demonstrates SDK beta.4; the installed beta.6 SDK and Panel source take precedence. Current constraints are Panel `>=2.0.0-dev <2.0.0-beta.3`, SDK `>=2.0.0-beta.6 <2.0.0-beta.7`, PHP `>=8.3 <9`. The Panel's extension compatibility API still reports `2.0.0-dev` despite the beta.2 release tag.

References: [v2 extensions](https://docs.pterodactyl.io/v2/extensions), [building](https://docs.pterodactyl.io/v2/extensions/building), [reference](https://docs.pterodactyl.io/v2/extensions/reference).

## License

MIT. See LICENSE.
