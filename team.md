<article class="iss-team-card">
  <figure class="iss-team-card__media">
    <img src="/wp-content/uploads/team/person.jpg" alt="Name der Person">
  </figure>

  <div class="iss-team-card__body">
    <p class="iss-team-card__kicker">Team</p>
    <h3 class="iss-team-card__name">Vorname Nachname</h3>
    <p class="iss-team-card__position">Position / Aufgabe</p>
    <p class="iss-team-card__text">
      Kurzer, ruhiger Text zur Rolle im Industriesalon. Zwei bis drei Sätze reichen.
    </p>
  </div>
</article>

/* Team card: calm inset portrait card, no rail */
.iss-team-card {
  display: grid;
  grid-template-rows: minmax(18rem, 48vh) auto;
  overflow: hidden;
  height: 100%;
  background: #fff;
  border: 1px solid rgba(30, 30, 30, 0.1);
  border-radius: var(--iss-radius-md, 16px);
  box-shadow: 0 10px 28px rgba(30, 30, 30, 0.055);
}

.iss-team-card__media {
  margin: 0;
  min-height: 0;
  background: var(--iss-grey, #c0b8b5);
}

.iss-team-card__media img {
  display: block;
  width: 100%;
  height: 100%;
  min-height: 18rem;
  object-fit: cover;
}

.iss-team-card__body {
  padding: clamp(1.1rem, 2vw, 1.45rem);
}

.iss-team-card__kicker {
  margin: 0 0 0.55rem;
  color: rgba(30, 30, 30, 0.58);
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.13em;
  line-height: 1;
  text-transform: uppercase;
}

.iss-team-card__name {
  margin: 0;
  font-size: clamp(1.35rem, 1rem + 0.8vw, 1.9rem);
  line-height: 1.08;
  letter-spacing: -0.02em;
}

.iss-team-card__position {
  margin: 0.45rem 0 0;
  color: var(--iss-red, #e81d25);
  font-size: 0.95rem;
  font-weight: 600;
  line-height: 1.35;
}

.iss-team-card__text {
  margin: 0.95rem 0 0;
  color: rgba(30, 30, 30, 0.72);
  font-size: 0.98rem;
  line-height: 1.55;
}

@media (max-width: 782px) {
  .iss-team-card {
    grid-template-rows: minmax(16rem, 58vw) auto;
  }
}