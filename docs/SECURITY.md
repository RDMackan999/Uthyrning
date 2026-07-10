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
