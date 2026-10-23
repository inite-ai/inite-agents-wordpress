=== INITE Agents ===
Contributors: inite
Tags: ai, llms.txt, mcp, agents, schema
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Hands AI agents the facts your business confirmed in INITE: llms.txt, agent card and MCP files, robots.txt signals, head tags, abilities, articles.

== Description ==

INITE Agents connects your WordPress site to [INITE](https://inite.ai/), where you confirm the facts about your business that AI agents may use: what you do, your services and prices, opening hours, locations and answers to common questions. Nothing you did not confirm is published.

The plugin keeps a copy of what INITE generated from those facts on your site and serves it from there:

* **Root files.** `/llms.txt`, `/.well-known/agent-card.json` (A2A agent card), `/.well-known/mcp.json` (where your MCP server is) and `/.well-known/api-catalog`.
* **robots.txt.** A `Content-Signal` line in the `User-agent: *` group: search and answers may use your content, training may not.
* **Page head.** A small WebMCP script, shipped with the plugin, that offers the same facts as tools to an agent working in a visitor's browser, and link tags pointing agents to the files.
* **Abilities for the WordPress MCP Adapter.** On WordPress 6.9 or later, five read-only abilities (`inite/business-facts`, `inite/services`, `inite/hours`, `inite/locations`, `inite/faq`). With the official [MCP Adapter](https://wordpress.org/plugins/mcp-adapter/) plugin active, an agent connected to your site can list and call them.
* **Daily report** (off until you switch it on). Which paths the plugin serves, how many times known AI agents read them per day, and how many visits came from AI assistants (ChatGPT, Perplexity, Gemini, Copilot, Claude and others) per day and page. Counts only: no address, no cookie.
* **Requests from agents** (only when you choose "To the WordPress plugin" in the INITE cabinet). An AI agent can ask your business to get in touch, or to book, through your MCP server or the WebMCP script; the requests appear under **Agent requests** in the WordPress menu, where you mark each one answered or spam.
* **Articles** (off until you switch it on). INITE drafts articles for your site from what people actually search for, and you approve each one in the INITE cabinet. The plugin turns every approved article into a WordPress post, as a draft for you to publish or published at once, as you choose, with its meta description for Yoast SEO or Rank Math. When the post is published, its address goes back to INITE. An article becomes one post, however often the plugin syncs. Nothing you did not approve is written.

Each feature has its own switch. A feature that is off claims no URL and prints nothing.

**What you get back, inside WordPress.** A dashboard widget and an **Overview** tab under **Settings → INITE Agents**: the AI visibility score of your site's latest INITE analysis and the checks it failed, what AI assistants say about your business against the facts you confirmed (once INITE has checked), which AI agents read which of your pages, how many people came from AI assistants and to which pages, and the suggestions and articles waiting for you in INITE. Each line links to where it is acted on. Breakdowns INITE keeps for Atlas Premium arrive as totals without it; the counts the plugin keeps itself are always shown in full.

**Fixes.** A **Fixes** tab switches on, one by one, what the analysis looks for and WordPress can add, each with a preview of exactly what it adds:

* JSON-LD on the front page from your confirmed facts: the business (Organization or LocalBusiness), FAQPage, and a Product for each service sold per item. With Yoast SEO or Rank Math active, ours goes into their schema graph: their values are kept, only what they lack is added, and no type is printed twice.
* An AI crawler policy in robots.txt: block the crawlers that collect pages for training, and, only if you ask, AI search and answer fetchers.
* A meta description on the front page, while no SEO plugin writes one.
* A check that robots.txt names your sitemap, and the line if it does not.

The two JSON-LD types earlier versions printed stay as they were; every other fix is off until you switch it on.

The plugin works around what is already there:

* A physical file at one of the paths (for example an `llms.txt` you uploaded) is left alone.
* An SEO plugin that serves its own llms.txt (Yoast SEO, Rank Math, All in One SEO, SEOPress PRO, Website LLMs.txt) keeps it while that feature is on in it.
* With Yoast SEO or Rank Math, the JSON-LD goes into their graph; with All in One SEO, or SEOPress with its knowledge graph on, our business node is left out.
* With a physical robots.txt, the settings screen shows the line to paste.

The settings screen says what was left alone and why.

The plugin never slows a page down for the service: the copy is fetched by WP-Cron every hour, when you save the settings and when you press "Test connection", with a five-second limit. If INITE cannot be reached, the last good copy keeps being served.

== Installation ==

1. Upload the plugin through **Plugins → Add New → Upload Plugin**, or unzip it into `wp-content/plugins/`, and activate it.
2. In your INITE cabinet, open the "For agents" tab, confirm your facts and make a site key for WordPress.
3. In WordPress, open **Settings → INITE Agents → Settings**, paste the key and save.
4. Press **Test connection**. The screen shows the facts version and the files now served.

For the MCP abilities, also install and activate the MCP Adapter plugin (WordPress 6.9 or later).

== Frequently Asked Questions ==

= Does it need an INITE account? =

Yes. The facts are confirmed in the INITE cabinet, and the site key comes from there.

= Are visits from AI assistants tracked? =

Counted, not tracked, and only while "Daily report" is on. A page view counts when the browser says it came from an assistant's site (for example chatgpt.com or perplexity.ai) or the link carries `utm_source=chatgpt.com`. The plugin keeps the day, the page's path without its query, the assistant and a number, for 30 days. No IP address, no cookie, no user agent, nothing that tells one person from another. Visits served from a full-page cache never reach WordPress, so the numbers are a floor.

= Does INITE read my site? =

Yes, from its own servers, not through the plugin: once a day, up to eight public pages (the homepage and the pages about prices, services, contact, hours, FAQ or menu), obeying your robots.txt. What it finds that your facts do not say appears in the INITE cabinet as a suggestion to accept or ignore. Nothing changes in your published facts unless you confirm it, or turn on "Keep in step with my site" in the cabinet for values your site states in its schema.org markup.

= What happens if INITE is down? =

Nothing visible. The site serves the copy it already stored, and tries again an hour later.

= What happens when I revoke the key or switch the connector off in INITE? =

At its next sync the plugin is told so, drops the stored copy and stops serving the files, the head tags and the abilities.

= Does it change my permalinks? =

No. The files are answered before WordPress parses the request, without rewrite rules, so there is nothing to flush and nothing left behind when the plugin is switched off.

= My WordPress is installed in a subdirectory. =

Then WordPress does not see requests to the domain root, where `/llms.txt` and `/.well-known/` must live. The settings screen says so. INITE can serve those files on an `agents.` subdomain of yours instead.

= Who can call the abilities? =

The MCP Adapter only lets authenticated WordPress users connect. Once connected, the abilities need no extra capability, because they return only what your business published.

= How do articles reach my site? =

Switch on **Articles** in **Settings → INITE Agents** and choose whether new articles are saved as drafts or published at once. On each hourly sync the plugin asks INITE for the articles you approved and not yet published on this site, and makes a post for each. If a post cannot be made, INITE is told why, and the article waits in your cabinet until you send it again.

= Will articles appear without my approval? =

No. Only articles you approved in the INITE cabinet are sent, exactly as you approved them. With "Save as drafts" nothing is public until you press Publish in WordPress.

= How is the plugin updated? =

The copy downloaded from inite.ai checks https://inite.ai/downloads/inite-agents.json when WordPress checks for updates, at most twice a day, and the update appears on the Plugins screen like any other. Automatic updates follow your WordPress setting for this plugin. The check sends no key and nothing about your site.

= Can I point the plugin at a staging server? =

Define `INITE_AGENTS_API` in `wp-config.php` (for example `define( 'INITE_AGENTS_API', 'https://staging.example' );`) or use the `inite_agents_api_base` filter. The site key is only ever sent to that address.

== External services ==

This plugin talks to INITE (https://inite.ai), the service where you confirm your business facts. It is the plugin's purpose: without the service there is nothing to serve.

What is sent, when, and where:

* **Sync** (every hour, when the settings are saved, when "Test connection" is pressed, and on activation): a request to `https://inite.ai/api/site/v1/manifest` and `https://inite.ai/api/site/v1/files/{name}` with your site key, the ETag of the copy held, and a user agent naming the plugin and WordPress versions. A request to `https://inite.ai/api/site/v1/public/{slug}` for the public facts, without the key.
* **Site basics** (with the hourly sync, only when they changed, and at most once a day otherwise): a request to `https://inite.ai/api/site/v1/ping` with your site key, the plugin version and a `site` field holding your site's title and tagline (Settings → General), its language and address, and, if WooCommerce is active, the number of published products. INITE uses them to suggest your business's name and description in your cabinet; nothing is published until you accept and confirm it. No user, no email address, nothing about visitors.
* **Daily report** (once a day, only after you switch "Daily report" on; it is off by default): a request to `https://inite.ai/api/site/v1/ping` with your site key, the plugin and WordPress versions, the paths the plugin serves, for each of the last seven days the number of times each known AI agent (such as GPTBot, ClaudeBot or PerplexityBot) read those paths, and the number of visits per page that came from an AI assistant (the `referrals` field: day, path, assistant, count). No IP address, no full user agent, no cookie, nothing that identifies a visitor.
* **Summary** (for the dashboard widget and the Overview tab, at most every four hours on the hourly sync, when the Overview finds nothing held, and when you press "Refresh"): a request to `https://inite.ai/api/site/v1/summary` with your site key. It sends nothing else and reads what INITE knows about the site: the latest analysis's score and failed checks, counts of agent reads, MCP calls and visits from AI assistants, and how many suggestions and articles wait for you.
* **Requests from agents** (only while you send them to the plugin in the INITE cabinet): opening **Agent requests** asks `https://inite.ai/api/site/v1/requests` with your site key for the list, which is not stored on your site; marking a request answered or spam sends that status to `https://inite.ai/api/site/v1/requests/{id}`.
* **In your visitors' browsers** (only while "Page head" is on): the WebMCP script is part of the plugin (`js/webmcp.js`) and is served from your own site; no code is loaded from INITE. Only when an agent in the browser calls one of its tools does the script read your public facts, a JSON document, from `https://inite.ai/api/site/v1/public/{slug}`, once per page, without cookies. Like any request to another site, it carries the visitor's IP address and browser user agent to INITE; INITE sets and reads no cookie on it. A page no agent works on makes no request to INITE. While you take requests from agents, the script's request tools post what the agent wrote (the person's name, contact and message, as the agent passes them) to the address on inite.ai the manifest names, and only when an agent calls one of them.
* **Articles** (only while "Articles" is on, on each sync): a request to `https://inite.ai/api/site/v1/content/queue` with your site key for the articles you approved, and, for each post made, a request to `https://inite.ai/api/site/v1/content/{id}/published` with the post's address, or to `https://inite.ai/api/site/v1/content/{id}/failed` with the reason it could not be made.
* **Update check** (only in the copy downloaded from inite.ai, when WordPress checks for updates, at most twice a day): a request to `https://inite.ai/downloads/inite-agents.json` without the key, and the zip of a newer version when you update.
* The link tags in the head point agents to your business's own address at `https://{slug}.inite.ai`.

INITE terms of service: https://inite.ai/en/terms
INITE privacy policy: https://inite.ai/en/privacy

== Screenshots ==

1. Settings → INITE Agents → Settings after "Test connection": the site key and a switch for each feature, with what each one serves now. The daily report and articles stay off until you switch them on.
2. The connection: the site, the facts version and when it was confirmed, the last sync, the files stored and the MCP server.
3. What the plugin leaves alone: an `llms.txt` file already on the server, and a robots.txt file it cannot add to, with the line to paste into it.
4. `/llms.txt` served from the site, built from the facts the business confirmed.

== Changelog ==

= 0.7.0 =
* Requests from agents: when you send them to the plugin in the INITE cabinet, the requests AI agents left for your business appear under Agent requests, to mark answered or spam; the WebMCP script offers agents request_contact (and request_booking when you take bookings).
* Overview tab and dashboard widget: your AI visibility score and failed checks, what AI agents read, visits from AI assistants, and what waits for you in INITE, each linked to where it is acted on.
* Fixes tab: JSON-LD from your confirmed facts (business, FAQPage, Product) merged into Yoast SEO's or Rank Math's graph when they are active, an AI crawler policy for robots.txt, a front-page meta description when no SEO plugin writes one, and a sitemap line check. Each with a preview; new ones off by default.
* With the daily report on, visits from AI assistants are counted per day and page (counts only) and reported with it.

= 0.5.0 =
* The hourly sync also tells INITE your site's title, tagline, language and address, and with WooCommerce the number of published products, so the INITE cabinet can suggest them. Sent only when they change, and at most once a day otherwise. No personal data.

= 0.4.0 =
* The WebMCP script ships inside the plugin and is served from your site. Only the facts, a JSON document, are read from INITE, and only when an agent calls a tool.
* The daily report is off until you switch it on. Sites that already switched it on keep it.

= 0.3.0 =
* Updates from inite.ai: the copy downloaded from there shows new versions on the Plugins screen and updates like any other plugin.
* The translation template covers the Articles settings.

= 0.2.0 =
* Articles (off by default): posts made from the articles you approved in the INITE cabinet, as drafts or published at once, with the meta description for Yoast or Rank Math; the address goes back to INITE when the post is published. One post per article, however often it syncs.

= 0.1.0 =
* First release: root files, robots.txt Content-Signal, head tags, abilities for the MCP Adapter, daily report.

== Upgrade Notice ==

= 0.7.0 =
Adds an Overview, a dashboard widget, one-click Fixes and Requests from agents. The JSON-LD your site printed stays as it was; every new fix is off until you switch it on.

= 0.5.0 =
The sync sends your site's title, tagline, language and address (and the WooCommerce product count) so INITE can suggest them in your cabinet.

= 0.4.0 =
The WebMCP script is now served from your site, not from inite.ai. The daily report is off on new installs until you switch it on.

= 0.3.0 =
Updates itself from inite.ai from now on. Install this one by hand once; later versions arrive on the Plugins screen.

= 0.2.0 =
Adds Articles: posts from the articles you approved in INITE. Off until you switch it on.

== Development ==

The plugin's source is public at https://github.com/inite-ai/inite-agents-wordpress, a read-only mirror updated on every change, with both zips attached to a GitHub Release for each version. Docs for every INITE Agents connector, webhook examples and issues: https://github.com/inite-ai/inite-agents. Security reports: security@inite.ai.
