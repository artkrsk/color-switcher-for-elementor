<?php

namespace Arts\ColorSwitcher\Tests\Integration;

use Arts\ColorSwitcher\Managers\Documents;

/**
 * The server-rendered half of the state: a dark-start page must paint dark
 * with no JS at all, and the wrapper mirror must carry the baseline for
 * routers that swap containers.
 */
class BaselineRenderTest extends TestCase {

	private function page_with_theme( string $theme ): int {
		$page_id = $this->factory()->post->create( array( 'post_type' => 'page' ) );

		update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );
		update_post_meta(
			$page_id,
			'_elementor_page_settings',
			array( Documents::CONTROL_PAGE_THEME => $theme )
		);

		return $page_id;
	}

	private function language_attributes_for( int $page_id ): string {
		$this->go_to( get_permalink( $page_id ) );
		setup_postdata( get_post( $page_id ) );

		ob_start();
		language_attributes();

		return (string) ob_get_clean();
	}

	public function test_alt_baseline_renders_on_html(): void {
		$output = $this->language_attributes_for( $this->page_with_theme( 'alt' ) );

		$this->assertStringContainsString( 'data-arts-cs="alt"', $output );
	}

	/**
	 * An Auto page renders nothing, but for a different reason than it used to:
	 * the server is blind to the visitor's device on purpose, because this
	 * output is cacheable and one visitor's device must not decide everyone's
	 * palette. The head script resolves it before paint instead.
	 */
	public function test_auto_baseline_renders_nothing(): void {
		$output = $this->language_attributes_for( $this->page_with_theme( Documents::THEME_AUTO ) );

		$this->assertStringNotContainsString( 'data-arts-cs', $output );
	}

	/** An author who says "light for everyone" also renders nothing — and means it. */
	public function test_explicit_default_baseline_renders_nothing(): void {
		$output = $this->language_attributes_for( $this->page_with_theme( Documents::THEME_DEFAULT ) );

		$this->assertStringNotContainsString( 'data-arts-cs', $output );
	}

	/**
	 * The wrapper is where those two part company: it is the only surface that
	 * can tell the runtime whether the author declined to choose or chose
	 * light, and an empty attribute value cannot say which.
	 */
	public function test_wrapper_tells_auto_apart_from_an_explicit_default(): void {
		foreach (
			array(
				Documents::THEME_AUTO    => 'auto',
				Documents::THEME_DEFAULT => 'default',
				Documents::THEME_ALT     => 'alt',
			) as $stored => $expected
		) {
			$document = \Elementor\Plugin::$instance->documents->get( $this->page_with_theme( (string) $stored ) );

			$this->assertNotNull( $document );
			$this->assertSame(
				$expected,
				$document->get_container_attributes()['data-arts-cs-baseline'] ?? null,
				sprintf( 'stored value %s', var_export( $stored, true ) )
			);
		}
	}

	public function test_wrapper_mirrors_the_baseline(): void {
		$page_id  = $this->page_with_theme( 'alt' );
		$document = \Elementor\Plugin::$instance->documents->get( $page_id );

		$this->assertNotNull( $document );

		$attributes = $document->get_container_attributes();

		$this->assertSame( 'alt', $attributes['data-arts-cs-baseline'] ?? null );
	}
}
