# Providers

| Package | Factory | Contracts | Options | Policy in short |
|---|---|---|---|---|
| omnical/google | `google` | calendar-events, free-busy | client_id, client_secret (a reference), token (the store key) or access_token (a reference), send_updates, base_uri, timeout | 10,000/min per project, 600/min per user, 1,000,000/day; sensitive scopes need Google's verification |
| omnical/microsoft | `microsoft` | calendar-events, free-busy (work or school accounts) | client_id, client_secret, tenant, token or access_token, base_uri, timeout | 10,000 requests per 10 minutes per app and mailbox, 4 at a time (both declared) |
| omnical/caldav | `caldav` | calendar-events, free-busy | base_uri, username, password (a reference) or token, calendar_home, user_agent, timeout | the server's own; none documented by iCloud, Fastmail, Nextcloud |
| omnical/openholidays | `openholidays` (data) | holidays | user_agent, base_uri, timeout | free, commercial use allowed, ODbL: credit the source |
| omnical/nager | `nager` (data) | holidays | user_agent, base_uri, timeout | no rate limit; commercial use needs a sponsorship; no holiday portal of one's own |

Every provider's README says what it calls, how each field is read, and what was verified and
what was not. Their policies, as JSON: `vendor/bin/omnisense-policies`.

Which one for what:
- a site's own appointments in the user's calendar: google, microsoft or caldav, by where the
  user's calendar lives;
- an appointment page offering free times: `free-busy` of the provider, `FreeBusy::common()`;
- closing days, delivery dates, a booking calendar's grey days: openholidays where it covers the
  country (regions, school holidays, the local language), nager elsewhere.
