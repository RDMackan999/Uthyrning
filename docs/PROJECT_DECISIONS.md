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

# Beslut 0014

## Datum

2026-07-20

## Status

Accepted

## Titel

Rental item foundation-beslut för Version 1

## Beslut

`public_id` ska vara publik, icke-sekventiell, oföränderlig, separat från tekniskt `id` och genererad i applikationslagret.

`slug` ska vara unik per organisation, inte globalt.

Objekt får skapas utan dagspris som utkast.

Dagspris krävs senare innan objektet får publiceras som bokningsbart.

Deposition är valfri.

`item_rates` ingår i rental item foundation och ska stödja Version 1-priserna `daily`, `weekend`, `weekly` och `monthly`.

Flera kategorier förbereds via `item_category_relations`, men applikationslagret använder fortfarande en primär kategori i Version 1.

Tills konfigurerbara statusdefinitioner byggs använder foundation-lagret kontrollerade statusnycklar för objektstatus och publiceringsstatus, inte ENUM.

## Motivering

Besluten stänger de öppna Sprint 4A-frågorna som måste vara låsta innan första objektsmigrationen.

En icke-sekventiell publik identifierare minskar risken att interna id:n exponeras eller kan räknas upp.

Slug per organisation gör framtida marknadsplats möjlig utan att alla uthyrare måste dela global URL-namnrymd.

Utkast utan dagspris gör adminflödet enklare utan att publiceringsregeln tappas bort.

`item_rates` från start gör att pris kan utvecklas över tid utan att låsas direkt i `rental_items`.

## Konsekvens

Sprint 4B får skapa migrationer, modeller och repositories för `rental_items` och `item_rates`.

Ingen admin-CRUD, frontend, publik objektlista, bokningslogik, mediahantering, dokumenthantering eller avancerad prislogik ingår.

Kommande publiceringssprint ska kontrollera att bokningsbara objekt har dagspris och övriga kravdata.

---

# Beslut 0015

## Datum

2026-07-20

## Status

Accepted

## Titel

Publiceringsregler för uthyrningsobjekt i Version 1

## Beslut

Version 1 använder publiceringsstatusarna `draft`, `published` och `archived` för uthyrningsobjekt.

Tillåtna övergångar är:

- `draft` till `published`
- `draft` till `archived`
- `published` till `draft`
- `published` till `archived`

Arkiverade objekt får inte publiceras direkt.

Publiceringsregler ska samlas i `RentalItemPublicationService`.

Ett objekt får endast publiceras när grunddata finns, objektet är aktivt och uthyrningsbart, objektet inte är soft delete:at och objektet har minst ett aktivt dagspris (`rate_type = daily`) i `item_rates`.

Prisadministration, pris-CRUD och pris-UI byggs inte i publiceringssprinten.

## Motivering

Publicering är en domänregel och ska därför inte spridas mellan controller, repository och vyer.

Dagspris krävs för bokningsbara objekt enligt tidigare beslut, men administration av priser ska införas i en separat sprint för att hålla scope tydligt.

## Konsekvens

Controller får endast anropa publiceringsservicen och visa resultat.

Repositories får endast lagra och hämta status- och prisdata.

Kommande prissprint ska bygga adminflödet för att skapa och ändra priser utan att flytta publiceringsreglerna från servicen.

---

# Beslut 0016

## Datum

2026-08-13

## Status

Accepted

## Titel

Bokningsmodell för Version 1

## Beslut

Version 1 använder `bookings` som bokningens huvudmodell och `booking_items` från start, även om applikationsflödet endast tillåter ett objekt per bokningsförfrågan.

Gästbokning ska tillåtas i Version 1. Kund ska kunna skicka bokningsförfrågan utan användarkonto genom att lämna namn, e-post och telefon. Företag och kommentar är frivilliga.

Bokningar sker per kalenderdag. `start_date` och `end_date` är inklusiva. Samma datum får inte vara både slutdatum för en blockerande bokning och startdatum för en ny bokning för samma objekt.

Statusnycklarna för Version 1 är `request`, `approved`, `rejected`, `cancelled`, `active` och `completed`.

Statusarna `request`, `approved` och `active` blockerar kalendern. `rejected`, `cancelled` och `completed` blockerar inte kalendern.

Bokningen ska spara snapshot av kundkontakt, pris, antal dagar, valuta och eventuell deposition så att historiska bokningar kan förstås även om kund eller objekt ändras senare.

Alla bokningar ska tillhöra samma `organization` som det bokade objektet.

## Motivering

Modellen håller MVP-flödet enkelt för kunden och administratören, samtidigt som den inte blockerar framtida paketbokningar, kundkonto, marknadsplats, avtal, betalning eller Fortnox/Swish.

Att låta `request` blockera kalendern minskar risken för dubbelbokning och parallella kundlöften i en manuell Version 1-process.

Inklusiva kalenderdagar är enkla att förstå för användaren och säkra innan upphämtning och återlämning får tidpunktsstöd.

## Konsekvens

Kommande bokningsimplementation ska följa `docs/DATABASE_DESIGN.md`, `docs/BUSINESS_RULES.md`, `docs/UI_FLOW.md` och `docs/USER_JOURNEYS.md`.

Ingen bokningskod, migration, controller, route, kalender eller admin-CRUD ingår i detta beslut.

Framtida sprintar behöver specificera eventuell automatisk utgångstid för obesvarade förfrågningar, serviceblockeringar, kundportal, betalstatus och avtal.

---

# Beslut 0017

## Datum

2026-08-19

## Status

Accepted

## Titel

Tillgänglighetskalender för Version 1

## Beslut

Version 1 ska använda `bookings` och `booking_items` som källa till sanning för tillgänglighet. Tillgänglighet ska beräknas av `BookingAvailabilityService` eller motsvarande gemensam domänservice.

Kalendern ska följa Sprint 5-reglerna för `DATE`, inkluderande `start_date` och `end_date`, och samma överlappsregel som bokningsflödet.

Statusarna `request`, `approved` och `active` blockerar kalendern. Statusarna `rejected`, `cancelled` och `completed` blockerar inte kalendern.

Publik kalender ska endast visa om datum är tillgängliga eller ej tillgängliga, samt användarens preliminärt valda startdatum, slutdatum och period. Publik kalender får inte visa kunddata, boknings-id, intern status, företag, kommentarer, prisdata som inte redan är publik eller admininformation.

Publik tillgänglighet ska begränsas till högst 6 månader framåt per fråga i Version 1.

Kalendern är informativ tills bokningsförfrågan skickas. Servern ska alltid kontrollera tillgänglighet igen vid submit.

Version 1 bör börja med server-renderad bokningssida och kan senare kompletteras med ett minimalt publikt JSON-endpoint för kalenderdata. Ett sådant endpoint får endast ta publikt objekt-id eller slug samt ett begränsat datumintervall och returnera anonymiserad tillgänglighet.

Manuella blockeringar, serviceblockeringar och buffertdagar ska designas och implementeras i separat sprint innan de används som kalenderkälla.

## Motivering

Direkt beräkning från bokningar och bokningsrader är enklast, säkrast och minskar risken för att kalendern och bokningslogiken hamnar ur synk.

En materialiserad kalender eller cache kan bli värdefull senare, men introducerar synkroniseringsrisk och bör vänta tills faktisk trafik eller PWA/offline-krav motiverar det.

Publik kalender behöver skydda både personuppgifter och affärsinformation. Kunden behöver bara veta om objektet kan bokas.

## Konsekvens

Kommande implementation ska återanvända gemensam tillgänglighetslogik och får inte skapa alternativa kalenderregler i controller, vy eller JavaScript.

Admin kan senare visa mer detaljerad kalenderstatus än publik vy, men ska fortfarande använda samma tillgänglighetskälla.

Sprint 6B bör besluta om manuell blockering ska implementeras direkt eller vänta tills service-/adminbehovet kräver det.

---

# Beslut 0018

## Datum

2026-08-19

## Status

Accepted

## Titel

Manuell kalenderblockering i Version 1

## Beslut

Sprint 6B implementerar manuell kalenderblockering med tabellen `blocked_periods`.

Tabellen använder explicit `organization_id`, `rental_item_id`, inkluderande `start_date` och `end_date`, `reason_code`, intern notering, skapande administratör samt `deleted_at` för soft delete.

Version 1 tillåter blockeringstyperna `manual`, `maintenance`, `owner_use` och `transport`. Dessa lagras som vanliga textnycklar, inte ENUM.

Publik kalender ska aldrig visa `reason_code`, intern notering, administratör, bokningsstatus eller kunddata. All blockerad tid visas publikt som ej tillgänglig.

Överlappande manuella blockeringar av samma typ för samma objekt nekas. Manuell blockering över befintlig blockerande bokning nekas. Befintliga bokningar ändras inte automatiskt.

## Motivering

`blocked_periods` följer Sprint 6A:s kalenderdesign och håller manuell blockering separat från bokningar, utan att skapa en parallell tillgänglighetssanning.

Minimala blockeringstyper räcker för Version 1 och ger samtidigt plats för framtida service- och transportflöden.

Soft delete bevarar historik och gör arkivering reversibelt på datanivå utan permanent radering.

## Konsekvens

`BookingAvailabilityService` ska kontrollera både blockerande bokningar och aktiva `blocked_periods`.

Admin kan skapa och arkivera blockeringar, men publik kalender får bara använda anonymiserat tillgänglighetsläge.

Service/UH ska senare kunna återanvända samma availability-lager eller kompatibel mekanism utan egen parallell kalendermodell.

---

# Beslut 0019

## Datum

2026-08-19

## Status

Accepted

## Titel

Notifieringsmodell för bokningar i Version 1

## Beslut

Version 1 använder e-post som första notifieringskanal. SMS, push, Kivra, marknadsföringsutskick, spårningspixlar och externa utskicksplattformar skjuts upp.

Notifieringar ska skapas från domänhändelser men får inte avgöra om själva bokningshändelsen lyckas. En bokning eller statusändring ska först sparas och audit-loggas. Därefter skapas notifiering och leveransförsök. Om e-post misslyckas ska bokningen inte rullas tillbaka.

Minsta händelser för Version 1:

- `booking_created`: kund får bekräftelse på mottagen förfrågan och administratör/uthyrare får besked om ny förfrågan.
- `booking_approved`: kund får besked om godkänd bokning.
- `booking_rejected`: kund får besked om nekad förfrågan.
- `booking_cancelled`: kund får besked om avbokning eller annullering.

Händelserna `booking_started` och `booking_completed` ska audit-loggas men behöver inte skicka e-post i första notifieringsimplementationen. `booking_reminder`, `return_reminder`, `overdue` och `booking_changed` förbereds i designen men skjuts upp till senare sprint.

Kundens mottagaradress ska hämtas från bokningens kundsnapshot när notifieringen gäller en specifik bokning. Historiska bokningsnotiser ska inte ändras för att en kund senare ändrar e-post i sin profil. Om snapshot saknas får kund- eller användaruppgift bara användas enligt dokumenterad fallback.

Admin-/uthyrarmottagare ska hämtas från organisationsinställning eller aktiva administratörer med relevant roll. Individuella administratörsadresser får inte hårdkodas.

Notifieringar ska vara idempotenta. Samma händelse, bokning, kanal, mottagare och mall ska inte skapa dubbletter. En framtida implementation bör använda en idempotency-nyckel baserad på dessa värden.

Version 1 ska använda filbaserade PHP-mallar för e-postinnehåll. Databasstyrda mallar och mallredigerare kan införas senare.

E-posttransport ska vara leverantörsoberoende. Development och test ska använda logg-/capture-transport eller explicit testtransport så att riktiga e-postmeddelanden inte skickas av misstag. Produktion kan senare använda SMTP eller annan provider via samma transportgränssnitt.

Audit ska logga `notification_created`, `notification_sent`, `notification_failed` och `notification_retried`, men aldrig hela e-postbody, hemligheter eller känsliga headers.

## Motivering

E-post räcker för ett tryggt manuellt bokningsflöde i Version 1 och är enklare att kontrollera än SMS, push eller externa meddelandetjänster.

Att spara bokningshändelsen före notifieringen skyddar affärsflödet från externa leveransfel. Uthyraren kan fortfarande se misslyckade notifieringar och skicka om manuellt.

Bokningssnapshot som källa till kundadress ger bättre historik, särskilt när bokningar görs utan konto eller när kunden senare ändrar kontaktuppgifter.

## Konsekvens

Sprint 7B bör införa notifieringspersistens, transportgränssnitt, säker development-transport och de första bokningsmailen utan att bygga SMS, push, template editor eller köarbetare.

Kommande kod får inte skicka e-post direkt från controller eller inline i statuslogik. Den ska gå via ett tydligt notifieringslager.

---

# Beslut 0020

## Datum

2026-08-19

## Status

Accepted

## Titel

Kunddomän för Version 1

## Beslut

Kunddomänen ska separera säkerhetsidentitet, affärsrelation och historisk bokningsdata.

`users` är säkerhetsidentiteter för inloggning, roller, sessioner och framtida BankID.

`customers` är affärsrelationer mellan en uthyrande `organization` och en kund. En kund kan vara privatperson eller företag och behöver inte ha användarkonto.

`companies` används för strukturerad företagsdata när företagskunden behöver mer än ett fritextnamn.

`booking_customer_snapshots` är immutable historisk representation av kontaktuppgifterna vid bokning.

Version 1 ska fortsatt tillåta gästbokning. En gästbokning får skapa eller återanvända en minimal `Customer` inom samma `organization`, men ska aldrig automatiskt skapa `User`, kundportal, login eller BankID-koppling.

Matchning får endast ske inom samma `organization`. Normaliserad e-post är primär matchningsnyckel. Telefon får bara användas som stöd eller varning. Företagsmatchning via organisationsnummer kan införas senare.

Kundstatusarna för Version 1 är `active`, `inactive` och `blocked`. Blockerad kund ska inte kunna skapa ny bokningsförfrågan om systemet känner igen kunden, men befintlig historik ska bevaras.

Interna kundanteckningar ska hållas separerade från bokningskommentarer, e-post och snapshots.

`customer_users` används först när kundkonto eller kundportal byggs. En kundrelation kan då ha flera användare och en användare kan vara kopplad till flera kundrelationer.

## Motivering

Modellen bevarar det enkla MVP-flödet med gästbokning samtidigt som kundhistorik, framtida kundportal, företag och marknadsplats förbereds.

Att hålla `customers` organization-scoped minskar risken för dataläckage mellan uthyrare.

Snapshots skyddar bokningshistorik från senare ändringar i kundregister, företag eller användarkonton.

En försiktig matchningsmodell minskar risken att fel personer slås ihop automatiskt.

## Konsekvens

Sprint 8B bör förfina kundrepository och adminflöde för kundlista, kunddetalj, kontaktredigering, status, bokningshistorik och dubblettvarningar.

Sprint 8B bör även granska om `companies.organization_number` ska vara unikt per `organization_id` i stället för globalt, eftersom samma juridiska företag kan vara kund hos flera uthyrare.

Kundportal, kundlogin, BankID, automatiserad merge, avancerad CRM, marketing, fakturering, avtal och Kivra skjuts upp.

---

# Beslut 0021

## Datum

2026-08-20

## Status

Accepted

## Titel

Organisationsscopad adminbehörighet

## Beslut

Adminbehörighet ska byggas med tydlig skillnad mellan global plattformsbehörighet och organisationsscopad behörighet.

`system_admin` är en global systemroll. Den får användas för plattformsadministration, felsökning och kontroller som uttryckligen kräver insyn över flera organisationer. Global åtkomst ska vara avsiktlig, serverkontrollerad och audit-loggad.

`organization_admin` är en organisationsscopad roll. En användare får vara `organization_admin` för en eller flera organisationer genom flera rader i `user_roles`, där varje tilldelning har ett `organization_id`.

Version 1 ska rekommenderat använda en global rollrad för `organization_admin` och lägga organisationsscope i `user_roles.organization_id`. `roles.organization_id` behålls för framtida organisationsspecifika roller, men ska inte krävas för standardrollen i Version 1.

`system_admin` ska endast tilldelas utan organisationsscope. `organization_admin` ska endast ge adminåtkomst när tilldelningen har ett giltigt `organization_id`.

Behörighetskontroller ska bygga ett auth context med minst:

- inloggad användare
- globala systemroller
- organisationer där användaren har adminscope
- aktuell organisationskontext
- resurstyp och resursidentifierare när åtgärden gäller ett befintligt objekt

Aktuell organisationskontext ska i första hand härledas från den resurs som hanteras. För nya resurser får organisation väljas från användarens tillåtna organisationer, men värdet ska alltid verifieras på serversidan.

Resursåtkomst ska alltid kontrolleras mot organisationen som äger resursen. Detta gäller minst uthyrningsobjekt, priser, tillgänglighetsblockeringar, bokningar, kunder, notifieringar och företag.

Route-middleware får göra grov kontroll, till exempel att användaren är `system_admin` eller har någon organisationsadminroll. Resursspecifik åtkomst ska därefter kontrolleras i services eller ett dedikerat auktoriseringslager innan repositories används för ändring.

Direkta resursreferenser får inte läcka om en resurs finns i en annan organisation. Organisation-admin ska få generiskt nekad åtkomst eller 404-liknande svar beroende på flöde. Nekade åtkomstförsök ska audit-loggas utan att avslöja hemligheter.

Framtida roller som `organization_staff`, `booking_manager` och `inventory_manager` förbereds av modellen men designas inte i detalj i denna sprint.

## Motivering

Version 1 har bara en uthyrare, men projektet ska kunna växa till marknadsplats. Om adminbehörighet byggs globalt från början blir cross-tenant-läckage och senare ombyggnad sannolikt.

Att lägga scope i `user_roles` gör att samma användare kan administrera flera organisationer utan duplicerade användarkonton. Det håller modellen enkel för Version 1 och bevarar stöd för mer detaljerade organisationsroller senare.

Tydlig uppdelning mellan route-kontroll och resurskontroll minskar risken för IDOR, särskilt när public_id, adminlänkar och framtida API:er införs.

## Konsekvens

Sprint 8D bör implementera auktoriseringsgrund för organisation-admin utan att bygga roll-UI eller organisationsväljare. Implementationen bör utöka befintlig middleware försiktigt, införa en auth context och lägga serverkontroller för organisationsscope i adminflöden som hanterar befintliga resurser.

Databasmodellen behöver på sikt säkerställa att `system_admin` inte kan råka få organisationsscope och att `organization_admin` inte kan tilldelas utan organisationsscope. Det kan göras via applikationsvalidering först och senare med constraints om databasmotorn stödjer det på ett tydligt sätt.

Audit-loggar bör kunna bära både `actor_user_id`, `organization_id`, resurstyp, resurs-id och resultatet av behörighetskontrollen.

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
- Finmaskiga organisationsroller
- Loggningsstrategi
- GDPR-strategi

---

# Grundprincip

Projektets viktigaste beslut ska alltid dokumenteras.

Kod kan ändras.

Arkitektur kan utvecklas.

Men historiken över varför ett beslut togs ska aldrig gå förlorad.
