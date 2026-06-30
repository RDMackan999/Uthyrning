# ROADMAP.md

# Projektets Roadmap

## Syfte

Detta dokument beskriver den övergripande utvecklingsplanen för projektet **Uthyrning**.

Roadmapen visar:

- utvecklingsordning
- prioriteringar
- beroenden
- mål för varje sprint
- när funktioner anses färdiga

Roadmapen är ett levande dokument.

Sprintar får delas upp, flyttas eller ändras om projektets behov förändras.

---

# Utvecklingsprincip

Projektet byggs lager för lager.

Varje sprint ska ge ett stabilt och testbart resultat innan nästa sprint påbörjas.

Prioriteringsordning:

1. Dokumentation
2. Arkitektur
3. Databas
4. Backend
5. Administration
6. Kundfunktioner
7. Integrationer
8. Marknadsplats

---

# Sprint 0 – Projektgrund

## Mål

Skapa en stabil utvecklingsmiljö och en gemensam teknisk grund.

### Leverabler

- GitHub-repository
- Dokumentation
- VS Code-konfiguration
- Node.js
- Laragon
- Lokal databas
- Projektstruktur
- AI-regler
- Arbetsflöde
- Databasprinciper

### Definition of Done

- Alla utvecklare kan köra projektet lokalt.
- Dokumentationen är komplett.
- Git-flödet fungerar.

---

# Sprint 1 – Teknisk plattform

## Mål

Skapa den tekniska grunden för backend.

### Leverabler

- PHP-bootstrap
- Config-system
- PDO-anslutning
- Felhantering
- Loggning
- Sessionshantering
- Grundläggande routing

### Definition of Done

Backend kan startas lokalt och ansluta till databasen.

---

# Sprint 2 – Databas

## Mål

Implementera den godkända datamodellen.

### Leverabler

- Migrationer
- Grundtabeller
- Foreign Keys
- Index
- Soft Delete
- Audit Trail-struktur

### Definition of Done

Databasen kan skapas från grunden med migrationer.

---

# Sprint 3 – Användare och behörigheter

## Mål

Skapa säker autentisering.

### Leverabler

- Inloggning
- Sessionshantering
- Roller
- Behörigheter
- Administratör

### Definition of Done

Behörigheter fungerar korrekt.

---

# Sprint 4 – Objekt

## Mål

Administrera uthyrningsobjekt.

### Leverabler

- Objekt
- Kategorier
- Bilder
- Dokument
- Priser
- Status
- Deposition

### Definition of Done

Objekt kan administreras fullt ut.

---

# Sprint 5 – Bokningar

## Mål

Skapa ett fungerande bokningsflöde.

### Leverabler

- Bokningsförfrågan
- Kalender
- Tillgänglighet
- Statusflöde
- Bokningshistorik

### Definition of Done

Kund kan genomföra en bokningsförfrågan.

---

# Sprint 6 – Avtal

## Mål

Hantera uthyrningsavtal.

### Leverabler

- Avtalsmallar
- Versioner
- Koppling till bokning
- Historik

### Definition of Done

Avtal kan skapas och kopplas till bokningar.

---

# Sprint 7 – Betalningar

## Mål

Förbereda ekonomiflöden.

### Leverabler

- Betalstatus
- Deposition
- Fakturastatus
- Dokumenterad integrationsstrategi

### Definition of Done

Systemet kan hantera manuella betalningar.

---

# Sprint 8 – Administration

## Mål

Färdigställa administrationsdelen.

### Leverabler

- Dashboard
- Statistik
- Loggar
- Inställningar
- Kategorier
- Användare

### Definition of Done

Administratören kan hantera hela systemet.

---

# Sprint 9 – Service

## Mål

Införa service och underhåll.

### Leverabler

- Servicehistorik
- Besiktningar
- Kontrollpunkter
- Påminnelser

### Definition of Done

Service kan registreras och följas upp.

---

# Sprint 10 – MVP färdig

## Mål

Systemet ska kunna användas i verklig drift.

### MVP ska kunna

- Visa objekt
- Ta emot bokningar
- Hantera kunder
- Hantera avtal
- Hantera betalstatus
- Hantera service
- Hantera dokument

### Definition of Done

En uthyrare kan driva sin verksamhet i systemet.

---

# Version 2

När MVP är stabil påbörjas Version 2.

Planerade funktioner:

- Flera uthyrare
- Marknadsplats
- Provision
- BankID
- Swish
- Fortnox
- API
- Mobilapp
- PWA
- QR-koder
- GPS
- AI
- BI
- Flera språk
- Flera valutor

---

# Prioriteringsprincip

Vid konflikt mellan funktioner gäller följande prioritering:

1. Säkerhet
2. Stabilitet
3. Datakvalitet
4. Underhållbarhet
5. Användarupplevelse
6. Nya funktioner

---

# Ändringshantering

Roadmapen är ett levande dokument.

Större förändringar ska:

- dokumenteras i PROJECT_DECISIONS.md
- brytas ner till Issues
- implementeras via Pull Requests

Ingen sprint påbörjas innan föregående sprint är stabil.
