# Databasprinciper

Uthyrning ska använda MySQL eller MariaDB med PDO när backend införs. Databasen ska vara tydlig, relationsbaserad och byggd för spårbarhet.

## Databasmotor

- MySQL eller MariaDB.
- InnoDB där foreign keys behövs.
- `utf8mb4` som teckenkodning.
- Konsekvent kollation för hela databasen.

## PDO

All databasåtkomst ska gå via PDO.

Krav:

- Prepared statements för all användarinput.
- Ingen direkt interpolering av användardata i SQL.
- Centraliserad PDO-konfiguration.
- Kontrollerad felhantering utan att exponera känsliga uppgifter.

## Foreign keys

- Relationer ska skyddas med foreign keys där det är rimligt.
- Delete-regler ska väljas medvetet.
- Viktig historik ska normalt inte raderas hårt.
- Använd `ON DELETE RESTRICT` eller soft delete när historik måste bevaras.

## created_at och updated_at

De flesta tabeller ska ha:

```sql
created_at DATETIME NOT NULL
updated_at DATETIME NOT NULL
```

Där det passar kan även följande användas:

```sql
deleted_at DATETIME NULL
created_by INT NULL
updated_by INT NULL
```

## Soft delete

Soft delete ska användas där data behöver kunna döljas utan att historik försvinner, till exempel:

- Användare
- Objekt
- Bokningar
- Avtal
- Bilder och skickdokumentation

Hård radering kan vara aktuell för temporär data eller anonymiserad data, men ska bedömas per fall.

## Audit trail

Systemet ska senare ha audit trail för viktiga händelser:

- Inloggning och utloggning
- Skapande och ändring av objekt
- Bokningsstatus
- Avtal
- Betalstatus
- Behörighetsändringar
- Filuppladdningar
- Adminåtgärder

Audit trail ska dokumentera vad som hände, när det hände, vilken användare eller process som gjorde det och relevant kontext. Hemligheter och onödig persondata ska inte loggas.

## Statusar och konfiguration

Undvik hårdkodade ENUM-värden när statusar ska vara konfigurerbara eller kunna ändras över tid.

Föredra separata status- eller konfigurationstabeller för exempelvis:

- Bokningsstatus
- Betalstatus
- Objektstatus
- Avtalsstatus
- Roller och behörigheter

## Framtida migrationer

Migrationer ska införas innan produktionsdata finns.

Principer:

- Varje schemaändring ska ha en migration.
- Migrationer ska vara versionshanterade.
- Destruktiva migrationer ska dokumenteras tydligt.
- Rollback-strategi ska finnas för större ändringar.
- Seed-data ska skiljas från schemaändringar.
