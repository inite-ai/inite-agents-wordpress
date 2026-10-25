# INITE Agents

> **Read-only mirror.** This is the source of the INITE Agents plugin, mirrored from INITE's private monorepo on every change. Download the installable zip from [Releases](https://github.com/inite-ai/inite-agents-wordpress/releases) or [inite.ai](https://inite.ai/downloads/inite-agents.zip). Issues and questions go to [inite-ai/inite-agents](https://github.com/inite-ai/inite-agents/issues); pull requests here are read and ported by hand. Security reports: security@inite.ai.

| | |
|---|---|
| Contributors | inite |
| Tags | ai, llms.txt, mcp, agents, schema |
| Requires at least | 6.0 |
| Tested up to | 7.1 |
| Requires PHP | 7.4 |
| Stable tag | 0.10.1 |
| License | GPLv2 or later |
| License URI | https://www.gnu.org/licenses/gpl-2.0.html |

Hands AI agents the facts your business confirmed in INITE: llms.txt, agent card and MCP files, robots.txt signals, head tags, abilities, articles.

## Description

INITE Agents connects your WordPress site to [INITE](https://inite.ai/), where you confirm the facts about your business that AI agents may use: what you do, your services and prices, opening hours, locations and answers to common questions. Nothing you did not confirm is published.

The plugin keeps a copy of what INITE generated from those facts on your site and serves it from there:

* **Root files.** `/llms.txt`, `/llms-full.txt`, `/.well-known/agent-card.json` (A2A agent card), `/.well-known/mcp.json` (where your MCP server is) and `/.well-known/api-catalog`.
* **robots.txt.** A `Content-Signal` line in the `User-agent: *` group: search and answers may use your content, training may not.
* **Page head.** A small WebMCP script, shipped with the plugin, that offers the same facts as tools to an agent working in a visitor's browser, and link tags pointing agents to the files.
* **Abilities for the WordPress MCP Adapter.** On WordPress 6.9 or later, five read-only abilities (`inite/business-facts`, `inite/services`, `inite/hours`, `inite/locations`, `inite/faq`). With the official [MCP Adapter](https://wordpress.org/plugins/mcp-adapter/) plugin active, an agent connected to your site can list and call them.
* **Daily report** (off until you switch it on). Which paths the plugin serves, how many times known AI agents read them per day, and how many visits came from AI assistants (ChatGPT, Perplexity, Gemini, Copilot, Claude and others) per day and page. Counts only: no address, no cookie.
* **Requests from agents** (only when you choose "To the WordPress plugin" in the INITE cabinet). An AI agent can ask your business to get in touch, or to book, through your MCP server or the WebMCP script; the requests appear under **Agent requests** in the WordPress menu, where you mark each one answered or spam.
* **Articles** (off until you switch it on). INITE drafts articles for your site from what people actually search for, and you approve each one in the INITE cabinet. The plugin turns every approved article into a WordPress post, as a draft for you to publish or published at once, as you choose, with its meta description for Yoast SEO or Rank Math. When the post is published, its address goes back to INITE. An article becomes one post, however often the plugin syncs. Nothing you did not approve is written.

Each feature has its own switch. A feature that is off claims no URL and prints nothing.

**What this site sends to INITE.** A second list of switches, all off until you turn them on, says exactly what leaves your site and what INITE does with it. Each is content your site already shows to anyone; nothing about a person.

* **Site basics:** the title, tagline, language and address, and the WooCommerce product count. Suggested in the cabinet as your business's name and description.
* **Pages and posts:** title, address, excerpt or first 300 characters, type, language, last change, for published pages and posts without a password and not marked noindex. Listed in llms.txt and llms-full.txt. Up to 2,000.
* **Products** (WooCommerce): name, address, price, regular and sale price, currency, stock status, SKU, short description, categories, image address. Published only after you turn on "Publish my catalogue" in the cabinet; then agents read your products as your store holds them, in llms.txt and through the MCP tools `products` and `product`. Up to 2,000.
* **Events** (The Events Calendar): upcoming events with their title, address, start, end and venue. Listed in llms.txt. Up to 200.
* **Services from booking plugins** (Bookly, Amelia): the services shown to customers, with duration and price. Suggestions in the cabinet.
* **Locations and hours** (WP Store Locator, Yoast Local SEO, Rank Math Local SEO): addresses, phones and opening hours. Suggestions in the cabinet.

Only what changed is sent, a hundred items at a time, by WP-Cron every hour. Switch a kind off and INITE deletes what it received of it.

**What you get back, inside WordPress.** A dashboard widget and an **Overview** tab under **Settings → INITE Agents**: the AI visibility score of your site's latest INITE analysis and the checks it failed, what AI assistants say about your business against the facts you confirmed (once INITE has checked), which AI agents read which of your pages, how many people came from AI assistants and to which pages, and the suggestions and articles waiting for you in INITE. Each line links to where it is acted on. Breakdowns INITE keeps for Atlas Premium arrive as totals without it; the counts the plugin keeps itself are always shown in full.

**Fixes.** A **Fixes** tab switches on, one by one, what the analysis looks for and WordPress can add, each with a preview of exactly what it adds:

* JSON-LD on the front page from your confirmed facts: the business (Organization or LocalBusiness), FAQPage, and a Product for each service sold per item. With Yoast SEO or Rank Math active, ours goes into their schema graph: their values are kept, only what they lack is added, and no type is printed twice.
* An AI crawler policy in robots.txt. Nothing is blocked unless you tick it: training crawlers, Google-Extended (its own box: blocking it removes the site from the Gemini app; AI Overviews are not affected), AI search crawlers and answer fetchers. Assistants also answer from what they were trained on, so the recommended policy leaves them all open.
* A meta description on the front page, while no SEO plugin writes one.
* A check that robots.txt names your sitemap, and the line if it does not.

**IndexNow** (off until you switch it on). When a public page, post or product is published, changed or removed, the plugin tells Bing, Yandex and the other search engines that share IndexNow a minute later, instead of waiting for their next crawl. Google does not use IndexNow. It stays quiet when another plugin (Bing's IndexNow plugin, Yoast SEO, Rank Math, SEOPress, All in One SEO) already sends IndexNow for the site.

**"How did you hear about us?"** (off until you switch it on). An optional question with AI assistants among the answers (ChatGPT, Perplexity, Gemini, Claude, Grok, Alice, GigaChat, another assistant) next to search, social media, friends and other: at the WooCommerce checkout, in Contact Form 7 with the `[inite_how_heard]` tag, in WPForms in a dropdown with the CSS class `inite-how-heard`, or anywhere with the `[inite_how_heard]` shortcode. The plugin keeps a count per day and answer and nothing else; the Overview shows the share that named an AI assistant, which analytics cannot see when an assistant's app sends no referrer.

The two JSON-LD types earlier versions printed stay as they were; every other fix is off until you switch it on.

The plugin works around what is already there:

* A physical file at one of the paths (for example an `llms.txt` you uploaded) is left alone.
* An SEO plugin that serves its own llms.txt (Yoast SEO, Rank Math, All in One SEO, SEOPress PRO, Website LLMs.txt) keeps it while that feature is on in it.
* With Yoast SEO or Rank Math, the JSON-LD goes into their graph; with All in One SEO, or SEOPress with its knowledge graph on, our business node is left out.
* With a physical robots.txt, the settings screen shows the line to paste.

The settings screen says what was left alone and why.

The plugin never slows a page down for the service: the copy is fetched by WP-Cron every hour, when you save the settings and when you press "Test connection", with a five-second limit. If INITE cannot be reached, the last good copy keeps being served.

## Installation

1. Upload the plugin through **Plugins → Add New → Upload Plugin**, or unzip it into `wp-content/plugins/`, and activate it.
2. In your INITE cabinet, open the "For agents" tab, confirm your facts and make a site key for WordPress.
3. In WordPress, open **Settings → INITE Agents → Settings**, paste the key and save.
4. Press **Test connection**. The screen shows the facts version and the files now served.

For the MCP abilities, also install and activate the MCP Adapter plugin (WordPress 6.9 or later).

## Frequently Asked Questions

### Does it need an INITE account?

Yes. The facts are confirmed in the INITE cabinet, and the site key comes from there.

### Are visits from AI assistants tracked?

Counted, not tracked, and only while "Daily report" is on. A page view counts when the browser says it came from an assistant's site (for example chatgpt.com or perplexity.ai) or the link carries `utm_source=chatgpt.com`. The plugin keeps the day, the page's path without its query, the assistant and a number, for 30 days. No IP address, no cookie, no user agent, nothing that tells one person from another. Visits served from a full-page cache never reach WordPress, so the numbers are a floor.

### Does INITE read my site?

Yes, from its own servers, not through the plugin: once a day, up to eight public pages (the homepage and the pages about prices, services, contact, hours, FAQ or menu), obeying your robots.txt. What it finds that your facts do not say appears in the INITE cabinet as a suggestion to accept or ignore. Nothing changes in your published facts unless you confirm it, or turn on "Keep in step with my site" in the cabinet for values your site states in its schema.org markup.

### Does INITE open my pages as an AI robot?

Once a week, from its own servers, INITE opens up to five of your pages (the homepage, prices, contact or sign-up, and the two pages agents and assistants visit most) with the names of the robots behind Bing, ChatGPT, Claude, Perplexity and Yandex, and once without a robot's name, and reads what robots.txt tells each of them. The Overview shows, per robot, whether it got the page, a refusal, a captcha, an empty page or a page that needs JavaScript. A refusal for our request is a strong hint, not proof: firewalls also check the robot's addresses, so confirm in Bing Webmaster Tools or your server logs before changing anything.

### What does the plugin send to INITE?

The site key and the plugin's version with every request, and nothing else until you switch it on under "What this site sends to INITE". Each switch names the fields it sends. Nothing about users, customers, orders or visitors is ever read or sent. Switching a kind off makes INITE delete what it received of it, at the plugin's next run.

### Are my products published as soon as I send them?

No. They appear in your INITE cabinet first. Only when the company's owner turns on "Publish my catalogue" there are they published, and from then on they follow your store: a new price, a product out of stock or removed reaches agents within the hour, without a confirmation each time.

### What happens if INITE is down?

Nothing visible. The site serves the copy it already stored, and tries again an hour later.

### What happens when I revoke the key or switch the connector off in INITE?

At its next sync the plugin is told so, drops the stored copy and stops serving the files, the head tags and the abilities.

### Does it change my permalinks?

No. The files are answered before WordPress parses the request, without rewrite rules, so there is nothing to flush and nothing left behind when the plugin is switched off.

### My WordPress is installed in a subdirectory.

Then WordPress does not see requests to the domain root, where `/llms.txt` and `/.well-known/` must live. The settings screen says so. INITE can serve those files on an `agents.` subdomain of yours instead.

### Who can call the abilities?

The MCP Adapter only lets authenticated WordPress users connect. Once connected, the abilities need no extra capability, because they return only what your business published.

### How do articles reach my site?

Switch on **Articles** in **Settings → INITE Agents** and choose whether new articles are saved as drafts or published at once. On each hourly sync the plugin asks INITE for the articles you approved and not yet published on this site, and makes a post for each. If a post cannot be made, INITE is told why, and the article waits in your cabinet until you send it again.

### Will articles appear without my approval?

No. Only articles you approved in the INITE cabinet are sent, exactly as you approved them. With "Save as drafts" nothing is public until you press Publish in WordPress.

### How is the plugin updated?

The copy downloaded from inite.ai checks https://inite.ai/downloads/inite-agents.json when WordPress checks for updates, at most twice a day, and the update appears on the Plugins screen like any other. Automatic updates follow your WordPress setting for this plugin. The check sends no key and nothing about your site.

### Can I point the plugin at a staging server?

Define `INITE_AGENTS_API` in `wp-config.php` (for example `define( 'INITE_AGENTS_API', 'https://staging.example' );`) or use the `inite_agents_api_base` filter. The site key is only ever sent to that address.

## External services

This plugin talks to INITE (https://inite.ai), the service where you confirm your business facts. It is the plugin's purpose: without the service there is nothing to serve.

What is sent, when, and where:

* **Sync** (every hour, when the settings are saved, when "Test connection" is pressed, and on activation): a request to `https://inite.ai/api/site/v1/manifest` and `https://inite.ai/api/site/v1/files/{name}` with your site key, the ETag of the copy held, and a user agent naming the plugin and WordPress versions. A request to `https://inite.ai/api/site/v1/public/{slug}` for the public facts, without the key.
* **Site basics, services and locations** (off by default; with the hourly sync, only when they changed, and at most once a day otherwise): a request to `https://inite.ai/api/site/v1/ping` with your site key, the plugin version and a `site` field. With "Site basics" on it holds your site's title and tagline (Settings → General), its language and address, and, if WooCommerce is active, the number of published products. With "Services from booking plugins" on, the services Bookly or Amelia show to customers (name, duration, description, price, currency). With "Locations and hours" on, the addresses, phones and opening hours of WP Store Locator, Yoast Local SEO or Rank Math Local SEO. INITE shows them in your cabinet as suggestions; nothing is published until you accept and confirm it. No user, no email address, nothing about visitors.
* **Pages, posts, products and events** (each off by default, each under its own switch; every hour, only what changed): requests to `https://inite.ai/api/site/v1/records` with your site key and up to 100 items at a time. Pages and posts: title, address, excerpt or first 300 characters, type, language and when it changed. Products (WooCommerce): name, address, price, regular and sale price, currency, stock status, SKU, short description, categories, image address. Events (The Events Calendar): title, address, excerpt, start, end, venue. Once a day the ids still published are sent, so INITE drops what was removed; when you switch a kind off, a request tells INITE to delete it. INITE lists pages, posts and events in your llms.txt and llms-full.txt, and publishes products only after you turn on "Publish my catalogue" in the cabinet. Nothing about users, customers or orders.
* **Daily report** (once a day, only after you switch "Daily report" on; it is off by default): a request to `https://inite.ai/api/site/v1/ping` with your site key, the plugin and WordPress versions, the paths the plugin serves, for each of the last seven days the number of times each known AI agent (such as GPTBot, ClaudeBot or PerplexityBot) read those paths, and the number of visits per page that came from an AI assistant (the `referrals` field: day, path, assistant, count). No IP address, no full user agent, no cookie, nothing that identifies a visitor.
* **How did you hear about us** (only while both the question and the daily report are on): the daily report's `survey` field holds, for each of the last seven days, how many people chose each answer (day, answer, count). Not who, not their order, not their message. With the question on and the report off, the counts stay on your site.
* **Summary** (for the dashboard widget and the Overview tab, at most every four hours on the hourly sync, when the Overview finds nothing held, and when you press "Refresh"): a request to `https://inite.ai/api/site/v1/summary` with your site key. It sends nothing else and reads what INITE knows about the site: the latest analysis's score and failed checks, counts of agent reads, MCP calls and visits from AI assistants, and how many suggestions and articles wait for you.
* **Requests from agents** (only while you send them to the plugin in the INITE cabinet): opening **Agent requests** asks `https://inite.ai/api/site/v1/requests` with your site key for the list, which is not stored on your site; marking a request answered or spam sends that status to `https://inite.ai/api/site/v1/requests/{id}`.
* **In your visitors' browsers** (only while "Page head" is on): the WebMCP script is part of the plugin (`js/webmcp.js`) and is served from your own site; no code is loaded from INITE. Only when an agent in the browser calls one of its tools does the script read your public facts, a JSON document, from `https://inite.ai/api/site/v1/public/{slug}`, once per page, without cookies. Like any request to another site, it carries the visitor's IP address and browser user agent to INITE; INITE sets and reads no cookie on it. A page no agent works on makes no request to INITE. While you take requests from agents, the script's request tools post what the agent wrote (the person's name, contact and message, as the agent passes them) to the address on inite.ai the manifest names, and only when an agent calls one of them.
* **Articles** (only while "Articles" is on, on each sync): a request to `https://inite.ai/api/site/v1/content/queue` with your site key for the articles you approved, and, for each post made, a request to `https://inite.ai/api/site/v1/content/{id}/published` with the post's address, or to `https://inite.ai/api/site/v1/content/{id}/failed` with the reason it could not be made.
* **IndexNow** (only while "IndexNow" is on, which it is not by default; a minute after a public page, post or product is published, changed or removed, at most 100 addresses a request, one request every five minutes and 500 addresses a day): a request to `https://api.indexnow.org/indexnow`, the shared endpoint that passes the addresses to Bing, Yandex and the other engines taking part, with your site's host, the IndexNow key the plugin made for your site, where that key is served (`/{key}.txt` on your site) and the addresses of the pages that changed. Nothing else, and nothing about visitors. This is a service of the IndexNow engines, not of INITE: terms https://www.indexnow.org/terms, about the protocol and privacy https://www.indexnow.org/faq.
* **Update check** (only in the copy downloaded from inite.ai, when WordPress checks for updates, at most twice a day): a request to `https://inite.ai/downloads/inite-agents.json` without the key, and the zip of a newer version when you update.
* **What INITE reads of your site** (not the plugin): once a week INITE opens up to five of your public pages and your robots.txt from its own servers, with the user agents of the robots behind Bing, ChatGPT, Claude, Perplexity and Yandex and once without one, to show in the Overview which robots get your pages.
* The link tags in the head point agents to your business's own address at `https://{slug}.inite.ai`.

INITE terms of service: https://inite.ai/en/terms
INITE privacy policy: https://inite.ai/en/privacy

## Screenshots

1. Settings → INITE Agents → Settings after "Test connection": the site key and a switch for each feature, with what each one serves now. The daily report and articles stay off until you switch them on.
2. The connection: the site, the facts version and when it was confirmed, the last sync, the files stored and the MCP server.
3. What the plugin leaves alone: an `llms.txt` file already on the server, and a robots.txt file it cannot add to, with the line to paste into it.
4. `/llms.txt` served from the site, built from the facts the business confirmed.

## Changelog

### 0.10.1
* Translations: the plugin speaks Russian (ru_RU), Brazilian Portuguese (pt_BR) and Spanish (es_ES) - every screen, notice and the "How did you hear about us?" question. WordPress 6.7 and later finds them by itself; on older versions the plugin loads them.

### 0.10.0
* Overview: "Can AI robots open your site?" - what INITE saw when it opened your key pages with the names of Bing's, ChatGPT's, Claude's, Perplexity's and Yandex's robots, and what robots.txt tells each of them, with a warning for a Cloudflare managed robots.txt. A refusal is marked "probably blocked", with where to confirm it.
* IndexNow: switch it on to tell Bing, Yandex and the other engines that share it about a published, changed or removed page at once. The plugin serves its key at `/{key}.txt`, batches addresses and stands down when another plugin already sends IndexNow.
* "How did you hear about us?": an optional question with AI assistants among the answers, at the WooCommerce checkout, in Contact Form 7 and WPForms, or with a shortcode. Only a count per day and answer is kept; the Overview shows the share that named an AI assistant, and the daily report sends the counts.
* AI crawler policy: nothing is blocked by default any more. Training crawlers are a box you tick, and Google-Extended is a box of its own, because blocking it removes the site from the Gemini app. A site that blocked training crawlers before keeps that choice, Google-Extended included.
* What assistants say: shares of answers that name your business and that cite your site, when INITE asked each question several times.

### 0.9.0
* Branches: with two places or more, the JSON-LD names the company and each branch as a business of its own, with its address, coordinates, phone, hours and special days, linked to the company. Merged into Yoast SEO's or Rank Math's graph beside their business node, never twice.
* Special days: holidays and short days (one date or a range) from your confirmed facts, as specialOpeningHoursSpecification; a day that has passed drops out by itself.
* Menus: a café's or restaurant's confirmed menus as Menu, MenuSection and MenuItem with prices and diets; the WebMCP script offers agents a menu tool, and its hours tool answers for a given date and branch.

### 0.8.0
* "What this site sends to INITE": a switch for each kind of data, each off until you turn it on and each naming the fields it sends. Pages and posts, WooCommerce products and The Events Calendar's events are sent a hundred at a time, only what changed, and deleted at INITE when you switch them off. Services from Bookly or Amelia, and locations and hours from WP Store Locator, Yoast Local SEO or Rank Math Local SEO, arrive in the cabinet as suggestions.
* `/llms-full.txt`: your confirmed facts, then every page, article, event and published product. Left alone, like llms.txt, where another plugin serves its own.
* Site basics are now off until you switch them on.

### 0.7.0
* Requests from agents: when you send them to the plugin in the INITE cabinet, the requests AI agents left for your business appear under Agent requests, to mark answered or spam; the WebMCP script offers agents request_contact (and request_booking when you take bookings).
* Overview tab and dashboard widget: your AI visibility score and failed checks, what AI agents read, visits from AI assistants, and what waits for you in INITE, each linked to where it is acted on.
* Fixes tab: JSON-LD from your confirmed facts (business, FAQPage, Product) merged into Yoast SEO's or Rank Math's graph when they are active, an AI crawler policy for robots.txt, a front-page meta description when no SEO plugin writes one, and a sitemap line check. Each with a preview; new ones off by default.
* With the daily report on, visits from AI assistants are counted per day and page (counts only) and reported with it.

### 0.5.0
* The hourly sync also tells INITE your site's title, tagline, language and address, and with WooCommerce the number of published products, so the INITE cabinet can suggest them. Sent only when they change, and at most once a day otherwise. No personal data.

### 0.4.0
* The WebMCP script ships inside the plugin and is served from your site. Only the facts, a JSON document, are read from INITE, and only when an agent calls a tool.
* The daily report is off until you switch it on. Sites that already switched it on keep it.

### 0.3.0
* Updates from inite.ai: the copy downloaded from there shows new versions on the Plugins screen and updates like any other plugin.
* The translation template covers the Articles settings.

### 0.2.0
* Articles (off by default): posts made from the articles you approved in the INITE cabinet, as drafts or published at once, with the meta description for Yoast or Rank Math; the address goes back to INITE when the post is published. One post per article, however often it syncs.

### 0.1.0
* First release: root files, robots.txt Content-Signal, head tags, abilities for the MCP Adapter, daily report.

## Upgrade Notice

### 0.10.1
Adds Russian, Brazilian Portuguese and Spanish translations.

### 0.10.0
Shows which AI robots get your pages, and adds IndexNow and a "How did you hear about us?" question, both off until you switch them on. The robots.txt policy no longer blocks anything by default; a choice you saved is kept.

### 0.9.0
Adds branches, special days and menus to the JSON-LD and the WebMCP tools, from the facts you confirm in INITE. Nothing changes on a site whose facts have none of them.

### 0.8.0
Adds "What this site sends to INITE": pages, products, events, booking services and locations, each sent only after you switch it on. Site basics are now off until you switch them on too.

### 0.7.0
Adds an Overview, a dashboard widget, one-click Fixes and Requests from agents. The JSON-LD your site printed stays as it was; every new fix is off until you switch it on.

### 0.5.0
The sync sends your site's title, tagline, language and address (and the WooCommerce product count) so INITE can suggest them in your cabinet.

### 0.4.0
The WebMCP script is now served from your site, not from inite.ai. The daily report is off on new installs until you switch it on.

### 0.3.0
Updates itself from inite.ai from now on. Install this one by hand once; later versions arrive on the Plugins screen.

### 0.2.0
Adds Articles: posts from the articles you approved in INITE. Off until you switch it on.

## Development

The plugin's source is public at https://github.com/inite-ai/inite-agents-wordpress, a read-only mirror updated on every change, with both zips attached to a GitHub Release for each version. Docs for every INITE Agents connector, webhook examples and issues: https://github.com/inite-ai/inite-agents. Security reports: security@inite.ai.
