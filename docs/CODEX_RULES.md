# CODEX_RULES.md

# AI Development Rules

Detta dokument innehåller bindande regler för AI-assistenter (Codex, ChatGPT, Copilot och framtida AI-verktyg) som arbetar i projektet.

Syftet är att säkerställa hög kodkvalitet, en stabil arkitektur och ett långsiktigt hållbart system.

---

# Projektets källa till sanning

GitHub-repot är projektets **Source of Truth**.

All utveckling ska utgå från den senaste versionen av GitHub.

Om lokal kod skiljer sig från GitHub ska AI stoppa arbetet och uppmärksamma användaren innan några större ändringar görs.

---

# Dokument som alltid ska läsas

Innan arbete påbörjas ska följande dokument läsas i denna ordning:

1. README.md
2. docs/ARCHITECTURE.md
3. docs/CODEX_RULES.md
4. docs/PROJECT_DECISIONS.md
5. docs/DATABASE_DESIGN.md (när den finns)
6. docs/ROADMAP.md

---

# Kontroll före arbete

Innan någon kod skrivs ska följande verifieras:

- Rätt GitHub-repo används.
- Aktuell branch är korrekt.
- Git-status är ren eller förstådd.
- Lokala ändringar har identifierats.
- Uppgiften är tydligt avgränsad.
- Ingen annan öppen PR påverkar samma område.

Om någon kontroll misslyckas ska arbetet stoppas.

---

# Arbetsprinciper

AI ska:

- endast arbeta med en avgränsad uppgift åt gången
- göra så små ändringar som möjligt
- återanvända befintlig kod när det är lämpligt
- undvika duplicerad kod (DRY)
- prioritera enkel och tydlig kod
- följa projektets kodstandard
- skriva kod som är lätt att underhålla
- aldrig spekulera om krav

---

# Arkitektur

AI får inte utan uttrycklig instruktion:

- ändra projektets arkitektur
- byta ramverk
- flytta större delar av projektet
- ändra databasdesign
- ändra API-design
- ändra säkerhetsmodell
- införa nya större beroenden
- ändra projektstruktur

Förslag får lämnas, men implementation kräver godkännande.

---

# Funktioner som kräver separat uppgift

Följande får aldrig implementeras utan en separat issue eller specifikation:

- BankID
- Swish
- Fortnox
- API
- Databas
- Migrationer
- Seeder
- Backend
- Behörighetssystem
- Betalningar
- Autentisering
- AI-funktioner
- IoT
- GPS
- QR-koder

---

# Frontend

Om uppgiften inte uttryckligen gäller design får AI inte:

- ändra layout
- ändra färger
- ändra typografi
- ändra komponentstruktur
- ändra UX-flöden
- byta frontend-teknik

---

# Databas

AI får inte skapa:

- SQL
- Tabeller
- Migrationer
- Views
- Stored Procedures
- Seeder

förrän databasdesignen är godkänd.

---
# Dokumentationsregler

Dokumentationen är nu etablerad.

Codex ska inte skapa nya `.md`-filer på eget initiativ.

Nya dokument får endast skapas om:

- användaren uttryckligen ber om det,
- dokumentet ersätter ett befintligt dokument enligt beslut,
- eller en förändring kräver ny dokumentation enligt en dokumenterad arkitekturbeslut i `PROJECT_DECISIONS.md`.

Om Codex anser att ny dokumentation behövs ska den:

1. föreslå dokumentets namn,
2. motivera varför det behövs,
3. invänta godkännande innan filen skapas.

Vid normala kodändringar ska befintliga dokument uppdateras istället för att nya skapas.

# Tester

Efter varje ändring ska relevanta tester köras.

Frontend:

```bash
npm run lint
npm run build
