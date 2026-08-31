# V1_READINESS.md

# Version 1 Readiness Review

## Syfte

Detta dokument sammanfattar Sprint 11A: en end-to-end-granskning av hur nära projektet är en användbar Version 1.

Granskningen är dokumenterande. Den ändrar inte kod, databas, vyer, frontend, tester eller affärslogik.

---

# Granskat läge

- Datum: 2026-08-31
- Repo: `RDMackan999/Uthyrning`
- Bas: `origin/main` efter Sprint 10C / PR #56
- Reviewperspektiv: publik kund, organisationsadmin/uthyrare och systemadmin
- Slutsats: V1 är tekniskt nära ett komplett uthyrningsflöde, men bör inte betraktas som produktionsredo innan P1-punkterna är hanterade.

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
| Publik objektkatalog | Redo för V1-verifiering | 0 | 0 | 0 | 1 | Landningssidans CTA, sök och kategorilänkar leder in i PHP-katalogen. |
| Objektdetalj/media | Delvis redo | 0 | 0 | 1 | 1 | Bilder visas via kontrollerade routes. Dokument/manualer saknas. |
| Bokning | Delvis redo | 0 | 0 | 1 | 0 | Bokningsförfrågan fungerar konceptuellt, men behöver full manuell V1-verifiering. |
| Tillgänglighet | Delvis redo | 0 | 0 | 1 | 1 | Kalender och manuella block finns. Serviceblockering saknas eftersom service inte är byggt. |
| Admin objekt | Delvis redo | 0 | 0 | 2 | 1 | Objekt, pris, publicering och media finns, men tekniska ID:n läcker i UI. |
| Admin bokningar | Delvis redo | 0 | 0 | 1 | 0 | Statusflöde och fulfillment finns. Avtalskoppling saknas. |
| Kunder | Delvis redo | 0 | 0 | 1 | 0 | Kundregister och historik finns. GDPR/anonymisering är inte implementerad. |
| Genomförande | Redo för V1-verifiering | 0 | 0 | 1 | 0 | Utlämning/återlämning finns. Full service- och dokumenthantering är post-V1. |
| Notifieringar | Delvis redo | 0 | 0 | 1 | 1 | E-postflöde och retry finns. Produktionstransport måste konfigureras och verifieras. |
| Behörighet | Delvis redo | 0 | 0 | 1 | 0 | Org-scope finns och verkar konsekvent. Behöver E2E-verifieras i separat testmiljö. |
| Säkerhet | Delvis redo | 0 | 0 | 1 | 0 | CSRF/PDO/loggningsprinciper och testisolering finns. Prod-check återstår. |
| Navigation | Redo för V1-verifiering | 0 | 0 | 1 | 0 | Publik kundresa och huvudnavigation i admin är sammanhållen. Systemadminflöden behöver fortsatt verifieras. |
| Fel-UX | Redo för V1-verifiering | 0 | 0 | 0 | 0 | Svenska 403/404-vyer finns utan interna detaljer. |
| Responsivt | Oklart | 0 | 0 | 1 | 0 | CSS är responsiv, men ingen visuell mobil/Desktop-verifiering gjordes i reviewn. |
| Tillgänglighet | Delvis redo | 0 | 0 | 2 | 0 | Semantiska element finns. Behöver tangentbordsskärmning och kalendertest med skärmläsare. |
| Testisolering | Redo för V1-verifiering | 0 | 0 | 0 | 0 | Testsviten vägrar köra databastester utan `APP_ENV=test` och dedikerad testdatabas. |
| Produktionsredo | Delvis redo | 0 | 0 | 2 | 1 | Production config gate, säkerhetsheaders och minimal health check finns. Manuell deployment-, backup/restore-, SMTP- och tillgänglighetsverifiering återstår. |

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

# Prioriterade problem

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
