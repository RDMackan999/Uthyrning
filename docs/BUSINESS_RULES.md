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
- en primär kategori
- ett pris
- ett statusläge
- bilder
- dokument
- servicehistorik
- bokningshistorik

---

# Kategorier

Version 1 ska använda en enkel kategorimodell i användargränssnittet.

Standardkategorier:

- Verktyg
- Maskiner
- Släp
- Trädgård
- Bygg
- Övrigt

Affärsregler:

- Varje publicerat objekt ska ha en primär kategori.
- Kategorier ska kunna användas i publik objektlista och som filter.
- Kategorier ska kunna läggas till i admin när kategoriadministration byggs.
- Version 1 visar kategorier som en enkel nivå.
- Underkategorier får förberedas i datamodellen men ska inte krävas i Version 1.
- En kategori kan vara aktiv, inaktiv eller arkiverad.
- Endast aktiva kategorier får väljas för nya objekt.
- Inaktiva kategorier ska döljas i publik filtrering men behållas på befintliga objekt.
- Arkiverade kategorier ska bevaras för historik och ska inte hårdraderas när objekt använder dem.
- Ett objekt ska bara kräva en primär kategori i Version 1.
- Flera kategorier per objekt kan aktiveras senare om filtrering, SEO eller marknadsplats kräver det.

Framtida marknadsplats:

- Globala kategorier ska kunna användas av alla uthyrare.
- Organisationsspecifika kategorier ska kunna stödja nischade objekt utan att förstöra den gemensamma kategoristrukturen.
- SEO-fält ska kunna förberedas men SEO-implementation byggs i en senare sprint.

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

# Autentisering

Version 1 använder e-post och lösenord för konton som ska logga in i skyddade ytor.

Affärsregler:

- Ett konto måste ha verifierad e-post innan skyddade ytor får användas.
- Kunder ska fortfarande kunna skicka bokningsförfrågan utan konto om det flödet väljs för Version 1.
- Administratörer ska logga in med e-post och lösenord.
- Inaktiva, spärrade eller arkiverade konton får inte logga in.
- Utloggning ska avsluta den aktuella sessionen.
- En användare får vara inloggad på flera enheter.
- Aktiva sessioner ska kunna återkallas av systemet när sessionshantering byggs.
- Remember me ingår inte i Version 1.

Sessionsregler:

- Normal session gäller högst 8 timmar.
- Inaktivitet i 30 minuter ska kräva ny inloggning.
- Session-id ska bytas efter lyckad inloggning.
- Session-id ska bytas efter större behörighetsändring.

Misslyckade inloggningar:

- 5 misslyckade försök för samma konto eller e-post inom 15 minuter ger temporär spärr i 15 minuter.
- 20 misslyckade försök från samma IP inom 15 minuter ger temporär IP-spärr i 30 minuter.
- Felmeddelanden ska vara generiska och får inte avslöja om e-postadressen finns.

Lösenord:

- Lösenord ska vara minst 12 tecken.
- Lösenfraser ska vara tillåtna.
- Vanliga eller kända läckta lösenord ska stoppas när kontroll finns tillgänglig.
- Lösenordsbyte för inloggad användare kräver aktuellt lösenord.
- Glömt lösenord ska använda engångstoken med kort giltighetstid.
- Reset-token ska lagras hashad och aldrig visas igen efter skapande.

Audit:

Följande händelser ska loggas:

- lyckad inloggning
- misslyckad inloggning
- utloggning
- temporär spärr
- lösenordsbyte
- lösenordsreset begärd
- lösenordsreset slutförd
- e-postverifiering
- återkallad session
- försök att logga in på spärrat eller inaktivt konto

BankID:

BankID är en framtida funktion. Version 1 får inte kräva BankID för att fungera.

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
