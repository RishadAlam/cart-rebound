<?php
/**
 * Email template store unit tests.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tests\Unit;

use Brain\Monkey\Functions;
use CartRebound\Mail\TemplateStore;
use CartRebound\Support\Settings;
use CartRebound\Tests\TestCase;

/**
 * @covers \CartRebound\Mail\TemplateStore
 */
final class TemplateStoreTest extends TestCase {

	/** @var array<string, mixed> */
	private $options = array();

	/** @var int */
	private $counter = 0;

	protected function set_up(): void {
		parent::set_up();

		$this->options = array();
		$this->counter = 0;

		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'wpautop' )->returnArg();
		Functions\when( '__' )->returnArg();
		Functions\when( 'wp_generate_uuid4' )->alias(
			function () {
				++$this->counter;

				return 'id-' . $this->counter;
			}
		);
		Functions\when( 'get_option' )->alias(
			function ( $key, $fallback = false ) {
				return $this->options[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( $key, $value ) {
				$this->options[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			function ( $key ) {
				unset( $this->options[ $key ] );

				return true;
			}
		);
	}

	private function store(): TemplateStore {
		return new TemplateStore( new Settings() );
	}

	public function test_all_seeds_and_persists_a_default_when_empty(): void {
		$all = $this->store()->all();

		$this->assertCount( 1, $all );
		$this->assertSame( 'Default', $all[0]['name'] );
		$this->assertTrue( $all[0]['is_default'] );
		$this->assertArrayHasKey( TemplateStore::OPTION, $this->options );
	}

	public function test_create_keeps_the_existing_default(): void {
		$store = $this->store();

		$created = $store->create(
			array(
				'name'    => 'Promo',
				'subject' => 'Hi',
				'body'    => '<p>x</p>',
			)
		);

		$this->assertFalse( $created['is_default'] );
		$this->assertCount( 2, $store->all() );
	}

	public function test_create_as_default_switches_the_default(): void {
		$store = $this->store();

		$store->create(
			array(
				'name'       => 'Promo',
				'subject'    => 'Hi',
				'is_default' => true,
			)
		);

		$defaults = array_values( array_filter( $store->all(), static fn( $t ) => $t['is_default'] ) );

		$this->assertCount( 1, $defaults );
		$this->assertSame( 'Promo', $defaults[0]['name'] );
	}

	public function test_set_default_moves_the_flag(): void {
		$store = $this->store();
		$promo = $store->create(
			array(
				'name'    => 'Promo',
				'subject' => 'Hi',
			)
		);

		$this->assertTrue( $store->set_default( $promo['id'] ) );
		$this->assertSame( $promo['id'], $store->default()['id'] );
	}

	public function test_delete_refuses_the_default(): void {
		$store  = $this->store();
		$seeded = $store->all()[0];

		$this->assertFalse( $store->delete( $seeded['id'] ) );
		$this->assertCount( 1, $store->all() );
	}

	public function test_delete_removes_a_non_default(): void {
		$store = $this->store();
		$promo = $store->create(
			array(
				'name'    => 'Promo',
				'subject' => 'Hi',
			)
		);

		$this->assertTrue( $store->delete( $promo['id'] ) );

		$remaining = $store->all();
		$this->assertCount( 1, $remaining );
		$this->assertTrue( $remaining[0]['is_default'] );
	}

	public function test_update_changes_fields(): void {
		$store  = $this->store();
		$seeded = $store->all()[0];

		$updated = $store->update(
			$seeded['id'],
			array(
				'name'    => 'Renamed',
				'subject' => 'S',
				'body'    => '<p>b</p>',
			)
		);

		$this->assertNotNull( $updated );
		$this->assertSame( 'Renamed', $updated['name'] );
	}

	public function test_update_unknown_id_returns_null(): void {
		$this->assertNull( $this->store()->update( 'nope', array( 'name' => 'x' ) ) );
	}

	public function test_table_config_defaults_when_absent(): void {
		$config = TemplateStore::table_config( null );

		$this->assertFalse( $config['enabled'] );
		$this->assertSame( 'lined', $config['style'] );
		$this->assertSame( array( 'name', 'quantity', 'subtotal' ), $config['columns'] );
		$this->assertSame( 48, $config['image_size'] );
		$this->assertTrue( $config['show_header'] );
		$this->assertSame( 0, $config['max_items'] );
	}

	public function test_table_config_keeps_column_order_and_drops_unknown(): void {
		$config = TemplateStore::table_config(
			array(
				'columns' => array( 'subtotal', 'image', 'bogus', 'image' ),
			)
		);

		$this->assertSame( array( 'subtotal', 'image' ), $config['columns'] );
	}

	public function test_table_config_falls_back_when_every_column_is_invalid(): void {
		$config = TemplateStore::table_config( array( 'columns' => array( 'nope' ) ) );

		$this->assertSame( array( 'name', 'quantity', 'subtotal' ), $config['columns'] );
	}

	public function test_table_config_clamps_style_size_and_max_items(): void {
		$config = TemplateStore::table_config(
			array(
				'style'      => 'neon',
				'image_size' => 999,
				'max_items'  => -4,
			)
		);

		$this->assertSame( 'lined', $config['style'] );
		$this->assertSame( 48, $config['image_size'] );
		$this->assertSame( 0, $config['max_items'] );
	}

	public function test_saved_template_carries_its_table_options(): void {
		$store = $this->store();

		$created = $store->create(
			array(
				'name'    => 'Boxed',
				'subject' => 'S',
				'table'   => array(
					'enabled'    => true,
					'style'      => 'boxed',
					'columns'    => array( 'image', 'name' ),
					'image_size' => 64,
					'max_items'  => 3,
				),
			)
		);

		$this->assertTrue( $created['table']['enabled'] );
		$this->assertSame( 'boxed', $created['table']['style'] );
		$this->assertSame( array( 'image', 'name' ), $created['table']['columns'] );
		$this->assertSame( 64, $created['table']['image_size'] );
		$this->assertSame( 3, $created['table']['max_items'] );
	}
}
