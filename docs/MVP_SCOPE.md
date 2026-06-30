# MVP Scope

Detta dokument beskriver tänkt omfattning för version 1. Syftet är att hålla första produktversionen tydlig och byggbar.

## Version 1 ska innehålla

### Startsida

En publik startsida som förklarar erbjudandet, visar kategorier och leder användaren vidare till objekt.

Nuvarande Sites-landningssida är första basen.

### Objektlista

En lista över uthyrningsbara objekt med grundinformation:

- namn
- kategori
- ort
- pris per dag
- enkel status eller tillgänglighetsindikator
- bild eller ikon

### Objektdetalj

En detaljsida för ett objekt med:

- beskrivning
- pris
- plats
- villkor
- bilder
- grundläggande skickinformation
- väg till bokningsförfrågan

### Enkel admin för objekt

En enkel administrativ yta för att hantera egna objekt:

- skapa objekt
- redigera objekt
- sätta pris
- lägga till bilder
- markera aktiv/inaktiv

Detta är inte ett komplett marknadsplats- eller rollsystem i version 1.

### Bokningsförfrågan

En enkel bokningsförfrågan där användaren kan ange:

- objekt
- önskat datumintervall
- namn och kontaktuppgifter
- eventuell kommentar

### Manuell godkännandeprocess

Version 1 ska stödja manuell hantering:

- inkommande förfrågan
- intern granskning
- godkänn eller neka
- kontakt med kund utanför automatiserade integrationsflöden vid behov

### Avtal förberett

Avtal ska vara förberett i struktur och dokumentation, men BankID-signering ska inte byggas i version 1.

Version 1 kan förbereda:

- avtalsstatus
- avtalsmall
- koppling mellan bokning och avtal

### Betalning och faktura förberett

Fortnox och Swish ska vara förberedda i struktur och dokumentation, men inte byggda som integrationer i version 1.

Version 1 kan förbereda:

- betalstatus
- fakturastatus
- manuella betalnoteringar

## Utanför version 1

Version 1 ska inte innehålla:

- BankID-integration
- Swish-integration
- Fortnox-integration
- full marknadsplats för externa uthyrare
- automatiserad provisionshantering
- avancerat rollsystem
- komplett PWA-läge
- flerspråkighet
- automatiserad digital signering

## Princip

MVP ska bevisa uthyrningsflödet med egna objekt först. Marknadsplats, integrationer och automation ska byggas först när grunden är stabil.
