# SECURITY.md

# Säkerhetspolicy

## Syfte

Detta dokument beskriver projektets säkerhetsprinciper.

Säkerhet ska byggas in från början och aldrig läggas till i efterhand.

Alla nya funktioner ska granskas ur ett säkerhetsperspektiv innan implementation.

---

# Säkerhetsprinciper

Projektet följer principerna:

- Least Privilege
- Defense in Depth
- Secure by Default
- Principle of Least Surprise
- Fail Secure

Säkerhet prioriteras alltid framför bekvämlighet.

---

# Autentisering

Lösenord ska aldrig sparas i klartext.

PHP ska använda:

```php
password_hash()
password_verify()
```

Krav:

- bcrypt eller Argon2 (PHP-standard)
- inga egna hashfunktioner
- inga MD5
- inga SHA1
- inga reversibla krypteringar

Lösenord ska aldrig loggas.

Reset-tokens ska vara slumpmässiga, tidsbegränsade och engångsanvända.

---

## Sprint 2C - autentiseringsdesign

Version 1 ska använda e-post och lösenord för administratörer och framtida kontoanvändare. BankID ska förberedas som framtida extern identitet men inte påverka första implementationen.

Rekommenderad modell:

- E-post normaliseras innan uppslag och jämförelse.
- Lösenord lagras endast med `password_hash()`.
- Inloggning ska använda generiska felmeddelanden för att undvika kontouppräkning.
- Lyckad inloggning ska regenerera session-id.
- Utloggning ska radera server-session och klient-cookie.
- Sessionsdata ska lagras serverside och bara innehålla minsta möjliga användaridentifierare och metadata.
- Aktiva sessioner ska kunna återkallas när sessionstabeller implementeras.
- Flera samtidiga enheter tillåts i Version 1, men ska kunna granskas och återkallas.
- Inaktiva, spärrade eller soft delete:ade konton får inte logga in.

Beslut för Version 1:

- E-postverifiering krävs innan ett konto får använda skyddade ytor.
- Remember me ingår inte i Version 1.
- Normal absolut sessionstid är 8 timmar.
- Maximal inaktivitetstid är 30 minuter.
- Efter 5 misslyckade försök för samma konto eller e-post inom 15 minuter spärras inloggning temporärt i 15 minuter.
- Efter 20 misslyckade försök från samma IP inom 15 minuter spärras IP temporärt i 30 minuter.
- Lösenordsreset-token ska alltid lagras hashad.
- Reset-token ska vara engångsanvänd, ha kort giltighetstid och bli ogiltig efter lösenordsbyte.
- Aktiva sessioner ska kunna återkallas när sessionsmodellen byggs.
- En användare får vara inloggad på flera enheter, men varje session ska spåras separat.

Lösenordspolicy:

- Minst 12 tecken.
- Max 128 tecken.
- Tillåt lösenfraser.
- Kräv inte artificiella teckenregler som försämrar användbarhet.
- Stoppa vanliga eller tidigare läckta lösenord när sådan kontroll finns tillgänglig.
- Lösenordsbyte ska kräva aktuellt lösenord för inloggad användare.
- Administrativt lösenordsbyte ska ske genom resetflöde, inte genom att administratören ser eller skriver användarens lösenord.

Alternativ som valdes bort:

1. Kort session, till exempel 1 timme.
   Fördel: mindre risk vid kapad session. Nackdel: sämre arbetsflöde för administratörer som arbetar löpande under dagen.
2. Lång session, till exempel 30 dagar.
   Fördel: bekvämt. Nackdel: för hög risk utan färdig remember me-modell och sessionsgranskning.
3. Remember me i Version 1.
   Fördel: bekvämt för återkommande användare. Nackdel: kräver separat persistent token-modell, rotation och återkallelse. Skjuts upp.

Autentiseringshändelser som ska audit-loggas:

- lyckad inloggning
- misslyckad inloggning
- utloggning
- temporär kontospärr
- temporär IP-spärr
- lösenordsbyte
- begärd lösenordsreset
- använd reset-token
- ogiltig eller utgången reset-token
- e-postverifiering
- återkallad session
- spärrat eller inaktiverat konto

Loggar får aldrig innehålla lösenord, reset-token, sessions-id, BankID-identifierare eller andra hemligheter i klartext.

BankID senare:

- BankID ska kopplas via en separat extern identitetsmodell.
- Personnummer ska inte användas som primärnyckel.
- BankID får inte implementeras innan juridiska krav, dataflöde, lagring, loggning och leverantör är dokumenterade.

---

# Auktorisering

Behörigheter ska kontrolleras på serversidan.

Frontend får aldrig avgöra om en användare har rättigheter.

All känslig funktionalitet ska verifiera:

- användare
- roll
- behörighet

innan åtgärden utförs.

## Organisationsscopad adminåtkomst

Adminåtkomst ska följa minsta privilegium.

`system_admin` är global och får endast användas där plattformsnivå behövs. Åtgärder där `system_admin` går över organisationsgränser ska audit-loggas.

`organization_admin` får endast komma åt data inom organisationer där användaren har en aktiv, organisationsscopad rolltilldelning.

Organisationstillhörighet får inte litas på från klienten. För befintliga resurser ska organisationen härledas från resursen eller dess ägare, till exempel:

- uthyrningsobjekt via `rental_items.organization_id`
- priser och tillgänglighet via ägande uthyrningsobjekt
- bokningar via `bookings.organization_id`
- kunder via `customers.organization_id`
- företag via `companies.organization_id`
- notifieringar via `notifications.organization_id`

För nya resurser får användaren välja organisation endast bland organisationer som finns i auth context. Servern ska alltid verifiera valet innan något sparas.

Direkta resursreferenser ska skyddas mot IDOR. En organisation-admin ska inte kunna avgöra om en nekad resurs finns i en annan organisation genom felmeddelanden, listor eller sidokanaler. Nekad cross-tenant-åtkomst ska loggas som säkerhetshändelse utan att hemligheter, personnummer, tokens eller sessions-id loggas.

---

# Sessionssäkerhet

Sessioner ska:

- använda HttpOnly
- använda Secure i produktion
- använda SameSite=Lax eller Strict
- regenerera session-id efter inloggning
- regenerera session-id efter behörighetsändring
- ha rimlig timeout
- avslutas korrekt vid utloggning

Känslig information ska inte lagras i sessionen.

---

# CSRF

Alla formulär som ändrar data ska använda CSRF-token.

Exempel:

- login
- logout
- profil
- bokningar
- objekt
- administration
- avtal
- betalningar

GET-anrop får aldrig ändra data.

---

# XSS

All output ska HTML-escapas.

Ingen användarstyrd HTML får visas utan sanering.

JavaScript ska aldrig byggas med användardata.

Vid behov används en HTML-sanitizer.

---

# SQL Injection

All databasåtkomst ska ske via PDO.

Krav:

- Prepared Statements
- parametrar
- inga SQL-strängar med användardata

Sortering och filtrering ska vitlistas.

---

# Inputvalidering

All input ska:

- valideras
- normaliseras
- typkontrolleras
- längdkontrolleras

Frontend-validering ersätter aldrig backend-validering.

---

# Filuppladdning

Tillåt endast godkända filtyper.

Kontrollera:

- MIME-typ
- filändelse
- filstorlek

Alla filer ska få nytt filnamn.

Filer ska lagras utanför publik webbrot när möjligt.

PHP-filer får aldrig kunna laddas upp eller köras.

## Bildpolicy för Version 1

Tillåt endast:

- JPEG
- PNG
- WebP

Tillåt inte i första bildflödet:

- SVG
- GIF
- TIFF
- HEIC
- video
- PDF
- Office-dokument
- PHP eller andra exekverbara filer

Säkerhetskrav:

- Max filstorlek ska vara 8 MB per bild om inget annat beslut dokumenteras.
- Max bilddimension ska vara 6000 x 6000 pixlar före normalisering.
- MIME-typ ska verifieras från filinnehåll på serversidan.
- Filändelse ska vitlistas men får aldrig vara enda kontroll.
- Uppladdat filnamn får endast sparas som metadata och aldrig användas som faktisk storage path.
- Storage key ska genereras av servern och vara svår att gissa.
- Klientinskickad katalog, path, `organization_id`, MIME-typ eller filstorlek får inte vara källa till sanning.
- EXIF och annan privat metadata ska tas bort eller minimeras i publika bildvarianter.
- Uppladdade filer ska lagras så att de inte kan köras av webbservern.
- Publika bild-URL:er ska genereras av applikation eller storage-adapter och får inte exponera lokala sökvägar.
- Felmeddelanden ska vara generella och inte läcka paths, storage-konfiguration, stack traces eller interna id:n.
- Mediaåtkomst ska kontrolleras mot organisation och roll innan admin kan visa eller ändra filer.
- Cross-tenant-åtkomst ska behandlas som säkerhetsfel och audit-loggas när auditflödet finns.

Metadata ska sparas:

- uppladdad av
- datum
- checksumma
- filstorlek

---

# API-säkerhet

När API införs ska:

- autentisering krävas
- auktorisering kontrolleras
- rate limiting införas
- loggning finnas
- felmeddelanden vara generella

API får aldrig exponera intern information.

---

# HTTP Headers

Produktion ska använda:

- Content-Security-Policy
- X-Frame-Options
- X-Content-Type-Options
- Referrer-Policy
- Permissions-Policy

HTTPS är obligatoriskt.

Backend lägger grundläggande säkerhetsheaders på `Response` som standard:

- `Content-Security-Policy` med `frame-ancestors 'none'`, `base-uri 'self'` och `form-action 'self'`
- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy` som stänger av geolocation, mikrofon och kamera

Explicit satta response-headers får behållas när en controller behöver ett specifikt innehållstyp- eller cachebeteende.

`/health` ska endast returnera minimal status och får inte exponera miljö, version, databastatus, SMTP-status eller hemligheter.

## Produktionsvakt

Produktion ska falla stängt om kritisk config saknas eller är osäker.

Vid `APP_ENV=production` ska backend vägra starta om:

- debug är aktivt
- `APP_BASE_URL` inte använder HTTPS
- HTTPS-krav inte är uttryckligen aktiverat
- session- eller CSRF-cookies inte är `Secure`
- databasconfig ser ut som development/test/exempel eller saknar lösenord
- SMTP inte är explicit konfigurerat
- logg-, sessions-, temp- eller media-kataloger inte är skrivbara

Loggar från denna kontroll får endast innehålla säkra issue-koder, inte configvärden, DSN, lösenord eller andra hemligheter.

---

# Loggning

Logga:

- tekniska fel
- inloggningar
- misslyckade inloggningar
- behörighetsfel
- adminåtgärder
- integrationsfel

Logga aldrig:

- lösenord
- API-nycklar
- tokens
- sessions-id
- personnummer
- fullständiga betaluppgifter
- fullständiga e-postmeddelanden

---

# E-post och notifieringar

Notifieringar ska följa samma säkerhetsprinciper som övrig backend.

Krav:

- Validera mottagaradresser innan leveransförsök.
- Förhindra header injection genom att neka radbrytningar i headerfält som mottagare, avsändare och ämne.
- HTML-escapa all användarstyrd data i e-postmallar.
- Logga aldrig hela e-postbody, lösenord, tokens, reset-länkar eller interna säkerhetsdetaljer.
- Logga endast säkra sammanfattningar av leveransfel, till exempel felkod och kort feltyp.
- Development och test ska inte skicka riktiga e-postmeddelanden av misstag.
- Production får inte använda development-transport som tyst fallback.
- SMTP i produktion ska använda TLS/STARTTLS eller SMTPS och explicit konfiguration.
- SMTP-uppgifter och provider-nycklar får aldrig committas.
- Extern e-postprovider ska granskas ur GDPR- och personuppgiftsperspektiv innan produktion.

Notifieringar får innehålla nödvändig bokningsinformation men ska inte exponera interna id:n, interna noteringar, auditdata eller andra kunders uppgifter.

---

# Audit Trail

Systemet ska kunna spåra:

- vem
- vad
- när
- varifrån

Audit-loggar ska inte kunna ändras av vanliga användare.

---

# GDPR

Projektet ska följa GDPR.

Principer:

- dataminimering
- ändamålsbegränsning
- lagringsminimering
- korrekthet
- integritet
- konfidentialitet

Personuppgifter ska endast sparas när de behövs.

## Kunddata

Kundregistret ska följa dataminimering.

Regler:

- `Customer` ska bara innehålla uppgifter som behövs för kundrelation, bokning, avtal, kontakt och eventuell fakturering.
- Booking customer snapshot ska bevara historisk bokningskontakt men inte användas som aktuell kundprofil.
- Interna kundanteckningar får aldrig visas publikt, skickas i e-post, exponeras i kundportal eller kopieras till booking snapshot.
- Kunddata får inte delas mellan uthyrande organization utan uttryckligt dokumenterat stöd.
- Automatisk matchning får inte slå ihop kunder mellan olika organization.
- Personnummer, kreditinformation och marknadsföringssamtycke får inte läggas till utan separat dokumenterat behov.
- Blockerade eller arkiverade kunder ska bevara nödvändig historik, men persondata ska kunna anonymiseras enligt framtida retentionregler.

## Fulfillment-data

Utlämning och återlämning ska följa dataminimering.

Regler:

- Fulfillment records ska återanvända booking customer snapshot i stället för att duplicera full kundprofil.
- Interna condition notes, damage notes och avvikelsekommentarer får inte visas publikt utan separat beslut.
- Deposition ska lagras som nödvändig affärsdata, inte som full betalningsinformation.
- Audit för utlämning och återlämning ska logga aktör, organisation, bokning och resultat, men inte hemligheter, sessionsdata eller onödig PII.
- Cross-tenant-åtkomst till fulfillment ska skyddas via bokningens `organization_id`.

---

# Anonymisering

När lagringstiden löpt ut ska data:

- anonymiseras
- eller raderas

Historik ska bevaras när lagen kräver det.

---

# Kryptering

HTTPS ska användas i produktion.

Känsliga hemligheter lagras aldrig i Git.

Konfiguration sker via:

- config.php
- miljövariabler

API-nycklar ska kunna roteras.

---

# Backup

Databasen ska kunna säkerhetskopieras.

Backup ska:

- testas
- dokumenteras
- kunna återställas

Backup är värdelös om återställning inte fungerar.

---

# Beroenden

Alla tredjepartsbibliotek ska:

- hållas uppdaterade
- granskas
- endast installeras vid verkligt behov

Onödiga beroenden ska undvikas.

---

# Incidenthantering

Vid säkerhetsincident:

1. Stoppa exponeringen.
2. Logga händelsen.
3. Informera ansvarig.
4. Dokumentera orsaken.
5. Åtgärda grundproblemet.
6. Uppdatera dokumentationen.

---

# Framtida integrationer

Innan BankID, Swish, Fortnox eller andra externa tjänster införs ska följande dokumenteras:

- hotbild
- dataflöde
- autentisering
- felhantering
- loggning
- sekretess
- backup-plan

Ingen integration implementeras utan säkerhetsgranskning.

---

# Security by Design

Varje ny funktion ska besvara följande frågor:

- Kan detta missbrukas?
- Kan data manipuleras?
- Kan information läcka?
- Kan behörigheter kringgås?
- Hur loggas detta?
- Hur återställs systemet vid fel?

Om svaret är oklart ska implementationen pausas tills frågorna är besvarade.

---

# Grundprincip

Det är alltid billigare att bygga säkerhet från början än att försöka lägga till den i efterhand.

Vid osäkerhet ska den säkrare lösningen väljas.
