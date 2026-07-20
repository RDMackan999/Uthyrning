# Database Design

Detta dokument är projektets databasbibel för Uthyrning. Det beskriver hur databasen bör designas innan första migrationen skrivs.

Detta dokument innehåller medvetet ingen SQL, inga migrationer och inga seeders.

## 1. Övergripande databasfilosofi

Databasen ska byggas för ett verkligt uthyrningsflöde med egna objekt i Version 1, men den får inte låsa projektet till en enda uthyrare för alltid. Det viktigaste arkitekturbeslutet är därför att redan från början separera plattform, organisation, användare, kund, objekt och bokning.

Grundprinciper:

- Normalisera kärndata.
- Bevara historik för händelser som påverkar bokningar, avtal, objekt och ekonomi.
- Undvik hårdkodade statusar när de behöver ändras över tid.
- Använd soft delete för affärsdata som inte bör försvinna.
- Använd audit trail för säkerhets- och affärskritiska händelser.
- Designa för flera uthyrare genom `organizations`, även om Version 1 bara använder en organisation.
- Bygg inte generiska "allting-tabeller" där viktiga foreign keys försvinner.
- Separera filer, dokument och bilder från affärsobjekten som använder dem.
- Håll integrationer för BankID, Swish, Fortnox, API, IoT och AI förberedda men inte implementerade.

Senior rekommendation: inför organisationsbegreppet från dag ett. Att lägga till multi-tenant-stöd i efterhand är dyrt och riskfyllt, särskilt när bokningar, objekt, avtal, fakturor och audit trail redan finns.

## 2. Namngivningsstandard

Rekommenderad standard:

- Tabeller namnges i plural: `users`, `bookings`, `rental_items`.
- Kolumner namnges med `snake_case`.
- Primärnyckel heter `id`.
- Foreign keys heter `<singular_table>_id`, till exempel `user_id`, `booking_id`, `organization_id`.
- Tidsfält heter konsekvent `created_at`, `updated_at`, `deleted_at`.
- Statusreferenser heter `<area>_status_id` när statusen är konfigurerbar.
- Historiktabeller slutar med `_history`.
- Länktabeller använder båda tabellnamnen, till exempel `role_permissions`.
- Audit- och loggtabeller ska ha tydliga domännamn, till exempel `audit_events` och `system_logs`.

Undvik:

- Blandning av svenska och engelska i tabellnamn.
- Hårdkodade statuskolumner som text om statusen kan behöva konfigureras.
- Generella `type`-fält utan styrd källa.
- Kolumner som betyder olika saker beroende på annan kolumn.

Rekommendation: använd engelska tabell- och kolumnnamn i databasen, men svensk UI-copy i applikationen.

## 3. Primärnycklar

Rekommenderad standard:

- Alla huvudtabeller har en intern primärnyckel `id`.
- Primärnyckeln ska vara teknisk och aldrig exponeras som säker identifierare i publika länkar.
- Publika objekt kan senare få separat `public_id`, `slug` eller UUID-liknande identifierare.
- Länktabeller kan antingen ha egen `id` eller sammansatt unik regel, beroende på om de behöver historik, audit eller metadata.

Senior rekommendation:

- Använd tekniska numeriska id:n internt för enkel drift och prestanda.
- Lägg till stabila publika identifierare för objekt, bokningar och avtal när publika länkar behövs.
- Undvik att låta ordernummer, avtalsnummer eller fakturanummer vara primärnyckel.

## 4. Foreign Keys

Foreign keys ska användas för relationsdata där referensen måste vara korrekt.

Principer:

- Bokningar ska referera till kund, organisation och objekt via foreign keys.
- Avtal ska referera till bokning och avtalstyp.
- Bilder och dokument ska referera via tydliga länktabeller där det behövs.
- Historiktabeller ska helst behålla referenser men även spara läsbara snapshots av viktiga värden.
- Radering ska normalt begränsas eller hanteras med soft delete.

Var försiktig med polymorfa relationer som `entity_type` och `entity_id`. De är flexibla men bryter ofta foreign key-skydd. Använd dem endast för lågkritisk loggning eller komplettera med domänspecifika länktabeller.

## 5. Indexstrategi

Index ska stödja verkliga sökvägar och affärsregler, inte läggas på slentrian.

Viktiga indexområden:

- Inloggning: e-post, användarstatus och organisation.
- Objektlista: organisation, kategori, status, plats, publiceringsstatus.
- Bokningar: objekt, kund, datumintervall, status.
- Kalender: objekt, datumintervall, blockeringstyp.
- Audit trail: aktör, händelsetyp, tidpunkt och organisation.
- Dokument: ägare, kopplat affärsobjekt, dokumenttyp.
- Service: objekt, serviceintervall, utförandedatum, nästa åtgärd.

Unika regler behövs troligen för:

- E-post inom relevant identitetsmodell.
- Slug eller public_id för publika objekt.
- Rollnamn inom organisation eller systemnivå.
- Statusnycklar inom statusgrupp.

Risk: för många index gör skrivningar långsammare och migrationer tyngre. Börja med de index som krävs av MVP-flödena och dokumentera nya index när nya sökvägar införs.

## 6. Audit Trail

Audit trail ska svara på:

- Vem gjorde något?
- Vad gjordes?
- När gjordes det?
- Vilken organisation berördes?
- Vilket objekt, bokning, avtal eller dokument berördes?
- Vilket värde ändrades från och till, där det är rimligt?

Föreslagen huvudtabell:

- `audit_events`

Syfte:

- Samla säkerhets- och affärskritiska händelser.
- Ge spårbarhet vid tvist, fel, support och administration.

Relationer:

- Kopplas till `organizations` när händelsen hör till en uthyrare.
- Kopplas till `users` när en inloggad användare agerar.
- Kan ha valfria referenser till bokning, objekt, avtal, dokument eller kund.

Framtida utbyggnad:

- API-klient som aktör.
- Systemprocess som aktör.
- Export till BI eller extern loggtjänst.
- Säkerhetsklassning av händelser.

Senior rekommendation: audit trail ska inte vara samma sak som teknisk fellogg. Audit är affärs- och säkerhetsspårning. Teknisk loggning bör ligga separat.

## 7. Soft Delete-strategi

Soft delete används för affärsdata där historik måste bevaras.

Tabeller som sannolikt behöver `deleted_at`:

- `users`
- `organizations`
- `customers`
- `customer_contacts`
- `rental_items`
- `item_categories`
- `bookings`
- `agreements`
- `media_assets`
- `documents`

Soft delete ska inte ersätta status. Ett objekt kan vara inaktivt utan att vara raderat.

Riktlinjer:

- `deleted_at` betyder att raden inte längre ska användas i normala flöden.
- Historik och audit ska fortfarande kunna läsa raden.
- Persondata kan behöva anonymiseras även om raden soft delete:as.
- Hård radering kan vara tillåten för temporära tokens, cache och importer som kan återskapas.

## 8. Historik

Historik ska användas när förändringar är affärsviktiga.

Föreslagna historiktabeller:

- `booking_status_history`
- `item_status_history`
- `agreement_status_history`
- `payment_status_history`
- `service_history`
- `inspection_history`

Historik ska inte bara lagra aktuell status. Den ska visa händelsekedjan.

För bokningar och avtal bör historiken innehålla:

- tidigare status
- ny status
- ansvarig användare eller systemaktör
- tidpunkt
- kommentar eller anledning

Senior rekommendation: spara snapshots för pris, villkor och kunduppgifter på boknings- och avtalsnivå. Om kunden eller objektet ändras senare ska gamla bokningar fortfarande vara begripliga.

## 9. Konfigurationstabeller

Konfiguration ska skiljas från kod när värden kan ändras av verksamheten.

Föreslagna tabeller:

- `system_settings`
- `organization_settings`
- `status_groups`
- `status_definitions`
- `document_types`
- `agreement_templates`
- `payment_methods`
- `tax_rates`
- `unit_types`
- `item_condition_grades`

Syfte:

- Göra statusar, dokumenttyper, betalmetoder och inställningar styrbara.
- Undvika hårdkodade ENUM-värden för sådant som kan ändras.

Relationer:

- `organization_settings` kopplas till `organizations`.
- Statusdefinitioner används av bokningar, objekt, avtal och betalningar.
- Dokumenttyper används av dokument och avtalsfiler.

Risk: för generisk konfiguration kan göra systemet svårt att förstå. Endast sådant som verkligen behöver ändras ska bli konfigurationstabeller.

## 10. Huvudområden

### Plattform

Tabeller:

- `organizations`
- `organization_settings`
- `system_settings`
- `status_groups`
- `status_definitions`

Syfte:

- Representera plattformen och dess uthyrare.
- Stödja Version 1 med en enda uthyrare.
- Förbereda Version 2 med flera uthyrare och marknadsplats.

Relationer:

- En organisation äger objekt, bokningar, användare, inställningar och dokument.
- Systeminställningar gäller hela installationen.
- Organisationsinställningar gäller en specifik uthyrare.

Framtida utbyggnad:

- Provision per organisation.
- Marketplace-profiler.
- Avtals- och prisregler per organisation.
- API-åtkomst per organisation.

Senior invändning: om `organization_id` inte finns på centrala tabeller från början blir marknadsplatsen svår att införa. Lägg in organisationstillhörighet i kärnmodellen redan i Version 1.

### Användare

Tabeller:

- `users`
- `user_profiles`
- `user_external_identities`
- `roles`
- `permissions`
- `role_permissions`
- `user_roles`
- `organization_users`
- `user_sessions`
- `password_reset_tokens`

Syfte:

- Hantera inloggning, roller och behörigheter.
- Skilja identitet från organisationstillhörighet.
- Stödja admin, intern personal, uthyrare och senare kunder.

Relationer:

- En användare kan vara kopplad till en eller flera organisationer.
- Roller kan vara systemroller eller organisationsroller.
- Behörigheter kopplas till roller.
- Sessionsdata kopplas till användare.

Framtida utbyggnad:

- BankID-identiteter.
- API-klienter.
- Tvåfaktorsautentisering.
- Externa teammedlemmar hos uthyrare.

Senior rekommendation: separera `users` från `customers`. En kund kan boka utan att först ha ett fullständigt användarkonto, men kan senare kopplas till en användare.

#### Sprint 2: Identity Domain Design

Identitetsdomänen ska skilja mellan säkerhetsidentitet, organisationsmedlemskap och affärsrelation. Det är den viktigaste gränsdragningen innan användare, roller och kunder byggs.

Domänobjekt:

- `User`: den tekniska och säkerhetsmässiga identiteten som kan logga in eller senare kopplas till BankID.
- `Role`: en namngiven behörighetsprofil, till exempel systemadmin, uthyrare, intern personal eller kundportal-användare.
- `Permission`: en finmaskig rättighet som kod och gränssnitt kan kontrollera, till exempel `items.manage` eller `bookings.approve`.
- `UserRole`: kopplingen mellan användare, roll och scope.
- `Company`: en juridisk eller kommersiell aktör som kan hyra, faktureras eller senare kopplas till Fortnox.
- `Customer`: kundrelationen i uthyrningssystemet. En kund kan vara privatperson eller företag och behöver inte alltid vara en inloggad användare.

Föreslagna tabeller för identitet:

- `users`: kärnidentitet, inloggningsstatus och tekniska säkerhetsfält.
- `user_profiles`: icke-kritiska personprofilfält som namn och telefon.
- `user_external_identities`: framtida koppling till BankID eller annan identitetsleverantör. Tabellen ska inte byggas förrän integrationskrav finns.
- `roles`: roller på systemnivå eller organisationsnivå.
- `permissions`: kodstyrda rättigheter.
- `role_permissions`: many-to-many mellan roller och rättigheter.
- `user_roles`: rolltilldelningar till användare, med globalt eller organisationsbundet scope.
- `organization_users`: medlemskap mellan användare och uthyrare/organisation.

Föreslagna tabeller för kund och företag:

- `customers`: affärskund inom en uthyrande organisation.
- `companies`: juridisk företagsinformation för företagskunder och framtida Fortnox-koppling.
- `company_users`: framtida many-to-many mellan användare och företag.
- `customer_users`: framtida many-to-many mellan användare och kundrelationer.
- `customer_contacts`: kontaktpersoner och kontaktdata för kundärenden.
- `company_contacts`: kontaktpersoner hos företag.
- `customer_addresses`: adresser för kund, leverans och faktura.
- `customer_notes`: interna noteringar, med tydliga GDPR-regler.

Relationer:

- `users` har en till noll/ett `user_profiles`.
- `users` kan ha flera `user_external_identities` i framtiden, men en extern identitet får bara kopplas till en aktiv användare.
- `users` kopplas till `organizations` via `organization_users`.
- `users` får roller via `user_roles`.
- `roles` får rättigheter via `role_permissions`.
- `user_roles` bör kunna ha `organization_id` som nullable scope: `NULL` för systemroller och värde för organisationsroller.
- `customers` tillhör alltid en `organization`.
- `customers` kan representera en privatperson eller ett företag.
- `customers` kan kopplas till `companies` när kunden är ett företag.
- `customers` kan kopplas till `users` via `customer_users` när kundportal eller inloggat kundkonto införs.
- `companies` kan kopplas till flera `users` via `company_users`, eftersom en användare kan agera för flera företag och ett företag kan ha flera användare.

Rekommenderade foreign keys:

- `user_profiles.user_id` -> `users.id`
- `user_external_identities.user_id` -> `users.id`
- `organization_users.user_id` -> `users.id`
- `organization_users.organization_id` -> `organizations.id`
- `roles.organization_id` -> `organizations.id` när rollen är organisationsspecifik, annars `NULL`
- `role_permissions.role_id` -> `roles.id`
- `role_permissions.permission_id` -> `permissions.id`
- `user_roles.user_id` -> `users.id`
- `user_roles.role_id` -> `roles.id`
- `user_roles.organization_id` -> `organizations.id` när tilldelningen är organisationsspecifik
- `customers.organization_id` -> `organizations.id`
- `customers.company_id` -> `companies.id` när kunden är företag
- `company_users.company_id` -> `companies.id`
- `company_users.user_id` -> `users.id`
- `customer_users.customer_id` -> `customers.id`
- `customer_users.user_id` -> `users.id`

Rekommenderade index:

- Unikt index för normaliserad e-post i `users`, om e-post används som lokal inloggningsidentifierare.
- Unikt index på `user_external_identities(provider, provider_subject)` när BankID eller annan extern identitet införs.
- Index på `organization_users(organization_id, user_id)`.
- Index på `user_roles(user_id, organization_id)`.
- Unikt index på `roles(organization_id, key)` eller motsvarande rollnyckel.
- Unikt index på `permissions(key)`.
- Index på `customers(organization_id, company_id)`.
- Index på `companies(organization_number)` om organisationsnummer lagras.
- Index på `company_users(company_id, user_id)` och `customer_users(customer_id, user_id)`.

Obligatoriska fält när tabellerna senare implementeras:

- `users`: status, normaliserad e-post eller annan primär identifierare, `password_hash` när lokal lösenordsinloggning används, `created_at`, `updated_at`, och `deleted_at` för soft delete.
- `user_profiles`: `user_id` och minst visningsnamn eller separerade namnfält när personprofil behövs.
- `roles`: stabil rollnyckel, läsbart namn, scope-nivå och aktiv/inaktiv status.
- `permissions`: stabil rättighetsnyckel, namn och beskrivning.
- `user_roles`: `user_id`, `role_id`, scope och tidsstämplar.
- `customers`: `organization_id`, kundtyp, status och kontaktväg eller koppling till kontakt/person/företag.
- `companies`: namn, organisationsnummer när det är känt, status och tidsstämplar.

Rollmodell: alternativ och rekommendation:

1. Globala roller utan organisationsscope.
   Fördel: enkelt i Version 1. Nackdel: svårt att införa marknadsplats och externa uthyrare utan ombyggnad.
2. Roller per organisation utan systemroller.
   Fördel: bra tenant-isolering. Nackdel: plattformsadmin och systemprocesser blir svårare att modellera.
3. Hybrid med systemroller och organisationsroller.
   Fördel: stödjer Version 1, framtida marknadsplats och plattformsadministration. Nackdel: kräver tydliga regler för scope.

Rekommendation: använd hybridmodellen. `roles.organization_id` och `user_roles.organization_id` kan vara `NULL` för systemroller och satta för organisationsroller. Kod ska alltid kontrollera rättighet i rätt scope.

Användare och företag:

- En användare bör kunna tillhöra flera företag i framtiden.
- Version 1 behöver inte bygga företagsanvändare, men datamodellen ska inte hindra det.
- `company_users` bör användas när en inloggad person får agera för ett företag.
- `customer_users` bör användas när en inloggad person får agera för en specifik kundrelation hos en uthyrare.

Framtida marknadsplats:

- `organizations` ska fortsätta vara tenant-/uthyrarscope.
- `users` ska vara globala identiteter som kan ha roller i flera organisationer.
- `customers` ska vara kundrelationer per organisation, inte globala inloggningskonton.
- Externa uthyrare kan få egna `organizations`, egna roller och egna `organization_users`.
- Plattformsadmin ska kunna vara systemroll utan att tillhöra varje organisation.

Framtida integrationer:

- BankID bör kopplas via `user_external_identities`, inte genom att personnummer blir primär identitet.
- Fortnox-koppling bör ligga på `companies` och/eller `customers` via integrationsspecifika referenser, inte som hårdkodad affärslogik i `users`.
- Personnummer ska inte lagras okrypterat om det inte finns dokumenterat juridiskt behov.
- Externa identifierare ska ha unikhet per provider och aldrig loggas i klartext om de är känsliga.

#### Sprint 2C: Authentication Design

Autentisering ska byggas som ett separat lager ovanpå identitetsdomänen. `users` är kontoidentiteten, men sessioner, reset-token, e-postverifiering och autentiseringsloggning ska modelleras i separata tabeller när de implementeras.

Rekommenderade framtida tabeller:

- `user_sessions`: aktiva och historiska sessioner som kan återkallas.
- `password_reset_tokens`: hashade engångstokens för lösenordsreset.
- `email_verification_tokens`: hashade engångstokens för e-postverifiering.
- `login_attempts`: kortlivad teknisk spärr- och rate-limit-data.
- `authentication_events` eller `audit_events`: varaktig audit för autentiseringshändelser.

Syfte:

- Hålla lösenordsinloggning, sessionslivscykel och tokenflöden separerade från användarens grundidentitet.
- Kunna återkalla sessioner utan att ändra användarraden.
- Kunna spåra säkerhetshändelser utan att logga hemligheter.
- Förbereda framtida BankID genom extern identitetskoppling i egen modell.

Relationer:

- `user_sessions.user_id` refererar `users.id`.
- `password_reset_tokens.user_id` refererar `users.id`.
- `email_verification_tokens.user_id` refererar `users.id`.
- `login_attempts` bör kunna kopplas till `users.id` när användaren är känd, men även stödja försök där bara e-post eller IP finns.
- Varaktiga autentiseringshändelser kopplas till `users.id` när möjligt och till `organizations.id` när händelsen har organisationsscope.

Viktiga databasprinciper:

- Reset-token och e-postverifieringstoken lagras endast hashade.
- Sessions-id lagras inte i klartext; lagra hash eller serverintern identifierare.
- IP-adress och user agent kan behövas för säkerhet men ska hanteras enligt GDPR och dataminimering.
- `expires_at`, `used_at` och `revoked_at` behövs för token- och sessionslivscykel.
- `created_at` och `updated_at` används konsekvent.
- `deleted_at` är normalt inte rätt för kortlivade tokens; använd giltighet, användning och återkallelse.
- Login attempts bör kunna rensas enligt retention-regler.

Rekommenderade index när tabellerna byggs:

- `user_sessions(user_id, revoked_at, expires_at)`
- `password_reset_tokens(token_hash)`
- `password_reset_tokens(user_id, expires_at)`
- `email_verification_tokens(token_hash)`
- `email_verification_tokens(user_id, expires_at)`
- `login_attempts(email_normalized, attempted_at)`
- `login_attempts(ip_address, attempted_at)`
- `authentication_events(user_id, created_at)`

Version 1-beslut:

- E-postverifiering krävs innan skyddade ytor får användas.
- Remember me byggs inte i Version 1.
- Normal absolut sessionstid är 8 timmar.
- Inaktivitetstid är 30 minuter.
- 5 misslyckade försök per konto/e-post inom 15 minuter ger 15 minuters temporär spärr.
- 20 misslyckade försök per IP inom 15 minuter ger 30 minuters temporär IP-spärr.
- Flera samtidiga sessioner tillåts men ska kunna återkallas.

Framtida BankID:

- BankID ska använda `user_external_identities` eller motsvarande separat tabell när integrationskraven är beslutade.
- BankID ska inte kräva ändring av `users.id`.
- Personnummer får inte bli primär teknisk identitet.

Risker:

- Duplicerad persondata mellan `users`, `user_profiles`, `customers`, `customer_contacts` och `company_contacts`.
- För grova roller kan leda till för mycket behörighet.
- För finmaskiga behörigheter kan göra administrationen svår.
- Om e-post görs globalt unik kan samma person inte ha separata identiteter per tenant; om e-post inte är globalt unik blir inloggning och återställning svårare.
- Om kund och användare slås ihop för tidigt blir bokningsförfrågningar utan konto svåra att stödja.
- Om företag bara modelleras som textfält på kund blir Fortnox, historik och flera kontaktpersoner svårare senare.

### Kunder och företag

Tabeller:

- `customers`
- `customer_contacts`
- `companies`
- `company_users`
- `company_contacts`
- `customer_users`
- `customer_addresses`
- `customer_notes`

Syfte:

- Stödja både privatpersoner och företag.
- Kunna spara kontaktuppgifter för bokningar, avtal och manuell hantering.
- Förbereda fakturering och Fortnox utan att integrera det nu.

Relationer:

- En kund kan vara privatperson eller kopplad till företag.
- Företag kan ha flera kontaktpersoner.
- Bokningar refererar till kund och eventuellt företag.

Framtida utbyggnad:

- Kundportal.
- Kreditkontroll.
- Fortnox kundnummer.
- Faktura- och betalhistorik.

Risk: duplicerad persondata mellan `users`, `customers` och `customer_contacts`. Definiera tydligt vilken tabell som är identitet, vilken som är affärskund och vilken som är kontaktuppgift.

### Objekt

Tabeller:

- `rental_items`
- `item_categories`
- `item_category_relations`
- `item_locations`
- `item_rates`
- `item_status_history`
- `item_condition_reports`
- `item_accessories`
- `item_documents`
- `item_media`

Syfte:

- Hantera verktyg, maskiner, släp och utrustning.
- Stödja kategorisering, pris, status, plats, skick och bilder.
- Förbereda flera uthyrare genom organisationstillhörighet.

Relationer:

- Objekt ägs av organisation.
- Objekt ska ha en primär kategori.
- Objekt kan senare ha flera kategorier via relationstabell.
- Objekt har plats.
- Objekt kan ha flera priser eller prisperioder.
- Objekt kan ha bilder, dokument, tillbehör och skickrapporter.

Framtida utbyggnad:

- GPS-position.
- QR-koder.
- IoT-sensorer.
- Serviceintervall.
- Marketplace-publicering.
- Paket eller bundles.

Senior rekommendation: skilj mellan objektets identitet och dess tillgänglighet. Tillgänglighet hör hemma i kalender/bokning, inte som ett enkelt fält på objektet.

#### Sprint 4A: Rental Item Design

Objektdomänen ska modellera fysisk utrustning som kan hyras ut: handverktyg, maskiner, släp, byggutrustning, trädgårdsmaskiner och senare även fordon. Version 1 ska hålla modellen enkel och utgå från egna uthyrningsobjekt, men varje central tabell ska ha `organization_id` så att marknadsplats med flera uthyrare kan införas utan ombyggnad.

Rekommenderad huvudmodell:

- Använd en gemensam huvudtabell `rental_items` för alla uthyrningsobjekt.
- Skapa inte separata tabeller som `tools`, `trailers`, `machines` eller `vehicles` i Version 1.
- Skillnader mellan objekttyper hanteras i första hand med kategori, status, skick, priser, dokument och senare konfigurerbara attribut.
- Undvik ett generiskt `type`-fält utan styrd källa. Om typ behövs senare bör den modelleras via kategori, statusgrupp eller en kontrollerad konfigurationstabell.

Alternativ som valts bort:

1. Separata tabeller per objekttyp.
   Fördel: varje typ kan få exakta fält. Nackdel: bokning, kalender, media, service och sökning blir duplicerad och svår att underhålla.
2. En extremt generisk entity/attribute/value-modell.
   Fördel: hög flexibilitet. Nackdel: svårare datakvalitet, sämre sökbarhet och mer komplex admin i Version 1.
3. En gemensam `rental_items`-tabell med konkreta kärnfält.
   Fördel: enkel, normaliserad och lätt att koppla till bokningar, kategorier, media och service. Nackdel: vissa framtida specialfält kräver kompletterande tabeller. Rekommenderas.

Identifierare:

- `id` är intern primärnyckel och ska aldrig användas som säker publik identifierare.
- `public_id` bör införas när objekt får publika detaljerade länkar. Den ska vara stabil, icke-sekventiell och kunna visas i supportflöden.
- `slug` används för publik URL och SEO. Den bör vara unik inom organisationens publiceringsscope.
- `inventory_number` används internt för inventariehantering och etiketter.
- `serial_number` är tillverkarens serienummer när det finns.
- `qr_code_value`, `barcode_value` och RFID-kopplingar ska vänta tills respektive flöde specificeras. Datamodellen får inte kräva QR, streckkod eller RFID i Version 1.

Rekommenderade fält på `rental_items` när tabellen senare implementeras:

- `id`
- `organization_id`
- `owning_company_id` nullable, för juridisk ägare om den skiljer sig från organisationen.
- `primary_category_id`
- `item_status_id`
- `condition_grade_id` nullable
- `public_id`
- `slug`
- `name`
- `short_name` nullable
- `description` nullable
- `internal_note` nullable
- `serial_number` nullable
- `inventory_number` nullable
- `manufacturer` nullable
- `model` nullable
- `model_year` nullable
- `purchase_date` nullable
- `purchase_price` nullable
- `replacement_value` nullable
- `insurance_value` nullable
- `weight_kg` nullable
- `length_mm` nullable
- `width_mm` nullable
- `height_mm` nullable
- `color` nullable
- `is_active`
- `is_rentable`
- `vat_rate_id` nullable eller `vat_rate` om skattetabell inte är införd ännu.
- `deposit_amount`
- `created_at`
- `updated_at`
- `deleted_at`

Prisfält bör inte läggas direkt på `rental_items` om pris historiskt behöver ändras eller om flera prisperioder behövs. Rekommendationen är:

- Version 1 använder `item_rates` för dagspris, veckopris, månadspris, helgpris och eventuell timpris.
- `daily_price` får användas i UI och snapshots, men bör komma från aktuell prisrad.
- Pengar lagras som `DECIMAL(12,2)`.
- Moms ska vara explicit, antingen via `tax_rates`/`vat_rate_id` eller ett dokumenterat `vat_rate`-fält om skattetabell väntar.

Version 1-fält:

- Obligatoriskt: organisation, primär kategori, status, namn, slug eller genererad slug, publicerings-/uthyrningsflaggor, dagspris, momsregel, skapad/uppdaterad tid.
- Rekommenderat från start: kort beskrivning, huvudbild via media, deposition, inventarienummer, intern anteckning, skick, plats.
- Valfritt från start: serienummer, tillverkare, modell, inköpsdatum, inköpspris, nypris/ersättningsvärde, försäkringsvärde, vikt, dimensioner, färg.
- Väntar: RFID, IoT, GPS, fordonsunika fält, dynamiska kategoriattribut, avancerad prislogik, flervaluta och BI-attribut.

Koppling till kategorier:

- `rental_items.primary_category_id` bör peka på den kategori som används i Version 1-listor, admin och SEO.
- `item_category_relations` finns för framtida flera kategorier och ska innehålla relationen mellan objekt och primär kategori när objektdomänen byggs.
- Version 1-regel: ett publicerat objekt ska ha exakt en primär kategori.
- Framtida regel: ett objekt kan ha flera kategorier, men endast en primär kategori.
- Validering ska säkerställa att kategorin är aktiv, inte soft delete:ad och antingen global eller tillhör samma organisation som objektet.

Ägarskap och marknadsplats:

- `organization_id` är uthyrarens tenant och äger objektets operativa flöde.
- `owning_company_id` kan användas om objektets juridiska ägare behöver särskiljas från organisationen.
- Version 1 använder en organisation och normalt ett ägarföretag.
- Version 2 kan låta flera organisationer publicera objekt i samma marknadsplats utan att objekt flyttas mellan tenants.
- Objekt ska inte kopplas direkt till en användare som ägare i kärnmodellen. Användare agerar via roller och medlemskap.

Media och dokument:

- Objektbilder ska gå via `media_assets` och `item_media`, inte som filvägar direkt på `rental_items`.
- Version 1 bör stödja en huvudbild och flera detaljbilder när media byggs.
- Dokument som manualer, CE-intyg, serviceprotokoll och besiktningsunderlag ska gå via dokument-/mediaflöden, inte som blandade kolumner på objektet.
- Kritiska dokument som avtal ska ha tydliga domänrelationer när avtalsmodulen byggs.
- Filer ska lagras utanför databasen; databasen lagrar metadata, checksumma, ägarskap och kopplingar.

Statusmodell:

- Objektstatus ska inte vara ENUM.
- Rekommendationen är `status_groups` + `status_definitions`, där objektstatusar ligger i en egen grupp, till exempel `item_status`.
- `rental_items.item_status_id` beskriver objektets permanenta eller operativa grundstatus.
- `item_status_history` sparar statusförändringar med tidpunkt, aktör och kommentar.

Version 1-statusar:

- `active`: objektet är aktivt i systemet.
- `rented`: objektet är utlämnat i en aktiv uthyrning.
- `reserved`: objektet är reserverat genom godkänd bokning eller blockerad period.
- `service`: objektet är under service eller underhåll.
- `broken`: objektet är trasigt och får inte hyras ut.
- `archived`: objektet är arkiverat och visas inte i normala flöden.
- `hidden`: objektet är dolt publikt men kan administreras internt.

Statusansvar:

- Permanenta administrativa lägen: `active`, `archived`, `hidden`.
- Temporära operativa lägen: `rented`, `reserved`, `service`, `broken`.
- Tillgänglighet ska ändå beräknas från bokningar, kalenderblockeringar och serviceperioder. Status får inte ensam avgöra kalendern.

Affärsregler:

- Ett objekt får hyras ut när det är aktivt, uthyrningsbart, inte arkiverat, inte soft delete:at, har aktiv primär kategori, har giltigt pris och inte är blockerat av bokning, kalender, service eller trasig-status.
- Ett objekt får inte hyras ut när det är inaktivt, dolt för publik bokning, under service, trasigt, arkiverat, soft delete:at eller saknar kravdata för bokning.
- Arkivering ska sätta status/flagga och normalt även hindra nya bokningar, men ska inte radera historik.
- Soft delete används endast när objektet ska tas bort ur normala flöden. Historiska bokningar, avtal, service och audit ska fortfarande kunna förstå objektet.
- Bokningar och avtal bör snapshotta namn, pris, moms, deposition och viktiga villkor eftersom objektdata kan ändras senare.

SEO:

- Publik URL bör baseras på organisationens publika scope och objektets slug, till exempel `/objekt/<slug>` i Version 1 och senare eventuell organisationsdel för marknadsplats.
- `slug` ska kunna ändras kontrollerat, men redirect-historik byggs senare.
- `canonical_url`, `seo_title`, `seo_description` och Open Graph-fält väntar tills SEO-sprint.
- Version 1 behöver en stabil slug och publik detaljvy, men inte full SEO-modul.

Risker:

- Om prisfält läggs direkt på objektet kan historiska prisändringar bli svåra att spåra.
- Om status används som ersättning för kalender uppstår risk för dubbelbokningar.
- Om objekt kopplas direkt till användare i stället för organisation blir marknadsplatsen svårare att införa.
- Om media lagras direkt på objektet blir dokument, huvudbild och framtida bildvarianter svårare att återanvända.
- Om fordon specialmodelleras för tidigt kan Version 1 bli onödigt tung. Fordonsunika krav bör vänta till separat sprint.

Öppna frågor innan första objektsmigration:

- Ska `public_id` vara slumpmässig kod, UUID/ULID-liknande sträng eller prefixad inventariekod?
- Ska `slug` vara unikt per organisation eller globalt i Version 1 när bara en uthyrare finns?
- Ska dagspris vara obligatoriskt från start eller får objekt sparas som utkast utan pris?
- Ska deposition vara obligatorisk, valfri eller standardstyrd per kategori/organisation?
- Ska `item_rates` byggas samtidigt som `rental_items` eller i separat pris-sprint?
- Vilka statusar ska vara seed-data i första objektsimplementationen?
- Ska plats ligga direkt på objektet i Version 1 eller alltid i `item_locations`?
- Vilka dokumenttyper är obligatoriska för vissa maskiner, till exempel CE-intyg eller besiktningsprotokoll?

### Kategorier

Tabeller:

- `item_categories`
- `item_category_relations`

Syfte:

- Hantera kategorier som Verktyg, Maskiner, Släp, Trädgård, Bygg och Övrigt.
- Stödja publik objektlista, filtrering och framtida SEO.
- Stödja hierarki senare utan att bygga ett tungt CMS.
- Stödja både globala plattformskategorier och organisationsspecifika kategorier.

Rekommenderad modell:

- `item_categories` innehåller kategoriidentiteten.
- `organization_id` är nullable.
- `organization_id = NULL` betyder global plattformskategori.
- `organization_id` med värde betyder kategori som ägs av en organisation.
- Version 1 använder globala standardkategorier och tillåter organisationsspecifika kategorier i admin när adminfunktionen byggs.
- `parent_id` ska finnas i modellen för framtida underkategorier, men Version 1 ska visa kategorier som en enkel nivå.
- `name` är visningsnamn.
- `slug` används för publik filtrering och framtida SEO.
- `description` är valfri.
- `sort_order` styr visningsordning.
- `status_key` beskriver om kategorin är aktiv, inaktiv eller arkiverad.
- `icon_key` kan användas för en enkel ikon i UI.
- `media_asset_id` kan senare peka på en bild i mediabiblioteket.
- `seo_title` och `seo_description` kan förberedas som valfria SEO-fält.
- `created_at`, `updated_at` och `deleted_at` ska finnas.
- Inga ENUM ska användas för status.
- Kategorier ska normalt arkiveras eller soft delete:as, inte hårdraderas.

Rekommenderad koppling till objekt:

- `item_category_relations` kopplar objekt till kategori.
- Varje objekt ska ha exakt en primär kategori i Version 1.
- Relationstabellen ska förbereda flera kategorier per objekt senare.
- Fält som `is_primary` och `sort_order` kan användas för att skilja primär kategori från framtida sekundära kategorier.
- Databasen bör förhindra dubbla relationer mellan samma objekt och kategori.
- Exakt en primär kategori per objekt kan behöva säkras med applikationsregel om MySQL/MariaDB-versionen inte ger en enkel portabel unik constraint för detta.

Relationer:

- `item_categories.organization_id` refererar `organizations.id` när kategorin är organisationsspecifik.
- `item_categories.parent_id` refererar `item_categories.id`.
- Global kategori får ha global förälder.
- Organisationsspecifik kategori får ha global förälder eller förälder inom samma organisation.
- Organisationsspecifik kategori får inte ha förälder i en annan organisation.
- `item_category_relations.rental_item_id` refererar `rental_items.id`.
- `item_category_relations.item_category_id` refererar `item_categories.id`.
- Viktig historik ska bevaras; hård delete ska därför undvikas när objekt redan använder kategorin.

Unika fält och index:

- `slug` ska vara unik inom sitt kategoriscope.
- Global kategori ska ha unik slug bland globala kategorier.
- Organisationsspecifik kategori ska ha unik slug inom samma organisation.
- `name` behöver inte vara tekniskt unikt, men admin bör varna vid snarlika namn inom samma scope.
- Index behövs för `organization_id`, `parent_id`, `slug`, `status_key` och `sort_order`.
- Relationstabellen behöver index för `rental_item_id` och `item_category_id`.
- Relationstabellen behöver unik constraint för kombinationen `rental_item_id` och `item_category_id`.

Framtida utbyggnad:

- Underkategorier i admin och publik filtrering.
- SEO-routes baserade på slug.
- Kategoriunika attribut.
- Marketplace-filter.
- Översättningar av kategorinamn och SEO-fält.
- Redirect-hantering när slug ändras.
- Bildhantering via mediabiblioteket.

Alternativ som valts bort:

- Endast globala kategorier: enkelt i Version 1 men begränsar framtida marknadsplats och organisationsunika nischer.
- Endast organisationsspecifika kategorier: flexibelt men riskerar duplicerade baskategorier och sämre publik SEO.
- Direkt `category_id` på `rental_items`: enkelt men gör framtida flera kategorier dyrare att införa.
- Separat `category_images`: inte motiverat i Version 1; mediabiblioteket bör återanvändas när bildbehovet finns.

Risker:

- Hybridmodellen kräver tydliga regler för slug-unicitet när `organization_id` är `NULL`.
- Parent-regler måste valideras så att kategorier inte kopplas över fel organisation.
- Kategoriunika attribut kan snabbt bli komplext. Vänta med dynamiska attribut tills verkliga behov finns.
- Om slug ändras efter publicering behövs framtida redirect-strategi för SEO.

### Bokningar

Tabeller:

- `bookings`
- `booking_items`
- `booking_status_history`
- `booking_customer_snapshots`
- `booking_price_snapshots`
- `booking_notes`

Syfte:

- Hantera bokningsförfrågan, manuell godkännandeprocess och bokningshistorik.
- Stödja flera objekt per bokning om det behövs.
- Bevara pris och kunddata som gällde vid bokning.

Relationer:

- Bokning tillhör organisation.
- Bokning kopplas till kund och eventuellt företag.
- Bokning har en eller flera bokningsrader.
- Bokningsrader kopplas till objekt.
- Bokning har statushistorik.

Framtida utbyggnad:

- Automatisk bekräftelse.
- Deposition.
- Delbetalning.
- Leverans och hämtning.
- Avbokningsregler.

Senior rekommendation: använd `booking_items` även om Version 1 oftast bokar ett objekt. Det gör senare paketbokningar och flera objekt per order enklare.

### Kalender

Tabeller:

- `availability_rules`
- `availability_exceptions`
- `calendar_events`
- `blocked_periods`

Syfte:

- Visa när objekt är lediga, bokade, blockerade eller på service.
- Förhindra överlappande bokningar.
- Stödja manuell blockering av objekt.

Relationer:

- Kalenderposter kopplas till objekt.
- Bokningar skapar kalenderhändelser.
- Service och besiktning kan skapa blockerade perioder.

Framtida utbyggnad:

- Synk med externa kalendrar.
- Återkommande regler.
- Resursplanering för leverans.
- Offline-PWA-cache av tillgänglighet.

Risk: överlappande datumintervall är ett klassiskt felområde. Definiera tidzon, heldag/del av dag och hämtning/återlämning innan migrationer skrivs.

### Service

Tabeller:

- `service_records`
- `service_tasks`
- `service_intervals`
- `service_parts`
- `service_providers`
- `service_history`

Syfte:

- Stödja underhåll och servicehistorik.
- Planera service efter datum, användning eller manuellt behov.
- Blockera objekt vid service.

Relationer:

- Serviceposter kopplas till objekt.
- Service kan kopplas till leverantör, dokument och bilder.
- Service kan skapa kalenderblockering.

Framtida utbyggnad:

- UH-modul.
- Reservdelar och kostnader.
- Automatiska servicepåminnelser.
- IoT-baserad service.

### Besiktningar

Tabeller:

- `inspection_templates`
- `inspection_checkpoints`
- `inspections`
- `inspection_results`
- `inspection_media`
- `inspection_history`

Syfte:

- Dokumentera skick vid utlämning, återlämning och intern kontroll.
- Minska tvister.
- Koppla bilder och kommentarer till checklistor.

Relationer:

- Besiktning kopplas till objekt.
- Besiktning kan kopplas till bokning.
- Resultat kopplas till checkpoints.
- Media kopplas till besiktning.

Framtida utbyggnad:

- Digital signering av skick.
- AI-stöd för bildjämförelse.
- QR-flöde vid utlämning.

Senior rekommendation: besiktning bör inte bara vara fria anteckningar. Checklistor ger jämförbar historik.

### Dokument

Tabeller:

- `documents`
- `document_types`
- `document_versions`
- `document_links`
- `agreement_templates`
- `agreements`
- `agreement_status_history`

Syfte:

- Hantera avtal, villkor, uppladdade dokument, serviceunderlag och kunddokument.
- Förbereda digital signering utan BankID-integration.

Relationer:

- Avtal kopplas till bokning.
- Dokument kan länkas till objekt, kund, företag, service eller besiktning.
- Dokumentversioner behåller historik när mall eller innehåll ändras.

Framtida utbyggnad:

- BankID-signering.
- Extern dokumentlagring.
- PDF-generering.
- Versionsstyrda avtalsmallar.

Risk: en generisk `document_links` ger flexibilitet men svagare foreign keys. För kritiska dokument, som avtal, ska det finnas tydliga domänrelationer.

### Media

Tabeller:

- `media_assets`
- `media_variants`
- `item_media`
- `inspection_media`
- `service_media`
- `document_media`

Syfte:

- Hantera bilder och filer separat från affärsobjekt.
- Stödja flera användningsområden utan duplicerad filinformation.

Relationer:

- Media ägs av organisation.
- Objekt, besiktningar, service och dokument länkar till media via länktabeller.

Framtida utbyggnad:

- Bildvarianter.
- CDN.
- AI-bildanalys.
- Filskanning.
- PWA offline-cache.

Senior rekommendation: spara filmetadata i databasen men inte själva filen. Databasen ska peka på lagringsplats, checksumma och ägarskap.

### Betalningar

Tabeller:

- `payment_methods`
- `payments`
- `payment_status_history`
- `invoice_drafts`
- `financial_accounts`

Syfte:

- Förbereda betalstatus, fakturastatus och manuell uppföljning.
- Inte bygga Swish eller Fortnox ännu.

Relationer:

- Betalningar kopplas till bokning och organisation.
- Fakturautkast kan kopplas till kund, företag och bokning.
- Statushistorik visar betalningsflöde.

Framtida utbyggnad:

- Swish-transaktioner.
- Fortnox-fakturor.
- Provision.
- Utbetalningar till externa uthyrare.
- Momsrapporter.

Risk: ekonomi blir snabbt juridiskt och bokföringsmässigt känsligt. Version 1 bör bara förbereda modellen och hantera manuell status tills Fortnox/Swish är specificerat.

### Loggning

Tabeller:

- `audit_events`
- `system_logs`
- `security_events`
- `integration_logs`

Syfte:

- Separera affärsaudit, tekniska fel och säkerhetshändelser.
- Ge spårbarhet utan att logga hemligheter.

Relationer:

- Loggar kan kopplas till organisation, användare och relevant affärsobjekt.
- Integrationsloggar kopplas senare till integrationstyp.

Framtida utbyggnad:

- Centraliserad loggtjänst.
- BI-analys.
- Säkerhetsövervakning.
- API-rate-limit-loggning.

### Administration

Tabeller:

- `admin_notes`
- `admin_tasks`
- `notification_templates`
- `notifications`
- `feature_flags`

Syfte:

- Stödja intern administration, notiser och framtida funktionsstyrning.
- Ge plattformen kontroll utan att hårdkoda allt.

Relationer:

- Adminanteckningar kan kopplas till objekt, kund, bokning eller organisation.
- Notiser kopplas till mottagare och mall.
- Feature flags kan vara globala eller organisationsspecifika.

Framtida utbyggnad:

- Moderation för marknadsplats.
- Intern supportvy.
- Automatiska påminnelser.
- Rollstyrd adminpanel.

## 11. Samlad tabellöversikt per område

Plattform:

- `organizations`
- `organization_settings`
- `system_settings`
- `status_groups`
- `status_definitions`

Användare:

- `users`
- `user_profiles`
- `user_external_identities`
- `roles`
- `permissions`
- `role_permissions`
- `user_roles`
- `organization_users`
- `user_sessions`
- `password_reset_tokens`

Kunder och företag:

- `customers`
- `customer_contacts`
- `companies`
- `company_users`
- `company_contacts`
- `customer_users`
- `customer_addresses`
- `customer_notes`

Objekt och kategorier:

- `rental_items`
- `item_categories`
- `item_category_relations`
- `item_locations`
- `item_rates`
- `item_status_history`
- `item_condition_reports`
- `item_accessories`
- `item_documents`
- `item_media`

Bokningar och kalender:

- `bookings`
- `booking_items`
- `booking_status_history`
- `booking_customer_snapshots`
- `booking_price_snapshots`
- `booking_notes`
- `availability_rules`
- `availability_exceptions`
- `calendar_events`
- `blocked_periods`

Service och besiktning:

- `service_records`
- `service_tasks`
- `service_intervals`
- `service_parts`
- `service_providers`
- `service_history`
- `inspection_templates`
- `inspection_checkpoints`
- `inspections`
- `inspection_results`
- `inspection_media`
- `inspection_history`

Dokument och media:

- `documents`
- `document_types`
- `document_versions`
- `document_links`
- `agreement_templates`
- `agreements`
- `agreement_status_history`
- `media_assets`
- `media_variants`
- `item_media`
- `service_media`
- `document_media`

Betalning och ekonomi:

- `payment_methods`
- `payments`
- `payment_status_history`
- `invoice_drafts`
- `financial_accounts`

Loggning och administration:

- `audit_events`
- `system_logs`
- `security_events`
- `integration_logs`
- `admin_notes`
- `admin_tasks`
- `notification_templates`
- `notifications`
- `feature_flags`

## 12. ER-diagram i textform

```text
Organizations
 |
 +--- OrganizationUsers --- Users
 |                         |
 |                         +--- UserRoles --- Roles --- RolePermissions --- Permissions
 |                         |
 |                         +--- UserProfiles
 |                         +--- UserExternalIdentities
 |
 +--- RentalItems --- ItemCategories
 |        |
 |        +--- ItemRates
 |        +--- ItemStatusHistory
 |        +--- ItemConditionReports
 |        +--- ItemMedia --- MediaAssets
 |        +--- ServiceRecords --- ServiceHistory
 |        +--- Inspections --- InspectionResults
 |        +--- CalendarEvents
 |
 +--- Customers
 |        |
 |        +--- CustomerContacts
 |        +--- CustomerUsers --- Users
 |        +--- CustomerAddresses
 |        +--- Companies --- CompanyContacts
 |                      |
 |                      +--- CompanyUsers --- Users
 |
 +--- Bookings --- BookingItems --- RentalItems
 |        |
 |        +--- BookingStatusHistory
 |        +--- BookingCustomerSnapshots
 |        +--- BookingPriceSnapshots
 |        +--- Agreements --- AgreementStatusHistory
 |        +--- Payments --- PaymentStatusHistory
 |
 +--- Documents --- DocumentVersions
 |        |
 |        +--- DocumentLinks
 |
 +--- AuditEvents
 +--- SystemLogs
 +--- SecurityEvents
 +--- OrganizationSettings

StatusGroups
 |
 +--- StatusDefinitions

AgreementTemplates
 |
 +--- Agreements

DocumentTypes
 |
 +--- Documents
```

## 13. Risker

### Multi-tenant i efterhand

Om organisationstillhörighet inte byggs in i kärntabeller från början blir marknadsplatsen dyr att införa. Rekommendation: ha `organization_id` i centrala affärstabeller redan i Version 1.

### Bokningsöverlapp

Kalender och datumintervall är riskfyllda. Hämtning, återlämning, heldag, halvdagsuthyrning och tidszon måste beslutas innan första migrationen.

### Kunddata och GDPR

Kunder, användare och kontakter kan leda till duplicerad persondata. Det behövs tydliga regler för anonymisering, retention och ansvar.

### Identitet och rollscope

Om roller inte har tydligt scope kan en användare få för bred behörighet mellan organisationer. Systemroller, organisationsroller och kundportalroller måste skiljas åt innan första användarrelaterade migrationen skrivs.

### Externa identiteter

BankID och andra externa identiteter får inte bli hårdkodade primärnycklar. Det behövs beslut om vilka externa identifierare som får lagras, hur de skyddas och hur de kopplas till användare.

### Dokumentrelationer

Generiska dokumentlänkar är flexibla men kan försvaga dataintegritet. Kritiska dokument som avtal bör ha tydliga relationer.

### Statusmodell

För generiska statusar kan bli svåra att förstå. För hårdkodade statusar blir svåra att ändra. Projektet måste hitta balans med statusgrupper och dokumenterade statusflöden.

### Ekonomi

Betalning, faktura, moms och provision är juridiskt känsliga. Modellen bör förberedas men inte byggas detaljerat innan Fortnox/Swish-krav finns.

### Media och uppladdningar

Bilder och dokument kräver säker filhantering, metadata, åtkomstkontroll och eventuell viruskontroll. Databasen ska inte lagra filerna som blobbar i första hand.

### För tidig generisk modell

Det är lockande att bygga en extremt flexibel modell för framtida AI, IoT, API och marketplace. Det kan göra Version 1 svår att färdigställa. Håll kärnan konkret.

## 14. Förslag innan databasen byggs

1. Besluta att `organizations` införs från första migrationen, även om bara en uthyrare finns.
2. Besluta om bokning kan innehålla flera objekt i Version 1 eller om modellen bara ska förbereda detta.
3. Besluta om kunder kan boka utan användarkonto.
4. Besluta hur datum och tid ska hanteras: heldagar, tider, tidszon och buffert mellan uthyrningar.
5. Besluta vilken data som måste snapshotas vid bokning och avtal.
6. Besluta om statusar ska vara helt konfigurerbara eller om vissa statusflöden ska vara kodstyrda.
7. Besluta var filer ska lagras fysiskt innan media- och dokumenttabeller byggs.
8. Besluta om `examples/` och starter-Drizzle-strukturen ska tas bort eller hållas separerad innan riktig PHP/MySQL-databas införs.
9. Besluta om databasdesignen ska dokumenteras som ERD i separat diagramformat senare.
10. Besluta om versionerade migrationer ska ägas av PHP-projektet, Drizzle eller annat verktyg. Detta dokument tar inte beslut om migrationsverktyg.

## 15. Frågor innan första migrationen

### Plattform och organisation

- Vad heter Version 1-uthyraren i systemet?
- Ska plattformen själv vara en organisation?
- Behövs flera interna användare redan i Version 1?
- Ska organisation kunna ha egna villkor, logotyp och kontaktuppgifter?

### Användare och kunder

- Ska kunder skapa konto i Version 1 eller bara skicka bokningsförfrågan?
- Ska e-post vara globalt unik eller unik per organisation?
- Ska företag och privatpersoner hanteras i samma kundflöde?
- Vilka roller behövs i Version 1?
- Vilka behörigheter behöver vara finmaskiga från start?
- Ska `users.password_hash` vara obligatoriskt, eller ska externa identiteter kunna skapa användare utan lokalt lösenord?
- Ska rolltilldelningar ligga direkt i `user_roles` med `organization_id`, eller via `organization_users`?
- Ska en användare kunna agera för flera företag redan i Version 1, eller endast förberedas via modellen?
- Ska privatperson som kund kopplas direkt till `users`, eller via `customer_users` när kundkonto införs?
- Vilka identitetsrelaterade händelser måste audit-loggas från första implementationen?

### Objekt

- Ska sekundära kategorier aktiveras i Version 1 eller vänta till marknadsplats/filter-sprint?
- Behövs objektvarianter eller räcker en rad per fysisk utrustning?
- Ska tillbehör hyras separat eller bara följa med ett objekt?
- Ska pris kunna variera över tid?
- Behövs deposition i Version 1?

### Kategorier

- Ska standardkategorierna vara exakt Verktyg, Maskiner, Släp, Trädgård, Bygg och Övrigt vid första seedning?
- Ska organisationsspecifika kategorier kunna publiceras publikt direkt eller kräva separat godkännande senare?
- Vilken ikonlista ska `icon_key` få använda i admin?
- Ska kategori-URL i Version 1 innehålla organisationens slug eller bara kategorins slug?
- Hur många nivåer av underkategorier ska tillåtas när hierarki aktiveras?
- När ska redirect-hantering byggas för ändrade kategori-slugs?

### Bokningar och kalender

- Är uthyrning alltid per dag eller kan tid på dagen behövas?
- Hur hanteras hämtning och återlämning?
- Ska bokningsförfrågan kunna omfatta flera objekt?
- Vilka bokningsstatusar behövs i Version 1?
- Ska objekt blockeras direkt vid förfrågan eller först vid godkännande?

### Avtal och dokument

- Vilka avtal behövs i Version 1?
- Ska avtal genereras från mall eller laddas upp manuellt?
- Behövs versionshistorik för avtalsmallar från start?
- Vilka dokumenttyper måste vara privata?

### Service och besiktning

- Vilka objekt kräver servicehistorik i Version 1?
- Ska besiktning krävas vid varje utlämning och återlämning?
- Behövs standardchecklistor per kategori?
- Ska service blockera kalender automatiskt?

### Betalning och ekonomi

- Ska betalning hanteras helt manuellt i Version 1?
- Behövs betalstatus även innan Swish/Fortnox?
- Behövs fakturautkast eller räcker interna noteringar?
- Ska moms och pris inklusive/exklusive moms lagras från start?

### Loggning och audit

- Vilka händelser måste audit-loggas från dag ett?
- Hur länge ska audit trail sparas?
- Ska tekniska loggar lagras i databasen eller i fil/loggtjänst?
- Vilken persondata får loggas?

### Framtid

- Vilka Version 2-funktioner är mest sannolika först: BankID, Swish, Fortnox eller flera uthyrare?
- Ska API byggas för intern frontend först eller externa partners?
- Finns krav på PWA/offline redan i Version 1-planeringen?
- Behövs BI/export från början eller först när bokningsvolym finns?
