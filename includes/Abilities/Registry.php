<?php
/**
 * Abilities Registry — orchestrator for all Shim MCP abilities.
 *
 * Registers two ability categories ('site' and 'user'), calls the
 * static register() method on each of the 13 ability files, and
 * applies a blanket filter to mark every ability as MCP-public.
 *
 * @package ShimMcp\Abilities
 * @since   1.0.0
 */

declare(strict_types=1);

namespace ShimMcp\Abilities;

use ShimMcp\Abilities\Content\Posts;
use ShimMcp\Abilities\Content\Pages;
use ShimMcp\Abilities\Content\Taxonomy;
use ShimMcp\Abilities\Content\Search;
use ShimMcp\Abilities\Content\Revisions;
use ShimMcp\Abilities\Media\Media;
use ShimMcp\Abilities\Users\Users;
use ShimMcp\Abilities\Plugins\Plugins;
use ShimMcp\Abilities\Menus\Menus;
use ShimMcp\Abilities\Widgets\Widgets;
use ShimMcp\Abilities\Comments\Comments;
use ShimMcp\Abilities\Options\Options;
use ShimMcp\Abilities\System\System;

/**
 * Registry that wires all ability categories and abilities into the
 * WordPress Abilities API (native 6.9+ or polyfill).
 *
 * @since 1.0.0
 */
final class Registry {

	/**
	 * Wire up the Abilities API hooks.
	 *
	 * Called once from Plugin::setup(). Registers callbacks on the two
	 * Abilities API init actions and adds the MCP exposure filter.
	 *
	 * @since 1.0.0
	 */
	public static function init(): void {
		add_action( 'wp_abilities_api_categories_init', array( self::class, 'register_categories' ) );
		add_action( 'wp_abilities_api_init', array( self::class, 'register_abilities' ) );
		add_filter( 'wp_register_ability_args', array( self::class, 'expose_all_abilities' ), 10, 2 );
	}

	/**
	 * Register ability categories.
	 *
	 * Hooked to {@see 'wp_abilities_api_categories_init'}.
	 *
	 * @since 1.0.0
	 */
	public static function register_categories(): void {
		wp_register_ability_category(
			'site',
			array(
				'label'       => __( 'Site Management', 'shim-mcp' ),
				'description' => __( 'Abilities for managing site content, media, plugins, menus, widgets, options, and system settings.', 'shim-mcp' ),
			)
		);

		wp_register_ability_category(
			'user',
			array(
				'label'       => __( 'User Management', 'shim-mcp' ),
				'description' => __( 'Abilities for managing WordPress users and their roles.', 'shim-mcp' ),
			)
		);
	}

	/**
	 * Register all 57 abilities from the 13 ability files.
	 *
	 * Hooked to {@see 'wp_abilities_api_init'}.
	 *
	 * @since 1.0.0
	 */
	public static function register_abilities(): void {
		// Content abilities.
		Posts::register();
		Pages::register();
		Taxonomy::register();
		Search::register();
		Revisions::register();

		// Media abilities.
		Media::register();

		// User abilities.
		Users::register();

		// Plugin abilities.
		Plugins::register();

		// Menu abilities.
		Menus::register();

		// Widget abilities.
		Widgets::register();

		// Comment abilities.
		Comments::register();

		// Option abilities.
		Options::register();

		// System abilities.
		System::register();
	}

	/**
	 * Mark every registered ability as MCP-public.
	 *
	 * Hooked to {@see 'wp_register_ability_args'} so the filter runs
	 * before each ability is stored in the registry.  Sets
	 * `meta.mcp.public = true` and `meta.mcp.type = 'tool'` (unless
	 * a type was already specified).
	 *
	 * @since 1.0.0
	 *
	 * @param array  $args         The ability registration arguments.
	 * @param string $ability_name The ability name being registered.
	 * @return array Modified arguments with MCP metadata.
	 */
	public static function expose_all_abilities( array $args, string $ability_name ): array {
		if ( ! isset( $args['meta'] ) ) {
			$args['meta'] = array();
		}
		if ( ! isset( $args['meta']['mcp'] ) ) {
			$args['meta']['mcp'] = array();
		}

		$args['meta']['mcp']['public'] = true;

		if ( ! isset( $args['meta']['mcp']['type'] ) ) {
			$args['meta']['mcp']['type'] = 'tool';
		}

		return $args;
	}
}
