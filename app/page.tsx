"use client";

import { FormEvent, useState } from "react";

const categories = [
  { name: "Verktyg", detail: "Borrar, sågar och handverktyg", icon: "VR" },
  { name: "Maskiner", detail: "Grävare, liftar och packmaskiner", icon: "MA" },
  { name: "Släp", detail: "Släpvagnar och transport", icon: "SL" },
  { name: "Trädgård", detail: "Klippare, trimmers och jordfräsar", icon: "TR" },
  { name: "Bygg", detail: "Ställning, kap, blandare och laser", icon: "BY" },
  { name: "Övrigt", detail: "Utrustning för tillfälliga behov", icon: "OV" },
];

const steps = [
  {
    title: "Sök och välj objekt",
    text: "Hitta rätt verktyg eller maskin efter kategori, plats och planerat datum.",
  },
  {
    title: "Boka lediga datum",
    text: "Se kommande kalenderläge, välj hyresperiod och få tydliga prisvillkor.",
  },
  {
    title: "Signera och hämta",
    text: "Digitalt avtal förbereds. Hämta på plats eller välj leverans när det erbjuds.",
  },
];

const trustItems = [
  "BankID-inloggning som förberedd funktion",
  "Digital avtalssignering inför varje uthyrning",
  "Tydliga villkor för pris, deposition och ansvar",
  "Dokumenterat skick vid utlämning och återlämning",
  "Omdömen och betyg för tryggare val",
  "Säker betalning via Swish eller faktura som kommande flöde",
];

const listings = [
  {
    name: "Skruvdragare",
    location: "Stockholm",
    price: "129 kr/dag",
    rating: "4,9",
    tag: "VR",
  },
  {
    name: "Minigrävare",
    location: "Västerås",
    price: "1 490 kr/dag",
    rating: "4,8",
    tag: "MG",
  },
  {
    name: "Släpvagn",
    location: "Uppsala",
    price: "349 kr/dag",
    rating: "4,7",
    tag: "SL",
  },
  {
    name: "Byggställning",
    location: "Norrköping",
    price: "590 kr/dag",
    rating: "4,9",
    tag: "BY",
  },
  {
    name: "Gräsklippare",
    location: "Malmö",
    price: "249 kr/dag",
    rating: "4,6",
    tag: "TR",
  },
  {
    name: "Markvibrator",
    location: "Göteborg",
    price: "420 kr/dag",
    rating: "4,8",
    tag: "MA",
  },
];

const faqs = [
  {
    question: "Hur bokar jag?",
    answer:
      "Sök efter det du behöver, välj ett objekt och markera önskade datum. I första versionen leder bokningen till kontaktförfrågan, och senare kopplas kalender och avtal på direkt i flödet.",
  },
  {
    question: "Hur fungerar betalning?",
    answer:
      "Swish och faktura visas som förberedda betalningssätt. Inga externa betalningsintegrationer är aktiva ännu.",
  },
  {
    question: "Behöver jag BankID?",
    answer:
      "BankID är planerat för trygg inloggning och verifiering. I den här första versionen är det en kommande funktion.",
  },
  {
    question: "Kan företag hyra?",
    answer:
      "Ja. Plattformen är tänkt för privatpersoner, företag, byggare, fastighetsägare och lantbrukare som vill hyra i stället för att köpa.",
  },
  {
    question: "Kan jag hyra ut egna saker?",
    answer:
      "Ja, det är en del av nästa steg. Du kan redan anmäla intresse som uthyrare för att få information när marknadsplatsläget öppnar.",
  },
];

export default function Home() {
  const [menuOpen, setMenuOpen] = useState(false);
  const [openFaq, setOpenFaq] = useState(0);
  const [searchMessage, setSearchMessage] = useState("");

  function handleSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSearchMessage(
      "Sökfunktionen är förberedd. Nästa steg är att koppla formuläret till PHP/MySQL och kalenderdata.",
    );
  }

  return (
    <main className="site-shell" id="hem">
      <header className="site-header">
        <a className="brand" href="#hem" aria-label="Uthyrning startsida">
          <span className="brand-mark">U</span>
          <span>Uthyrning</span>
        </a>

        <nav className="desktop-nav" aria-label="Huvudmeny">
          <a href="#hem">Hem</a>
          <a href="#objekt">Hyr objekt</a>
          <a href="#sa-fungerar-det">Så fungerar det</a>
          <a href="#uthyrare">För uthyrare</a>
          <a href="#kontakt">Kontakt</a>
        </nav>

        <div className="header-actions">
          <a className="login-link" href="#kontakt">
            Logga in
          </a>
          <a className="button button-primary button-small" href="#kontakt">
            Skapa konto
          </a>
        </div>

        <button
          className="menu-button"
          type="button"
          aria-label={menuOpen ? "Stäng meny" : "Öppna meny"}
          aria-expanded={menuOpen}
          onClick={() => setMenuOpen((isOpen) => !isOpen)}
        >
          <span></span>
          <span></span>
          <span></span>
        </button>
      </header>

      <nav
        className={menuOpen ? "mobile-nav mobile-nav-open" : "mobile-nav"}
        aria-label="Mobil meny"
      >
        <a href="#hem" onClick={() => setMenuOpen(false)}>
          Hem
        </a>
        <a href="#objekt" onClick={() => setMenuOpen(false)}>
          Hyr objekt
        </a>
        <a href="#sa-fungerar-det" onClick={() => setMenuOpen(false)}>
          Så fungerar det
        </a>
        <a href="#uthyrare" onClick={() => setMenuOpen(false)}>
          För uthyrare
        </a>
        <a href="#kontakt" onClick={() => setMenuOpen(false)}>
          Kontakt
        </a>
      </nav>

      <section className="hero-section" aria-labelledby="hero-title">
        <div className="hero-copy">
          <p className="eyebrow">Verktyg, maskiner och utrustning nära dig</p>
          <h1 id="hero-title">
            Hyr verktyg och maskiner enkelt, tryggt och nära dig
          </h1>
          <p className="hero-text">
            Boka verktyg, maskiner, släp och utrustning per dag. Se
            tillgänglighet i kalendern, signera avtal digitalt och välj Swish
            eller faktura.
          </p>
          <div className="hero-actions">
            <a className="button button-primary" href="#objekt">
              Hitta objekt
            </a>
            <a className="button button-secondary" href="#uthyrare">
              Lägg upp objekt
            </a>
          </div>
          <div className="feature-strip" aria-label="Förberedda funktioner">
            <span>BankID förbereds</span>
            <span>Swish förbereds</span>
            <span>Fortnox förbereds</span>
          </div>
        </div>

        <div className="hero-media">
          <img
          src="/uthyrning-hero.png"
          alt="Verktyg, byggutrustning och släpvagn redo för uthyrning"
/>
          <div className="hero-status" aria-label="Exempel på tillgänglighet">
            <strong>Ledigt i veckan</strong>
            <span>Kalenderstöd förbereds</span>
          </div>
        </div>
      </section>

      <section className="search-section" aria-label="Sök uthyrningsobjekt">
        <form className="search-form" onSubmit={handleSearch}>
          <label>
            <span>Vad vill du hyra?</span>
            <input type="search" placeholder="Ex. släpvagn, minigrävare" />
          </label>
          <label>
            <span>Ort eller postnummer</span>
            <input type="text" placeholder="Ex. Uppsala" />
          </label>
          <label>
            <span>Från</span>
            <input type="date" />
          </label>
          <label>
            <span>Till</span>
            <input type="date" />
          </label>
          <button className="button button-primary search-button" type="submit">
            Sök
          </button>
        </form>
        <p className="form-status" aria-live="polite">
          {searchMessage}
        </p>
      </section>

      <section className="section" id="objekt" aria-labelledby="categories-title">
        <div className="section-heading">
          <p className="eyebrow">Hyr det du behöver</p>
          <h2 id="categories-title">Kategorier för vardag, bygge och gård</h2>
          <p>
            Plattformen är formad för både privatpersoner och företag, från
            småverktyg till maskiner som bara behövs några dagar.
          </p>
        </div>
        <div className="category-grid">
          {categories.map((category) => (
            <a className="category-card" href="#exempelobjekt" key={category.name}>
              <span className="category-icon" aria-hidden="true">
                {category.icon}
              </span>
              <strong>{category.name}</strong>
              <p>{category.detail}</p>
            </a>
          ))}
        </div>
      </section>

      <section
        className="section split-section"
        id="sa-fungerar-det"
        aria-labelledby="steps-title"
      >
        <div className="section-heading compact-heading">
          <p className="eyebrow">Så fungerar det</p>
          <h2 id="steps-title">Från behov till bokning på tre steg</h2>
          <p>
            Flödet är enkelt redan i första versionen och byggt för att senare
            kunna kopplas till kalender, digital signering och betalning.
          </p>
        </div>
        <div className="steps-grid">
          {steps.map((step, index) => (
            <article className="step-card" key={step.title}>
              <span>{index + 1}</span>
              <h3>{step.title}</h3>
              <p>{step.text}</p>
            </article>
          ))}
        </div>
      </section>

      <section className="trust-section" aria-labelledby="trust-title">
        <div>
          <p className="eyebrow">Trygghet först</p>
          <h2 id="trust-title">Seriös uthyrning med tydliga villkor</h2>
          <p>
            Uthyrning ska kännas tryggt för både den som hyr och den som hyr
            ut. Därför synliggörs verifiering, avtal, skick och betalning
            redan från start.
          </p>
        </div>
        <ul className="trust-list">
          {trustItems.map((item) => (
            <li key={item}>{item}</li>
          ))}
        </ul>
      </section>

      <section className="owner-section" id="uthyrare" aria-labelledby="owner-title">
        <div>
          <p className="eyebrow">För uthyrare</p>
          <h2 id="owner-title">Gör oanvänd utrustning till intäkt</h2>
          <p>
            Har du verktyg eller maskiner som står oanvända? I framtiden kan du
            hyra ut dem via plattformen och tjäna pengar när andra använder
            dem.
          </p>
        </div>
        <a className="button button-primary" href="#kontakt">
          Anmäl intresse som uthyrare
        </a>
      </section>

      <section
        className="section"
        id="exempelobjekt"
        aria-labelledby="listings-title"
      >
        <div className="section-heading">
          <p className="eyebrow">Exempelobjekt</p>
          <h2 id="listings-title">Populära objekt att hyra per dag</h2>
          <p>
            Objektkorten är redo att senare kopplas mot databas, tillgänglighet,
            omdömen och en riktig detaljsida.
          </p>
        </div>
        <div className="listing-grid">
          {listings.map((listing) => (
            <article className="listing-card" key={listing.name}>
              <div className="listing-media" aria-hidden="true">
                <span>{listing.tag}</span>
              </div>
              <div className="listing-body">
                <div>
                  <h3>{listing.name}</h3>
                  <p>{listing.location}</p>
                </div>
                <div className="listing-meta">
                  <strong>{listing.price}</strong>
                  <span>Betyg {listing.rating}/5</span>
                </div>
                <a className="button button-secondary button-full" href="#kontakt">
                  Visa objekt
                </a>
              </div>
            </article>
          ))}
        </div>
      </section>

      <section className="faq-section" aria-labelledby="faq-title">
        <div className="section-heading compact-heading">
          <p className="eyebrow">FAQ</p>
          <h2 id="faq-title">Vanliga frågor</h2>
        </div>
        <div className="faq-list">
          {faqs.map((faq, index) => {
            const isOpen = openFaq === index;
            return (
              <article className="faq-item" key={faq.question}>
                <button
                  type="button"
                  aria-expanded={isOpen}
                  onClick={() => setOpenFaq(isOpen ? -1 : index)}
                >
                  <span>{faq.question}</span>
                  <span aria-hidden="true">{isOpen ? "-" : "+"}</span>
                </button>
                <p hidden={!isOpen}>{faq.answer}</p>
              </article>
            );
          })}
        </div>
      </section>

      <footer className="site-footer" id="kontakt">
        <div>
          <a className="brand footer-brand" href="#hem">
            <span className="brand-mark">U</span>
            <span>Uthyrning</span>
          </a>
          <p>
            En svensk plattform för trygg uthyrning av verktyg, maskiner, släp
            och utrustning.
          </p>
        </div>
        <div className="footer-links" aria-label="Footerlänkar">
          <a href="mailto:kontakt@uthyrning.example">Kontakt</a>
          <a href="#kontakt">Villkor</a>
          <a href="#kontakt">Integritetspolicy</a>
          <a href="#uthyrare">För uthyrare</a>
        </div>
        <div className="footer-links social-links" aria-label="Sociala länkar">
          <a href="#kontakt">LinkedIn</a>
          <a href="#kontakt">Instagram</a>
          <a href="#kontakt">Facebook</a>
        </div>
      </footer>
    </main>
  );
}
