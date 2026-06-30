# DATABASE_NAMING_STANDARD.md

# Databasens namngivningsstandard

## Syfte

Detta dokument definierar hur databasen ska namnges.

Målet är att hela databasen ska vara:

- konsekvent
- lättläst
- enkel att förstå
- enkel att underhålla
- enkel att dokumentera

Alla framtida tabeller, kolumner, index, constraints och migrationer ska följa denna standard.

---

# Språk

Allt i databasen skrivs på **engelska**.

Exempel:

✔ users

✔ bookings

✔ objects

Inte:

✘ användare

✘ bokningar

✘ objekt

Kommentarer och dokumentation skrivs däremot på svenska.

---

# Gemener

Allt skrivs med små bokstäver.

✔

```
booking_items
```

Inte

```
BookingItems
```

eller

```
Booking_Items
```

---

# snake_case

Alla namn använder snake_case.

✔

```
created_at

booking_status

customer_id
```

Inte

```
CreatedAt

bookingStatus

CustomerID
```

---

# Tabellnamn

Alla tabeller skrivs i plural.

Exempel:

```
users

roles

permissions

customers

companies

objects

categories

bookings

booking_items

contracts

payments

documents

images

audit_logs

settings
```

---

# Primärnycklar

Alla tabeller använder:

```
id
```

som primärnyckel.

Exempel:

```
users.id

objects.id

bookings.id
```

---

# Foreign Keys

Foreign Keys namnges:

```
tabell_i_singular + _id
```

Exempel:

```
user_id

object_id

booking_id

contract_id

company_id

category_id
```

Inte:

```
userid

objid

booking

fk_booking
```

---

# Datumfält

Standardfält:

```
created_at

updated_at

deleted_at
```

Använd aldrig:

```
created

updated

date_created
```

---

# Användarfält

Standard:

```
created_by

updated_by

deleted_by
```

---

# Boolean

Boolean-fält börjar med:

```
is_

has_

can_
```

Exempel:

```
is_active

is_deleted

has_image

can_book
```

---

# Prisfält

Använd tydliga namn.

Exempel:

```
price

daily_price

weekly_price

monthly_price

deposit

vat_rate
```

Undvik:

```
cost1

price2

amount
```

---

# Datum

Beskrivande namn.

Exempel:

```
booking_start

booking_end

service_date

inspection_date

payment_date
```

---

# Statusfält

Undvik ENUM.

Använd:

```
status_id
```

med relation till en statustabell.

Exempel:

```
booking_statuses

payment_statuses

contract_statuses

object_statuses
```

---

# Bilder

Bildtabeller:

```
images

object_images

document_images
```

Bildfält:

```
filename

filepath

mime_type

filesize

checksum
```

---

# Dokument

Dokumenttabeller:

```
documents

contracts

attachments
```

---

# Audit Trail

Audit-tabell:

```
audit_logs
```

Kolumner:

```
user_id

action

entity

entity_id

ip_address

user_agent

created_at
```

---

# Inställningar

Konfiguration lagras i:

```
settings
```

eller

```
system_settings
```

Inte i kod.

---

# Relationstabeller

Många-till-många-tabeller skrivs:

```
booking_objects

role_permissions

user_roles
```

Alfabetisk ordning används när möjligt.

---

# Index

Index namnges:

```
idx_<table>_<column>
```

Exempel:

```
idx_users_email

idx_bookings_customer_id

idx_objects_category_id
```

---

# Unika index

Namnges:

```
uniq_<table>_<column>
```

Exempel:

```
uniq_users_email

uniq_objects_serial_number
```

---

# Foreign Key Constraints

Namnges:

```
fk_<table>_<referenced_table>
```

Exempel:

```
fk_bookings_users

fk_bookings_objects

fk_objects_categories
```

---

# Check Constraints

Namnges:

```
chk_<table>_<rule>
```

Exempel:

```
chk_prices_positive

chk_booking_dates
```

---

# Views

Namnges:

```
vw_
```

Exempel:

```
vw_active_bookings

vw_available_objects

vw_customer_statistics
```

---

# Stored Procedures

Om sådana införs används:

```
sp_
```

Exempel:

```
sp_create_booking

sp_close_booking
```

---

# Funktioner

Om sådana införs används:

```
fn_
```

Exempel:

```
fn_calculate_price

fn_booking_duration
```

---

# Trigger

Namnges:

```
trg_
```

Exempel:

```
trg_booking_history

trg_update_timestamp
```

---

# Migrationer

Migrationer numreras.

Exempel:

```
0001_create_users.sql

0002_create_roles.sql

0003_create_permissions.sql

0004_create_customers.sql

0005_create_objects.sql
```

Numreringen ska aldrig ändras i efterhand.

---

# Seed-data

Seed-filer namnges:

```
seed_roles.sql

seed_permissions.sql

seed_settings.sql
```

---

# Förkortningar

Undvik förkortningar.

Skriv:

```
customer

category

description

manufacturer
```

Inte:

```
cust

cat

desc

mfg
```

Undantag:

```
id

url

api

gps

qr

vat

ip
```

---

# Grundprincip

Namn ska vara:

- tydliga
- beskrivande
- konsekventa
- enkla att förstå

Om ett namn kräver en förklaring är det oftast fel namn.

Databasen ska kunna förstås av en ny utvecklare utan ytterligare dokumentation.
