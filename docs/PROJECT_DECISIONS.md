# PROJECT_DECISIONS.md

# Project Decisions

## Syfte

Detta dokument innehåller alla större beslut som påverkar projektets arkitektur, teknikval, arbetsflöde och affärslogik.

Målet är att varje viktigt beslut ska vara:

- dokumenterat
- motiverat
- daterat
- spårbart

Om ett beslut inte finns dokumenterat här ska det betraktas som preliminärt.

---

# Hur dokumentet används

När ett större beslut tas ska följande dokumenteras:

- Datum
- Beslut
- Motivering
- Konsekvenser
- Status

Status kan vara:

- Proposed
- Accepted
- Deprecated
- Replaced

Äldre beslut ska aldrig raderas.

De markeras istället som ersatta.

---

# Beslut 0001

## Datum

2026-06-30

## Status

Accepted

## Titel

GitHub är projektets Source of Truth.

## Beslut

All utveckling utgår från GitHub.

Ingen lokal version är överordnad GitHub.

## Motivering

Ger:

- spårbarhet
- historik
- backup
- Pull Requests
- enkel samverkan mellan AI och människor.

## Konsekvens

Alla ändringar ska gå via Git.

---

# Beslut 0002

## Datum

2026-06-30

## Status

Accepted

## Titel

Arbetsmodell

## Beslut

Projektet utvecklas enligt följande ansvar.

Product Owner

Marcus Möller

Ansvar:

- krav
- prioriteringar
- affärsbeslut

Solution Architect

ChatGPT

Ansvar:

- arkitektur
- databas
- säkerhet
- tekniska beslut
- kodgranskning
- dokumentation

Developer

Codex

Ansvar:

- implementation
- tester
- Pull Requests

## Motivering

Ger tydliga roller.

Minskar risken att AI börjar fatta egna arkitekturbeslut.

---

# Beslut 0003

## Datum

2026-06-30

## Status

Accepted

## Titel

Frontend

## Beslut

Nuvarande Codex Sites/Vinext-frontend behålls.

Ingen omskrivning görs innan det finns ett tydligt behov.

## Motivering

Minskar teknisk risk.

Ger snabbare utveckling.

---

# Beslut 0004

## Datum

2026-06-30

## Status

Accepted

## Titel

Backend

## Beslut

Backend byggs i:

- PHP 8.x
- PDO
- MariaDB/MySQL

Stora PHP-ramverk används inte.

## Motivering

Låg komplexitet.

Lång livslängd.

Enkel drift.

---

# Beslut 0005

## Datum

2026-06-30

## Status

Accepted

## Titel

Databas

## Beslut

Databasen designas färdigt innan första migrationen skrivs.

## Motivering

Minskar framtida ombyggnationer.

Ger stabil datamodell.

---

# Beslut 0006

## Datum

2026-06-30

## Status

Accepted

## Titel

Utvecklingsmodell

## Beslut

Projektet utvecklas genom:

Issue

↓

Branch

↓

Commit

↓

Draft PR

↓

Review

↓

Merge

## Motivering

Små förändringar.

Lättare felsökning.

Enklare kodgranskning.

---

# Beslut 0007

## Datum

2026-06-30

## Status

Accepted

## Titel

Version 1

## Beslut

Version 1 ska endast stödja:

- en uthyrare
- egna objekt
- manuell bokningshantering

Ingen marknadsplats.

## Motivering

MVP ska hållas liten.

---

# Beslut 0008

## Datum

2026-06-30

## Status

Accepted

## Titel

Version 2

## Beslut

Version 2 får innehålla:

- flera uthyrare
- marknadsplats
- BankID
- Swish
- Fortnox
- API
- AI
- QR-koder
- GPS
- BI

## Motivering

Version 1 ska först bevisa affärsmodellen.

---

# Beslut 0009

## Datum

2026-06-30

## Status

Accepted

## Titel

Kodstandard

## Beslut

Ingen AI får ändra arkitekturen utan uttryckligt beslut.

## Motivering

Förhindrar okontrollerad teknisk utveckling.

---

# Beslut 0010

## Datum

2026-06-30

## Status

Accepted

## Titel

Dokumentation

## Beslut

Alla större tekniska beslut ska dokumenteras här innan implementation.

## Motivering

Projektets historik ska kunna förstås flera år senare.

---

# Framtida beslut

Exempel på beslut som senare ska dokumenteras:

- API-versionering
- BankID-leverantör
- Swish-integration
- Fortnox-strategi
- Backup-strategi
- Deploy-strategi
- Hosting
- Cache
- Filhantering
- Behörighetsmodell
- Loggningsstrategi
- GDPR-strategi

---

# Grundprincip

Projektets viktigaste beslut ska alltid dokumenteras.

Kod kan ändras.

Arkitektur kan utvecklas.

Men historiken över varför ett beslut togs ska aldrig gå förlorad.
