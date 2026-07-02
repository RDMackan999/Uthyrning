# BUSINESS_RULES.md

# Business Rules

## Syfte

Detta dokument beskriver projektets affärsregler.

Affärsregler beskriver **hur verksamheten fungerar**, inte hur den implementeras tekniskt.

Databas, API och kod ska alltid följa dessa regler.

Om en affärsregel ändras ska detta dokument uppdateras innan implementation.

---

# Grundprincip

Systemet ska hjälpa uthyraren att:

- hyra ut objekt
- hålla ordning på bokningar
- minska administration
- dokumentera hela uthyrningsprocessen
- ge kunder en enkel bokningsupplevelse

---

# Objekt

Ett objekt representerar något som kan hyras ut.

Exempel:

- Skruvdragare
- Minigrävare
- Släpvagn
- Markvibrator
- Byggställning
- Motorsåg

Varje objekt har:

- ett namn
- en kategori
- ett pris
- ett statusläge
- bilder
- dokument
- servicehistorik
- bokningshistorik

---

# Objektstatus

Version 1 använder följande statusar.

- Aktiv
- Uthyrd
- Bokad
- Service
- Trasig
- Arkiverad

Endast aktiva objekt får bokas.

---

# Bokningar

En bokning reserverar ett objekt under ett bestämt tidsintervall.

En bokning får aldrig överlappa en annan godkänd bokning.

---

# Bokningsstatus

Version 1 använder:

- Förfrågan
- Godkänd
- Nekad
- Avbokad
- Aktiv
- Slutförd

Historik ska sparas.

Status ska aldrig skrivas över utan loggning.

---

# Bokningsprocess

```
Förfrågan

↓

Granskning

↓

Godkänd

↓

Avtal

↓

Utlämning

↓

Aktiv uthyrning

↓

Återlämning

↓

Slutförd
```

---

# Kalender

Kalendern visar:

- Lediga datum
- Bokade datum
- Spärrade datum

Administratören kan blockera datum.

---

# Prissättning

Version 1 använder fasta priser.

Exempel:

- Pris per dag
- Pris per vecka
- Pris per månad

Automatisk prissättning införs inte i Version 1.

---

# Deposition

Objekt kan ha deposition.

Deposition registreras separat från hyran.

Systemet ska kunna visa:

- Depositionsbelopp
- Betald
- Återbetald

---

# Avtal

Varje godkänd bokning kan kopplas till ett avtal.

Version 1 använder manuella avtalsmallar.

Digital signering införs senare.

---

# Betalningar

Version 1 hanterar:

- Betald
- Delbetald
- Obetald

Ingen automatisk betalningsintegration.

---

# Kunder

En kund kan:

- ha flera bokningar
- ha flera avtal
- ha historik

Historik får inte tas bort.

---

# Dokument

Objekt kan ha dokument.

Exempel:

- Manual
- Besiktningsprotokoll
- Serviceprotokoll
- Bilder

Dokument ska versionshanteras när det är relevant.

---

# Bilder

Varje objekt kan ha:

- huvudbild
- flera detaljbilder

Huvudbild används i sökresultat.

---

# Service

Objekt kan registreras för service.

Servicehistoriken ska visa:

- datum
- utfört av
- kommentar

Service får aldrig radera tidigare historik.

---

# Besiktning

Objekt kan ha återkommande besiktningar.

Systemet ska kunna lagra:

- senaste besiktning
- nästa besiktning
- kommentarer

---

# Skador

Vid återlämning ska administratören kunna registrera:

- skador
- kommentarer
- bilder

Skador ska sparas permanent.

---

# Utlämning

Vid utlämning ska följande kunna registreras:

- datum
- utlämnad av
- mottagare
- kommentarer

---

# Återlämning

Vid återlämning ska följande registreras:

- datum
- skick
- kommentar
- eventuella skador

Objektet blir därefter tillgängligt igen om ingen service krävs.

---

# Avbokning

Version 1 tillåter administratören att avboka bokningar.

Senare kan kunder själva avboka.

Historiken ska alltid sparas.

---

# Arkivering

Objekt ska aldrig raderas permanent.

Soft Delete används.

Historiken ska bevaras.

---

# Historik

Systemet ska kunna visa historik för:

- bokningar
- service
- besiktningar
- dokument
- prisändringar
- statusändringar

Historiken får inte kunna ändras i efterhand.

---

# Behörigheter

Version 1 har följande roller:

- Administratör
- Kund

Version 2 inför:

- Uthyrare
- Support

---

# Version 2

Version 2 kan införa:

- Marknadsplats
- Flera uthyrare
- Provision
- BankID
- Swish
- Fortnox
- QR-koder
- GPS
- AI
- Mobilapp
- Pushnotiser

Dessa regler ska dokumenteras innan implementation.

---

# Affärsprinciper

Systemet ska alltid:

- skydda historik
- undvika dubbelbokningar
- minska administration
- ge tydlig återkoppling
- vara enkelt att använda

---

# Beslutsprincip

Vid konflikt mellan teknik och affärsregler gäller affärsreglerna.

Tekniken ska anpassas till verksamheten – aldrig tvärtom.

---

# Grundprincip

En användare ska kunna förstå hur uthyrningsprocessen fungerar utan utbildning.

Om processen känns krånglig ska affärsreglerna förenklas innan ny funktionalitet utvecklas.
