# Codex Workflow

Detta dokument beskriver hur Codex ska arbeta i projektet.

## Grundregler

- Arbeta alltid i repo `RDMackan999/Uthyrning`.
- Utgå från aktuell `main` innan ny branch skapas.
- En issue eller tydligt avgränsad uppgift per PR.
- Gör små, fokuserade ändringar.
- Ändra inte landningssidans design utan uttrycklig uppgift.
- Bygg inga backendfunktioner utan specifikation.
- Bygg inga BankID-, Swish- eller Fortnox-integrationer utan separat specifikation.

## Innan kodändringar

Codex ska läsa:

- `README.md`
- `docs/ARCHITECTURE.md`
- relevant dokument för uppgiften, till exempel `docs/SECURITY.md` eller `docs/DATABASE_PRINCIPLES.md`

Codex ska också kontrollera:

- aktuell branch
- git status
- om det finns ändringar som inte hör till uppgiften
- vilka kommandon som finns i `package.json`

## Under arbetet

- Håll ändringen så liten som möjligt.
- Bevara fungerande Sites-struktur tills migrering är dokumenterad.
- Uppdatera dokumentation när struktur, kommandon, säkerhet, databas eller arbetsflöde ändras.
- Lägg inte hemligheter, API-nycklar eller lokal config i repo.
- Skapa inte spekulativa integrationer.

## Validering före PR

Kör alltid när Node-beroenden finns tillgängliga:

```bash
npm run lint
npm run build
```

Om validering inte kan köras ska PR-beskrivningen säga varför.

## PR-krav

Varje PR ska innehålla:

- Kort sammanfattning.
- Vilka filer eller områden som ändrats.
- Vad som inte ingår.
- Hur ändringen validerats.
- Kända återstående steg.

Draft PR är standard tills ändringen är granskad och redo.
