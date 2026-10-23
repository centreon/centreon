# Non-admin personas with ACLs

Read this when a scenario needs a user who is not admin: ACL rules, read-only access, a user who
sees only some resources, the API as a restricted user. The recipe follows the CLAPI fixtures of
the Centreon E2E suite (`centreon/tests/e2e/fixtures/resources/clapi/config-ACL/*.json`) and
the `addContact` / `addACLGroup` / `addACLMenu` / `addACLAction` / `addACLResource` Cypress
commands of `centreon/packages/js-config/cypress/e2e/commands/configuration.ts`: look there for
more variations before inventing one.

## Build the persona with the scripts

The scripts live in this skill's own directory (`.claude/skills/qa-ticket-verify/scripts/`), run
from the repo root, same as every other command in this recipe.

`.claude/skills/qa-ticket-verify/scripts/create-persona.sh <login> [options]` builds the whole
thing — contact, ACL group, and whichever of menu/resource/action grants you ask for — in the
right order, ending with the `ACL RELOAD` that makes any of it apply. Object names are derived
from `<login>` (`<login>-ACLGROUP` etc.), so personas from different runs don't collide as long
as logins don't. Run it with no arguments' worth of options and it still works: the persona
exists and can log in, but sees nothing until something is granted (handy for a "this is denied"
scenario).

```bash
persona=".claude/skills/qa-ticket-verify/scripts/create-persona.sh"

# A read-write Configuration persona restricted to one host, for a ticket about an ACL'd page
"$persona" qa-user --menu-page Configuration --grant-host "Centreon-Server"

# A read-only Monitoring persona with API access and no resource restriction
"$persona" qa-reader --reach-api --menu-page-ro Monitoring --resource-all

# Actions only (e.g. the right to regenerate a poller's config), no menu/resource grant
"$persona" qa-actor --action "poller_listing|generate_cfg"
```

Read the script's own header comment for the full flag list (`--grant-hostgroup`,
`--grant-servicegroup`, `--grant-hostcategory`, `--grant-servicecategory`,
`--grant-metaservice`, `--filter-instance` to restrict to one poller, `--reach-api-rt`,
`--password`, repeated `--menu-page`/`--menu-page-ro` for several pages). It shells out to
`.claude/skills/qa-ticket-verify/scripts/clapi.sh <object> <action> [values]`, which runs one
CLAPI call directly if you need something the script doesn't wrap, or a sequence it doesn't
support (e.g. a module's own ACL object). Both stop on the first failure: a half-built persona
tests nothing.

Grant exactly what the ticket's scenario describes, not more: an over-permissive persona hides
the bug.

## Make the restriction visible

A persona that sees everything the admin sees proves nothing about ACLs: the ACL code path runs,
but nothing tells it apart from the admin one. Before granting, make sure the stack holds at
least one resource of the right kind **outside** the grant. The fresh stack has a single host,
`Centreon-Server`, which belongs to the default host groups (e.g. `Linux-Servers`), so granting
one of those groups grants everything. Add an ungranted host and service first when needed:

```bash
.claude/skills/qa-ticket-verify/scripts/clapi.sh HOST ADD "qa-hidden-host;qa-hidden-host;127.0.0.1;generic-host;Central;"
.claude/skills/qa-ticket-verify/scripts/clapi.sh SERVICE ADD "qa-hidden-host;qa-hidden-svc;Ping-LAN"
```

and grant the persona something narrower than that (`GRANT_HOST` on `Centreon-Server` only,
not a group that contains every host).

## Check the persona before using it

Log in as the persona and confirm it sees what you granted and **less than the admin**: compare
the same call for both. Equal results mean the grant is too wide — narrow it, or say so in the
report instead of claiming the ACL case is covered. Through the API,
`.claude/skills/qa-ticket-verify/scripts/verify-persona.sh <login> <password> <api-path>` logs in
and prints the response:

```bash
verify=".claude/skills/qa-ticket-verify/scripts/verify-persona.sh"
"$verify" qa-user Centreon!2021User "configuration/hosts?limit=5" | jq '.meta.total'  # only the granted hosts
"$verify" admin 'Centreon!2021' "configuration/hosts?limit=5" | jq '.meta.total'       # the comparison
```

Then run the scenario **as this user, where the ticket happens**:

- A ticket about a page or a form: log in **in the browser** with the persona's credentials
  (step 7 of SKILL.md) and play the scenario there. The session video only records the browser,
  so API calls made with the persona's token never show in it.
- A ticket about the API: pass the persona's token to the calls. The report must then say the
  ACL case was checked through the API, not on screen.

Keep the admin for setup and for the comparison ("admin sees X, the persona sees Y"). If you log
in as both in the browser, log out between them, so each part of the video shows one account.

## In the report

Say in the result row which persona ran it, what it was granted, what it saw against the admin,
and where it ran, e.g. "Pass — qa-user (1 of 2 hosts), UI" or "Pass — qa-user sees 10 of 11
services, API only". If a right can't be expressed with these objects (a module's own
ACL, a Resource Access Management rule the ticket builds in the UI), keep that scenario
`@not-covered` and say why.
