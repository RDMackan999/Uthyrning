
---

## `docs/WIREFRAMES.md`

```markdown
# WIREFRAMES.md

# Wireframes

## Syfte

Detta dokument beskriver enkla skärmskisser för systemets viktigaste vyer.

Detta är inte färdig design.

Wireframes används för att förstå:

- layout
- navigering
- innehåll
- knappar
- formulär
- användarflöden

---

# 1. Publik startsida

```text
+--------------------------------------------------+
| Logo             Hem Objekt Så fungerar Kontakt  |
|                         Logga in  Skapa konto    |
+--------------------------------------------------+
|                                                  |
|  Hyr verktyg och maskiner enkelt                 |
|  tryggt och nära dig                             |
|                                                  |
|  [Vad vill du hyra?] [Ort] [Datum] [Sök]          |
|                                                  |
|  [Hitta objekt] [Lägg upp objekt]                |
|                                                  |
+--------------------------------------------------+
| Kategorier                                       |
| [Verktyg] [Maskiner] [Släp] [Trädgård] [Bygg]    |
+--------------------------------------------------+
| Populära objekt                                  |
| [Kort] [Kort] [Kort]                             |
+--------------------------------------------------+
| Så fungerar det                                  |
| 1. Sök  2. Boka  3. Hämta                        |
+--------------------------------------------------+
| FAQ                                              |
+--------------------------------------------------+
| Footer                                           |
+--------------------------------------------------+
# 2. Objektlista

```text
+--------------------------------------------------+
| Header                                           |
+--------------------------------------------------+
| Sök och filtrera                                 |
| [Sökfält] [Kategori] [Ort] [Datum från/till]      |
+----------------------+---------------------------+
| Filter               | Objektresultat            |
|                      |                           |
| Kategori             | [Objektkort]              |
| Pris                 | [Objektkort]              |
| Ort                  | [Objektkort]              |
| Tillgänglighet       | [Objektkort]              |
+----------------------+---------------------------+

# 3. Objektkort

```text
+-----------------------------+
| Bild                        |
+-----------------------------+
| Namn                        |
| Ort                         |
| Pris: 250 kr/dag            |
| Status: Tillgänglig         |
|                             |
| [Visa objekt]               |
+-----------------------------+

# 4. Objektdetalj

```text
+--------------------------------------------------+
| Header                                           |
+--------------------------------------------------+
| Bildgalleri              | Namn                  |
|                          | Pris per dag          |
|                          | Ort                   |
|                          | Status                |
|                          | [Boka]                |
+--------------------------------------------------+
| Beskrivning                                      |
+--------------------------------------------------+
| Kalender / tillgänglighet                        |
+--------------------------------------------------+
| Villkor                                          |
+--------------------------------------------------+
| Dokument                                         |
+--------------------------------------------------+

# 5. Bokningsförfrågan

```text
+--------------------------------------------------+
| Boka objekt                                      |
+--------------------------------------------------+
| Objekt: Skruvdragare Bosch                       |
|                                                  |
| Datum från: [____]                               |
| Datum till: [____]                               |
|                                                  |
| Namn: [________________]                         |
| E-post: [________________]                       |
| Telefon: [________________]                      |
|                                                  |
| Kommentar:                                       |
| [________________________________________]       |
|                                                  |
| [Skicka bokningsförfrågan]                       |
+--------------------------------------------------+

# 6. Bekräftelse

```text
+--------------------------------------------------+
| Bokningsförfrågan mottagen                       |
+--------------------------------------------------+
| Tack! Din förfrågan har skickats.                |
| Vi återkommer med besked.                        |
|                                                  |
| [Till startsidan] [Visa fler objekt]             |
+--------------------------------------------------+

# 7. Login

```text
+--------------------------------------------------+
| Logga in                                         |
+--------------------------------------------------+
| E-post                                           |
| [________________]                               |
|                                                  |
| Lösenord                                         |
| [________________]                               |
|                                                  |
| [Logga in]                                       |
|                                                  |
| Framtid: Logga in med BankID                     |
+--------------------------------------------------+

# 8. Admin Dashboard

```text
+--------------------------------------------------+
| Admin                                           |
+--------------------------------------------------+
| Sidebar              | Dashboard                 |
|                      |                           |
| Dashboard            | Dagens bokningar          |
| Objekt               | Nya förfrågningar         |
| Bokningar            | Objekt i service          |
| Kunder               | Senaste händelser         |
| Service              |                           |
| Dokument             |                           |
| Inställningar        |                           |
+----------------------+---------------------------+

# 9. Admin Objektlista

```text

+--------------------------------------------------+
| Admin > Objekt                                   |
+--------------------------------------------------+
| [Nytt objekt]                                    |
|                                                  |
| Sök: [________________]                          |
|                                                  |
| Tabell:                                          |
| Namn | Kategori | Pris | Status | Åtgärder       |
|--------------------------------------------------|
| ...                                              |
+--------------------------------------------------+

# 10. Admin Skapa / Redigera objekt

```text
+--------------------------------------------------+
| Nytt objekt                                      |
+--------------------------------------------------+
| Namn                                             |
| [________________]                               |
|                                                  |
| Kategori                                         |
| [Välj kategori]                                  |
|                                                  |
| Pris per dag                                     |
| [________________]                               |
|                                                  |
| Plats                                            |
| [________________]                               |
|                                                  |
| Beskrivning                                      |
| [________________________________________]       |
|                                                  |
| Bilder                                           |
| [Ladda upp]                                      |
|                                                  |
| Status                                           |
| [Aktiv]                                          |
|                                                  |
| [Spara] [Avbryt]                                 |
+--------------------------------------------------+

# 11. Admin Bokningar

```text
+--------------------------------------------------+
| Admin > Bokningar                                |
+--------------------------------------------------+
| Filter: [Status] [Datum] [Objekt]                |
|                                                  |
| Bokningslista                                    |
| Kund | Objekt | Datum | Status | Åtgärder        |
|--------------------------------------------------|
| ...                                              |
+--------------------------------------------------+

# 12. Admin Bokningsdetalj

```text
+--------------------------------------------------+
| Bokning #123                                     |
+--------------------------------------------------+
| Kund                                             |
| Objekt                                           |
| Datum                                            |
| Status                                           |
| Pris                                             |
|                                                  |
| [Godkänn] [Neka] [Avboka]                        |
+--------------------------------------------------+
| Avtal                                            |
| [Skapa avtal]                                    |
+--------------------------------------------------+
| Historik                                         |
+--------------------------------------------------+

# 13. Admin kunder

```text
+--------------------------------------------------+
| Admin > Kunder                                   |
+--------------------------------------------------+
| Sök kund                                         |
| [________________]                               |
|                                                  |
| Namn | E-post | Telefon | Bokningar | Åtgärder   |
+--------------------------------------------------+

# 14. Kunddetalj

```text
Admin Bokningsdetalj
+--------------------------------------------------+
| Kund                                             |
+--------------------------------------------------+
| Namn                                             |
| E-post                                           |
| Telefon                                          |
| Adress                                           |
+--------------------------------------------------+
| Bokningshistorik                                 |
+--------------------------------------------------+
| Avtal                                            |
+--------------------------------------------------+
| Noteringar                                       |
+--------------------------------------------------+
# 15. Servicevy

```text
+--------------------------------------------------+
| Service                                          |
+--------------------------------------------------+
| Objekt                                           |
| Senaste service                                  |
| Nästa service                                    |
| Senaste besiktning                               |
| Nästa besiktning                                 |
|                                                  |
| [Registrera service] [Registrera besiktning]     |
+--------------------------------------------------+
