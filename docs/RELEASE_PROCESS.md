# RELEASE_PROCESS.md

# Release Process

## Syfte

Detta dokument beskriver hur nya funktioner går från idé till produktionssatt kod.

Målet är att:

- säkerställa hög kvalitet
- minska risken för fel
- skapa en tydlig och reproducerbar process
- säkerställa att alla förändringar är spårbara

Ingen kod får publiceras utan att följa denna process.

---

# Översikt

All utveckling följer denna process.

```
Idé
 ↓
Issue
 ↓
Planering
 ↓
Branch
 ↓
Implementation
 ↓
Tester
 ↓
Dokumentation
 ↓
Draft Pull Request
 ↓
Review
 ↓
Godkännande
 ↓
Merge
 ↓
Release
 ↓
Deployment
 ↓
Verifiering
```

---

# Steg 1 – Idé

En ny funktion börjar alltid som en idé.

Idén ska beskriva:

- vilket problem som ska lösas
- varför funktionen behövs
- vem som påverkas

Ingen kod skrivs i detta steg.

---

# Steg 2 – Issue

Varje funktion ska ha en egen GitHub Issue.

Issue ska innehålla:

- beskrivning
- mål
- avgränsning
- eventuella beroenden

En Issue ska endast beskriva **en funktion eller ett problem**.

---

# Steg 3 – Planering

Innan implementation ska följande kontrolleras:

- Finns arkitekturstöd?
- Behöver databasdesign uppdateras?
- Behöver dokumentation uppdateras?
- Finns säkerhetsrisker?
- Påverkas API?
- Påverkas andra sprintar?

Om svaret är ja ska dokumentationen uppdateras först.

---

# Steg 4 – Branch

Skapa alltid en ny branch.

Exempel:

```
feature/object-images

feature/booking-calendar

feature/contracts

bugfix/login

docs/security
```

Arbeta aldrig direkt i `main`.

---

# Steg 5 – Implementation

Kod ska skrivas enligt:

- ARCHITECTURE.md
- CODING_STANDARDS.md
- SECURITY.md
- CODEX_RULES.md

Målet är minsta möjliga förändring.

---

# Steg 6 – Tester

Följande ska köras innan PR skapas.

Frontend:

```bash
npm run lint
npm run build
```

Backend (när den finns):

- PHP syntaxkontroll
- Databasmigrationstest
- Funktionstest

Alla fel ska åtgärdas innan nästa steg.

---

# Steg 7 – Dokumentation

Kontrollera om ändringen påverkar:

- ROADMAP
- PROJECT_DECISIONS
- DATABASE_DESIGN
- SECURITY
- API_DESIGN
- DEVELOPMENT_GUIDE

Om ja ska dokumentationen uppdateras i samma Pull Request.

---

# Steg 8 – Commit

Commit ska vara små och tydliga.

Bra exempel:

```
Add booking calendar

Fix hero image

Update security documentation

Implement customer management
```

Dåliga exempel:

```
Fix stuff

Update

Changes
```

---

# Steg 9 – Push

Push endast den aktuella branchen.

Kontrollera att:

- inga orelaterade filer följer med
- inga hemligheter finns i commiten

---

# Steg 10 – Draft Pull Request

Alla nya funktioner börjar som Draft PR.

PR ska innehålla:

## Syfte

Vad löser denna PR?

## Ändringar

Vilka filer och funktioner har ändrats?

## Tester

Hur verifierades ändringen?

## Dokumentation

Vilka dokument har uppdaterats?

## Risker

Finns några risker?

## Nästa steg

Vad rekommenderas härnäst?

---

# Steg 11 – Review

Review ska kontrollera:

- funktionalitet
- kodkvalitet
- säkerhet
- dokumentation
- tester
- arkitektur

Kod som inte uppfyller projektets standard ska inte mergas.

---

# Steg 12 – Godkännande

Pull Request får mergas när:

- tester passerar
- dokumentation är uppdaterad
- review är godkänd
- Product Owner godkänner förändringen

---

# Steg 13 – Merge

Merge sker till:

```
main
```

Historiken ska vara tydlig.

Undvik onödiga merge-konflikter.

---

# Steg 14 – Release

Produktionsklara versioner märks med Git-taggar.

Semantic Versioning används.

```
v1.0.0

v1.0.1

v1.1.0

v2.0.0
```

---

# Steg 15 – Deployment

Deployment följer:

`docs/DEPLOYMENT.md`

Kontrollera:

- backup
- databas
- loggar
- HTTPS
- konfiguration

---

# Steg 16 – Verifiering

Efter deployment ska följande verifieras:

- Startsidan fungerar
- Inloggning fungerar
- Objekt visas
- Bokningar fungerar
- Databasen fungerar
- Inga kritiska loggfel finns

---

# Hotfix-process

Vid kritiska fel:

```
main
 ↓
hotfix/*
 ↓
Test
 ↓
Review
 ↓
Merge
 ↓
Deployment
```

Hotfixar ska dokumenteras i `PROJECT_DECISIONS.md`.

---

# Rollfördelning

## Product Owner

Ansvarar för:

- prioritering
- krav
- godkännande

## Solution Architect

Ansvarar för:

- arkitektur
- säkerhet
- databas
- tekniska beslut
- dokumentation

## Developer (Codex)

Ansvarar för:

- implementation
- tester
- Pull Requests

---

# Definition of Ready

En uppgift är redo att utvecklas när:

- Issue finns
- krav är tydliga
- beroenden är identifierade
- arkitektur stödjer lösningen
- omfattningen är rimlig

---

# Definition of Done

En uppgift är klar när:

- funktionaliteten fungerar
- tester passerar
- dokumentation är uppdaterad
- koden följer projektets standard
- Pull Request är godkänd
- merge är genomförd
- deployment är verifierad

---

# Kontinuerlig förbättring

Efter varje större release ska processen utvärderas.

Frågor att besvara:

- Vad fungerade bra?
- Vad tog onödigt lång tid?
- Vilka fel uppstod?
- Hur kan processen förbättras?

Förbättringar ska dokumenteras i:

`PROJECT_DECISIONS.md`

---

# Grundprincip

En långsammare men stabil release är alltid bättre än en snabb release som skapar problem.

Kvalitet går före hastighet.
