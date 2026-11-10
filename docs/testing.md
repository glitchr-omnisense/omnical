# Testing

- `Testing\FixedProvider` (and `FixedProviderFactory`, "fixed"): calendars, events written in
  memory (ids, etags, conflicts), busy time from those events, holidays - every contract with no
  network, for an application's tests.
- `Testing\RecordedHttpClient`: answers by route (`'~^GET https://…~' => [200, '@file.json',
  ['etag' => ['"1"']]]`), keeps every request (`$requests`, `header()`).
- `Testing\BareTestCase`: a package's classes loaded alone in a process of their own, nothing of
  a framework or an application.
- `Testing\DeclaredBareTestCase`: the same for a package of data - its definition loaded and
  asked over recorded answers.

The providers' tests run on recorded answers only. The two open holiday APIs also have a
`LiveTest` (group `live`, two requests each, a User-Agent with a contact), never in the default
run; the calendar providers have none: they would need an account.
