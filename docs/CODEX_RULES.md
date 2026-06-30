# Codex Rules

Detta dokument beskriver bindande regler för hur Codex ska arbeta i projektet.

## Startregler

- Codex ska alltid läsa `README.md`, `docs/ARCHITECTURE.md`, `docs/CODEX_RULES.md` och `docs/PROJECT_DECISIONS.md` innan arbete påbörjas.
- Codex ska alltid bekräfta att rätt repo används: `RDMackan999/Uthyrning`.
- Codex ska bara arbeta med en avgränsad uppgift åt gången.
- Codex ska kontrollera aktuell branch och git status innan ändringar görs.

## Begränsningar

- Codex får inte ta stora arkitekturbeslut utan uttrycklig instruktion.
- Codex får inte bygga BankID, Swish, Fortnox, databas, backend eller API utan separat specificerad issue.
- Codex ska inte ändra design eller layout om uppgiften inte uttryckligen gäller design.
- Codex ska inte skapa spekulativa funktioner eller framtida integrationer.

## Validering

- Codex ska alltid köra relevanta tester.
- Om frontend påverkas ska minst följande köras:

```bash
npm run lint
npm run build
```

- Om validering inte kan köras ska Codex tydligt dokumentera varför.

## Branch, commit och PR

- Codex ska skapa branch, commit och PR.
- PR ska beskriva vad som ändrats.
- PR ska beskriva vad som inte ändrats.
- PR ska beskriva hur ändringen har testats.
- Draft PR används som standard tills ändringen är granskad och redo.

## Stoppa och fråga

Codex ska stoppa och fråga om något av följande upptäcks:

- Lokala ändringar som inte hör till uppgiften.
- Fel repo.
- Oklar struktur.
- Motstridiga instruktioner.
- Risk för att ändra design eller app-logik utanför uppgiften.
