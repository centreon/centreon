# Authentication providers

Centreon ships five authentication providers. This doc gives each one's configuration keys and flow, then digs into the SAML logout behavior every dev trips on. For how auth events are logged, see [`logging.md`](./logging.md).

## Table of contents

1. [Overview](#1-overview)
2. [SAML](#2-saml)
3. [OpenID Connect](#3-openid-connect)
4. [Web SSO](#4-web-sso)
5. [LDAP](#5-ldap)
6. [Gotchas](#6-gotchas)

---

## 1. Overview

The four "modern" providers implement `ProviderAuthenticationInterface` and converge on the `Login` use case; LDAP still runs through the legacy `centreonAuth` path. Two bounded contexts split the concerns: **`Authentication`** (the login/logout runtime) and **`ProviderConfiguration`** (the stored settings).

| Provider | `Provider::*` | Settings storage | Logout |
|---|---|---|---|
| Local | `local` | `ProviderConfiguration` (JSON) | local session only |
| LDAP | `ldap` | legacy `auth_ressource` tables | local session only |
| OpenID Connect | `openid` | `ProviderConfiguration` (JSON) | optional end-session redirect |
| SAML 2.0 | `saml` | `ProviderConfiguration` (JSON) | optional SLO round-trip |
| Web SSO | `web-sso` | `ProviderConfiguration` (JSON) | local session only |

All `ProviderConfiguration` providers share the same building blocks: `auto_import` (create the contact on first login), and `authentication_conditions` / `roles_mapping` / `groups_mapping` (security access validated against the IdP claims/attributes).

---

## 2. SAML

Centreon is the SAML **Service Provider** wrapping [`onelogin/php-saml`](https://github.com/SAML-Toolkits/php-saml) `~4.3.1`; the customer's IdP holds the identities. Three endpoints (`config/routes/Centreon/authentication.yaml`):

| Route | Method | Path | Role |
|---|---|---|---|
| `…_login_saml` | GET | `/login/saml` | build the `AuthnRequest`, 302 to the IdP |
| `…_saml_acs` | POST | `/saml/acs` | Assertion Consumer Service — consume the IdP response |
| `…_callback_sls` | GET | `/saml/sls` | Single Logout Service — IdP logout callback |

The toolkit `OneLogin\Saml2\Auth` is always built through `SamlAuthFactoryInterface`, so the flow stays mockable.

### Configuration keys

Stored as JSON, rehydrated into `…\Domain\SAML\Model\CustomConfiguration`:

| Key | Meaning |
|---|---|
| `entity_id_url` | IdP entityId / issuer — **also the logout `NameID` fallback ([§2 trap](#the-logout-nameid-trap))** |
| `remote_login_url` | IdP SSO URL (`AuthnRequest` target) |
| `logout_from_url` | IdP SLO URL (`LogoutRequest` target) |
| `logout_from` | `false` = local logout only; `true` = also log out of the IdP |
| `certificate` | IdP signing x509 cert |
| `user_id_attribute` | assertion attribute carrying the Centreon login |
| `requested_authn_context` (+ `_comparison`) | request an authn context (`exact`/`minimum`/`maximum`/`better`) |
| `auto_import` (+ `contact_template`, `email_bind_attribute`, `fullname_bind_attribute`) | create the contact from the assertion |

`OneLoginSettingsFormatter::format()` maps these onto the toolkit settings. Two facts are load-bearing:

- **`sp.entityId` is the host Centreon is reached on** (`HttpUrlTrait::getHost()`, resolved live from the request) — it must match the **Audience** declared on the IdP, or the ACS rejects the response.
- **`idp.entityId` (= `entity_id_url`) is not just the issuer** — it is what php-saml emits as the logout `NameID` when none is provided (see below).

### Login & logout flow

On the ACS step, `SAML::authenticateOrFail()` validates the `SAMLResponse`, reads the login from `user_id_attribute`, then stores the identifiers a future `LogoutRequest` needs in the **native** session (`$_SESSION['saml']`): `samlNameId`, `samlSessionIndex`, `samlNameIdFormat`, `samlNameIdNameQualifier`, `samlNameIdSPNameQualifier`.

`LogoutSession` runs the SAML logout **before** local invalidation (because `logout()` reads `$_SESSION['saml']`): it forwards the five identifiers to `Auth::logout()` and redirects to the IdP SLO URL; the IdP then calls `/saml/sls`, which invalidates the local session.

### The logout `NameID` trap

When `OneLogin\Saml2\LogoutRequest` is built with an **empty `nameId`**, php-saml silently substitutes the IdP entityId:

```php
// vendor/onelogin/php-saml/src/Saml2/LogoutRequest.php (~L94-101)
} else {
    $nameId = $idpData['entityId'];      // ← fallback
    $nameIdFormat = Constants::NAMEID_ENTITY;
}
```

So a missing `NameID` produces a `LogoutRequest` that identifies *the IdP itself* instead of the user — and SLO does nothing. **It hides easily:** tolerant IdPs (e.g. Keycloak) accept a wrong `NameID`, while strict IdPs (e.g. OneLogin) validate it against the one issued at authentication — so the bug only surfaces against a strict IdP, which is the one to test SLO with.

**Fix:** capture the real `NameID` + format + qualifiers + `SessionIndex` at ACS time and forward them to `Auth::logout()`. The format/qualifiers matter too — some IdPs reject a `LogoutRequest` whose `NameID` shape differs from the assertion.

> **OneLogin SLO URL subtlety:** OneLogin's SLO endpoint URL often uses a *numeric app id* while its SSO endpoint uses a UUID — `logout_from_url` is **not** derived from `remote_login_url`. Copy each from the IdP separately, or the `LogoutRequest` goes nowhere.

---

## 3. OpenID Connect

Provider `OpenId` (authorization-code flow). Config (`…\Domain\OpenId\Model\CustomConfiguration`):

| Key | Meaning |
|---|---|
| `client_id` / `client_secret` | OIDC client credentials |
| `base_url` | issuer base; every endpoint below is appended to it |
| `authorization_endpoint` / `token_endpoint` | auth-code exchange |
| `introspection_token_endpoint` / `userinfo_endpoint` | resolve the user |
| `end_session_endpoint` | logout (RP-initiated) |
| `connection_scopes` | requested scopes |
| `login_claim` | claim carrying the Centreon login |
| `authentication_type` | how client creds are sent (`client_secret_post` / `_basic`) |
| `verify_peer` | TLS peer verification toggle |
| `auto_import` (+ template / bind attrs) | create the contact from the claims |

**Logout** (`OpenId::logout($idToken, $stay)`) redirects to `base_url + end_session_endpoint` with `post_logout_redirect_uri` and `id_token_hint`. The `id_token` must be **captured before** the local session is invalidated, then the redirect happens **after** — the reverse of SAML's ordering (`logout()` does not read the session, so there is no `/saml/sls`-style callback).

---

## 4. Web SSO

Trusts a user pre-authenticated by an upstream reverse proxy — there is no redirect dance. Config (`…\Domain\WebSSO\Model\CustomConfiguration`):

| Key | Meaning |
|---|---|
| `login_header_attribute` | request header carrying the authenticated login |
| `pattern_matching_login` / `pattern_replace_login` | regex to extract the login from the header value |
| `trusted_client_addresses` | client IPs allowed to assert a header identity |
| `blacklist_client_addresses` | client IPs always refused |

Because the trust is purely network-based, the IP allow/block lists are the security boundary — a misconfigured proxy that lets a client set the header is an auth bypass. Logout clears the local session only.

---

## 5. LDAP

LDAP is the one provider still handled by the **legacy** `centreonAuth` / `centreonAuth.LDAP` path, not the DDD providers. Its settings live in the legacy `auth_ressource` / `auth_ressource_host` / `auth_ressource_info` tables (configured in the UI, no provider REST API): bind DN + password, host/port, search base and filters, plus per-resource flags such as `ldap_auto_import` and `ldap_store_password`.

Login goes through the local form (the password is verified by binding against the directory); logout clears the local session only.

---

## 6. Gotchas

- **`getHost()` needs a live request.** It resolves the SAML `sp.entityId` from `RequestStack` and throws when there is none. In a unit test, inject one (`$formatter->setHttpServerBag($requestStack)`) instead of faking `$_SERVER`.
- **SAML `sp.entityId` must equal the IdP Audience** — reach Centreon on the host declared on the IdP.
- **php-saml 4.x rejects SHA-1** signatures (*Signature validation failed*) — sign with SHA-256 on the IdP.
- **Two sessions.** SAML identifiers live in the native `$_SESSION['saml']`; the Centreon user lives in the Symfony session.
- **Logout ordering differs by provider.** SAML logs out before local invalidation (it reads the session); OpenID captures the `id_token`, invalidates, then redirects. Not interchangeable.
- **Build SAML `Auth` through `SamlAuthFactoryInterface`**, never `new Auth(...)`, to keep the flow testable.
