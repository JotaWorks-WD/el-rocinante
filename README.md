# El Rocinante

The Jota Works parent theme. Child themes build on it; it ships the head / SEO layer, schema, Theme Settings, the FAQ and forms modules, and (since 8.1.0) authenticated mail. Changes are in `CHANGELOG.md`; the network rules are in the workspace `CONVENTIONS.md`.

---

## Setting up mail on a site

Since **8.1.0** every `wp_mail()` on an El Rocinante site — form notifications included — can go through an authenticated SMTP provider, with no plugin. The steps below use **Brevo**; any SMTP provider works the same way (host, port, login, key).

**If a mail plugin is active** (Post SMTP, WP Mail SMTP, FluentSMTP), El Rocinante's mail stays off and Theme Settings → Mail says which plugin is handling it. Deactivate that plugin first if El Rocinante should send.

### 1. Verify the sending domain in Brevo (SPF / DKIM)

The From address must be on a domain you have verified, or providers will send it to spam or reject it.

1. In Brevo, open **Senders, Domains & Dedicated IPs → Domains** and add the site's domain (for example `fishpotrero.com`).
2. Brevo shows the DNS records to add: a Brevo verification code, **DKIM** and **DMARC**, plus **SPF**. Add each one at the domain's DNS host **exactly as Brevo shows it** — copy the values, do not retype them. If the domain already has an SPF record, add Brevo's `include:` to it; a domain must have only one SPF record.
3. Back in Brevo, verify the domain. DNS can take a while to propagate.
4. Under **Senders**, add the From address you will use (for example `bookings@fishpotrero.com`).

### 2. Create the SMTP key

1. In Brevo, open **SMTP & API → SMTP**.
2. Note the **SMTP server**, **port** and **login** shown there.
3. **Generate a new SMTP key** and copy it once — it is the password. Never put it in a repo, an email or a ticket.

### 3. Add the constants to wp-config.php

Above the line `/* That's all, stop editing! */`, on the server only:

```php
define( 'ROCI_SMTP_HOST',   'smtp-relay.brevo.com' );   // the SMTP server Brevo shows
define( 'ROCI_SMTP_PORT',   587 );
define( 'ROCI_SMTP_USER',   'your-login@smtp-brevo.com' ); // the SMTP login Brevo shows
define( 'ROCI_SMTP_PASS',   'the-smtp-key' );
define( 'ROCI_SMTP_SECURE', 'tls' );                     // 'tls' for 587, 'ssl' for 465
```

- The module turns on only when `ROCI_SMTP_HOST`, `ROCI_SMTP_USER` and `ROCI_SMTP_PASS` are all defined. Otherwise WordPress's default mail runs, unchanged.
- `define( 'ROCI_SMTP_DISABLED', true );` turns it off again without removing the others (for example on a staging copy).
- The key is never stored in the database and never shown in the admin, the CLI or an error message.

### 4. Theme Settings → Mail

1. **From Name** — the business name people see (for example "Fish Potrero").
2. **From Email** — the address on the domain verified in step 1. A warning appears if its domain is not the site's: that is fine only if that domain is verified too.
3. **Form Notifications To** — where form submissions are emailed (for example `fishpotrero@gmail.com`). It can be any inbox; it is separate from the From address. Empty → the site admin email (after any older Forms or Business setting).
4. Save. The **Status** line should read "SMTP active: host:port (TLS), user ab•••@…".

The From settings replace only WordPress's default `wordpress@…` sender; mail that sets its own From keeps it. Form notifications always come From the site, with Reply-To set to the visitor.

### 5. Send a test

- In Theme Settings → Mail, **Send test email** (to the notification address, or type another). It shows "Test email sent to …" or the provider's error.
- Or on the server: `wp roci mail-test` (or `wp roci mail-test you@example.com`), and `wp roci mail-status` for the status line.
- Then submit a real form and check **Submissions → Mail**: each submission shows **Sent** or **Failed** with the error. A submission is stored either way.

**Common errors:** "Could not authenticate" → wrong login or key; "Could not connect to SMTP host" → wrong host / port, or the server blocks outgoing SMTP on that port (try 465 with `ssl`, or ask the host); mail sent but in spam → the From domain is not verified (step 1).
