# Säkerhet

Säkerhet ska byggas in från början. Detta dokument beskriver grundprinciper för Uthyrning.

## Lösenordshashning

- Lösenord ska aldrig sparas i klartext.
- Använd `password_hash()` i PHP.
- Verifiera med `password_verify()`.
- Logga aldrig lösenord eller lösenordsreset-tokens.

## Sessionssäkerhet

- Använd säkra session cookies.
- Sätt `HttpOnly` och `Secure` i produktion.
- Använd `SameSite=Lax` eller striktare där det fungerar.
- Regenerera session-id efter inloggning och behörighetsändring.
- Sätt rimliga timeout-regler.
- Lagra inte onödig känslig information i sessionen.

## CSRF

Alla formulär som ändrar data ska skyddas med CSRF-token.

Det gäller bland annat:

- Inloggningsrelaterade flöden
- Profiländringar
- Objektändringar
- Bokningar
- Avtal
- Adminåtgärder

## XSS

- Escapa all output som visas i HTML.
- Validera och normalisera input.
- Tillåt inte okontrollerad HTML från användare.
- Använd konsekventa helperfunktioner för HTML-escaping.
- Var extra försiktig med CMS-innehåll och uppladdade filer.

## SQL injection

- Använd PDO prepared statements.
- Interpolera aldrig användardata direkt i SQL.
- Validera sorteringsfält, filter och sidnummer mot tillåtna värden.
- Logga fel kontrollerat utan att exponera SQL eller hemligheter för användaren.

## Filuppladdningssäkerhet

Filuppladdningar ska byggas restriktivt.

Principer:

- Tillåt endast godkända filtyper.
- Kontrollera MIME-typ och filändelse.
- Byt filnamn vid uppladdning.
- Spara inte filer med användarstyrda sökvägar.
- Kör aldrig uppladdade filer som PHP.
- Begränsa filstorlek.
- Spara metadata om vem som laddat upp filen och när.

## GDPR och anonymisering

Personuppgifter ska hanteras med dataminimering.

- Samla bara in data som behövs.
- Dokumentera varför persondata sparas.
- Stöd anonymisering där historik måste bevaras men personen inte längre ska vara identifierbar.
- Skilj på radering, soft delete och anonymisering.
- Logga inte mer persondata än nödvändigt.

## Loggning

Loggar ska hjälpa felsökning och säkerhet utan att läcka känslig information.

Logga gärna:

- Tekniska fel
- Misslyckade inloggningsförsök
- Behörighetsnekade åtgärder
- Viktiga adminåtgärder
- Integrationsfel

Logga inte:

- Lösenord
- API-nycklar
- Fullständiga betalningshemligheter
- Onödigt detaljerade personuppgifter

## Framtida integrationer

BankID, Swish och Fortnox ska säkerhetsgranskas innan de byggs. Varje integration ska få dokumenterat flöde, felhantering, loggning och sekretessbedömning innan implementation.
