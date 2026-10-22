<?php
/**
 * Which AI agents read the served paths, counted per day.
 *
 * The one list of agents the plugin knows is AGENTS below. A request is
 * counted only when its user agent names one of them and it asked for a
 * path this plugin served. Nothing else about the request is kept: no
 * address, no full user agent, no page, no cookie. The counts are a few
 * numbers per day in one option, trimmed to the last seven days, and the
 * daily ping sends them (see readme.txt, "External services").
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Agent visit counter.
 */
final class Inite_Agents_Visitors {

	const OPTION = 'inite_agents_visits';
	const DAYS   = 7;

	/**
	 * Known AI agents: the name reported, and the token looked for in the
	 * user agent (case-insensitive). Order matters: the first match wins, so
	 * the more specific token comes first.
	 */
	const AGENTS = array(
		'ChatGPT-User'       => 'chatgpt-user',
		'OAI-SearchBot'      => 'oai-searchbot',
		'GPTBot'             => 'gptbot',
		'Claude-User'        => 'claude-user',
		'Claude-SearchBot'   => 'claude-searchbot',
		'ClaudeBot'          => 'claudebot',
		'anthropic-ai'       => 'anthropic-ai',
		'Perplexity-User'    => 'perplexity-user',
		'PerplexityBot'      => 'perplexitybot',
		'Google-Extended'    => 'google-extended',
		'Google-CloudVertexBot' => 'google-cloudvertexbot',
		'Gemini-Deep-Research' => 'gemini-deep-research',
		'Applebot-Extended'  => 'applebot-extended',
		'Meta-ExternalAgent' => 'meta-externalagent',
		'Meta-ExternalFetcher' => 'meta-externalfetcher',
		'MistralAI-User'     => 'mistralai-user',
		'DuckAssistBot'      => 'duckassistbot',
		'YouBot'             => 'youbot',
		'cohere-ai'          => 'cohere-ai',
		'Amazonbot'          => 'amazonbot',
		'Bytespider'         => 'bytespider',
		'CCBot'              => 'ccbot',
	);

	/**
	 * The agent a user agent names, or null for anyone else.
	 *
	 * @param string $user_agent The User-Agent header.
	 * @return string|null
	 */
	public static function agent( $user_agent ) {
		$ua = strtolower( (string) $user_agent );
		if ( '' === $ua ) {
			return null;
		}
		foreach ( self::AGENTS as $name => $token ) {
			if ( false !== strpos( $ua, $token ) ) {
				return $name;
			}
		}
		return null;
	}

	/**
	 * Count this request if an agent asked for a served path.
	 *
	 * @param string $path The served path.
	 */
	public static function count( $path ) {
		if ( ! Inite_Agents_Settings::on( 'ping' ) ) {
			return;
		}
		$ua    = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$agent = self::agent( $ua );
		if ( null === $agent ) {
			return;
		}
		$day  = gmdate( 'Y-m-d' );
		$all  = self::all();
		$prev = isset( $all[ $day ][ $path ][ $agent ] ) ? (int) $all[ $day ][ $path ][ $agent ] : 0;

		$all[ $day ][ $path ][ $agent ] = $prev + 1;
		self::save( $all );
	}

	/**
	 * All counts kept, trimmed to the last DAYS days.
	 *
	 * @return array<string,array<string,array<string,int>>> day => path => agent => hits.
	 */
	public static function all() {
		$all    = get_option( self::OPTION, array() );
		$all    = is_array( $all ) ? $all : array();
		$oldest = gmdate( 'Y-m-d', time() - ( self::DAYS - 1 ) * DAY_IN_SECONDS );
		foreach ( array_keys( $all ) as $day ) {
			if ( ! is_string( $day ) || $day < $oldest ) {
				unset( $all[ $day ] );
			}
		}
		return $all;
	}

	/**
	 * The counts as the ping sends them: one row per day, path and agent.
	 *
	 * @return array<int,array{day:string,path:string,agent:string,hits:int}>
	 */
	public static function rows() {
		$rows = array();
		foreach ( self::all() as $day => $paths ) {
			foreach ( $paths as $path => $agents ) {
				foreach ( $agents as $agent => $hits ) {
					$rows[] = array(
						'day'   => (string) $day,
						'path'  => (string) $path,
						'agent' => (string) $agent,
						'hits'  => (int) $hits,
					);
				}
			}
		}
		return array_slice( $rows, -60 );
	}

	/**
	 * Write the counts.
	 *
	 * @param array $all The counts.
	 */
	private static function save( array $all ) {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $all, '', false );
		} else {
			update_option( self::OPTION, $all, false );
		}
	}
}
