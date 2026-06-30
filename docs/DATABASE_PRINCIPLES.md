# DATABASE_PRINCIPLES.md

# Databasprinciper

## Syfte

Detta dokument beskriver de grundläggande principerna för hur databasen ska designas och utvecklas.

Målet är att skapa en databas som är:

- säker
- snabb
- normaliserad
- enkel att underhålla
- lätt att vidareutveckla
- skalbar för framtida funktioner

Alla framtida databasbeslut ska följa dessa principer.

---

# Databasmotor

Projektet ska använda:

- MariaDB (primärt)
- MySQL (fullt kompatibel)

Lagringsmotor:

- InnoDB

Teckenkodning:

```
utf8mb4
```

Kollation:

```
utf8mb4_unicode_ci
```

Detta ska användas konsekvent för hela databasen.

---

# Grundprincip

Databasen ska designas för:

- lång livslängd
- hög datakvalitet
- enkel felsökning
- tydliga relationer
- hög prestanda
- framtida utbyggnad

Snabba lösningar får aldrig prioriteras framför en hållbar datamodell.

---

# Normalisering

Version 1 ska minst följa tredje normalformen (3NF).

Data ska inte dupliceras i onödan.

Undantag får endast göras om:

- prestanda kräver det
- beslutet dokumenteras

---

# Namngivningsstandard

Tabeller:

- snake_case
- plural

Exempel:

```
users
roles
permissions
objects
bookings
booking_items
contracts
```

Kolumner:

```
created_at
updated_at
deleted_at
created_by
updated_by
```

Foreign Keys:

```
user_id
object_id
booking_id
contract_id
```

Undvik förkortningar om de inte är allmänt vedertagna.

---

# Primärnycklar

Alla tabeller ska ha en primärnyckel.

Standard:

```
BIGINT UNSIGNED AUTO_INCREMENT
```

Primärnyckten ska heta:

```
id
```

UUID kan införas senare där det finns särskilda behov.

---

# Foreign Keys

Alla logiska relationer ska använda Foreign Keys.

Delete-regler ska väljas medvetet.

Föredragen ordning:

```
RESTRICT
```

eller

```
SET NULL
```

`CASCADE DELETE` används endast när det är helt säkert.

---

# Index

Index ska skapas med eftertanke.

Normalt indexeras:

- Foreign Keys
- sökfält
- datumfält
- unika identifierare

Onödiga index ska undvikas.

---

# PDO

All databasåtkomst ska ske via PDO.

Krav:

- Prepared Statements
- parametriserade frågor
- central anslutning
- exceptions aktiverade
- inga SQL-strängar med användardata

Exempel:

```
SELECT * FROM users WHERE id = ?
```

inte

```
SELECT * FROM users WHERE id = '$id'
```

---

# Datatyper

Pengar:

```
DECIMAL(12,2)
```

inte FLOAT eller DOUBLE.

Datum:

```
DATETIME
```

Boolean:

```
TINYINT(1)
```

Text:

```
VARCHAR
```

eller

```
TEXT
```

beroende på behov.

---

# Datum och tid

Alla tabeller ska normalt innehålla:

```
created_at
updated_at
```

Vid behov även:

```
deleted_at
created_by
updated_by
```

All lagring sker i UTC.

Presentation sker i användarens tidszon.

---

# Soft Delete

Soft delete används när historik är viktig.

Exempel:

- användare
- objekt
- bokningar
- avtal
- kunder
- dokument

Soft delete sker med:

```
deleted_at
```

Inte med en boolesk flagga.

---

# Audit Trail

Systemet ska ha full Audit Trail.

Logga bland annat:

- inloggningar
- utloggningar
- ändrade objekt
- bokningar
- avtal
- betalstatus
- uppladdade filer
- behörighetsändringar
- administrativa åtgärder

Auditloggen ska aldrig kunna manipuleras av vanliga användare.

---

# Historik

Historik ska sparas när det ger affärsnytta.

Exempel:

- prisändringar
- statusändringar
- servicehistorik
- besiktningshistorik

Historik ska inte skrivas över.

Ny rad är bättre än att ändra gammal.

---

# Statusar

Undvik ENUM.

Statusar ska normalt ligga i egna tabeller.

Exempel:

```
booking_statuses
payment_statuses
object_statuses
contract_statuses
```

Det gör systemet mer flexibelt.

---

# Konfiguration

Konfigurerbara värden ska inte hårdkodas.

Exempel:

- moms
- bokningsregler
- avgifter
- roller
- behörigheter

ska kunna ändras utan kodändringar.

---

# Säkerhet

Personuppgifter ska lagras enligt GDPR.

Lösenord:

```
password_hash()
```

Personnummer ska aldrig lagras okrypterat om det inte finns ett dokumenterat behov.

Känslig information ska minimeras.

---

# Anonymisering

När data inte längre behöver sparas ska den:

- anonymiseras
- eller raderas enligt GDPR

Historik ska bevaras där lagen tillåter.

---

# Filer

Själva filerna lagras inte i databasen.

Databasen lagrar endast:

- sökväg
- filnamn
- storlek
- MIME-typ
- checksumma
- uppladdad av
- uppladdad tid

---

# Migrationer

Alla schemaändringar ska ske via migrationer.

Exempel:

```
0001_create_users.sql

0002_create_roles.sql

0003_create_permissions.sql
```

Migrationer ska:

- versionshanteras
- kunna köras om
- dokumenteras

Destruktiva migrationer ska ha rollback-plan.

---

# Seed-data

Seed-data ska skiljas från migrationer.

Exempel:

- standardroller
- standardbehörigheter
- grundinställningar

---

# Prestanda

Optimera först när ett problem finns.

Mät alltid före optimering.

Undvik:

- SELECT *
- onödiga joins
- N+1-problem

---

# Skalbarhet

Databasen ska redan från början kunna växa till:

- flera uthyrare
- flera företag
- flera språk
- flera valutor
- API
- mobilapp
- PWA
- IoT
- QR-koder
- GPS
- AI
- BI

utan att grundmodellen behöver göras om.

---

# Beslutsregel

Alla större förändringar av datamodellen ska dokumenteras i:

```
docs/PROJECT_DECISIONS.md
```

innan implementation.

---

# Grundprincip

En enkel och tydlig datamodell är alltid bättre än en avancerad modell som ingen förstår.

Databasen ska optimeras för:

1. Datakvalitet
2. Säkerhet
3. Läsbarhet
4. Underhållbarhet
5. Skalbarhet
6. Prestanda
