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
