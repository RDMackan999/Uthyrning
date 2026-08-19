# UI_FLOW.md

# User Interface Flow

## Syfte

Detta dokument beskriver hur användare rör sig genom systemet.

Det beskriver:

- alla sidor
- alla användartyper
- menyer
- navigering
- formulär
- arbetsflöden

Dokumentet används innan implementation.

Ingen sida ska byggas utan att först finnas beskriven här.

---

# Användartyper

Systemet har följande användartyper.

## Besökare

Ej inloggad.

Kan:

- söka objekt
- visa objekt
- läsa information
- skicka bokningsförfrågan

---

## Kund

Inloggad.

Kan:

- skapa bokningar
- se sina bokningar
- se sina avtal
- uppdatera profil

---

## Uthyrare

Version 1:

Samma person som administratören.

Version 2:

Separat användarroll.

---

## Administratör

Kan hantera hela systemet.

---

# Huvudmeny

Publik meny.

```
Hem

Objekt

Så fungerar det

För uthyrare

Kontakt

Logga in

Skapa konto
```

Efter inloggning.

```
Dashboard

Objekt

Bokningar

Kunder

Service

Dokument

Inställningar

Logga ut
```

---

# Publikt flöde

```
Startsida

↓

Objekt

↓

Objektdetalj

↓

Kalender

↓

Bokningsförfrågan

↓

Bekräftelse
```

---

# Startsida

Visar:

- Hero
- Sök
- Kategorier
- Populära objekt
- FAQ
- Kontakt

Knappar:

- Hitta objekt
- Lägg upp objekt (Version 2)

---

# Objektlista

Visar:

- bild
- namn
- pris
- ort
- kategori
- status

Version 1 ska bara visa publikt bokningsbara objekt. Objekt som är dolda, arkiverade, soft delete:ade, trasiga eller under service ska inte visas som bokningsbara i den publika listan.

Filter:

- kategori
- ort
- pris
- tillgänglighet

Kategori-filter:

- Visar endast aktiva kategorier.
- Ska stödja standardkategorierna Verktyg, Maskiner, Släp, Trädgård, Bygg och Övrigt.
- Underkategorier visas inte i Version 1.

---

# Objektdetalj

Visar:

- bilder
- beskrivning
- pris
- deposition
- kalender
- dokument
- villkor

Objektdetaljen ska senare använda objektets publika slug i URL. Interna id:n ska inte visas som primär publik identifierare.

Knapp:

"Boka"

---

# Bokningsförfrågan

Version 1 bör hålla kalender och kontaktformulär på samma objektsida eller samma bokningssida. Det minskar antal steg och passar MVP-flödet där kunden skickar en bokningsförfrågan utan konto.

Rekommenderat flöde:

```
Objektdetalj
    ↓
Välj startdatum i kalender
    ↓
Välj slutdatum i kalender
    ↓
Fyll i kontaktuppgifter
    ↓
Granska förfrågan
    ↓
Skicka
```

Användaren anger:

- startdatum
- slutdatum
- namn
- telefon
- e-post
- företag, valfritt
- kommentar

Version 1 ska tillåta bokningsförfrågan utan användarkonto.

Formuläret ska vara kopplat till ett publikt, bokningsbart objekt.

Formuläret ska inte visa interna objekt-id:n, interna noteringar eller adminfält.

Kalendern ska visa:

- lediga datum
- ej tillgängliga datum
- valt startdatum
- valt slutdatum
- vald period

Publik kalender ska inte visa om ett datum är blockerat av förfrågan, godkänd bokning, aktiv bokning, service eller manuell blockering. Kunden ska endast se om datumet kan väljas.

Kalendern ska vara tillgänglig:

- datum ska kunna nås med tangentbord
- inaktiva datum ska ha tydligt disabled-läge
- färg får inte vara enda betydelsebärare
- datumknappar ska ha tydliga labels
- felmeddelanden ska vara kopplade till fältet de gäller

Innan förfrågan skickas ska användaren kunna granska:

- valt objekt
- startdatum
- slutdatum
- antal kalenderdagar
- pris-snapshot när prisvisning finns
- eventuell deposition

Knapp:

"Skicka förfrågan"

---

# Bekräftelse

Visar:

"Bokningsförfrågan mottagen."

Bekräftelsen ska vara tydlig med att förfrågan inte är slutgiltigt godkänd förrän administratören har granskat den.

---

# Login

Visar:

- e-post
- lösenord

Knapp:

"Logga in"

Framtid:

BankID.

---

# Dashboard

Visar:

- aktiva bokningar
- kommande bokningar
- objekt
- intäkter
- servicepåminnelser

---

# Objekt

Administratören kan:

- skapa
- redigera
- arkivera
- lägga till bilder
- lägga till dokument

Vid skapa och redigera objekt väljer administratören en primär kategori.

Kategorier hanteras som en enkel lista i Version 1.

Framtida underkategorier och flera kategorier per objekt ska inte visas innan separat sprint.

Version 1-formulär för objekt ska hållas enkelt.

Rekommenderade fält:

- namn
- kort beskrivning
- primär kategori
- status
- uthyrningsbar ja/nej
- dagspris
- moms
- deposition
- plats
- huvudbild
- intern anteckning
- inventarienummer
- serienummer
- tillverkare
- modell
- skick

Fält som kan vänta till senare vyer eller avancerat läge:

- inköpspris
- försäkringsvärde
- vikt och dimensioner
- flera prisperioder
- SEO-fält
- QR-kod
- streckkod
- RFID
- GPS
- IoT
- fordonsunika uppgifter

---

# Kategorier

Administratören ska senare kunna:

- skapa kategori
- redigera namn, slug, beskrivning och sortering
- markera kategori som aktiv eller inaktiv
- arkivera kategori om den inte ska användas för nya objekt

Version 1 ska hålla kategoriadministrationen enkel.

Underkategorier, kategoribilder, SEO-publicering och avancerade filter väntar till senare sprint.

---

# Objektflöde

```
Dashboard

↓

Objekt

↓

Nytt objekt

↓

Spara

↓

Publicera
```

---

# Bokningar

Visar:

- kalender
- status
- kund
- objekt
- startdatum
- slutdatum
- pris-snapshot
- kundens kommentar
- intern administratörsnotering

Administratören kan:

- godkänna
- neka
- avboka
- markera som aktiv
- markera som slutförd

Endast administratör får ändra bokningsstatus i Version 1.

Statusändringar som godkännande, nekande, avbokning, start och slutförande ska kräva en tydlig administrativ handling.

Kundens kommentar och intern administratörsnotering ska visas separat. Intern notering får aldrig visas publikt.

---

# Bokningsflöde

```
Bokningsförfrågan

↓

Granska

↓

Godkänn / Neka

↓

Skapa avtal

↓

Betalning

↓

Utlämning

↓

Återlämning

↓

Slutförd
```

Kalenderpåverkan:

- Förfrågan reserverar datum preliminärt.
- Godkänd bokning reserverar datum.
- Aktiv bokning reserverar datum.
- Nekad, avbokad och slutförd bokning frigör datum för framtida bokningar.

Administrativ kalender ska senare kunna visa skillnad mellan förfrågan, godkänd bokning, aktiv bokning, manuell blockering och service. Den ska aldrig bygga egna tillgänglighetsregler som avviker från backendens gemensamma tillgänglighetskontroll.

---

# Kunder

Visar:

- kontaktuppgifter
- bokningar
- avtal
- historik

---

# Service

Visar:

- senaste service
- kommande service
- besiktningar

Administratören kan:

- registrera service
- registrera besiktning
- skriva kommentarer

---

# Dokument

Objekt kan ha:

- bilder
- manualer
- besiktningsprotokoll
- serviceprotokoll

---

# Inställningar

Version 1.

Administratören kan ändra:

- företagsuppgifter
- moms
- deposition
- standardvillkor

---

# Version 2

Version 2 lägger till:

- flera uthyrare
- marknadsplats
- BankID
- Swish
- Fortnox
- API
- AI
- QR-koder
- GPS
- PWA

---

# Navigeringsprincip

Användaren ska aldrig behöva mer än tre klick för att nå en vanlig funktion.

Navigeringen ska vara:

- enkel
- konsekvent
- mobilanpassad
- förutsägbar

---

# Designprincip

Varje sida ska ha ett tydligt syfte.

Varje knapp ska ha ett tydligt resultat.

Varje formulär ska guida användaren.

Systemet ska kännas:

- snabbt
- tryggt
- enkelt
- professionellt

---

# Grundprincip

Om en användare tvekar över vad nästa steg är har gränssnittet misslyckats.

Gränssnittet ska guida användaren genom hela uthyrningsprocessen utan att instruktioner behövs.
