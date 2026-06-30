# Project Decisions

Detta dokument samlar projektets initiala beslut. Nya större beslut ska dokumenteras här eller i ett separat beslutsdokument som länkas härifrån.

## Initiala beslut

- GitHub-repo: `RDMackan999/Uthyrning`.
- GitHub är source of truth.
- Marcus är product owner.
- ChatGPT används som arkitekt/projektstöd.
- Codex används som utvecklare.
- Frontend från Codex Sites/Vinext behålls tills vidare.
- PHP/MySQL/MariaDB är framtida backend-målbild.
- Ingen BankID-, Swish- eller Fortnox-integration byggs ännu.
- Version 1 fokuserar på egna uthyrningsobjekt.
- Marknadsplats med externa uthyrare förbereds men byggs senare.
- All kommunikation och utveckling ska styras via dokumenterade beslut och PR-flöde.

## Konsekvenser

- Fungerande frontend ska inte flyttas eller byggas om utan uttryckligt beslut.
- Backend, databas och API ska införas stegvis via separata issues och PR:er.
- Externa integrationer ska föregås av dokumenterad kravbild, säkerhetsbedömning och tekniskt beslut.
- Dokumentation ska hållas uppdaterad när projektets struktur eller arbetsprocess ändras.
