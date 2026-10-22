=== INITE Agents ===
Contributors: inite
Tags: ai, llms.txt, mcp, agents, schema
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Hands AI agents the facts your business confirmed in INITE: llms.txt, agent card and MCP files, robots.txt signals, head tags, abilities, articles.

== Description ==

INITE Agents connects your WordPress site to [INITE](https://inite.ai/), where you confirm the facts about your business that AI agents may use: what you do, your services and prices, opening hours, locations and answers to common questions. Nothing you did not confirm is published.

The plugin keeps a copy of what INITE generated from those facts on your site and serves it from there:

* **Root files.** `/llms.txt`, `/.well-known/agent-card.json` (A2A agent card), `/.well-known/mcp.json` (where your MCP server is) and `/.well-known/api-catalog`.
* **robots.txt.** A `Content-Signal` line in the `User-agent: *` group: search and answers may use your content, training may not.
* **Page head.** A small WebMCP script that offers the same facts as tools to an agent working in a visitor's browser, link tags pointing agents to the files, and JSON-LD (Organization or LocalBusiness, and FAQPage) on the front page.
* **Abilities for the WordPress MCP Adapter.** On WordPress 6.9 or later, five read-only abilities (`inite/business-facts`, `inite/services`, `inite/hours`, `inite/locations`, `inite/faq`). With the official [MCP Adapter](https://wordpress.org/plugins/mcp-adapter/) plugin active, an agent connected to your site can list and call them.
* **Daily report.** Which paths the plugin serves and how many times known AI agents read them per day.
* **Articles** (off until you switch it on). INITE drafts articles for your site from what people actually search for, and you approve each one in the INITE cabinet. The plugin turns every approved article into a WordPress post, as a draft for you to publish or published at once, as you choose, with its meta description for Yoast SEO or Rank Math. When the post is published, its address goes back to INITE. An article becomes one post, however often the plugin syncs. Nothing you did not approve is written.

Each feature has its own switch. A feature that is off claims no URL and prints nothing.

The plugin works around what is already there:

* A physical file at one of the paths (for example an `llms.txt` you uploaded) is left alone.
* An SEO plugin that serves its own llms.txt (Yoast SEO, Rank Math, All in One SEO, SEOPress PRO, Website LLMs.txt) keeps it while that feature is on in it.
* When Yoast SEO, Rank Math, All in One SEO or SEOPress already print an Organization schema, ours is left out of the JSON-LD.
* With a physical robots.txt, the settings screen shows the line to paste.

The settings screen says what was left alone and why.

The plugin never slows a page down for the service: the copy is fetched by WP-Cron every hour, when you save the settings and when you press "Test connection", with a five-second limit. If INITE cannot be reached, the last good copy keeps being served.

== Installation ==

1. Upload the plugin through **Plugins → Add New → Upload Plugin**, or unzip it into `wp-content/plugins/`, and activate it.
2. In your INITE cabinet, open the "For agents" tab, confirm your facts and make a site key for WordPress.
3. In WordPress, open **Settings → INITE Agents**, paste the key and save.
4. Press **Test connection**. The screen shows the facts version and the files now served.

For the MCP abilities, also install and activate the MCP Adapter plugin (WordPress 6.9 or later).

== Frequently Asked Questions ==

= Does it need an INITE account? =

Yes. The facts are confirmed in the INITE cabinet, and the site key comes from there.

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
* **Daily report** (once a day, only while "Daily report" is on): a request to `https://inite.ai/api/site/v1/ping` with your site key, the plugin and WordPress versions, the paths the plugin serves, and for each of the last seven days the number of times each known AI agent (such as GPTBot, ClaudeBot or PerplexityBot) read those paths. No IP address, no full user agent, no cookie, nothing about human visitors.
* **In your visitors' browsers** (only while "Page head" is on): the WebMCP script is loaded from `https://inite.ai/api/site/v1/webmcp.js`, and, only when an agent in the browser calls one of its tools, it reads your public facts from `https://inite.ai/api/site/v1/public/{slug}`. Like any file fetched from another site, that request carries the visitor's IP address and browser user agent to INITE; INITE sets and reads no cookie on it.
* **Articles** (only while "Articles" is on, on each sync): a request to `https://inite.ai/api/site/v1/content/queue` with your site key for the articles you approved, and, for each post made, a request to `https://inite.ai/api/site/v1/content/{id}/published` with the post's address, or to `https://inite.ai/api/site/v1/content/{id}/failed` with the reason it could not be made.
* **Update check** (only in the copy downloaded from inite.ai, when WordPress checks for updates, at most twice a day): a request to `https://inite.ai/downloads/inite-agents.json` without the key, and the zip of a newer version when you update.
* The link tags in the head point agents to your business's own address at `https://{slug}.inite.ai`.

INITE terms of service: https://inite.ai/en/terms
INITE privacy policy: https://inite.ai/en/privacy

== Changelog ==

= 0.3.0 =
* Updates from inite.ai: the copy downloaded from there shows new versions on the Plugins screen and updates like any other plugin.
* The translation template covers the Articles settings.

= 0.2.0 =
* Articles (off by default): posts made from the articles you approved in the INITE cabinet, as drafts or published at once, with the meta description for Yoast or Rank Math; the address goes back to INITE when the post is published. One post per article, however often it syncs.

= 0.1.0 =
* First release: root files, robots.txt Content-Signal, head tags, abilities for the MCP Adapter, daily report.

== Upgrade Notice ==

= 0.3.0 =
Updates itself from inite.ai from now on. Install this one by hand once; later versions arrive on the Plugins screen.

= 0.2.0 =
Adds Articles: posts from the articles you approved in INITE. Off until you switch it on.
