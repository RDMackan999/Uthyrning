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

Knapp:

"Boka"

---

# Bokningsförfrågan

Användaren anger:

- datum
- namn
- telefon
- e-post
- kommentar

Knapp:

"Skicka förfrågan"

---

# Bekräftelse

Visar:

"Bokningsförfrågan mottagen."

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

Administratören kan:

- godkänna
- neka
- avboka

---

# Bokningsflöde

```
Ny bokning

↓

Granska

↓

Godkänn

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
