# API_DESIGN.md

# API-design

## Syfte

Detta dokument beskriver hur API:et för projektet **Uthyrning** ska designas när backend införs.

API:et ska vara:

- konsekvent
- säkert
- versionshanterat
- enkelt att förstå
- enkelt att vidareutveckla
- förberett för framtida integrationer

Detta dokument är styrande för all framtida API-utveckling.

---

# Status

API är inte implementerat ännu.

Detta dokument beskriver målbilden och principerna inför framtida implementation.

Ingen API-kod ska skrivas innan:

- databasdesign är godkänd
- autentisering är definierad
- behörighetsmodell är definierad
- säkerhetskrav är dokumenterade

---

# Grundprinciper

API:et ska följa REST-liknande principer.

Det ska använda:

- JSON
- HTTPS i produktion
- versionshantering
- tydliga HTTP-metoder
- konsekventa svar
- server-side behörighetskontroll

---

# API-versionering

Alla endpoints ska versionshanteras.

Exempel:

```text
/api/v1/objects
/api/v1/bookings
/api/v1/users
```

När inkompatibla ändringar införs ska ny version skapas:

```text
/api/v2/objects
```

Gamla versioner ska inte brytas utan dokumenterat beslut.

---

# HTTP-metoder

Använd standardiserade HTTP-metoder.

| Metod | Användning |
|---|---|
| GET | Hämta data |
| POST | Skapa data |
| PUT | Ersätta hel resurs |
| PATCH | Uppdatera delar av resurs |
| DELETE | Ta bort eller soft delete |

GET får aldrig ändra data.

---

# URL-standard

Använd plural på resurser.

Bra:

```text
/api/v1/objects
/api/v1/bookings
/api/v1/customers
```

Dåligt:

```text
/api/v1/getObject
/api/v1/createBooking
/api/v1/object
```

---

# Exempel på framtida endpoints

## Objekt

```text
GET    /api/v1/objects
GET    /api/v1/objects/{id}
POST   /api/v1/objects
PATCH  /api/v1/objects/{id}
DELETE /api/v1/objects/{id}
```

## Bokningar

```text
GET    /api/v1/bookings
GET    /api/v1/bookings/{id}
POST   /api/v1/bookings
PATCH  /api/v1/bookings/{id}
POST   /api/v1/bookings/{id}/approve
POST   /api/v1/bookings/{id}/reject
POST   /api/v1/bookings/{id}/cancel
```

## Kunder

```text
GET    /api/v1/customers
GET    /api/v1/customers/{id}
PATCH  /api/v1/customers/{id}
```

## Avtal

```text
GET    /api/v1/contracts
GET    /api/v1/contracts/{id}
POST   /api/v1/contracts
POST   /api/v1/contracts/{id}/generate
```

## Dokument

```text
GET    /api/v1/documents
POST   /api/v1/documents
GET    /api/v1/documents/{id}
DELETE /api/v1/documents/{id}
```

---

# Svarsformat

Alla API-svar ska ha konsekvent struktur.

## Lyckat svar

```json
{
  "success": true,
  "data": {
    "id": 1
  },
  "meta": {}
}
```

## Lista

```json
{
  "success": true,
  "data": [],
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 100
  }
}
```

## Fel

```json
{
  "success": false,
  "error": {
    "code": "validation_failed",
    "message": "Valideringen misslyckades.",
    "details": {}
  }
}
```

---

# HTTP-statuskoder

| Kod | Betydelse |
|---|---|
| 200 | OK |
| 201 | Skapad |
| 204 | Ingen data |
| 400 | Felaktig begäran |
| 401 | Inte inloggad |
| 403 | Saknar behörighet |
| 404 | Hittades inte |
| 409 | Konflikt |
| 422 | Valideringsfel |
| 429 | För många anrop |
| 500 | Internt fel |

---

# Felkoder

Felkoder ska vara maskinläsbara.

Exempel:

```text
validation_failed
unauthorized
forbidden
not_found
conflict
rate_limited
internal_error
```

Felmeddelanden ska vara begripliga för användaren men inte läcka intern information.

---

# Autentisering

API:et ska stödja säker autentisering.

För interna webbflöden kan session användas.

För framtida externa integrationer kan API-nycklar eller OAuth-liknande flöde införas.

Ingen API-åtkomst till känsliga resurser får ske utan autentisering.

---

# Auktorisering

All behörighet ska kontrolleras på serversidan.

Frontend får aldrig avgöra åtkomst.

Varje endpoint ska kontrollera:

- användare
- roll
- behörighet
- eventuell ägarkoppling

Exempel:

En uthyrare får bara ändra sina egna objekt.

En kund får bara se sina egna bokningar.

Admin kan se mer beroende på behörighet.

---

# Inputvalidering

All input ska valideras på servern.

Validering ska omfatta:

- datatyp
- längd
- format
- tillåtna värden
- behörighet
- affärsregler

Frontend-validering är endast hjälp för användaren.

---

# Pagination

List-endpoints ska stödja pagination.

Exempel:

```text
GET /api/v1/objects?page=1&per_page=25
```

Standard:

```text
page=1
per_page=25
```

Maxgräns ska sättas, exempelvis:

```text
per_page=100
```

---

# Filtrering

Filtrering ska ske med tydliga query-parametrar.

Exempel:

```text
GET /api/v1/objects?category_id=2&location=falun&available_from=2026-07-01
```

Alla filter ska vitlistas.

---

# Sortering

Sortering ska vitlistas.

Exempel:

```text
GET /api/v1/objects?sort=daily_price&direction=asc
```

Endast godkända fält får användas.

---

# Sökning

Sökning kan ske med:

```text
GET /api/v1/objects?q=skruvdragare
```

Sökning ska skyddas mot:

- SQL injection
- för stora sökningar
- prestandaproblem

---

# Rate limiting

API:et ska på sikt stödja rate limiting.

Särskilt viktigt för:

- inloggning
- bokning
- filuppladdning
- externa integrationer

---

# Idempotens

Vissa POST-anrop bör stödja idempotency key.

Exempel:

- betalning
- bokning
- avtalsskapande
- externa integrationer

Detta minskar risken för dubbelbokningar eller dubbla betalningar.

---

# Loggning

API-anrop ska loggas när det är relevant.

Logga:

- endpoint
- metod
- användare
- statuskod
- tidpunkt
- IP-adress
- felkod

Logga inte:

- lösenord
- tokens
- API-nycklar
- personnummer
- betalhemligheter

---

# Audit Trail

API-anrop som ändrar data ska kunna skapa audit trail.

Exempel:

- objekt ändrat
- bokning skapad
- bokning godkänd
- avtal genererat
- betalstatus ändrad
- behörighet ändrad

---

# Säkerhet

API:et ska följa:

- `docs/SECURITY.md`
- `docs/CODEX_RULES.md`
- `docs/CODING_STANDARDS.md`

Särskilt viktigt:

- HTTPS
- CSRF-skydd vid sessionbaserade formulär
- CORS-kontroll
- inputvalidering
- outputsanering
- säker felhantering

---

# CORS

CORS ska vara restriktivt.

Tillåt endast godkända domäner.

Wildcard ska inte användas i produktion:

```text
Access-Control-Allow-Origin: *
```

---

# API-dokumentation

När API implementeras ska varje endpoint dokumenteras med:

- metod
- URL
- beskrivning
- behörighetskrav
- request-exempel
- response-exempel
- felkoder

---

# Bakåtkompatibilitet

Ändringar som bryter befintliga klienter ska undvikas.

Om breaking changes krävs ska:

- ny API-version skapas
- beslut dokumenteras i `PROJECT_DECISIONS.md`
- gamla endpoints fasas ut kontrollerat

---

# Framtida externa integrationer

API:et ska på sikt kunna stödja:

- BankID
- Swish
- Fortnox
- mobilappar
- externa partners
- framtida AI-tjänster
- rapportverktyg

Dessa ska inte implementeras förrän separata flöden och säkerhetskrav är dokumenterade.

---

# Grundprincip

API:et ska vara förutsägbart.

Samma typ av anrop ska alltid bete sig på samma sätt.

Om en endpoint kräver specialregler ska dessa dokumenteras innan implementation.
