# ER_DIAGRAM.md

# ER-diagram

## Syfte

Detta dokument beskriver databasens huvudrelationer visuellt i textform.

Detta är inte SQL och inte en migration.

Dokumentet används som ritning innan databasen implementeras.

---

# Övergripande modell

```text
users
  ├── user_roles
  │     └── roles
  │           └── role_permissions
  │                 └── permissions
  │
  ├── customers
  ├── companies
  ├── bookings
  ├── documents
  └── audit_logs


objects
  ├── categories
  ├── object_statuses
  ├── object_images
  ├── object_documents
  ├── object_properties
  ├── bookings
  ├── maintenance_records
  ├── inspections
  └── damage_reports


bookings
  ├── booking_statuses
  ├── users / customers
  ├── objects
  ├── contracts
  ├── payments
  ├── handovers
  ├── returns
  └── audit_logs
