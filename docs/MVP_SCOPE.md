# MVP_SCOPE.md

# Minimum Viable Product (MVP)

## Syfte

Detta dokument definierar omfattningen av Version 1 (MVP) för projektet **Uthyrning**.

MVP ska vara tillräckligt komplett för att användas i verklig drift av **en uthyrare**, samtidigt som den lägger grunden för framtida funktioner.

All utveckling ska utgå från detta dokument.

---

# Vision

Version 1 ska visa att hela uthyrningsprocessen fungerar:

- Kunden hittar ett objekt.
- Kunden skickar en bokningsförfrågan.
- Uthyraren hanterar förfrågan.
- Avtal kan kopplas till bokningen.
- Objekt lämnas ut.
- Objekt återlämnas.
- Historik sparas.

Om detta fungerar har MVP uppnått sitt mål.

---

# Primär målgrupp

Version 1 riktar sig till:

- Privatpersoner
- Små företag
- En enda uthyrare

Systemet behöver inte stödja flera uthyrare ännu.

---

# Affärsmål

Version 1 ska kunna användas för att:

- publicera objekt
- ta emot bokningar
- administrera objekt
- administrera kunder
- dokumentera uthyrningar

utan externa integrationer.

---

# Funktioner som SKA ingå

## Publik webbplats

- Startsida
- Om oss
- Kontakt
- FAQ
- Integritetspolicy
- Villkor

---

## Objekt

Systemet ska kunna:

- skapa objekt
- redigera objekt
- ta bort objekt (soft delete)
- aktivera/inaktivera objekt
- ladda upp bilder
- ange kategori
- ange pris
- ange deposition
- ange beskrivning
- ange plats
- ange status

---

## Objektlista

Besökaren ska kunna:

- se alla objekt
- filtrera
- söka
- öppna objektdetaljer

---

## Objektdetalj

Objektsidan ska visa:

- bilder
- pris
- beskrivning
- tillgänglighet
- skick
- villkor
- bokningsknapp

---

## Bokningar

Version 1 ska stödja:

- bokningsförfrågan
- datumintervall
- kommentar
- kontaktuppgifter
- bokningsstatus

Ingen automatisk bokningsbekräftelse krävs.

---

## Kalender

En enkel kalender ska visa:

- upptagna datum
- lediga datum

---

## Kunder

Systemet ska kunna lagra:

- namn
- adress
- telefon
- e-post
- historik

---

## Avtal

Version 1 ska stödja:

- avtalsmall
- avtalsstatus
- koppling mellan bokning och avtal

Ingen digital signering.

---

## Betalning

Version 1 ska stödja:

- betalstatus
- deposition
- manuell betalningsnotering

Ingen integration mot:

- Swish
- Fortnox

---

## Administration

Administratören ska kunna:

- hantera objekt
- hantera kunder
- hantera bokningar
- hantera kategorier
- se historik

---

## Service

Version 1 ska kunna registrera:

- servicedatum
- besiktningsdatum
- kommentarer

Ingen automatiserad serviceplanering.

---

## Dokument

Objekt ska kunna ha:

- bilder
- manualer
- besiktningsprotokoll

---

## Loggning

Systemet ska logga:

- skapade objekt
- ändrade objekt
- bokningar
- administratörsåtgärder

---

# Funktioner som INTE ingår

Version 1 ska INTE innehålla:

- BankID
- Swish
- Fortnox
- flera uthyrare
- provision
- marknadsplats
- QR-koder
- GPS
- IoT
- AI-funktioner
- mobilapp
- PWA offline
- flerspråkighet
- digital signering
- automatisk fakturering
- automatiska e-postflöden
- avancerat behörighetssystem

---

# Förberedelser för Version 2

Datamodellen ska redan från början kunna stödja:

- flera uthyrare
- företag
- API
- mobilapp
- BankID
- Swish
- Fortnox
- marknadsplats
- provision
- QR-koder
- GPS
- BI
- AI

utan att databasen behöver göras om.

---

# Definition of Done

Version 1 är klar när följande fungerar:

- Objekt kan administreras.
- Objekt kan visas publikt.
- Kund kan skicka bokningsförfrågan.
- Bokning kan administreras.
- Kalender fungerar.
- Avtal kan kopplas.
- Betalstatus kan registreras.
- Servicehistorik kan registreras.
- Dokument kan laddas upp.
- Historik sparas.
- Systemet fungerar stabilt.

---

# Framgångskriterier

MVP anses lyckad om:

- minst en uthyrare kan använda systemet i skarp drift
- hela uthyrningsflödet fungerar
- inga manuella Excel-listor behövs
- systemet är enkelt att vidareutveckla

---

# Grundprincip

Version 1 ska lösa ett verkligt problem på ett enkelt och stabilt sätt.

Alla funktioner som inte direkt bidrar till uthyrningsflödet ska vänta till Version 2.
