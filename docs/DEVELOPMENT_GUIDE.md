# DEVELOPMENT_GUIDE.md

## Utvecklingsguide

### Syfte

Detta dokument beskriver hur utveckling ska bedrivas i Digital Compliance Platform.

Guiden är avsedd för:

- utvecklare
- tekniska förvaltare
- arkitekter
- AI-assistenter
- externa konsulter
- framtida medarbetare

Målet är att göra utvecklingen säker, konsekvent, spårbar och långsiktigt hållbar.

Dokumentet beskriver arbetsmetoder och utvecklingsprinciper. Det ersätter inte de styrande dokumenten utan fungerar som en praktisk vägledning för hur de ska användas tillsammans.

---

## Projektets källa till sanning

GitHub-repot är projektets Source of Truth.

All utveckling ska utgå från den senaste godkända versionen i GitHub.

Lokala filer, AI-konversationer, anteckningar och tillfälliga utkast är inte styrande om de skiljer sig från GitHub.

Om lokal kod eller dokumentation skiljer sig från GitHub ska skillnaden identifieras och hanteras innan utvecklingen fortsätter.

---

## Obligatorisk läsordning

Innan större utvecklingsarbete påbörjas ska följande dokument läsas:

1. README.md
2. DOCUMENTATION_INDEX.md
3. VISION.md
4. PROJECT_PRINCIPLES.md
5. PROJECT_DECISIONS.md
6. SYSTEM_ARCHITECTURE.md
7. DOCUMENT_STANDARD.md
8. CODING_STANDARDS.md
9. AI_DEVELOPMENT_RULES.md
10. AI_DEVELOPMENT_WORKFLOW.md

Därefter ska de dokument som berör den aktuella uppgiften läsas.

Exempel:

- WORKFLOW_ENGINE.md
- WORKFLOW_BLOCKS.md
- RULE_ENGINE.md
- FORM_ENGINE.md
- REPORT_ENGINE.md
- SECURITY_ENGINE.md
- DATABASE.md
- API.md
- MODULE_SJALVEL.md
- SJALVEL_ENTITY_MODEL.md
- SJALVEL_WORKFLOWS.md

---

## Styrande principer

Utvecklingen ska alltid följa projektets beslutshierarki:

1. Människors säkerhet
2. Lagar och regler
3. Projektets vision
4. Projektets principer
5. Affärsnytta
6. Teknik
7. Bekvämlighet

Vid konflikt mellan dokument ska arbetet stoppas tills frågan har utretts och dokumenterats i PROJECT_DECISIONS.md.

---

## Projektets arkitektur i korthet

Digital Compliance Platform byggs som en modulär monolit.

Plattformen består av:

- gemensamma plattformsmotorer
- återanvändbara Asset-definitioner
- domänmoduler
- arbetsflödesinstanser
- organisationsspecifik data
- gemensamma säkerhets- och revisionsfunktioner

Grundmodellen är:

```text
Plattform
    ↓
Motorer
    ↓
Assets
    ↓
Moduler
    ↓
Arbetsflöden
    ↓
Instanser
    ↓
Bevisunderlag
    ↓
Historik
```

---

## Plattformsmotorer

Plattformens motorer tillhandahåller generella förmågor.

Identifierade motorer:

- Workflow Engine
- Rule Engine
- Form Engine
- Report Engine
- Dashboard Engine
- AI Engine
- Document Engine
- Notification Engine
- Security Engine
- Integration Engine

Motorerna ska inte innehålla modulspecifik verksamhetslogik.

De ska kunna återanvändas av flera moduler.

---

## Assets

En Asset är en återanvändbar och versionshanterad definition.

Exempel:

- arbetsflödesblock
- formulärdefinition
- regeldefinition
- rapportdefinition
- dashboarddefinition
- AI-prompt
- integrationsdefinition
- notifieringsmall
- dokumentmall

Assets ska:

- lagras i centrala bibliotek
- versionshanteras
- testas
- granskas
- publiceras
- refereras i stället för att kopieras

Publicerade Asset-versioner är oföränderliga.

---

## Moduler

En modul beskriver ett verksamhetsområde.

Exempel:

- SjälvEL
- SjälvBrand
- SjälvDrift
- SjälvEnergi

En modul ska i första hand bestå av:

- arbetsflöden
- regler
- formulär
- rapporter
- dashboards
- juridiska kopplingar
- kompetenskrav
- AI-instruktioner
- notifieringar
- konfiguration

Ny generell plattformskod ska endast skapas när befintliga motorer inte kan lösa behovet på ett säkert och återanvändbart sätt.

---

## Definitioner och instanser

Projektet ska tydligt skilja mellan definition och faktisk användning.

Exempel:

```text
Arbetsflödesdefinition
    ↓
Publicerad arbetsflödesversion
    ↓
Arbetsflödesinstans
    ↓
Instansdata
    ↓
Historik
```

Samma princip gäller för:

- formulär
- regler
- rapporter
- dashboards
- AI-promptar
- notifieringar
- integrationer

Definitioner får inte blandas ihop med instansdata.

---

## Organisationsisolering

Plattformen stödjer flera organisationer i samma installation.

All organisationsägd data ska hanteras inom en tydlig organisationskontext.

Ingen organisation får kunna läsa eller påverka en annan organisations information.

Kontroll ska ske på serversidan.

Organisationsisolering ska gälla för:

- databasfrågor
- filer
- dokument
- rapporter
- dashboards
- API
- AI-kontext
- integrationer
- loggar
- notifieringar
- exporter

Organisationsisolering är ett kritiskt säkerhetskrav och ska testas automatiskt.

---

## Arbetsmetod

Utveckling ska ske i små, avgränsade steg.

Varje uppgift ska:

- ha ett tydligt syfte
- vara avgränsad
- ha definierade acceptanskriterier
- påverka så få områden som möjligt
- kunna testas separat
- kunna granskas separat

Stora uppgifter ska delas upp innan implementation.

---

## Standardflöde för utveckling

Varje utvecklingsuppgift ska följa denna process:

1. Förstå uppgiften.
2. Kontrollera repository, branch och git-status.
3. Läs obligatorisk dokumentation.
4. Läs relevanta domän- och teknikdokument.
5. Identifiera berörda motorer.
6. Identifiera berörda Assets.
7. Identifiera berörda moduler.
8. Kontrollera projektbeslut.
9. Implementera minsta nödvändiga förändring.
10. Kör relevanta tester.
11. Uppdatera dokumentation vid behov.
12. Granska diffen.
13. Commit.
14. Skapa Draft Pull Request.
15. Dokumentera syfte, ändringar, tester och risker.
16. Invänta review innan merge.

---

## Scope Control

Utvecklaren eller AI-assistenten ska hålla sig till den aktuella uppgiften.

Följande får inte göras utan uttryckligt stöd i uppgiften:

- refaktorera orelaterad kod
- ändra arkitektur
- ändra design
- byta ramverk
- lägga till nya större beroenden
- ändra databasmodell
- ändra API-kontrakt
- skapa nya funktioner
- skapa nya dokument
- ändra säkerhetsmodell

Förbättringsförslag utanför scope ska dokumenteras som förslag till nästa steg.

---

## När arbetet ska stoppas

Arbetet ska stoppas när:

- fel repository används
- fel branch används
- lokala ändringar riskerar att skrivas över
- dokumentationen motsäger sig själv
- arkitekturen behöver ändras
- juridisk osäkerhet påverkar implementationen
- säkerhetsrisk upptäcks
- uppgiften påverkar flera funktionella områden utan plan
- större refaktorering krävs
- databasmigrering krävs utan godkänd design
- behörighetsmodellen är oklar
- organisationsisolering inte kan verifieras

Arbetet ska inte fortsätta genom gissningar.

---

## Kodorganisation

Kod ska organiseras efter ansvar.

Projektet ska undvika:

- stora filer
- stora klasser
- stora komponenter
- otydliga hjälpfunktioner
- cirkulära beroenden
- direktkoppling mellan modulers interna delar
- verksamhetslogik i användargränssnittet
- dold logik i rapporter
- dold logik i databasen

Generell funktionalitet hör hemma i plattformen.

Domänspecifik konfiguration hör hemma i modulen.

---

## Verksamhetslogik

Verksamhetslogik ska i första hand beskrivas genom:

- arbetsflöden
- arbetsflödesblock
- regler
- formulär
- rapportdefinitioner
- notifieringsregler
- juridiska kopplingar
- kompetenskrav

Programkod ska beskriva hur plattformen fungerar.

Konfiguration ska beskriva hur verksamheten fungerar.

---

## Databas

Databasen ska följa DATABASE.md.

Grundprinciper:

- relationsdata normaliseras
- kärnrelationer lagras relationellt
- JSON används främst för versionerade definitioner och konfiguration
- främmande nycklar används
- transaktioner används där det behövs
- index skapas utifrån verifierade behov
- organisations-ID ska finnas eller kunna härledas för organisationsägd data
- publicerade versioner skrivs aldrig över
- historik och revisionsspår bevaras

Ingen tabell eller migration får skapas utan godkänd datamodell.

---

## API

API ska följa API.md.

API ska:

- vara konsekvent
- vara versionshanterat
- kontrollera behörighet på serversidan
- respektera organisationsisolering
- validera all input
- returnera konsekventa fel
- logga relevanta operationer
- stödja idempotens där det behövs
- dokumenteras

API ska beskriva verksamheten, inte databasen.

---

## Säkerhet

Kod ska följa SECURITY_ENGINE.md.

Grundprinciper:

- minsta möjliga behörighet
- neka som standard
- kontroll på serversidan
- säker sessionshantering
- säker lagring av hemligheter
- inga hemligheter i repository
- kryptering under överföring
- skydd mot CSRF
- skydd mot XSS
- prepared statements
- säker filuppladdning
- spårbar administration

Säkerhetskritiska funktioner ska testas särskilt.

---

## AI-funktioner

AI ska följa AI_ENGINE.md.

AI är beslutsstöd.

AI får inte:

- fatta säkerhetskritiska beslut
- publicera regler
- ändra arbetsflöden
- ändra rapportdefinitioner
- kringgå behörigheter
- exponera data mellan organisationer
- presentera osäker information som fakta
- ändra användarens data utan uttryckligt beslut

AI-funktioner ska:

- använda verifierade källor
- redovisa osäkerhet
- arbeta inom användarens behörighetskontext
- logga modell, promptversion och relevanta inställningar
- vara möjliga att stänga av per organisation eller arbetsflöde

---

## Dokument och bevisunderlag

Document Engine ska hantera dokument och övrigt verifierbart bevisunderlag.

Exempel:

- bilder
- videor
- mätvärden
- formulärsvar
- signeringar
- rapporter
- tidsstämplar
- kontrollresultat
- loggar

Bevisunderlag ska:

- kopplas till relevant objekt eller sammanhang
- versionshanteras där det är relevant
- ha metadata
- kunna verifieras
- vara sökbart
- följa behörighetsmodellen
- ingå i revisionsspåret

---

## Loggning

Systemet ska skilja mellan:

### Applikationslogg

För teknisk felsökning och drift.

### Säkerhetslogg

För säkerhetshändelser, åtkomst och incidentutredning.

### Revisionslogg

För att visa vem som ändrade vad och när.

### Verksamhetshistorik

För arbetsflöden, beslut, kontroller, signeringar och dokumentation.

Känslig information ska inte loggas i onödan.

---

## Felhantering

Fel ska hanteras kontrollerat.

Systemet ska:

- visa begripliga felmeddelanden
- logga tekniska detaljer internt
- undvika att exponera känslig information
- stödja spårbar felsökning
- hantera återförsök där det är lämpligt
- aldrig markera säkerhetskritiska steg som godkända efter tekniska fel

---

## Tester

Relevanta tester ska köras efter varje ändring.

Teststrategin ska omfatta:

- enhetstester
- integrationstester
- API-tester
- behörighetstester
- organisationsisoleringstester
- arbetsflödestester
- regeltester
- databastester
- säkerhetstester
- end-to-end-tester

När frontend påverkas ska minst följande köras:

```bash
npm run lint
npm run build
```

När backend finns ska även relevanta syntax-, enhets-, integrations- och databastester köras.

Om ett test inte kan köras ska orsaken dokumenteras i Pull Requesten.

---

## Dokumentation

Dokumentation ska uppdateras i samma Pull Request när ändringen påverkar:

- arkitektur
- databas
- API
- säkerhet
- arbetsflöden
- regler
- formulär
- rapporter
- Assets
- moduler
- konfiguration
- mappstruktur
- utvecklingsprocess

Nya dokument får endast skapas när behovet är uttryckligen beslutat.

Befintliga dokument ska uppdateras i första hand.

---

## Git och brancher

Grundregler:

- inga direkta commits till main
- en branch per uppgift
- en Pull Request per uppgift
- utgå från senaste main
- lokala ändringar ska vara förstådda
- commits ska vara tydliga och fokuserade
- Draft Pull Request används som standard
- merge sker först efter review

Branch-namn bör vara tydliga.

Exempel:

```text
feature/workflow-builder
fix/tenant-access-check
docs/update-security-engine
refactor/report-renderer
```

---

## Commit-meddelanden

Commit-meddelanden ska beskriva vad ändringen gör.

Exempel:

```text
Add tenant validation to workflow access
Fix report version reference
Update SjälvEL workflow documentation
```

Undvik:

```text
fix
update
changes
stuff
```

---

## Pull Request-standard

Varje Pull Request ska innehålla:

### Syfte

Vad löser denna ändring?

### Ändringar

Vilka filer, funktioner eller definitioner har ändrats?

### Ej inkluderat

Vad ligger utanför uppgiften?

### Tester

Vilka tester har körts?

### Säkerhet

Påverkas behörigheter, organisationsisolering eller känslig information?

### Risker

Finns kända risker eller begränsningar?

### Dokumentation

Vilka dokument har uppdaterats?

### Nästa steg

Vilka förbättringar bör hanteras senare?

---

## Definition of Done

En uppgift är klar när:

- kraven är uppfyllda
- scope har följts
- koden följer CODING_STANDARDS.md
- arkitekturen följer SYSTEM_ARCHITECTURE.md
- säkerhetskraven är uppfyllda
- organisationsisolering är verifierad där det är relevant
- relevanta tester är gröna
- dokumentationen är uppdaterad
- diffen är granskad
- commit är skapad
- Draft Pull Request är skapad
- kända risker är dokumenterade
- inga kända fel har introducerats

---

## Kodgranskning

Kodgranskning ska kontrollera:

- korrekt funktion
- säkerhet
- organisationsisolering
- läsbarhet
- testbarhet
- arkitektur
- versionshantering
- dokumentation
- felhantering
- loggning
- prestanda där det är relevant

Review ska inte enbart kontrollera att koden fungerar.

---

## Prestanda

Optimera inte i förtid.

Arbetsordning:

1. Skriv tydlig och korrekt kod.
2. Mät verkliga problem.
3. Identifiera flaskhals.
4. Optimera.
5. Verifiera förbättringen.

Prestanda får aldrig prioriteras framför säkerhet, korrekthet eller spårbarhet.

---

## Tillgänglighet

Användargränssnitt ska, där det är relevant, stödja:

- semantisk HTML
- tydliga etiketter
- tangentbordsnavigering
- tillräcklig kontrast
- alt-texter
- begriplig felhantering
- responsiv design
- tydlig fokusmarkering

Tillgänglighet ska byggas in från början.

---

## Beroenden

Nya beroenden får endast införas när de är motiverade.

Före införande ska följande bedömas:

- löser beroendet ett verkligt behov?
- finns enklare alternativ?
- är projektet aktivt underhållet?
- är licensen acceptabel?
- påverkar det säkerheten?
- påverkar det prestandan?
- ökar det komplexiteten?
- skapar det leverantörslåsning?

Beslut om större beroenden ska dokumenteras i PROJECT_DECISIONS.md.

---

## Release och driftsättning

Release och driftsättning ska vara reproducerbar.

En release ska minst innehålla:

- versionsnummer
- ändringsbeskrivning
- migreringsinformation
- testresultat
- kända risker
- rollback-plan
- uppdaterad dokumentation

Produktionsändringar ska inte genomföras manuellt utan spårbar process när automatisering finns tillgänglig.

---

## Juridik

Juridiska frågor ska hanteras enligt LEGAL_OVERVIEW.md och OPEN_LEGAL_QUESTIONS.md.

Utveckling ska stoppas när:

- juridisk osäkerhet påverkar säkerhet
- funktionen kan förändra ansvarsfördelning
- funktionen kan uppfattas som juridisk rådgivning
- funktionen påverkar elsäkerhetsansvar
- funktionen kräver obehandlade personuppgifter
- AI-regelverk kan vara tillämpligt

Juridiska beslut ska dokumenteras innan implementation.

---

## Utveckling av SjälvEL

SjälvEL är plattformens första referensmodul.

Utveckling av SjälvEL ska:

- verifiera plattformens motorer
- använda återanvändbara Assets
- undvika modulspecifik hårdkodning
- dokumentera juridiska frågor
- skapa spårbart bevisunderlag
- prioritera professionella användare i första versionen
- användas för att förbättra plattformens generella förmågor

SjälvEL ska inte bli en separat teknisk speciallösning.

---

## Förbjudna genvägar

Följande är inte tillåtet:

- säkerhetslogik endast i frontend
- organisationsfiltrering endast i frontend
- direkt SQL med användardata
- hemligheter i kod eller repository
- ändring av publicerade versioner
- kopiering av Assets mellan arbetsflöden
- kundspecifik hårdkodning utan beslut
- AI-anrop utan behörighetskontroll
- AI-genererade beslut utan mänskligt ansvar
- otestade databasmigreringar
- dolda beroenden
- okontrollerad körning av användarskapad kod
- implementation på juridiska antaganden

---

## Grundprincip

Utvecklingen ska göra det lättare att bygga rätt, inte bara snabbare att bygga.

Varje tekniskt beslut ska kunna motiveras utifrån:

- säkerhet
- juridik
- tydlighet
- spårbarhet
- återanvändning
- underhållbarhet
- projektets vision

> **Göra rätt. Inga olyckor. Håll ryggen fri.**

---

## Relaterade dokument

- DOCUMENTATION_INDEX.md
- PROJECT_PRINCIPLES.md
- PROJECT_DECISIONS.md
- SYSTEM_ARCHITECTURE.md
- DOCUMENT_STANDARD.md
- CODING_STANDARDS.md
- AI_DEVELOPMENT_RULES.md
- AI_DEVELOPMENT_WORKFLOW.md
- SECURITY_ENGINE.md
- DATABASE.md
- API.md

---

## Påverkar

- samtliga utvecklingsuppgifter
- samtliga Pull Requests
- samtliga tekniska implementationer
- samtliga AI-assistenter
- all projektkod

---

## Status

Styrande dokument

---

## Senast uppdaterad

2026-07-15
