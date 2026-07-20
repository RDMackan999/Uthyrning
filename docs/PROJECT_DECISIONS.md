# PROJECT_DECISIONS.md

# Project Decisions

## Syfte

Detta dokument innehåller alla större beslut som påverkar projektets arkitektur, teknikval, arbetsflöde och affärslogik.

Målet är att varje viktigt beslut ska vara:

- dokumenterat
- motiverat
- daterat
- spårbart

Om ett beslut inte finns dokumenterat här ska det betraktas som preliminärt.

---

# Hur dokumentet används

När ett större beslut tas ska följande dokumenteras:

- Datum
- Beslut
- Motivering
- Konsekvenser
- Status

Status kan vara:

- Proposed
- Accepted
- Deprecated
- Replaced

Äldre beslut ska aldrig raderas.

De markeras istället som ersatta.

---

# Beslut 0001

## Datum

2026-06-30

## Status

Accepted

## Titel

GitHub är projektets Source of Truth.

## Beslut

All utveckling utgår från GitHub.

Ingen lokal version är överordnad GitHub.

## Motivering

Ger:

- spårbarhet
- historik
- backup
- Pull Requests
- enkel samverkan mellan AI och människor.

## Konsekvens

Alla ändringar ska gå via Git.

---

# Beslut 0002

## Datum

2026-06-30

## Status

Accepted

## Titel

Arbetsmodell

## Beslut

Projektet utvecklas enligt följande ansvar.

Product Owner

Marcus Möller

Ansvar:

- krav
- prioriteringar
- affärsbeslut

Solution Architect

ChatGPT

Ansvar:

- arkitektur
- databas
- säkerhet
- tekniska beslut
- kodgranskning
- dokumentation

Developer

Codex

Ansvar:

- implementation
- tester
- Pull Requests

## Motivering

Ger tydliga roller.

Minskar risken att AI börjar fatta egna arkitekturbeslut.

---

# Beslut 0003

## Datum

2026-06-30

## Status

Accepted

## Titel

Frontend

## Beslut

Nuvarande Codex Sites/Vinext-frontend behålls.

Ingen omskrivning görs innan det finns ett tydligt behov.

## Motivering

Minskar teknisk risk.

Ger snabbare utveckling.

---

# Beslut 0004

## Datum

2026-06-30

## Status

Accepted

## Titel

Backend

## Beslut

Backend byggs i:

- PHP 8.x
- PDO
- MariaDB/MySQL

Stora PHP-ramverk används inte.

## Motivering

Låg komplexitet.

Lång livslängd.

Enkel drift.

---

# Beslut 0005

## Datum

2026-06-30

## Status

Accepted

## Titel

Databas

## Beslut

Databasen designas färdigt innan första migrationen skrivs.

## Motivering

Minskar framtida ombyggnationer.

Ger stabil datamodell.

---

# Beslut 0006

## Datum

2026-06-30

## Status

Accepted

## Titel

Utvecklingsmodell

## Beslut

Projektet utvecklas genom:

Issue

↓

Branch

↓

Commit

↓

Draft PR

↓

Review

↓

Merge

## Motivering

Små förändringar.

Lättare felsökning.

Enklare kodgranskning.

---

# Beslut 0007

## Datum

2026-06-30

## Status

Accepted

## Titel

Version 1

## Beslut

Version 1 ska endast stödja:

- en uthyrare
- egna objekt
- manuell bokningshantering

Ingen marknadsplats.

## Motivering

MVP ska hållas liten.

---

# Beslut 0008

## Datum

2026-06-30

## Status

Accepted

## Titel

Version 2

## Beslut

Version 2 får innehålla:

- flera uthyrare
- marknadsplats
- BankID
- Swish
- Fortnox
- API
- AI
- QR-koder
- GPS
- BI

## Motivering

Version 1 ska först bevisa affärsmodellen.

---

# Beslut 0009

## Datum

2026-06-30

## Status

Accepted

## Titel

Kodstandard

## Beslut

Ingen AI får ändra arkitekturen utan uttryckligt beslut.

## Motivering

Förhindrar okontrollerad teknisk utveckling.

---

# Beslut 0010

## Datum

2026-06-30

## Status

Accepted

## Titel

Dokumentation

## Beslut

Alla större tekniska beslut ska dokumenteras här innan implementation.

## Motivering

Projektets historik ska kunna förstås flera år senare.

---

# Beslut 0011

## Datum

2026-07-10

## Status

Accepted

## Titel

Autentiseringsmodell för Version 1

## Beslut

Version 1 använder e-post och lösenord som första autentiseringsmodell.

E-postverifiering krävs innan skyddade ytor får användas.

Remember me ingår inte i Version 1.

Normal sessionstid är 8 timmar med 30 minuters inaktivitetstimeout.

Efter 5 misslyckade försök för samma konto eller e-post inom 15 minuter spärras inloggning temporärt i 15 minuter.

Efter 20 misslyckade försök från samma IP inom 15 minuter spärras IP temporärt i 30 minuter.

Reset-token och e-postverifieringstoken ska lagras hashade.

Flera samtidiga sessioner tillåts, men aktiva sessioner ska kunna återkallas när sessionsmodellen implementeras.

BankID förbereds som framtida extern identitet men byggs inte i Version 1.

## Motivering

E-post och lösenord är den enklaste säkra startpunkten för en administrerad MVP.

E-postverifiering minskar risken för felaktiga konton och lösenordsreset till fel mottagare.

Remember me kräver persistent token-rotation och återkallelse och bör därför vänta tills sessionsmodellen är stabil.

BankID kräver separat juridisk, teknisk och säkerhetsmässig specifikation.

## Konsekvens

Kommande autentiseringssprintar ska designa och implementera sessioner, reset-token, e-postverifiering, login attempts och audit-loggning enligt `docs/SECURITY.md`, `docs/DATABASE_DESIGN.md` och `docs/BUSINESS_RULES.md`.

Ingen autentiseringskod, migration eller BankID-integration ingår i detta beslut.

---

# Beslut 0012

## Datum

2026-07-13

## Status

Accepted

## Titel

Kategorimodell för uthyrningsobjekt

## Beslut

Kategorier för uthyrningsobjekt ska modelleras som en hybrid mellan globala plattformskategorier och organisationsspecifika kategorier.

Globala kategorier används för gemensam publik filtrering, startsida och framtida SEO.

Organisationsspecifika kategorier ska kunna läggas till i admin när kategoriadministration byggs.

Version 1 ska visa kategorier som en enkel nivå.

Datamodellen får förberedas med `parent_id` för framtida underkategorier, men underkategorier ska inte aktiveras i Version 1.

Varje objekt ska ha exakt en primär kategori i Version 1.

Datamodellen ska förbereda flera kategorier per objekt via relationstabell, men sekundära kategorier ska inte aktiveras förrän ett tydligt behov finns.

`slug` ska vara unik inom sitt scope.

Global kategori-slug ska vara unik bland globala kategorier.

Organisationsspecifik kategori-slug ska vara unik inom samma organisation.

Kategorier kan vara aktiva, inaktiva eller arkiverade.

Inaktiva kategorier ska inte kunna väljas för nya objekt och ska döljas i publika filter, men befintliga objekt ska behålla sin historik.

SEO-fält kan förberedas i datamodellen, men SEO-routes och redirect-hantering byggs senare.

Separat `category_images` ska inte skapas i Version 1.

En enkel `icon_key` kan användas för UI och framtida kategori-bild bör kopplas via mediabiblioteket.

## Motivering

En hybridmodell håller Version 1 enkel men gör att plattformen kan växa till marknadsplats utan att kategoristrukturen måste göras om.

En primär kategori per objekt ger enkel administration och tydlig publik filtrering.

Relationstabell förbereder framtida flera kategorier per objekt utan att låsa databasen till en för snäv modell.

Att vänta med underkategorier, kategoriunika attribut och separat bildhantering minskar komplexitet i MVP.

## Konsekvens

Kommande kategorimigrationer ska följa `docs/DATABASE_DESIGN.md`.

Adminflödet ska börja med enkel kategoriadministration.

Frontend och publik objektlista ska bara behöva hantera en primär kategori i Version 1.

Marknadsplats, SEO, underkategorier, översättningar och avancerade filter kräver separata sprintar.

---

# Beslut 0013

## Datum

2026-07-20

## Status

Accepted

## Titel

Objektmodell för uthyrningsobjekt

## Beslut

Alla uthyrningsobjekt ska modelleras i en gemensam objektdomän med `rental_items` som huvudtabell när objektschemat senare implementeras.

Version 1 ska inte skapa separata tabeller eller separata domänmodeller för verktyg, maskiner, släp, byggutrustning, trädgårdsmaskiner eller fordon.

Varje objekt representerar en fysisk uthyrningsbar enhet.

Varje objekt ska kopplas till `organizations` från start.

Ett objekt kan även kopplas till ett ägarföretag senare om juridiskt ägarskap behöver särskiljas från den operativa uthyrarorganisationen.

Version 1 kräver exakt en primär kategori per publicerat objekt.

Datamodellen ska använda `item_category_relations` för att förbereda flera kategorier senare, men sekundära kategorier ska inte aktiveras i Version 1.

Objekt ska ha intern teknisk identitet och senare även publik identifierare genom `public_id` och/eller `slug`.

QR-kod, streckkod, RFID, GPS, IoT och fordonsunika fält byggs inte i Version 1.

Statusar ska inte vara ENUM. Objektstatus ska kunna modelleras via statusdefinitioner och historik när objektschemat byggs.

Pris bör modelleras via `item_rates` så att prisändringar och framtida prisperioder inte låses direkt på objektets kärnrad.

Media och dokument ska kopplas via media- och dokumentdomänen, inte som filvägar direkt på objektet.

Objekt ska arkiveras eller soft delete:as, inte hårdraderas, eftersom boknings-, avtals-, service- och skadehistorik måste bevaras.

## Motivering

En gemensam objektdomän håller Version 1 enkel och gör samtidigt att bokningar, kalender, media, service, dokument och historik kan återanvändas för alla typer av utrustning.

Separata tabeller per objekttyp skulle skapa duplicerad logik och göra framtida marknadsplats, sökning och bokningar svårare.

En alltför generisk attributmodell skulle ge för hög komplexitet innan verkliga behov finns.

Organisationstillhörighet från start minskar risken för en dyr multi-tenant-ombyggnad när marknadsplatsen införs.

## Konsekvens

Kommande objektdesign, migrationer, modeller och repositories ska följa `docs/DATABASE_DESIGN.md`.

Adminflödet ska börja med en enkel objektadministration för Version 1.

Publik objektlista och objektdetalj ska utgå från aktiva, uthyrningsbara objekt med primär kategori.

SEO, QR-koder, streckkoder, RFID, GPS, IoT, fordonsunika fält, avancerad prislogik och flera kategorier kräver separata sprintar.

---

# Framtida beslut

Exempel på beslut som senare ska dokumenteras:

- API-versionering
- BankID-leverantör
- Swish-integration
- Fortnox-strategi
- Backup-strategi
- Deploy-strategi
- Hosting
- Cache
- Filhantering
- Behörighetsmodell
- Loggningsstrategi
- GDPR-strategi

---

# Grundprincip

Projektets viktigaste beslut ska alltid dokumenteras.

Kod kan ändras.

Arkitektur kan utvecklas.

Men historiken över varför ett beslut togs ska aldrig gå förlorad.
