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

### Kunder och företag

Tabeller:

- `customers`
- `customer_contacts`
- `companies`
- `company_contacts`
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
- Objekt har kategori.
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

### Kategorier

Tabeller:

- `item_categories`
- `item_category_relations`

Syfte:

- Hantera kategorier som Verktyg, Maskiner, Släp, Trädgård, Bygg och Övrigt.
- Stödja hierarki senare utan att bygga ett tungt CMS.

Relationer:

- Objekt kopplas till kategori.
- Kategorier kan ha förälder/barn-relation.

Framtida utbyggnad:

- SEO-slugs.
- Kategoriunika attribut.
- Marketplace-filter.

Risk: kategoriunika attribut kan snabbt bli komplext. Vänta med dynamiska attribut tills verkliga behov finns.

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
- `company_contacts`
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
 |        +--- CustomerAddresses
 |        +--- Companies --- CompanyContacts
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

### Objekt

- Kan ett objekt tillhöra flera kategorier?
- Behövs objektvarianter eller räcker en rad per fysisk utrustning?
- Ska tillbehör hyras separat eller bara följa med ett objekt?
- Ska pris kunna variera över tid?
- Behövs deposition i Version 1?

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
