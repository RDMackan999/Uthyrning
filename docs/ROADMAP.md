# Roadmap

Roadmapen beskriver föreslagen utvecklingsordning. Varje sprint ska brytas ner i issues innan implementation.

## Sprint 0: projektgrund

- Flytta över fungerande Sites-landningssida.
- Skapa styrdokument.
- Behålla byggsetup och hero-bild.
- Dokumentera att PHP-struktur kommer senare.

## Sprint 1: teknisk grund

- Besluta hur Sites-frontend och PHP-backend ska samexistera.
- Skapa PHP-bootstrap.
- Skapa config-laddning med `config.example.php` och lokal `config.php`.
- Skapa PDO-anslutning.
- Skapa enkel felhantering och loggstruktur.

## Sprint 2: databas

- Skapa första databasschema.
- Införa migrationer.
- Skapa tabeller för användare, roller, objekt och grundstatusar.
- Säkerställa foreign keys, tidsstämplar och soft delete där det passar.

## Sprint 3: användare/roller

- Skapa inloggning med säker lösenordshashning.
- Skapa sessionshantering.
- Skapa roller för admin, kund och uthyrare.
- Skapa behörighetskontroller.

## Sprint 4: objekt

- Skapa objektkatalog.
- Skapa kategorier.
- Skapa bilder och dokumenterat skick.
- Skapa pris per dag och grundvillkor.
- Skapa objektflöde för uthyrare.

## Sprint 5: bokningar/kalender

- Skapa tillgänglighetskalender.
- Skapa bokningsförfrågan.
- Hantera överlappande bokningar.
- Skapa statusflöden för bokning.

## Sprint 6: avtal

- Skapa avtalsmallar.
- Förbereda digital signering.
- Koppla avtal till bokningar och objekt.
- Spara avtalsversioner och händelser.

## Sprint 7: betalningar/Fortnox/Swish

- Förbereda betalstatusar.
- Utreda Swish-flöde.
- Utreda Fortnox fakturering.
- Dokumentera krav innan integrationer byggs.

## Sprint 8: BankID

- Utreda BankID-leverantör.
- Dokumentera autentiseringsflöden.
- Koppla verifiering till användarkonto.
- Säkerställa fallback och loggning.

## Sprint 9: UH/service

- Skapa underhålls- och servicehistorik för objekt.
- Skapa kontrollpunkter före och efter uthyrning.
- Stödja serviceintervall och interna notiser.

## Sprint 10: marknadsplats

- Öppna för externa uthyrare.
- Skapa provision och intäktsrapportering.
- Skapa omdömen, betyg och moderation.
- Separera tydligare mellan plattform, uthyrare och kund.
