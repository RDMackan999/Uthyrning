# Contributing

Allt arbete i Uthyrning ska vara spårbart, fokuserat och dokumenterat.

## Grundregler

- Allt arbete ska ske via Git.
- GitHub är projektets källa till sanning.
- En uppgift per branch.
- En uppgift per PR.
- Håll ändringar små och fokuserade.
- Gör inga direkta commits till `main`.
- PR ska granskas innan merge.
- Dokumentation ska uppdateras när beslut, struktur eller arbetsflöde ändras.

## Innan ny uppgift startas

- Kontrollera att rätt repo används: `RDMackan999/Uthyrning`.
- Utgå från aktuell `main`.
- Läs `README.md`, `docs/ARCHITECTURE.md`, `docs/CODEX_RULES.md` och `docs/PROJECT_DECISIONS.md`.
- Lokala ändringar i VS Code ska committas eller stashas innan ny Codex-uppgift startas.
- Om arbetsytan innehåller oklara lokala ändringar ska arbetet stoppas tills de är hanterade.

## Pull requests

En PR ska beskriva:

- Vilken uppgift eller issue den hör till.
- Vad som ändrats.
- Vad som inte ändrats.
- Varför ändringen behövs.
- Hur ändringen har testats.
- Eventuella säkerhets- eller databasaspekter.

## Kodprinciper

- Använd PHP 8.x när backend införs.
- Använd PDO och prepared statements för databasåtkomst.
- Undvik stora ramverk om arkitekturen inte först uppdateras.
- Lägg aldrig hemligheter, API-nycklar, lösenord eller riktig `config.php` i repo.
- Kommentera kod när syftet inte är självklart.

## Dokumentation

Dokumentation ska uppdateras när ändringen påverkar:

- Arkitektur
- Databas
- Säkerhet
- Roller och behörigheter
- Externa integrationer
- API-struktur
- Frontend- eller hostingstruktur
- Arbetsflöde och projektbeslut
