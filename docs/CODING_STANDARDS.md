# CODING_STANDARDS.md

# Kodstandard

## Syfte

Detta dokument beskriver hur kod ska skrivas i projektet **Uthyrning**.

Målet är att all kod ska vara:

- tydlig
- konsekvent
- säker
- testbar
- enkel att förstå
- enkel att vidareutveckla

Kodstandarden gäller både människor och AI-assistenter.

---

# Grundprinciper

Kod ska alltid prioriteras i denna ordning:

1. Säkerhet
2. Läsbarhet
3. Underhållbarhet
4. Testbarhet
5. Prestanda
6. Funktionalitet

Snabb kod som är svår att förstå är inte godkänd.

---

# Generella regler

- Skriv små funktioner.
- Ge variabler tydliga namn.
- Undvik duplicerad kod.
- Undvik onödig komplexitet.
- Kommentera varför något görs, inte vad varje rad gör.
- Använd konsekvent formatering.
- Lämna inte kvar död kod.
- Lämna inte kvar gamla debugutskrifter.
- Lämna inte kvar onödiga TODO-kommentarer.

---

# Språk

Kod skrivs på engelska.

Exempel:

```ts
const bookingStartDate = "2026-07-01";
```

Inte:

```ts
const bokningsStartDatum = "2026-07-01";
```

Kommentarer och dokumentation får skrivas på svenska.

---

# Namngivning

## Variabler

Använd beskrivande namn.

Bra:

```ts
const dailyPrice = 250;
const bookingStatus = "pending";
```

Dåligt:

```ts
const x = 250;
const bs = "pending";
```

---

## Funktioner

Funktioner ska beskriva vad de gör.

Bra:

```ts
calculateBookingPrice()
validateBookingDates()
createAuditLogEntry()
```

Dåligt:

```ts
calc()
handle()
doStuff()
```

---

## Boolean

Boolean-variabler ska börja med:

- `is`
- `has`
- `can`
- `should`

Exempel:

```ts
const isActive = true;
const hasPermission = false;
const canBookObject = true;
```

---

# TypeScript / JavaScript

## Standard

- Använd TypeScript där projektet redan använder TypeScript.
- Undvik `any`.
- Typa props, returvärden och viktiga datastrukturer.
- Håll komponenter små.
- Separera presentation och logik när det är rimligt.
- Undvik stora filer.

---

## React-komponenter

Komponenter ska:

- ha tydligt ansvar
- vara lätta att läsa
- återanvända befintliga komponenter
- inte innehålla onödig affärslogik

Exempel:

```tsx
type ObjectCardProps = {
  name: string;
  location: string;
  dailyPrice: number;
};

export function ObjectCard({ name, location, dailyPrice }: ObjectCardProps) {
  return (
    <article>
      <h3>{name}</h3>
      <p>{location}</p>
      <p>{dailyPrice} kr/dag</p>
    </article>
  );
}
```

---

## Imports

Imports ska hållas rena.

Undvik oanvända imports.

Ordna gärna imports i grupper:

1. externa bibliotek
2. interna komponenter
3. helpers
4. typer
5. styles

---

## State

State ska hållas så lokal som möjligt.

Undvik global state om det inte behövs.

---

# CSS / Styling

## Grundregler

- Följ befintlig styling.
- Ändra inte design utan tydlig uppgift.
- Återanvänd befintliga klasser.
- Undvik inline styles om det inte finns god anledning.
- Håll CSS organiserad.

---

## Responsiv design

Alla publika vyer ska fungera på:

- mobil
- surfplatta
- desktop

Mobilvy är lika viktig som desktopvy.

---

# PHP

När PHP-backend införs gäller följande.

## Standard

- Använd PHP 8.x.
- Använd `declare(strict_types=1);` där det är rimligt.
- Använd PDO för all databasåtkomst.
- Använd `password_hash()` för lösenord.
- Använd `password_verify()` vid inloggning.
- Separera config, databas, logik och presentation.

---

## Säkerhet

PHP-kod ska aldrig:

- interpolera användardata direkt i SQL
- visa interna fel för användaren
- lagra lösenord i klartext
- lita på frontend-validering
- köra uppladdade filer

---

## Felhantering

Fel ska hanteras kontrollerat.

- Visa generella felmeddelanden för användare.
- Logga tekniska detaljer internt.
- Läck inte SQL, filvägar, tokens eller konfiguration.

---

# Databas

Databaskod ska följa:

- `docs/DATABASE_PRINCIPLES.md`
- `docs/DATABASE_NAMING_STANDARD.md`
- `docs/DATABASE_DESIGN.md`

Inga tabeller eller migrationer får skapas utan godkänd databasdesign.

---

# API

När API införs gäller:

- använd konsekvent JSON-format
- validera all input
- kontrollera behörighet serverside
- returnera rätt HTTP-statuskod
- läck inte intern information
- dokumentera endpoints

---

# Säkerhet

Kod ska följa:

- `docs/SECURITY.md`

Särskilt viktigt:

- CSRF-skydd för formulär
- XSS-skydd vid output
- PDO prepared statements
- säker filuppladdning
- säker sessionshantering
- inga hemligheter i repo

---

# Kommentarer

Kommentarer ska användas när de tillför förståelse.

Bra kommentar:

```php
// Regenerera session-id efter inloggning för att minska risken för session fixation.
session_regenerate_id(true);
```

Dålig kommentar:

```php
// Sätter variabeln till true.
$isActive = true;
```

---

# TODO-kommentarer

TODO får bara användas om:

- det är tydligt vad som återstår
- det inte påverkar säkerhet
- det bör kopplas till issue

Exempel:

```ts
// TODO(#42): Replace manual payment status with Fortnox integration.
```

---

# Felmeddelanden

Felmeddelanden till användare ska vara:

- tydliga
- vänliga
- utan tekniska detaljer

Tekniska detaljer ska loggas, inte visas.

---

# Testning

När frontend påverkas ska minst köras:

```bash
npm run lint
npm run build
```

När backend finns ska även relevanta PHP- och databastester köras.

---

# Pull Requests

Kodändringar ska vara små och fokuserade.

En PR ska inte blanda:

- designändringar
- databasändringar
- backendlogik
- dokumentation
- refaktorering

om det inte uttryckligen är syftet.

---

# Beroenden

Nya beroenden får bara införas om de är motiverade.

Innan nytt beroende läggs till ska följande bedömas:

- behövs det verkligen?
- är det aktivt underhållet?
- har det rimlig licens?
- påverkar det säkerheten?
- ökar det komplexiteten?

---

# Prestanda

Optimera inte i förtid.

Först:

1. Skriv tydlig kod.
2. Mät problem.
3. Optimera där det behövs.

---

# Tillgänglighet

Publika vyer ska byggas med grundläggande tillgänglighet:

- semantisk HTML
- tydliga labels
- alt-texter på bilder
- tillräcklig kontrast
- tangentbordsnavigering där det behövs

---

# AI-regler

AI-assistenter ska:

- följa denna kodstandard
- följa `docs/CODEX_RULES.md`
- följa `docs/CODEX_WORKFLOW.md`
- inte gissa vid osäkerhet
- hellre stoppa än göra fel

---

# Definition of Good Code

Kod är bra när:

- den löser rätt problem
- den är enkel att läsa
- den är enkel att testa
- den är säker
- den följer projektets arkitektur
- den inte introducerar onödig komplexitet
- nästa utvecklare förstår den snabbt

---

# Grundprincip

Kod skrivs för människor först och datorer sedan.

Om koden är svår att förstå är den inte färdig.
