# Codex Workflow

## Syfte

Detta dokument beskriver hur AI-assistenter (Codex, ChatGPT, GitHub Copilot och framtida AI-verktyg) ska arbeta i projektet.

Målet är att skapa ett stabilt, spårbart och långsiktigt hållbart utvecklingsflöde.

---

# Standardarbetsflöde

Varje uppgift ska följa denna process.

1. Läs dokumentationen.
2. Verifiera repository, branch och git-status.
3. Förstå uppgiften.
4. Implementera minsta möjliga förändring.
5. Kör validering.
6. Uppdatera dokumentation vid behov.
7. Commit.
8. Skapa Draft Pull Request.
9. Beskriv ändringen.
10. Vänta på review innan merge.

Ingen implementation får hoppa över något steg.

---

# Grundregler

- Arbeta alltid i repository `RDMackan999/Uthyrning`.
- Utgå alltid från senaste `main`.
- En issue eller tydligt avgränsad uppgift per branch.
- En Pull Request per uppgift.
- Gör små, fokuserade förändringar.
- GitHub är projektets **Source of Truth**.
- Ändra inte arkitektur utan uttrycklig instruktion.
- Ändra inte design om inte uppgiften gäller design.

---

# Innan kodändringar

Följande dokument ska alltid läsas:

- README.md
- docs/ARCHITECTURE.md
- docs/CODEX_RULES.md
- docs/PROJECT_DECISIONS.md

Läs dessutom relevanta dokument för uppgiften.

Exempel:

- docs/DATABASE_DESIGN.md
- docs/SECURITY.md
- docs/API_DESIGN.md
- docs/MVP_SCOPE.md

---

# Kontroll före arbete

Verifiera alltid:

- rätt repository
- rätt branch
- git status
- lokala ändringar
- aktuell uppgift
- package.json
- beroenden

Om något är fel:

STOPPA.

Fråga användaren.

---

# Under utveckling

AI ska:

- göra minsta möjliga förändring
- återanvända befintlig kod
- följa projektets kodstandard
- undvika duplicerad kod
- skriva lättläst kod
- skriva underhållbar kod
- inte skapa spekulativa funktioner

---

# Kodkvalitet

All ny kod ska vara:

- enkel
- tydlig
- konsekvent
- säker
- testbar
- lätt att underhålla

Optimera för långsiktig förvaltning framför kortsiktiga lösningar.

---

# Dokumentation

Om ändringen påverkar:

- arkitektur
- databas
- API
- säkerhet
- arbetsflöde
- mappstruktur
- konfiguration

ska relevant dokumentation uppdateras i samma Pull Request.

Kod och dokumentation ska alltid vara synkroniserade.

---

# Validering

När frontend påverkas ska alltid köras:

```bash
npm run lint
npm run build
```

När backend finns ska även följande köras:

- PHP syntaxkontroll
- Databasmigrationstest
- Enhetstester

Om tester inte kan köras ska orsaken dokumenteras.

---

# Git-regler

- En branch per uppgift.
- En PR per uppgift.
- Inga direkta commits till main.
- Draft PR används som standard.
- Commit-meddelanden ska vara tydliga.
- Lokala ändringar ska committas eller stashas innan ny AI-uppgift påbörjas.

---

# Pull Request

Varje Pull Request ska innehålla följande.

## Syfte

Vad löser denna PR?

## Ändringar

Vilka filer och funktioner har ändrats?

## Ej inkluderat

Vad ingår inte?

## Tester

Hur verifierades ändringen?

## Risker

Finns några kända risker?

## Nästa steg

Vad rekommenderas härnäst?

---

# När arbetet ska stoppas

AI ska stoppa och be om vägledning om:

- fel repository används
- fel branch används
- lokala ändringar riskerar att skrivas över
- instruktioner motsäger varandra
- arkitekturen måste ändras
- säkerhetsrisk upptäcks
- större refaktorering krävs
- dokumentationen är otillräcklig
- flera funktionella områden påverkas samtidigt

AI ska aldrig gissa.

---

# Om problem upptäcks

Om AI hittar:

- buggar
- säkerhetsrisker
- dålig arkitektur
- prestandaproblem
- teknisk skuld

ska dessa dokumenteras.

De ska inte automatiskt åtgärdas om de ligger utanför aktuell uppgift.

---

# Projektfilosofi

Projektet byggs långsiktigt.

Prioriteringsordning:

1. Stabilitet
2. Säkerhet
3. Läsbarhet
4. Underhållbarhet
5. Prestanda
6. Funktionalitet

Snabba lösningar får aldrig försämra arkitekturen.

---

# Definition of Done

En uppgift anses klar när:

- Kraven är uppfyllda.
- Koden följer projektets standard.
- Tester har körts.
- Dokumentationen är uppdaterad.
- Commit är skapad.
- Draft Pull Request är skapad.
- PR beskriver ändringen.
- Inga kända fel har introducerats.

Först därefter är uppgiften redo för review.

---

# Grundprincip

Om AI är osäker ska den aldrig gissa.

Den ska istället:

1. Stoppa arbetet.
2. Beskriva problemet.
3. Presentera möjliga lösningar.
4. Vänta på beslut från användaren.

Det är alltid bättre att fråga än att göra ett antagande som kan påverka projektets arkitektur eller kvalitet.
