# Contributing

Allt arbete i Uthyrning ska vara spårbart, fokuserat och dokumenterat.

## Arbetsflöde

1. Allt arbete ska ske via GitHub issues.
2. Skapa en PR per issue.
3. Håll ändringar små och fokuserade.
4. Gör inga stora arkitekturbeslut utan dokumentation.
5. Uppdatera relevant dokumentation när kod, struktur, databas eller säkerhetsprinciper ändras.
6. Skriv tydliga commit-meddelanden.

## Pull requests

En PR ska beskriva:

- Vilket issue den hör till.
- Vad som ändrats.
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

Codex och andra automatiserade kodändrare ska alltid läsa `docs/ARCHITECTURE.md` innan kodändringar görs.

Dokumentation ska uppdateras när ändringen påverkar:

- Arkitektur
- Databas
- Säkerhet
- Roller och behörigheter
- Externa integrationer
- API-struktur
- Frontend- eller hostingstruktur
