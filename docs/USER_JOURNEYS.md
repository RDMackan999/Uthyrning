# USER_JOURNEYS.md

# User Journeys

## Syfte

Detta dokument beskriver hur olika användare interagerar med systemet i verkliga scenarier.

Målet är att:

- säkerställa ett enkelt användarflöde
- upptäcka saknade funktioner
- identifiera onödiga steg
- verifiera att MVP uppfyller verkliga behov

Detta dokument används innan implementation och uppdateras när nya funktioner införs.

---

# Användartyper

Systemet har följande användartyper:

- Besökare
- Kund
- Administratör
- (Version 2) Uthyrare
- (Version 2) Support

---

# Journey 1 – Hyra ett verktyg

## Mål

Kunden vill hyra en skruvdragare över helgen.

## Flöde

```
Startsida
    ↓
Sök objekt
    ↓
Filtrera
    ↓
Objektdetalj
    ↓
Kontrollera kalender
    ↓
Välj startdatum och slutdatum
    ↓
Fyll i kontaktuppgifter
    ↓
Granska förfrågan
    ↓
Skicka bokningsförfrågan
    ↓
Bekräftelse
```

### Resultat

- Kunden vet att förfrågan är mottagen.
- Kunden förstår att bokningen väntar på manuell granskning.
- Administratören får en notis.
- Kunden får senare en e-postbekräftelse baserad på bokningens kundsnapshot.
- Administratör/uthyrare får senare notifiering via organisationens notifieringsadress eller relevant administratörsroll.
- Kunden ser endast om datum är lediga eller ej tillgängliga, aldrig andra kunders uppgifter eller intern bokningsstatus.
- Systemet kontrollerar tillgänglighet igen när förfrågan skickas.

---

# Journey 2 – Administratören godkänner bokningen

```
Dashboard
    ↓
Bokningar
    ↓
Öppna bokning
    ↓
Kontrollera tillgänglighet
    ↓
Godkänn eller avslå
    ↓
Skapa avtal
```

### Resultat

- Godkänd bokning reserverar objektet.
- Avslagen bokning frigör datum.
- Statushistorik sparas.
- Kunden får senare e-postbesked vid godkänd, avslagen eller avbruten bokning.
- Ett misslyckat e-postutskick ska inte ångra statusändringen, men ska kunna synas för administratören.
- Administratören kan senare se varför datum är blockerade, till exempel förfrågan, godkänd bokning, pågående bokning, manuell blockering eller service.

---

# Journey 3 – Utlämning

```
Dashboard
    ↓
Godkänd bokning
    ↓
Kontrollera legitimation
    ↓
Dokumentera skick
    ↓
Bekräfta villkor/kvittens
    ↓
Registrera eventuell deposition
    ↓
Lämna ut objekt
```

### Resultat

- Utlämning registrerad.
- Faktisk utlämningstid sparas.
- Ansvarig administratör sparas.
- Skick vid utlämning sparas som snapshot.
- Bokningen går från `approved` till `active`.
- Historik och audit sparas.

---

# Journey 4 – Återlämning

```
Dashboard
    ↓
Pågående bokning
    ↓
Inspektera objekt
    ↓
Dokumentera skick
    ↓
Avsluta bokning
```

### Resultat

- Faktisk återlämningstid sparas.
- Ansvarig administratör sparas.
- Skick vid återlämning sparas som snapshot.
- Eventuell avvikelse eller skada markeras på enkel V1-nivå.
- Depositionens manuella utfall kan dokumenteras när deposition finns.
- Bokningen går från `active` till `completed`.
- Objektet blir ledigt enligt befintliga availability-regler om ingen annan blockering finns.
- Historik sparas.

---

# Journey 5 – Skapa nytt objekt

```
Dashboard
    ↓
Objekt
    ↓
Nytt objekt
    ↓
Grundinformation
    ↓
Pris
    ↓
Kategori
    ↓
Bilder
    ↓
Dokument
    ↓
Publicera
```

### Resultat

Objektet blir sökbart.

---

# Journey 6 – Registrera service

```
Dashboard
    ↓
Objekt
    ↓
Service
    ↓
Ny service
    ↓
Kommentar
    ↓
Spara
```

### Resultat

Servicehistorik uppdateras.

---

# Journey 7 – Registrera besiktning

```
Objekt
    ↓
Besiktning
    ↓
Datum
    ↓
Kommentar
    ↓
Nästa besiktning
```

---

# Journey 8 – Hantera kund

```
Dashboard
    ↓
Kunder
    ↓
Öppna kund
    ↓
Visa historik
    ↓
Redigera kontaktuppgifter
```

Mål:

- Administratören kan förstå kundrelationen utan att ändra historiska bokningssnapshots.
- Administratören kan se om kunden är privatkund eller företagskund.
- Administratören kan se bokningshistorik inom sin organization.
- Administratören kan ändra aktuell kontaktinformation och kundstatus.

Viktiga gränser:

- Interna kundanteckningar visas inte publikt.
- Booking customer snapshot ändras inte när kundregistret uppdateras.
- Dubbletter ska varnas för men inte slås ihop automatiskt i Version 1.
- Kundportal och kundlogin byggs senare.

---

# Journey 9 – Ladda upp dokument

```
Objekt
    ↓
Dokument
    ↓
Ladda upp
    ↓
Välj dokumenttyp
    ↓
Spara
```

---

# Journey 10 – Söka objekt

```
Startsida
    ↓
Sök
    ↓
Filter
    ↓
Resultat
    ↓
Objektdetalj
```

---

# Journey 11 – Administratören loggar in

```
Login
    ↓
Dashboard
```

Framtid:

```
BankID
```

---

# Journey 12 – Ändra pris

```
Objekt
    ↓
Redigera
    ↓
Pris
    ↓
Spara
```

Historik ska sparas.

---

# Journey 13 – Arkivera objekt

```
Objekt
    ↓
Arkivera
    ↓
Bekräfta
```

Objektet ska inte raderas.

Soft Delete används.

---

# Journey 14 – Hantera dokument

Administratören ska kunna:

- ladda upp
- ersätta
- ladda ner
- arkivera

---

# Journey 15 – Visa historik

```
Objekt
    ↓
Historik
```

Visar:

- bokningar
- service
- besiktningar
- dokument
- prisändringar

---

# Framtida Journeys (Version 2)

- Registrera ny uthyrare
- BankID-inloggning
- Swish-betalning
- Fortnox-fakturering
- Provision
- QR-kod vid utlämning
- GPS-spårning
- Förläng bokning
- Avboka bokning
- Delbetalning
- Flera filialer
- Flera lagerplatser
- Mobilapp
- Pushnotiser

---

# UX-principer

Alla journeys ska följa:

- Max tre klick till vanliga funktioner.
- Tydlig återkoppling efter varje steg.
- Ingen användare ska behöva gissa nästa steg.
- Felmeddelanden ska vara begripliga.
- Alla formulär ska kunna användas på mobil.

---

# Definition of Done

En journey anses färdig när:

- hela flödet fungerar
- inga manuella steg saknas
- användaren får tydlig återkoppling
- nödvändig data sparas
- historik loggas
- säkerhetskrav uppfylls

---

# Grundprincip

Systemet ska byggas utifrån användarnas arbetsflöden – inte utifrån databasen eller tekniken.

Om en användarresa känns krånglig ska lösningen förenklas innan ny funktionalitet läggs till.
