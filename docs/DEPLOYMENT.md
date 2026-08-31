# DEPLOYMENT.md

# Deployment Guide

## Syfte

Detta dokument beskriver hur projektet **Uthyrning** ska publiceras och driftsättas.

Målet är att:

- publicering ska vara säker
- publicering ska vara reproducerbar
- produktionsmiljön ska vara stabil
- inga manuella ändringar ska göras direkt på servern

Deployment ska vara dokumenterad och kunna upprepas.

---

# Miljöer

Projektet använder följande miljöer.

## Development

Används för lokal utveckling.

Exempel:

- Windows
- Laragon
- PHP
- MariaDB
- Node.js

Databas:

```
uthyrning_dev
```

---

## Test

Används för intern testning.

Miljön ska så långt som möjligt motsvara produktion.

Exempel:

```
https://test.uthyrning.se
```

Ej publik.

---

## Production

Den publika miljön.

Exempel:

```
https://uthyrning.se
```

Alla förändringar ska testas innan de når produktion.

---

# Deploymentprincip

Deployment ska alltid ske från GitHub.

Ingen kod ska ändras direkt på servern.

Flödet ska vara:

Developer

↓

Git Commit

↓

Push

↓

Pull Request

↓

Review

↓

Merge

↓

Deployment

---

# Branch-strategi

main

- Stabil kod
- Produktionsklar

feature/*

- Nya funktioner

bugfix/*

- Buggfixar

docs/*

- Dokumentation

refactor/*

- Refaktorering

---

# Produktionsprincip

Produktion ska alltid bygga på:

- senaste godkända commit
- testad kod
- dokumenterad release

Ingen "snabbfix" direkt på servern.

---

# Konfiguration

Konfiguration ska aldrig lagras i Git.

Exempel:

```
config.php
```

eller

```
.env
```

ska vara lokala.

Endast:

```
config.example.php
```

versionshanteras.

## V1 production config gate

Backend stoppar production-start om kritiska inställningar saknas.

Minimikrav:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_BASE_URL=https://...`
- `SECURITY_FORCE_HTTPS=true`
- `AUTH_SESSION_COOKIE_SECURE=true`
- `AUTH_CSRF_COOKIE_SECURE=true`
- produktionsdatabas med separat användare och lösenord
- `MAIL_TRANSPORT=smtp`
- SMTP-host, port, TLS/SMTPS och avsändaradress
- skrivbara kataloger för `storage/logs`, `storage/sessions`, `storage/temp`, `storage/media/original` och `storage/media/variants`

Om applikationen körs bakom lastbalanserare eller reverse proxy ska endast betrodda proxy-IP:n anges i `SECURITY_TRUSTED_PROXIES`. Klienten får aldrig själv styra om en request behandlas som HTTPS.

---

# Hemligheter

Följande får aldrig finnas i Git:

- lösenord
- API-nycklar
- BankID-certifikat
- Swish-certifikat
- Fortnox-token
- databaslösenord
- privata certifikat

---

# Databas

Databasen ska aldrig uppdateras manuellt.

Schemaändringar ska ske genom migrationer.

Ordning:

1. Backup
2. Migration
3. Verifiering
4. Deployment

---

# Deploymentchecklista

Innan deployment ska följande kontrolleras.

## Kod

- Kod granskad
- Inga merge-konflikter
- Ingen debugkod
- Ingen död kod

---

## Tester

Frontend:

```bash
npm run lint
npm run build
```

Backend:

- PHP syntaxkontroll
- Databastester
- Funktionstest
- `composer validate --no-check-publish`

## Smoke test efter deployment

Verifiera manuellt:

- `/health` returnerar endast `{"status":"ok"}`
- publik objektlista öppnas
- objektdetalj öppnas
- bokningsförfrågan kan skickas i testat flöde
- adminlogin fungerar
- adminlistor för objekt, bokningar, kunder och notifieringar öppnas
- bildleverans fungerar via kontrollerade media-routes
- SMTP skickar eller fångar e-post enligt vald driftkonfiguration
- loggar saknar hemligheter, stack traces i production och kritiska fel

---

## Dokumentation

Kontrollera att:

- README är uppdaterad
- ROADMAP är uppdaterad vid behov
- PROJECT_DECISIONS är uppdaterad
- SECURITY är uppdaterad vid behov

---

# Backup

Innan produktionsdeployment ska backup tas på:

- databas
- uppladdade filer
- dokument

Backup ska verifieras.

Verifiering innebär minst att en återläsning testas i separat miljö och att både databas och `storage/media` finns med.

---

# Rollback

Om deployment misslyckas ska systemet kunna återställas.

Rollback ska omfatta:

- kod
- databas
- konfiguration
- uppladdade filer

Rollbackplan ska finnas innan deployment.

Databasrollback ska planeras innan migrationer körs. Om en migration inte säkert kan rullas tillbaka automatiskt ska en manuell återställningsplan och backup finnas innan releasefönstret öppnas.

---

# Loggning

Deployment ska logga:

- datum
- version
- commit
- ansvarig
- resultat

Historik ska sparas.

---

# Versionshantering

Varje produktionsrelease ska märkas.

Exempel:

```
v1.0.0

v1.0.1

v1.1.0
```

Semantic Versioning används.

```
MAJOR.MINOR.PATCH
```

Exempel:

```
1.0.0

1.1.0

1.1.3

2.0.0
```

---

# Releaseprocess

Deployment sker först efter:

- godkänd Pull Request
- godkända tester
- uppdaterad dokumentation
- godkännande från Product Owner

---

# Serverkrav (målbild)

Backend:

- PHP 8.x
- MariaDB / MySQL
- Apache eller Nginx

Frontend:

- Nuvarande React/Vinext

HTTPS är obligatoriskt.

---

# Övervakning

Produktionsmiljön ska kunna övervakas.

Exempel:

- fel
- CPU
- minne
- databas
- diskutrymme
- certifikat
- svarstid

---

# Incidenthantering

Vid driftstörning:

1. Identifiera problemet.
2. Avgör påverkan.
3. Återställ tjänsten.
4. Dokumentera orsaken.
5. Vidta förebyggande åtgärder.
6. Uppdatera dokumentationen.

---

# Framtida CI/CD

På sikt ska deployment automatiseras.

Målbild:

GitHub

↓

GitHub Actions

↓

Automatiska tester

↓

Build

↓

Deployment

↓

Verifiering

↓

Notifiering

Ingen CI/CD implementeras innan den manuella processen är stabil.

---

# Definition of Done

En deployment är klar när:

- systemet fungerar
- tester passerar
- databasen fungerar
- loggar visar inga kritiska fel
- användare kan använda systemet
- backup finns
- dokumentationen är uppdaterad

---

# Grundprincip

Deployment ska vara:

- säker
- reproducerbar
- dokumenterad
- automatiserbar

Ingen publicering ska vara beroende av manuella ändringar direkt på servern.
