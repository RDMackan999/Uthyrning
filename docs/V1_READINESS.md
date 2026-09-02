# V1_READINESS.md

# Version 1 Readiness Review

## Syfte

Detta dokument sammanfattar V1-readiness och den senaste release candidate-granskningen.

Granskningen är dokumenterande och releaseförberedande. Den ändrar inte affärslogik, databasmodell, frontenddesign eller scope.

---

# Granskat läge

- Datum: 2026-09-01
- Repo: `RDMackan999/Uthyrning`
- Bas: `origin/main` efter Sprint 11C / PR #59
- Reviewperspektiv: publik kund, organisationsadmin/uthyrare och systemadmin
- Slutsats: V1 har inga kända P0/P1-kodblockerare efter Sprint 12A-verifieringen. Release candidate kan rekommenderas för manuell staging-/produktionsnära acceptans, men skarp V1 kräver fortfarande faktisk driftkonfiguration, juridiskt innehåll och manuell tillgänglighets-/responsiv verifiering.

---

# RC2 bugfix efter stagingtest

Stagingtest av `v1.0.0-rc.1` på Polar55 identifierade två blockerande regressionsfel:

- publik sökning återanvände en namngiven PDO-placeholder och gav `HY093` med native MySQL prepared statements
- riktig HTTP-bilduppladdning flyttade PHP:s temporära originalfil innan bildvarianterna skapades

RC2-fixen använder unika sökparametrar, skapar bildvarianter före den destruktiva originalflytten och loggar fångade uploadfel säkert. Regressionstester täcker native PDO-semantik, destruktiv flytt, rollback, temporärfilstädning och controllerloggning.

Efter merge och ny RC-tagg krävs ett nytt staging-smoke-test av publik sökning och riktig HTTP-uppladdning innan `v1.0.0-rc.2` kan godkännas för vidare release.

---

# Dokument som låg till grund

Följande dokument lästes inför granskningen:

- `README.md`
- `docs/DOCUMENTATION_INDEX.md`
- `docs/ARCHITECTURE.md`
- `docs/CODEX_RULES.md`
- `docs/CODEX_WORKFLOW.md`
- `docs/PROJECT_DECISIONS.md`
- `docs/CODING_STANDARDS.md`
- `docs/SECURITY.md`
- `docs/DATABASE_DESIGN.md`
- `docs/DATABASE_PRINCIPLES.md`
- `docs/DATABASE_NAMING_STANDARD.md`
- `docs/BUSINESS_RULES.md`
- `docs/UI_FLOW.md`
- `docs/USER_JOURNEYS.md`
- `docs/MVP_SCOPE.md`
- `docs/ROADMAP.md`
- `docs/DEVELOPMENT_GUIDE.md`
- `docs/DEPLOYMENT.md`
- `docs/RELEASE_PROCESS.md`

---

# Readiness Matrix

| Område | Status | P0 | P1 | P2 | P3 | Kommentar |
|---|---:|---:|---:|---:|---:|---|
| Publik objektkatalog | RC-verifierad | 0 | 0 | 0 | 1 | `/items` svarar lokalt och automatiska tester verifierar endast publikt bokningsbara objekt. Pagination är post-V1. |
| Objektdetalj/media | RC-verifierad med manuellt innehållsbehov | 0 | 0 | 1 | 1 | Detaljvy svarar, fallback för saknad bild fungerar och mediaflödet testas automatiskt. Verkliga bilder/innehåll behöver granskas före skarp drift. |
| Bokning | RC-verifierad | 0 | 0 | 0 | 0 | Publik bokningsförfrågan skapades i isolerad testdatabas och automatiska tester täcker snapshots, överlapp, CSRF och bekräftelse. |
| Tillgänglighet | RC-verifierad med manuell tillgänglighetskontroll kvar | 0 | 0 | 1 | 1 | Kalender och blockeringsregler testas automatiskt. Skärmläsar-/tangentbordsrunda i faktisk browser återstår. |
| Admin objekt | RC-verifierad | 0 | 0 | 0 | 1 | Objekt, pris, publicering, media och huvudnavigation täcks av tester och admin-smoke. Större listor saknar pagination. |
| Admin bokningar | RC-verifierad | 0 | 0 | 0 | 0 | Admin-smoke verifierade godkännande, utlämning, återlämning och slutförd bokning i testdatabas. |
| Kunder | RC-verifierad med GDPR-process kvar | 0 | 0 | 1 | 0 | Kund skapas via gästbokning och kundadmin täcks av tester. Retention/anonymisering kräver senare process. |
| Genomförande | RC-verifierad | 0 | 0 | 0 | 0 | Utlämning och återlämning verifierades lokalt med faktisk fulfillmentdata och statushistorik. |
| Notifieringar | RC-verifierad i testtransport | 0 | 0 | 1 | 1 | Bokningsnotifieringar skapades och skickades via test/development-flöde. Produktions-SMTP kräver manuell driftverifiering. |
| Behörighet | RC-verifierad | 0 | 0 | 0 | 0 | Testsviten täcker systemadmin, organization admin och cross-tenant-skydd. Systemadminnavigation finns i adminlayout. |
| Säkerhet | RC-verifierad med driftgate kvar | 0 | 0 | 1 | 0 | CSRF, PDO-principer, säkerhetsheaders, health payload, auth och production guard täcks av tester/smoke. Faktisk production config måste verifieras i driftmiljö. |
| Navigation | RC-verifierad | 0 | 0 | 0 | 0 | Publika och administrativa huvudvägar svarar, och systemadminflödet finns i navigation när rollen har rättighet. |
| Fel-UX | RC-verifierad | 0 | 0 | 0 | 0 | 404-smoke visar svensk felsida utan stacktrace eller interna detaljer; oinloggad admin redirectar till login. |
| Responsivt | Delvis verifierad | 0 | 0 | 1 | 0 | CSS har responsiva regler och build passerar. Visuell mobil/desktop-verifiering i riktig browser återstår. |
| Tillgänglighet | Delvis verifierad | 0 | 0 | 1 | 0 | Labels, focus states och ARIA finns i kritiska vyer. Manuell tangentbords- och skärmläsarverifiering återstår. |
| Testisolering | RC-verifierad | 0 | 0 | 0 | 0 | Migration, seed och testsvit kördes mot separat `uthyrning_test_rc`; testskydd stoppar osäkra databaser. |
| Produktionsredo | Konfiguration krävs | 0 | 0 | 2 | 1 | Production config gate finns. Skarp drift kräver HTTPS, SMTP, backup/restore, storage-rättigheter och smoke test i faktisk miljö. |

---

# Sprint 11B uppdatering

Sprint 11B stänger eller omklassificerar de högsta V1-riskerna utan att bygga nya större produktområden.

Löst eller stabiliserat:

- P1-01 Testisolering: `tests/run.php` stoppar innan första databasskrivning om miljön inte är uttryckligen test och databasnamnet inte är en dedikerad testdatabas.
- P1-02 Publik kundresa: landningssidans CTA, sökformulär, kategorier och exempelobjekt leder till PHP-katalogen på `/items`; PHP-rooten skickar också vidare till katalogen.
- P1-03 MVP-yta: full avtal/PDF/digital signering, avancerad dokumenthantering och serviceorder omklassificeras till post-V1 om ingen separat release-sprint specificerar dem. V1 behåller manuell uthyrningshantering med bokning, kund, notifiering, media, utlämning och återlämning.
- P2-01 Dashboardtext: tekniska och gamla placeholdertexter är ersatta med V1-relevant svensk text.
- P2-02 Tekniska ID:n: V1-kritiska adminvyer visar publika referenser där referens behövs och döljer interna tekniska id:n där de inte behövs för administratören.
- P2-04 Fel-UX: svenska 403/404-vyer finns för publik och admin.
- P2-07 Dokumentationsdrift: `README.md`, `docs/ARCHITECTURE.md`, `docs/DEVELOPMENT_GUIDE.md`, `docs/BUSINESS_RULES.md`, `docs/UI_FLOW.md`, `docs/USER_JOURNEYS.md` och `docs/PROJECT_DECISIONS.md` är uppdaterade för nuvarande V1-yta.

Kvar efter Sprint 11B:

- P1: samlad release-hardening före skarp drift.
- P2: publika juridiska standardsidor, manuell tillgänglighetsverifiering, systemadminnavigering i alla relevanta adminvyer och produktionskonfiguration.
- P3: pagination, prestandagranskning av bildleverans och större kataloger.

---

# Sprint 11C uppdatering

Sprint 11C stänger P1-04 som kod- och dokumentationsrisk inför release.

Löst eller stabiliserat:

- Production startar inte med debugläge, osäker base URL, osäkra auth/CSRF-cookies, development/test/exempeldatabas, tomt databaslösenord, development-mailtransport eller oskrivbara storage/media-kataloger.
- Standardiserade säkerhetsheaders läggs på HTTP-svar utan att skriva över specifika controllerheaders.
- `/health` returnerar endast minimal status och kräver fortfarande inte databas.
- Reverse proxy/HTTPS kan tolkas via explicit konfigurerade betrodda proxyadresser.
- Första adminskapande i production kräver explicit bekräftelse via miljövariabel under godkänt releasefönster.
- Deployment- och releaseprocessen beskriver V1-checklista, backup/restore, rollback, SMTP, storage/media och smoke test tydligare.

Kvar efter Sprint 11C:

- P2: publika juridiska standardsidor, manuell tillgänglighetsverifiering, systemadminnavigering i alla relevanta adminvyer och produktionskonfiguration i faktisk driftmiljö.
- P3: pagination, prestandagranskning av bildleverans och större kataloger.

---

# Sprint 12A release candidate-uppdatering

Sprint 12A verifierar V1 release candidate från `origin/main` efter PR #59.

Miljö:

- Datum: 2026-09-01
- Branch: `codex/sprint-12-v1-release-candidate`
- Databas: separat lokal testdatabas `uthyrning_test_rc`
- PHP: 8.3.30
- Databasdrift: lokal MySQL via Laragon
- Frontend/build: befintlig React/Vinext/Sites-struktur

Automatisk verifiering:

- `APP_ENV=test DB_DATABASE=uthyrning_test_rc php database/migrate.php`: passerade.
- `APP_ENV=test DB_DATABASE=uthyrning_test_rc php database/seed.php`: passerade.
- `APP_ENV=test DB_DATABASE=uthyrning_test_rc php tests/run.php`: 59 passerade, 0 fel.
- PHP syntaxkontroll på samtliga PHP-filer under `app`, `config`, `database`, `public`, `resources`, `routes` och `tests`: passerade.
- `composer validate --no-check-publish`: passerade.
- `npm run lint`: passerade med 0 fel och 1 känd befintlig Next-varning för `<img>` i landningssidan.
- `npm run build`: passerade.
- `git diff --check`: passerade.

Lokal HTTP-smoke:

- `GET /health`: 200 och endast `{"status":"ok"}`.
- `GET /items`: 200.
- `GET /items/{public_id}/{slug}`: 200.
- `GET /items/{public_id}/{slug}/book`: 200 med CSRF-cookie och bokningsformulär.
- Publik bokningsförfrågan skapades i testdatabasen.
- Adminlogin med tillfällig testadmin fungerade.
- `/admin`, `/admin/items`, `/admin/bookings`, `/admin/customers`, `/admin/notifications` och `/admin/organization-admins` svarade 200 efter inloggning.
- Adminlivscykeln för testbokning verifierades: `request` -> `approved` -> `active` -> `completed`.
- 404-smoke visade svensk felsida utan stacktrace.
- Oinloggad `/admin` redirectade till `/login`.
- Felaktig login visade generiskt felmeddelande.
- Säkerhetsheaders fanns på verifierade HTML- och JSON-svar.

Acceptansmatris:

| Område | Resultat | Klassning | Kommentar |
|---|---|---|---|
| Full automatiserad regression | Godkänd | Ingen blockerare | 59/0 PHP-tester plus lint/build/composer/diff-check. |
| Ren testdatabas | Godkänd | Ingen blockerare | Separat `uthyrning_test_rc` skapades och migrerades. |
| Publik kundresa | Godkänd i smoke/test | Ingen blockerare | Lista, detalj, bokningsformulär, submit och bekräftelse verifierades lokalt. |
| Adminlogin | Godkänd i smoke/test | Ingen blockerare | Tillfällig testadmin användes endast i testdatabasen. |
| Systemadmin | Godkänd i smoke/test | Ingen blockerare | Organisationsadminflöde nås i navigation och svarar 200 för systemadmin. |
| Organization/cross-tenant | Godkänd i automatiska tester | Ingen blockerare | Testsviten täcker scope, 404-liknande nekad åtkomst och audit för global systemadminåtkomst. |
| Objekt/pris/media | Godkänd i automatiska tester | P3 kvar | Foundation fungerar. Pagination och större media/prestandagranskning är post-V1. |
| Bokningslivscykel | Godkänd i smoke/test | Ingen blockerare | Testbokning slutfördes med fulfillmentdata och statushistorik. |
| Kund | Godkänd i smoke/test | P2 kvar | Kund skapades via gästbokning. GDPR-retention/anonymisering är senare process. |
| Notifieringar | Godkänd i testtransport | Konfiguration krävs | Testnotifieringar skapades/skickades. Production-SMTP måste verifieras i driftmiljö. |
| Fulfillment | Godkänd i smoke/test | Ingen blockerare | Utlämning och återlämning verifierades. |
| Säkerhet | Godkänd för RC | Konfiguration krävs | CSRF, auth, headers, minimal health och production guard verifierades. Production config måste sättas skarpt. |
| Juridiskt innehåll | Ej komplett som standardsidor | Content/business input required | Villkor, integritetspolicy, kontakt/FAQ finns inte som fulla publika PHP-standardsidor. |
| Tillgänglighet | Delvis verifierad | Manual verification required | Kod och markup har bra grund, men tangentbord/skärmläsare ska testas manuellt. |
| Responsivt | Delvis verifierad | Manual verification required | Responsiva regler och build passerar; visuell mobil/desktop-verifiering återstår. |
| Deployment | Dokumenterat men ej skarpt verifierat | Configuration required | HTTPS, SMTP, backup/restore, storage-rättigheter och produktion smoke test kvar i riktig miljö. |

Kvarvarande klassning efter Sprint 12A:

## Code blockers

Inga kända P0- eller P1-kodblockerare.

## Configuration required

- Production config enligt `docs/DEPLOYMENT.md`: HTTPS, säkra cookies, `APP_DEBUG=false`, `SECURITY_FORCE_HTTPS=true`, produktionsdatabas och `MAIL_TRANSPORT=smtp`.
- SMTP ska verifieras i faktisk driftmiljö med riktig provider eller godkänd mail-capture.
- Storage- och media-kataloger ska vara skrivbara av webbservern men inte publikt exponerade.
- Backup och restore ska testas för både databas och `storage/media`.

## Content/business input required

- Juridiskt granskade texter för villkor och integritetspolicy.
- Kommersiell kontaktinformation, företagspresentation och FAQ-innehåll för skarp publik drift.
- Beslut om vilka juridiska standardsidor som måste finnas före första publika release om landningssidans befintliga ankare inte räcker.

## Manual verification required

- Visuell mobil-, surfplatte- och desktopverifiering i faktisk browser.
- Tangentbordsnavigering och skärmläsarliknande kontroll av kalender, formulär och adminflöden.
- Manuell staging-/production-smoke enligt `docs/DEPLOYMENT.md`.
- Granskning av produktionsloggar efter smoke test för att säkerställa att inga hemligheter, stack traces eller känsliga data läcker.

## Post-V1

- Pagination för större listor.
- Prestandagranskning av bildleverans och större kataloger.
- Full dokument-/manualhantering.
- Avtal/PDF/digital signering.
- BankID, Swish, Fortnox, PWA offline, API och marknadsplats.
- GDPR-retention och anonymiseringsverktyg.

Sprint 12A-rekommendation:

V1 kan behandlas som release candidate `v1.0.0-rc.1` efter merge av denna PR, under förutsättning att återstående configuration/content/manual verification hanteras innan skarp V1-release.

---

# Prioriterade problem

Följande avsnitt är den historiska Sprint 11A-klassningen. Den senaste gällande klassningen finns i Sprint 12A-sektionen ovan.

## P0

Inga P0-blockerare hittades i denna dokument- och kodstrukturgranskning.

## P1

### P1-01 Testisolering saknas inför säker V1-verifiering

`tests/run.php` använder projektets vanliga konfiguration och kör seeders samt databasfixtures. Flera tester använder transaktioner, men seedning och vissa filoperationer sker utanför en strikt isolerad testmiljö.

Risk: om `config/database.php` pekar mot `uthyrning_dev` eller senare en delad miljö kan verifiering påverka verklig utvecklingsdata.

Rekommendation: Sprint 11B bör skapa eller dokumentera en hård regel för separat testdatabas, exempelvis `uthyrning_test`, och stoppa testkörning om miljön inte uttryckligen är test.

### P1-02 Publik kundresa är inte helt ihopkopplad

React/Vinext-landningssidan finns kvar och PHP-katalogen finns på `/items`, men startsidans sök- och CTA-flöden är fortfarande i praktiken förberedda länkar/ankare. PHP-rooten returnerar backend-testvyn `Backend initialized`.

Risk: en verklig kund kan komma till landningssidan utan tydlig väg in i den fungerande PHP-katalogen och bokningsflödet.

Rekommendation: Sprint 11B bör fokusera på navigation och minsta möjliga koppling mellan landningssida, katalog, objektdetalj och bokningsförfrågan.

### P1-03 MVP-krav saknar färdig V1-yta

`docs/MVP_SCOPE.md` anger avtal, betalstatus, servicehistorik och dokument som V1-delar. Kodläget har viktiga förberedelser: depositionsfält, fulfillment, bildmedia, bokningssnapshots och villkorsversion. Däremot finns ännu inte färdig administrativ yta för avtalsmall/status, servicehistorik eller dokument/manualer.

Risk: uthyraren kan hantera bokning och utlämning, men behöver fortfarande manuella sidoprocesser för delar som MVP säger ska finnas i systemet.

Rekommendation: antingen bygg minsta V1-stöd för dessa delar eller justera V1-scope innan releasebeslut.

### P1-04 Produktionsredo kräver samlad release-hardening

`docs/DEPLOYMENT.md` och `docs/RELEASE_PROCESS.md` beskriver målbilden, men kodläget behöver en konkret V1-check av produktionskonfiguration, filrättigheter, HTTPS, e-posttransport, loggrotation, backup av `storage/media`, backup av databas och rollback.

Risk: funktioner kan fungera lokalt men ändå vara svåra att driftsätta säkert.

Rekommendation: skapa en release-hardening-sprint innan V1 går skarpt.

## P2

### P2-01 Admin-dashboarden har kvar teknisk/åldrad text

`resources/views/admin/dashboard.php` visar engelska tekniska etiketter som `Authentication` och `Authorization`, samt texten att administrationsfunktioner byggs i kommande sprintar trots att flera adminflöden nu finns.

Risk: adminupplevelsen känns intern och ofärdig.

### P2-02 Tekniska ID:n är synliga i adminflöden

Exempel finns i objekt-, pris- och kundvyer: `Tekniskt ID`, `Tekniskt pris-ID` och `Public ID`.

Risk: administratören behöver förstå tekniska detaljer som inte är nödvändiga för V1-arbetet. Referenser kan behövas för support, men bör de-emphaseras eller flyttas till en detaljerad supportsektion.

### P2-03 Publika standardsidor saknar backend-yta

MVP nämner `Om oss`, `Kontakt`, `FAQ`, `Integritetspolicy` och `Villkor`. Landningssidan innehåller vissa delar och ankarlänkar, men PHP-sidan för katalogen har bara publik navigation till objekt.

Risk: kundens publika väg känns splittrad och juridiskt/kommersiellt ofullständig.

### P2-04 Felhantering saknar användarvänliga 403/404-vyer

`Router` och `AuthorizationMiddleware` returnerar enkla textresponser vid 404/403. Det är säkert nog för teknisk grund, men inte färdigt som publik/admin-UX.

Risk: användare hamnar i tekniska återvändsgränder i stället för tydliga fel- och tillbakalänkar.

### P2-05 Tillgänglighet behöver manuell E2E-verifiering

Vyerna har många bra grunder: labels, alt-texter, aria-labels och fokusmarkeringar. Kalendern och adminformulären bör ändå testas med tangentbord och skärmläsarliknande flöde.

Risk: kalenderns tillgängliga/otillgängliga datum och datumintervall kan vara svåra att förstå utan mus.

### P2-06 Adminnavigationen är inte komplett för systemadmin

Organisationsadmin-hantering finns, men nås via dashboardvillkor och inte i huvudnavigationen.

Risk: systemadmin kan missa ett centralt behörighetsflöde.

### P2-07 Dokumentation och implementation är delvis osynkade

`docs/ARCHITECTURE.md` beskriver fortfarande `routes/web.php` som tekniska routes för `/` och `/health`, trots att routefilen nu innehåller publik katalog, auth och flera adminflöden.

Risk: nya utvecklare och Codex kan göra fel antaganden.

## P3

### P3-01 `docs/DEVELOPMENT_GUIDE.md` innehåller text från annat projekt

Dokumentet nämner `Digital Compliance Platform`, `SjälvEL` och andra begrepp som inte hör till Uthyrning.

Risk: låg direkt produktionsrisk, men hög förvirringsrisk vid onboarding.

### P3-02 Listor saknar pagination

Publik objektlista och flera adminlistor saknar pagination.

Risk: acceptabelt för tidig V1 med få objekt, men behöver åtgärdas innan katalogen växer.

### P3-03 Publik bildhämtning bör prestandagranskas senare

Public listing väljer huvudbild via SQL-subquery och media levereras via PHP-route.

Risk: fullt rimligt för V1, men bör mätas när antal objekt och bilder ökar.

---

# Positiva observationer

- Backend och frontend är separerade tillräckligt för fortsatt inkrementell utveckling.
- PDO, CSRF, password hashing, sessionsmodell och audit-principer finns.
- Organisation-scope används i centrala adminflöden.
- Publicerade objekt kräver aktiv organisation, aktiv kategori, aktivt pris, publicerad status och uthyrningsbar flagga.
- Bokningsflödet använder snapshots och blockerande statusar.
- Fulfillment håller faktisk utlämning och återlämning separerat från bokningens planerade data.
- Media lagras privat och exponeras via kontrollerade routes.
- Notifieringsflödet är idempotent och skiljer kö, försök och transport.

---

# Rekommenderad Sprint 11B

Sprint 11B bör vara en V1 Stabilization & Test Isolation-sprint.

Prioriterad scope:

1. Säkerställ separat testdatabas och stoppa testkörning om miljön inte är test.
2. Uppdatera utvecklingsdokumentation som är direkt felaktig eller gammal.
3. Koppla ihop landningssida, publik katalog, objektdetalj och bokningsflöde med minsta möjliga designpåverkan.
4. Rensa admin-dashboard och navigation från intern/stale text.
5. Lägg till användarvänliga 403/404-vyer.

---

# Behövs Sprint 11C?

Ja, troligen.

Sprint 11C bör fokusera på V1 Release Hardening:

- produktionskonfiguration
- e-posttransport
- backup och rollback
- loggning och loggrotation
- filrättigheter för media
- HTTPS-krav
- manuell releasechecklista
- visuell mobil/Desktop-verifiering
- tillgänglighetsrunda för kalender och adminformulär

---

# Validering

Denna sprint ska inte ändra implementationen.

`php tests/run.php` bör inte köras i denna review innan testdatabasen är isolerad, eftersom testsviten använder projektets konfigurerade databas, kör seeders och skapar fixtures.

Körda kontroller i denna PR ska begränsas till dokumentdiff och whitespace-kontroll.

---

# Self Review

- Följdes `CODEX_RULES.md`? Ja.
- Påverkades React/Vinext-frontenden? Nej.
- Påverkades PHP-backendimplementation? Nej.
- Påverkades databasschemat? Nej.
- Skapades någon kod? Nej.
- Skapades nytt dokument? Ja, detta readiness-dokument. Det ryms inom Sprint 11A eftersom prompten tillåter maximalt ett nytt dokument och reviewresultatet behöver en egen samlad plats.
- Behöver något byggas om innan V1? Ja, P1-punkterna bör hanteras innan skarp release.
- Finns blockerare? Inga tekniska blockerare för PR:n, men testisolering blockerar trygg fullständig verifiering.
- Rekommenderad nästa sprint: Sprint 11B - V1 Stabilization & Test Isolation.
