#!/usr/bin/env node
/**
 * Builds the INITE Agents WordPress plugin (plugins/wordpress/inite-agents)
 * into a zip WordPress installs as it is: every file under one folder named
 * after the slug, `inite-agents/`.
 *
 *   node scripts/build-wp-plugin.mjs            # plugins/wordpress/dist/: inite-agents.zip, .json, -org.zip
 *   node scripts/build-wp-plugin.mjs --public   # and public/downloads/inite-agents.{zip,json}
 *
 * `pnpm run build` runs it with --public, so the image serves the zip at
 * /downloads/inite-agents.zip and the update info the plugin reads
 * (includes/class-inite-agents-updater.php) at /downloads/inite-agents.json;
 * every output is gitignored.
 *
 * Two variants of one source. The inite.ai zip updates itself from
 * inite.ai: its header names `Update URI: https://inite.ai/downloads/inite-agents`.
 * The WordPress.org zip (`inite-agents-org.zip`, never public here) leaves
 * out the updater and that header line, as WordPress.org forbids a hosted
 * plugin to update from anywhere else; it is the one to submit there. The zip is
 * written here, without a dependency or the system `zip` (the image has
 * none): stored entries are deflated with node:zlib, and every entry has
 * the same date, so the same sources give the same bytes.
 *
 * It refuses to build when the version in the plugin header, the
 * INITE_AGENTS_VERSION constant and readme.txt's Stable tag disagree:
 * WordPress.org reads the readme, WordPress reads the header.
 */

import { readFileSync, readdirSync, statSync, mkdirSync, writeFileSync } from 'node:fs'
import path from 'node:path'
import { deflateRawSync } from 'node:zlib'
import { fileURLToPath } from 'node:url'

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
export const SLUG = 'inite-agents'
export const PLUGIN_DIR = path.join(ROOT, 'plugins', 'wordpress', SLUG)
export const DIST = path.join(ROOT, 'plugins', 'wordpress', 'dist')
export const PUBLIC_ZIP = path.join(ROOT, 'public', 'downloads', `${SLUG}.zip`)
export const PUBLIC_INFO = path.join(ROOT, 'public', 'downloads', `${SLUG}.json`)
/** Where the inite.ai copy is downloaded and updated from. */
export const DOWNLOADS = 'https://inite.ai/downloads'
/** What the WordPress.org build leaves out. */
export const UPDATER_FILE = 'includes/class-inite-agents-updater.php'
const UPDATE_URI_LINE = /^ \* Update URI:.*\n/m

/** 2026-01-01 00:00 in DOS time: one date for every entry. */
const DOS_TIME = 0
const DOS_DATE = ((2026 - 1980) << 9) | (1 << 5) | 1

/** The plugin's files, relative to its folder, sorted. Dotfiles stay out. */
export function pluginFiles(dir = PLUGIN_DIR) {
  const out = []
  const walk = (rel) => {
    for (const name of readdirSync(path.join(dir, rel)).sort()) {
      if (name.startsWith('.')) continue
      const r = rel ? `${rel}/${name}` : name
      if (statSync(path.join(dir, r)).isDirectory()) walk(r)
      else out.push(r)
    }
  }
  walk('')
  return out
}

/** The versions the plugin states: header, constant, readme's Stable tag. */
export function versions(dir = PLUGIN_DIR) {
  const main = readFileSync(path.join(dir, `${SLUG}.php`), 'utf8')
  const readme = readFileSync(path.join(dir, 'readme.txt'), 'utf8')
  return {
    header: /^\s*\*\s*Version:\s*(\S+)/m.exec(main)?.[1] ?? null,
    constant: /define\(\s*'INITE_AGENTS_VERSION',\s*'([^']+)'\s*\)/.exec(main)?.[1] ?? null,
    stableTag: /^Stable tag:\s*(\S+)/m.exec(readme)?.[1] ?? null,
  }
}

const CRC_TABLE = (() => {
  const t = new Uint32Array(256)
  for (let n = 0; n < 256; n++) {
    let c = n
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1
    t[n] = c >>> 0
  }
  return t
})()

export function crc32(buf) {
  let c = 0xffffffff
  for (const b of buf) c = CRC_TABLE[(c ^ b) & 0xff] ^ (c >>> 8)
  return (c ^ 0xffffffff) >>> 0
}

/** A zip of entries [{ name, data }], deflated, with one fixed date. */
export function zip(entries) {
  const locals = []
  const centrals = []
  let offset = 0
  for (const { name, data } of entries) {
    const nameBuf = Buffer.from(name, 'utf8')
    const packed = deflateRawSync(data, { level: 9 })
    const crc = crc32(data)
    const local = Buffer.alloc(30)
    local.writeUInt32LE(0x04034b50, 0)
    local.writeUInt16LE(20, 4)
    local.writeUInt16LE(0x0800, 6) // names are UTF-8
    local.writeUInt16LE(8, 8)
    local.writeUInt16LE(DOS_TIME, 10)
    local.writeUInt16LE(DOS_DATE, 12)
    local.writeUInt32LE(crc, 14)
    local.writeUInt32LE(packed.length, 18)
    local.writeUInt32LE(data.length, 22)
    local.writeUInt16LE(nameBuf.length, 26)
    local.writeUInt16LE(0, 28)
    const central = Buffer.alloc(46)
    central.writeUInt32LE(0x02014b50, 0)
    central.writeUInt16LE(0x0314, 4) // made by Unix, 2.0
    central.writeUInt16LE(20, 6)
    central.writeUInt16LE(0x0800, 8)
    central.writeUInt16LE(8, 10)
    central.writeUInt16LE(DOS_TIME, 12)
    central.writeUInt16LE(DOS_DATE, 14)
    central.writeUInt32LE(crc, 16)
    central.writeUInt32LE(packed.length, 20)
    central.writeUInt32LE(data.length, 24)
    central.writeUInt16LE(nameBuf.length, 28)
    central.writeUInt32LE(((0o100644 << 16) >>> 0), 38) // -rw-r--r--
    central.writeUInt32LE(offset, 42)
    locals.push(local, nameBuf, packed)
    centrals.push(central, nameBuf)
    offset += 30 + nameBuf.length + packed.length
  }
  const dir = Buffer.concat(centrals)
  const end = Buffer.alloc(22)
  end.writeUInt32LE(0x06054b50, 0)
  end.writeUInt16LE(entries.length, 8)
  end.writeUInt16LE(entries.length, 10)
  end.writeUInt32LE(dir.length, 12)
  end.writeUInt32LE(offset, 16)
  return Buffer.concat([...locals, dir, end])
}

/**
 * The plugin's zip, in memory. Throws when the versions disagree.
 * `{ org: true }`: the WordPress.org variant, without the updater and the Update URI header.
 */
export function buildPluginZip(dir = PLUGIN_DIR, { org = false } = {}) {
  const v = versions(dir)
  if (!v.header || v.header !== v.constant || v.header !== v.stableTag) {
    throw new Error(`inite-agents: versions disagree (header ${v.header}, INITE_AGENTS_VERSION ${v.constant}, Stable tag ${v.stableTag})`)
  }
  const files = pluginFiles(dir).filter((rel) => !org || rel !== UPDATER_FILE)
  const entries = files.map((rel) => {
    let data = readFileSync(path.join(dir, rel))
    if (org && rel === `${SLUG}.php`) data = Buffer.from(data.toString('utf8').replace(UPDATE_URI_LINE, ''), 'utf8')
    return { name: `${SLUG}/${rel}`, data }
  })
  return { buffer: zip(entries), files, version: v.header }
}

const escape = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

/** readme.txt's Changelog as the HTML WordPress shows in "View details": a heading per version, a list of its lines. */
export function changelogHtml(readme) {
  const section = readme.split(/^== Changelog ==$/m)[1]?.split(/^== /m)[0] ?? ''
  const out = []
  let open = false
  for (const line of section.split('\n')) {
    const version = /^= (.+) =$/.exec(line.trim())
    const item = /^\* (.+)$/.exec(line.trim())
    if (version) {
      if (open) out.push('</ul>')
      out.push(`<h4>${escape(version[1])}</h4>`, '<ul>')
      open = true
    } else if (item && open) out.push(`<li>${escape(item[1]).replace(/`([^`]+)`/g, '<code>$1</code>')}</li>`)
  }
  if (open) out.push('</ul>')
  return out.join('')
}

/** The update info the inite.ai copy reads (includes/class-inite-agents-updater.php). The same sources give the same bytes. */
export function updateInfo(dir = PLUGIN_DIR) {
  const v = versions(dir)
  const main = readFileSync(path.join(dir, `${SLUG}.php`), 'utf8')
  const readme = readFileSync(path.join(dir, 'readme.txt'), 'utf8')
  const header = (re, text) => re.exec(text)?.[1]?.trim() ?? ''
  const info = {
    name: 'INITE Agents',
    slug: SLUG,
    version: v.header,
    download_url: `${DOWNLOADS}/${SLUG}.zip?v=${v.header}`,
    requires: header(/^\s*\*\s*Requires at least:\s*(\S+)/m, main),
    requires_php: header(/^\s*\*\s*Requires PHP:\s*(\S+)/m, main),
    tested: header(/^Tested up to:\s*(\S+)/m, readme),
    changelog: changelogHtml(readme),
  }
  return `${JSON.stringify(info, null, 2)}\n`
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const { buffer, files, version } = buildPluginZip()
  const org = buildPluginZip(PLUGIN_DIR, { org: true })
  const info = Buffer.from(updateInfo(), 'utf8')
  const outs = [
    [path.join(DIST, `${SLUG}.zip`), buffer, `${files.length} files`],
    [path.join(DIST, `${SLUG}.json`), info, 'update info'],
    [path.join(DIST, `${SLUG}-org.zip`), org.buffer, `${org.files.length} files, WordPress.org`],
  ]
  if (process.argv.includes('--public')) outs.push([PUBLIC_ZIP, buffer, `${files.length} files`], [PUBLIC_INFO, info, 'update info'])
  for (const [out, data, what] of outs) {
    mkdirSync(path.dirname(out), { recursive: true })
    writeFileSync(out, data)
    console.log(`${SLUG} ${version}: ${what}, ${data.length} bytes -> ${path.relative(ROOT, out)}`)
  }
}
