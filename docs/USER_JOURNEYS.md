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
Skicka bokningsförfrågan
    ↓
Bekräftelse
```

### Resultat

- Kunden vet att förfrågan är mottagen.
- Administratören får en notis.

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
Godkänn
    ↓
Skapa avtal
```

### Resultat

- Bokningen blir aktiv.
- Objektet reserveras.

---

# Journey 3 – Utlämning

```
Dashboard
    ↓
Aktiv bokning
    ↓
Kontrollera legitimation
    ↓
Dokumentera skick
    ↓
Lämna ut objekt
```

### Resultat

- Utlämning registrerad.
- Starttid sparas.

---

# Journey 4 – Återlämning

```
Dashboard
    ↓
Aktiv bokning
    ↓
Inspektera objekt
    ↓
Dokumentera skick
    ↓
Avsluta bokning
```

### Resultat

- Objekt blir ledigt.
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
