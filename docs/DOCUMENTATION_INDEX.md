# DOCUMENTATION_INDEX.md

# Dokumentationsindex

## Syfte

Detta dokument är projektets huvudingång till all dokumentation.

Alla utvecklare, AI-assistenter och framtida medarbetare ska börja här för att förstå:

- vad projektet är
- vilka dokument som finns
- när dokumenten ska läsas
- vilket dokument som styr vilket område

---

# Projekt

**Namn:** Uthyrning  
**Repo:** `RDMackan999/Uthyrning`  
**Syfte:** Plattform för uthyrning av verktyg, maskiner och utrustning.

Version 1 fokuserar på en uthyrare och egna uthyrningsobjekt.

Version 2 förbereds för marknadsplats, externa uthyrare och integrationer.

---

# Obligatorisk läsning före utveckling

Innan kod ändras ska följande läsas:

1. `README.md`
2. `docs/ARCHITECTURE.md`
3. `docs/CODEX_RULES.md`
4. `docs/CODEX_WORKFLOW.md`
5. `docs/PROJECT_DECISIONS.md`
6. Relevant dokument för aktuell uppgift

---

# Dokumentöversikt

## Projekt och styrning

| Dokument | Syfte |
|---|---|
| `README.md` | Kort projektöversikt och startinformation |
| `docs/DOCUMENTATION_INDEX.md` | Huvudingång till all dokumentation |
| `docs/PROJECT_DECISIONS.md` | Beslutslogg för viktiga tekniska och affärsmässiga beslut |
| `docs/ROADMAP.md` | Övergripande utvecklingsplan |
| `docs/MVP_SCOPE.md` | Vad som ingår och inte ingår i Version 1 |

---

## Arkitektur och utveckling

| Dokument | Syfte |
|---|---|
| `docs/ARCHITECTURE.md` | Systemarkitektur och teknisk målbild |
| `docs/DEVELOPMENT_GUIDE.md` | Lokal utvecklingsmiljö och installation |
| `docs/CODING_STANDARDS.md` | Kodstandard för frontend och framtida backend |
| `docs/CODEX_RULES.md` | Bindande regler för AI-assistenter |
| `docs/CODEX_WORKFLOW.md` | Arbetsflöde för Codex/AI från uppgift till PR |

---

## Databas

| Dokument | Syfte |
|---|---|
| `docs/DATABASE_PRINCIPLES.md` | Grundprinciper för databasdesign |
| `docs/DATABASE_NAMING_STANDARD.md` | Namngivningsstandard för tabeller, kolumner och migrationer |
| `docs/DATABASE_DESIGN.md` | Databasens faktiska design när den är godkänd |

---

## Säkerhet och API

| Dokument | Syfte |
|---|---|
| `docs/SECURITY.md` | Säkerhetsprinciper och säkerhetspolicy |
| `docs/API_DESIGN.md` | Målbild och standard för framtida API |

---

## Drift och release

| Dokument | Syfte |
|---|---|
| `docs/DEPLOYMENT.md` | Publicering och drift |
| `docs/RELEASE_PROCESS.md` | Process från idé till release |

---

# När ska vilket dokument uppdateras?

## Vid arkitekturbeslut

Uppdatera:

- `PROJECT_DECISIONS.md`
- `ARCHITECTURE.md`

---

## Vid ändrad databasmodell

Uppdatera:

- `DATABASE_DESIGN.md`
- `DATABASE_PRINCIPLES.md` vid behov
- `PROJECT_DECISIONS.md` vid större beslut

---

## Vid nya API-principer

Uppdatera:

- `API_DESIGN.md`
- `SECURITY.md` vid behov
- `PROJECT_DECISIONS.md`

---

## Vid ändrat arbetsflöde

Uppdatera:

- `CODEX_WORKFLOW.md`
- `CODEX_RULES.md`
- `DEVELOPMENT_GUIDE.md`

---

## Vid ändrad release/deploy-process

Uppdatera:

- `RELEASE_PROCESS.md`
- `DEPLOYMENT.md`
- `PROJECT_DECISIONS.md`

---

## Vid ny funktion

Kontrollera om följande påverkas:

- `MVP_SCOPE.md`
- `ROADMAP.md`
- `ARCHITECTURE.md`
- `SECURITY.md`
- `DATABASE_DESIGN.md`
- `API_DESIGN.md`

---

# Dokumenthierarki

Om dokument säger olika saker gäller följande ordning:

1. `PROJECT_DECISIONS.md`
2. `ARCHITECTURE.md`
3. `SECURITY.md`
4. `DATABASE_DESIGN.md`
5. `API_DESIGN.md`
6. `CODING_STANDARDS.md`
7. `CODEX_RULES.md`
8. `CODEX_WORKFLOW.md`
9. `ROADMAP.md`
10. `MVP_SCOPE.md`

Vid konflikt ska arbetet stoppas och beslut dokumenteras.

---

# Regler för AI-assistenter

AI-assistenter ska:

- börja med detta dokument
- läsa obligatorisk dokumentation
- kontrollera om uppgiften påverkar flera dokument
- inte gissa vid dokumentkonflikt
- föreslå dokumentuppdatering när något saknas

---

# Dokumentstatus

| Dokument | Status |
|---|---|
| `README.md` | Aktiv |
| `ARCHITECTURE.md` | Aktiv |
| `CODEX_RULES.md` | Aktiv |
| `CODEX_WORKFLOW.md` | Aktiv |
| `PROJECT_DECISIONS.md` | Aktiv |
| `ROADMAP.md` | Aktiv |
| `MVP_SCOPE.md` | Aktiv |
| `SECURITY.md` | Aktiv |
| `DATABASE_PRINCIPLES.md` | Aktiv |
| `DATABASE_NAMING_STANDARD.md` | Aktiv |
| `DATABASE_DESIGN.md` | Under arbete |
| `DEVELOPMENT_GUIDE.md` | Aktiv |
| `CODING_STANDARDS.md` | Aktiv |
| `API_DESIGN.md` | Planerad/Aktiv |
| `DEPLOYMENT.md` | Aktiv |
| `RELEASE_PROCESS.md` | Aktiv |

---

# Grundprincip

Dokumentationen är en del av produkten.

Kod utan uppdaterad dokumentation är inte färdig.

Om en ny utvecklare eller AI inte förstår projektet efter att ha läst dokumentationen är dokumentationen inte tillräckligt bra.
