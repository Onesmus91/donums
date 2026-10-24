<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Jobs | Donums Consultants</title>
  <meta name="description" content="Explore jobs, roles and career opportunities with Donums Consultants. Find work across HR, operations, finance, sales, tech and leadership roles.">
  <meta name="theme-color" content="#0F1111">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    :root {
      --green: #45d6b5;
      --green-deep: #1bb79a;
      --red: #e63a2f;
      --charcoal: #111111;
      --ink: #171717;
      --muted: #53615c;
      --line: #e6e2dc;
      --off: #f7f4f1;
      --card: #ffffff;
      --shadow: 0 18px 40px rgba(17,17,17,.08);
    }

    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body {
      margin: 0;
      font-family: "Montserrat", sans-serif;
      background: var(--off);
      color: var(--ink);
      line-height: 1.6;
    }
    a { text-decoration: none; color: inherit; }
    img { display: block; max-width: 100%; }
    button, input, select { font: inherit; }
    .wrap { width: min(1180px, calc(100% - 30px)); margin: 0 auto; }

    .brand {
      display: flex;
      align-items: center;
      gap: 14px;
      min-width: 260px;
    }
    .brand-mark {
      width: 54px;
      height: 54px;
      border-radius: 50% 50% 46% 50%;
      background: linear-gradient(135deg, var(--green), #82ebd9);
      color: var(--charcoal);
      display: grid;
      place-items: center;
      font-weight: 900;
      font-size: 26px;
      box-shadow: inset 0 0 0 3px rgba(17,17,17,0.08);
      transform: rotate(-7deg);
    }
    .brand-word {
      font-size: 32px;
      line-height: .9;
      letter-spacing: -0.08em;
      font-weight: 900;
      text-transform: uppercase;
      color: var(--charcoal);
    }
    .brand-word .sub {
      display: block;
      color: var(--red);
      font-size: 18px;
      letter-spacing: -0.03em;
      margin-top: 4px;
      text-transform: uppercase;
    }

    .site-header {
      position: sticky;
      top: 0;
      z-index: 50;
      background: rgba(247, 244, 241, 0.88);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid rgba(17,17,17,0.05);
    }
    .header-inner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      min-height: 84px;
      gap: 20px;
    }
    .main-nav {
      display: flex;
      align-items: center;
      gap: 24px;
      color: var(--muted);
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.12em;
      text-transform: uppercase;
    }
    .main-nav a { position: relative; }
    .main-nav a::after {
      content: "";
      position: absolute;
      left: 0;
      bottom: -9px;
      width: 100%;
      height: 2px;
      background: var(--red);
      transform: scaleX(0);
      transition: transform 0.2s ease;
    }
    .main-nav a:hover::after, .main-nav a:focus-visible::after { transform: scaleX(1); }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 44px;
      padding: 0 18px;
      border-radius: 999px;
      border: 1px solid transparent;
      font-weight: 700;
      font-size: 13px;
      letter-spacing: 0.02em;
      transition: transform 0.2s ease;
    }
    .btn:hover { transform: translateY(-1px); }
    .btn-primary {
      background: linear-gradient(135deg, var(--green), var(--green-deep));
      color: var(--charcoal);
      box-shadow: var(--shadow);
    }
    .btn-secondary {
      background: var(--red);
      color: white;
    }
    .btn-ghost {
      background: transparent;
      border-color: var(--line);
      color: var(--charcoal);
    }

    .page-hero {
      padding: 54px 0 28px;
      background: linear-gradient(180deg, #f6f5f2 0%, #edf9f5 100%);
    }
    .hero-row {
      display: grid;
      grid-template-columns: 1.2fr 0.8fr;
      gap: 28px;
      align-items: end;
    }
    .eyebrow {
      margin: 0 0 14px;
      color: var(--red);
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.14em;
      text-transform: uppercase;
    }
    h1 {
      margin: 0 0 14px;
      font-size: clamp(2.5rem, 4vw, 4.2rem);
      letter-spacing: -0.08em;
      line-height: 0.94;
    }
    .lead {
      margin: 0;
      max-width: 620px;
      font-size: 16px;
      color: var(--muted);
    }

    .hero-metric {
      background: linear-gradient(135deg, var(--charcoal), #1a2d2a);
      color: white;
      border-radius: 24px;
      padding: 24px 22px;
      box-shadow: var(--shadow);
    }
    .hero-metric span {
      color: rgba(255,255,255,0.72);
      font-size: 11px;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      font-weight: 700;
    }
    .hero-metric strong {
      display: block;
      margin: 14px 0 6px;
      font-size: 42px;
      letter-spacing: -0.08em;
    }
    .hero-metric small {
      color: rgba(255,255,255,0.78);
      font-size: 13px;
    }

    .toolbar {
      background: white;
      border: 1px solid var(--line);
      border-radius: 20px;
      box-shadow: 0 10px 18px rgba(17,17,17,0.02);
      padding: 18px;
      margin-top: 28px;
    }
    .toolbar-inner {
      display: grid;
      grid-template-columns: repeat(5, minmax(0, 1fr));
      gap: 14px;
    }
    .filter {
      display: grid;
      gap: 8px;
    }
    .filter label {
      font-size: 11px;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--muted);
      font-weight: 800;
    }
    .filter input,
    .filter select {
      width: 100%;
      min-height: 46px;
      border-radius: 12px;
      border: 1px solid var(--line);
      background: #fbfaf8;
      padding: 0 12px;
      color: var(--ink);
      font: inherit;
    }
    .toolbar .btn {
      align-self: end;
      min-height: 46px;
    }

    .jobs-area {
      padding: 40px 0 80px;
    }
    .jobs-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }
    .job-card {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 22px;
      padding: 22px 20px;
      box-shadow: 0 10px 18px rgba(17,17,17,0.02);
    }
    .job-top {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: center;
      margin-bottom: 10px;
    }
    .tag {
      display: inline-flex;
      align-items: center;
      min-height: 28px;
      padding: 0 10px;
      border-radius: 999px;
      background: rgba(69,214,181,0.12);
      border: 1px solid rgba(69,214,181,0.2);
      color: var(--charcoal);
      font-size: 10px;
      font-weight: 800;
      letter-spacing: 0.12em;
      text-transform: uppercase;
    }
    .job-card h3 {
      margin: 0 0 8px;
      font-size: 26px;
      letter-spacing: -0.06em;
    }
    .company {
      margin: 0 0 14px;
      color: var(--muted);
      font-weight: 700;
    }
    .details {
      display: flex;
      flex-wrap: wrap;
      gap: 10px 16px;
      color: var(--muted);
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      margin-bottom: 16px;
    }
    .desc {
      margin: 0 0 18px;
      color: var(--muted);
      line-height: 1.7;
    }
    .job-bottom {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding-top: 14px;
      border-top: 1px solid var(--line);
    }
    .salary {
      font-weight: 800;
      letter-spacing: -0.04em;
      font-size: 20px;
      color: var(--charcoal);
    }
    .job-meta {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      color: var(--muted);
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }

    .sidebar {
      display: grid;
      gap: 18px;
    }
    .side-card {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 22px;
      padding: 22px 20px;
      box-shadow: 0 10px 18px rgba(17,17,17,0.02);
    }
    .side-card h3 {
      margin: 0 0 14px;
      font-size: 20px;
      letter-spacing: -0.05em;
    }
    .list {
      list-style: none;
      padding: 0;
      margin: 0;
      display: grid;
      gap: 12px;
      color: var(--muted);
    }
    .list li { display: flex; align-items: center; gap: 10px; }
    .list li::before {
      content: "✓";
      color: var(--red);
      font-weight: 800;
    }

    .site-footer {
      background: var(--charcoal);
      color: rgba(255,255,255,0.8);
      padding: 52px 0 26px;
    }
    .footer-grid {
      display: grid;
      grid-template-columns: 1.4fr .8fr .8fr 1fr;
      gap: 24px;
    }
    .site-footer h4 {
      margin: 0 0 12px;
      color: white;
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.12em;
      text-transform: uppercase;
    }
    .site-footer ul {
      list-style: none;
      padding: 0;
      margin: 0;
      display: grid;
      gap: 10px;
      color: rgba(255,255,255,0.72);
    }
    .site-footer a { color: rgba(255,255,255,0.8); }
    .footer-bottom {
      margin-top: 24px;
      padding-top: 18px;
      border-top: 1px solid rgba(255,255,255,0.12);
      display: flex;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
      color: rgba(255,255,255,0.7);
      font-size: 12px;
    }

    @media (max-width: 980px) {
      .main-nav { display: none; }
      .hero-row, .jobs-grid, .footer-grid, .toolbar-inner { grid-template-columns: 1fr; }
      .job-top, .job-bottom { align-items: flex-start; flex-direction: column; }
    }

    @media (max-width: 640px) {
      .brand-word { font-size: 26px; }
      .brand-word .sub { font-size: 14px; }
      h1 { font-size: 2.5rem; }
      .job-card h3 { font-size: 22px; }
    }
  </style>
</head>
<body>
  <header class="site-header">
    <div class="wrap header-inner">
      <div class="brand" aria-label="Donums Consultants home">
        <div class="brand-mark">D</div>
        <div class="brand-word">
          Donums
          <span class="sub">Consultants</span>
        </div>
      </div>

      <nav class="main-nav" aria-label="Main navigation">
        <a href="/jobs.php">Jobs</a>
        <a href="/employers.php">Employers</a>
        <a href="/recruiters.php">Recruiters</a>
        <a href="/learning.php">Learning</a>
        <a href="/hr-services.php">HR Services</a>
        <a href="/contact.php">Contact</a>
      </nav>

      <a class="btn btn-ghost" href="/contact.php">Talk to us</a>
    </div>
  </header>

  <main>
    <section class="page-hero">
      <div class="wrap hero-row">
        <div>
          <p class="eyebrow">Career opportunities</p>
          <h1>Find the right role for your next move.</h1>
          <p class="lead">Work with employers, recruiters and a global network of HR and business leaders who value practical talent, performance and growth.</p>
        </div>

        <div class="hero-metric">
          <span>Live roles</span>
          <strong>2,400+</strong>
          <small>career and hiring opportunities across operations, finance, leadership, sales and specialist support.</small>
        </div>
      </div>

      <div class="wrap toolbar">
        <div class="toolbar-inner">
          <div class="filter">
            <label for="keyword">Keyword</label>
            <input id="keyword" type="text" placeholder="Job title, skill, company">
          </div>
          <div class="filter">
            <label for="category">Category</label>
            <select id="category">
              <option>All roles</option>
              <option>Executive</option>
              <option>Operations</option>
              <option>HR</option>
              <option>Finance</option>
              <option>Technology</option>
              <option>Sales</option>
            </select>
          </div>
          <div class="filter">
            <label for="location">Location</label>
            <select id="location">
              <option>Anywhere</option>
              <option>Nairobi</option>
              <option>Kenya</option>
              <option>Remote</option>
              <option>Regional</option>
              <option>International</option>
            </select>
          </div>
          <div class="filter">
            <label for="type">Type</label>
            <select id="type">
              <option>Any</option>
              <option>Full time</option>
              <option>Contract</option>
              <option>Hybrid</option>
              <option>Remote</option>
            </select>
          </div>
          <button class="btn btn-primary" type="button">Search roles</button>
        </div>
      </div>
    </section>

    <section class="jobs-area">
      <div class="wrap jobs-grid">
        <div>
          <article class="job-card">
            <div class="job-top">
              <span class="tag">Executive</span>
              <span class="job-meta">2 days ago</span>
            </div>
            <h3>Head of Human Resources</h3>
            <p class="company">Northstar Group</p>
            <div class="details">
              <span>Nairobi</span>
              <span>Full time</span>
              <span>Hybrid</span>
            </div>
            <p class="desc">Lead people strategy, performance frameworks and organisational design for a rapidly expanding business operating across East Africa.</p>
            <div class="job-bottom">
              <div class="salary">KSh 220,000+</div>
              <a class="btn btn-secondary" href="/contact.php">Apply now</a>
            </div>
          </article>

          <article class="job-card">
            <div class="job-top">
              <span class="tag">Operations</span>
              <span class="job-meta">4 days ago</span>
            </div>
            <h3>Operations Manager</h3>
            <p class="company">Greenside Logistics</p>
            <div class="details">
              <span>Mombasa</span>
              <span>Full time</span>
              <span>On-site</span>
            </div>
            <p class="desc">Own operational execution, team performance and process improvement as the business scales its regional distribution footprint.</p>
            <div class="job-bottom">
              <div class="salary">KSh 180,000+</div>
              <a class="btn btn-secondary" href="/contact.php">Apply now</a>
            </div>
          </article>

          <article class="job-card">
            <div class="job-top">
              <span class="tag">Finance</span>
              <span class="job-meta">1 week ago</span>
            </div>
            <h3>Senior Finance Officer</h3>
            <p class="company">Asterline Capital</p>
            <div class="details">
              <span>Remote</span>
              <span>Contract</span>
              <span>Regional</span>
            </div>
            <p class="desc">Support reporting, budgeting and stakeholder analysis for a growing regional finance function with a strong compliance focus.</p>
            <div class="job-bottom">
              <div class="salary">KSh 160,000+</div>
              <a class="btn btn-secondary" href="/contact.php">Apply now</a>
            </div>
          </article>

          <article class="job-card">
            <div class="job-top">
              <span class="tag">Technology</span>
              <span class="job-meta">2 weeks ago</span>
            </div>
            <h3>Product Operations Lead</h3>
            <p class="company">Nexora Studio</p>
            <div class="details">
              <span>Nairobi</span>
              <span>Hybrid</span>
              <span>Full time</span>
            </div>
            <p class="desc">Drive product operations, delivery planning and team coordination across engineering, customer and product teams.</p>
            <div class="job-bottom">
              <div class="salary">KSh 210,000+</div>
              <a class="btn btn-secondary" href="/contact.php">Apply now</a>
            </div>
          </article>
        </div>

        <aside class="sidebar">
          <div class="side-card">
            <h3>Why candidates choose Donums</h3>
            <ul class="list">
              <li>Roles from employers and recruiters who value clarity</li>
              <li>Career guidance and role matching support</li>
              <li>Professional recruitment process and clear communication</li>
              <li>Access to growth-focused roles across sectors</li>
            </ul>
          </div>

          <div class="side-card">
            <h3>Popular categories</h3>
            <ul class="list">
              <li>Executive and leadership</li>
              <li>HR and people operations</li>
              <li>Finance and compliance</li>
              <li>Sales and business development</li>
              <li>Technology and digital operations</li>
            </ul>
          </div>

          <div class="side-card">
            <h3>Need hiring support?</h3>
            <p style="margin:0 0 18px; color: var(--muted);">We help employers hire well, faster and with more confidence.</p>
            <a class="btn btn-primary" href="/employers.php">Hire talent</a>
          </div>
        </aside>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="wrap footer-grid">
      <div>
        <div class="brand" aria-label="Donums Consultants footer brand" style="color:white;">
          <div class="brand-mark">D</div>
          <div class="brand-word" style="color:white; font-size:26px;">
            Donums
            <span class="sub" style="color:var(--red); font-size:15px;">Consultants</span>
          </div>
        </div>
      </div>

      <div>
        <h4>Explore</h4>
        <ul>
          <li><a href="/jobs.php">Jobs</a></li>
          <li><a href="/employers.php">Employers</a></li>
          <li><a href="/recruiters.php">Recruiters</a></li>
          <li><a href="/learning.php">Learning</a></li>
        </ul>
      </div>

      <div>
        <h4>Services</h4>
        <ul>
          <li><a href="/hr-services.php">Recruitment</a></li>
          <li><a href="/hr-services.php">HR Advisory</a></li>
          <li><a href="/hr-services.php">Payroll</a></li>
          <li><a href="/hr-services.php">Learning</a></li>
        </ul>
      </div>

      <div>
        <h4>Contact</h4>
        <ul>
          <li><a href="mailto:info@donums.co.ke">info@donums.co.ke</a></li>
          <li><a href="tel:+254106026970">+254 106 026 970</a></li>
          <li>Kiambu County, Nairobi</li>
        </ul>
      </div>
    </div>

    <div class="wrap footer-bottom">
      <span>© 2026 Donums Consultants</span>
      <span>Built for better talent and better teams.</span>
    </div>
  </footer>
</body>
</html>
