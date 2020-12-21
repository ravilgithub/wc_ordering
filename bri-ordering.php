<?php
/**
 * Plugin Name: BRI Ordering
 * Plugin URI:  http://www.yandex.ru
 * Description: BRI Ordering
 * Version:     1.0.2
 * Author:      Ravil
 * Author URI:  http://www.tstudio.zzz.com.ua
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Bri_product_ordering {
	public $ordering_vars = array();
	public function __construct() {
		$this->get_ordering_vars();

		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );
		add_action( 'init', array( $this, 'remove_default_actions' ), 10 );

		add_filter( 'woocommerce_get_catalog_ordering_args', array( $this, 'filter_woocommerce_get_catalog_ordering_args' ), 10, 1 );
		add_action( 'woocommerce_before_shop_loop', array( $this, 'ordering' ), 28 );
		
		add_filter( 'loop_shop_columns', array( $this, 'set_default_loop_shop_columns' ) );
		add_filter( 'loop_shop_per_page', array( $this, 'sort_by_page' ), 999 );
		

		// Удаление функции "Woocommerce" - "Продуктов в строке" - из настроек ( Customizer'a )
		// add_action( 'customize_register', 'remove_woocommerce_customize_items', 9999 );

	}

	// Удаление функции "Woocommerce" - "Продуктов в строке" - из настроек ( Customizer'a )
	/*function remove_woocommerce_customize_items( $wp_customize ) {
		$wp_customize->remove_section( 'woocommerce_catalog_rows' );
		$wp_customize->remove_control( 'woocommerce_catalog_rows' );
		$wp_customize->remove_panel( 'woocommerce_catalog_rows' );
	}*/


	// Get query variables
	public function get_ordering_vars() {
		if ( isset( $_GET[ 'orderby' ] ) ) {
			$this->ordering_vars[ 'orderby' ] = strtolower( wc_clean( $_GET[ 'orderby' ] ) );
			setcookie( 'orderby', $this->ordering_vars[ 'orderby' ] );
		} elseif ( isset( $_COOKIE[ 'orderby' ] ) ) {
			$this->ordering_vars[ 'orderby' ] = strtolower( wc_clean( $_COOKIE[ 'orderby' ] ) );
		} else {
			$this->ordering_vars[ 'orderby' ] = '';
		}

		if ( isset( $_GET[ 'product_count' ] ) ) {
			$this->ordering_vars[ 'count' ] = absint( wc_clean( $_GET[ 'product_count' ] ) );
			setcookie( 'product_count', $this->ordering_vars[ 'count' ] );
		} elseif ( isset( $_COOKIE[ 'product_count' ] ) ) {
			$this->ordering_vars[ 'count' ] = absint( wc_clean( $_COOKIE[ 'product_count' ] ) );
		} else {
			$this->ordering_vars[ 'count' ] = 0;
		}

		if ( isset( $_GET[ 'product_order' ] ) ) {
			$this->ordering_vars[ 'order' ] = strtoupper( wc_clean( $_GET[ 'product_order' ] ) );
			setcookie( 'product_order', $this->ordering_vars[ 'order' ] );
		} elseif ( isset( $_COOKIE[ 'product_order' ] ) ) {
			$this->ordering_vars[ 'order' ] = strtoupper( wc_clean( $_COOKIE[ 'product_order' ] ) );
		} else {
			$this->ordering_vars[ 'order' ] = '';
		}
	}

	// Adding CSS and Scripts
	public function scripts() {
		wp_enqueue_style( 'bri-ordering-css', plugins_url( 'css/bri-ordering.css', __FILE__ ) );
		wp_enqueue_script( 'bri-ordering-js', plugins_url( 'js/bri-ordering.js', __FILE__ ), array( 'jquery' ), '1.0', true );
	}

	// Remove default catalog ordering
	public function remove_default_actions() {
		remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
	}

	/**
		Удаление функции "Woocommerce" - "Продуктов в строке" - из настроек ( Customizer'a )
		Если указать фильтр для события "loop_shop_columns" то удалится настройка "Продуктов в строке"
		Условие:
			if ( has_filter( 'loop_shop_columns' ) ) {
				return;
			}
		в файле - /home/ravil/www/wordpress/wpfirst/wp-content/plugins/woocommerce/includes/customizer/class-wc-shop-customizer.php
	*/
	public function set_default_loop_shop_columns( $count ) {
		$count = get_option( 'posts_per_page' );
		return $count;
	}

	// How mutch products on page
	public function sort_by_page( $count ) {
		if ( $this->ordering_vars[ 'count' ] ) {
			$count = $this->ordering_vars[ 'count' ];
		} else {
			// Можно определить фильтр "bri_custom_loop_shop_per_page" в "functions.php"
			$count = apply_filters( 'bri_custom_loop_shop_per_page', get_option( 'posts_per_page' ) );
			$this->ordering_vars[ 'count' ] = $count;
		}
		return $count;
	}
	
	// Adding the ability to sort products in descending order and ascending
	public function get_popular_products( $args ) {
		global $wpdb;
		$order = ( $this->ordering_vars[ 'order' ] ) ? $this->ordering_vars[ 'order' ] : 'DESC';
		$args['orderby'] = "$wpdb->postmeta.meta_value+0 $order, $wpdb->posts.post_date $order";

		$this->debug( $args ); // !!!

		return $args;
	}

	// Переопределение сортировки по популярности и рейтингу, а именно добавление возможности сортировки по возрастанию и убыванию
	// Redefine sorting by popularity and rating, namely adding the ability to sort in ascending and descending order
	public function filter_woocommerce_get_catalog_ordering_args( $args ) {
		if ( $this->ordering_vars[ 'order' ] ) {
			$order = $args[ 'order' ] = $this->ordering_vars[ 'order' ];
			$reverse_order = ( 'DESC' === $order ) ? 'ASC' : $order;
			if ( $this->ordering_vars[ 'orderby' ] ) {
				switch ( $this->ordering_vars[ 'orderby' ] ) {
					case 'popularity' :
						$args['meta_key'] = 'total_sales';
						add_filter( 'posts_clauses', array( $this, 'get_popular_products' ) );
					break; 
					case 'rating' :
						$args['meta_key'] = '_wc_average_rating'; 
						$args['orderby'] = array( 
							'meta_value_num' => $order,  
							'ID' => $reverse_order, // Всегда ASC
						); 
					break;
				}
			}
		}

		$this->debug( $args ); // !!!

		return $args;
	}

	/**
	* Если в разделе "Постоянные ссылки" настроек "WordPress" установленны "URL" отличые от простых, то текущая страница будет указана в суффиксе "path" части "URL" ( например: /page/2 ) если текущая страница > 1.
	* Функция возвращает массив:
		1. С постраничной навигацией( строка запроса без изменений )
		2. Без постраничной навигацией( отсутствие суффикса "path" части "URL" означает - показывай 1ю страницу )
	*/
	public function get_url_path() {
		$query_path = array();

		$cur_url = $_SERVER[ 'REQUEST_URI' ];
		$url_path = parse_url( $cur_url, PHP_URL_PATH );
		$paged_pos = strrpos( $url_path, '/page/' );

		$query_path[ 'no_paged' ] = $url_path;
		$query_path[ 'paged' ] = $url_path;

		if ( FALSE !== $paged_pos ) {
			$query_path[ 'no_paged' ] = substr( $url_path, 0, $paged_pos );
		} 

		return $query_path;
	}

	/**
	* Если в разделе "Постоянные ссылки" настроек "WordPress" установленны "Простые" "URL", то текущая страница будет указана в параметре "paged", если текущая страница > 1.
	* Функция возвращает массив:
		1. С постраничной навигацией( строка запроса без изменений )
		2. Без постраничной навигацией( отсутствие параметра "paged" означает - показывай 1ю страницу )
	*/
	public function get_url_options() {
		$exclude_props = array( 'orderby', 'product_count', 'product_order' );
		$query_prop = array();
		$paged = '';
		$no_paged = '';

		if ( ! empty( $_GET ) ) {
			foreach ( $_GET as $prop => $value ) {
				if ( in_array( $prop, $exclude_props ) ) {
					continue;
				} elseif ( 'paged' === $prop ) {
					$paged = $prop . '=' . $value . '&';
				} else {
					$no_paged .= $prop . '=' . $value . '&';
				}
			}
		}

		$query_prop[ 'no_paged' ] = $no_paged;
		$query_prop[ 'paged' ] = $no_paged . $paged;
		return $query_prop;
	}

	/**
	* Иногда при сортировке товаров можно попасть на страницу 404.
	* Происходит это в результате выбора отображения количества товаров больше чем текущее значение( например: было 6 товаров, выбрали 36 ), находясь на странице > 1.
	* Чтобы этого избежать нужно при выборе отображения количества товаров( product_count ) на странице отображалась всёгда 1я страница, а при выборе другого варианта сортировки отображалась текущая.
	* Для этого текущий URL нужно получть в 2х вариантах:
		1. С постраничной навигацией ( соритируем и остаёмся на текущей странице )
		2. Без постраничной навигации ( соритируем, но переходим на 1ю страницу )
	*/
	public function escape_from_404() {
		$path = $this->get_url_path();

		$this->debug( $path, 'path' ); // !!!

		$options = $this->get_url_options();

		$this->debug( $options, 'options' ); // !!!

		$result = array();
		// С постраничной навигацией
		$result[ 'paged' ] = $path[ 'paged' ] . '?' . $options[ 'paged' ];
		// Без постраничной навигации
		$result[ 'no_paged' ] = $path[ 'no_paged' ] . '?' . $options[ 'no_paged' ];

		return $result;
	}

	// Create ordering menu
	public function ordering() {
		global $wp_query;
		$order = 'ASC';
		$label = 'Ascending';
		$link_order = 'DESC';
		$arrow = 'up';

		// $shop_url = get_permalink( wc_get_page_id( 'shop' ) );
		// $shop_url = get_permalink( get_option( 'woocommerce_shop_page_id' ) );

		if ( 1 === (int) $wp_query->found_posts || ! woocommerce_products_will_display() ) {
			return;
		}

		$orderby = ( $this->ordering_vars[ 'orderby' ] ) ? $this->ordering_vars[ 'orderby' ] : apply_filters( 'woocommerce_default_catalog_orderby', get_option( 'woocommerce_default_catalog_orderby' ) );
		$show_default_orderby = 'menu_order' === apply_filters( 'woocommerce_default_catalog_orderby', get_option( 'woocommerce_default_catalog_orderby' ) );
		$catalog_orderby_options = apply_filters( 'woocommerce_catalog_orderby', array(
			'menu_order' => __( 'Default sorting', 'woocommerce' ),
			'popularity' => __( 'Sort by popularity', 'woocommerce' ),
			'rating'     => __( 'Sort by average rating', 'woocommerce' ),
			'date'       => __( 'Sort by newness', 'woocommerce' ),
			'price'      => __( 'Sort by price', 'woocommerce' ),
		) );

		if ( ! $show_default_orderby ) {
			unset( $catalog_orderby_options['menu_order'] );
		}

		if ( 'no' === get_option( 'woocommerce_enable_review_rating' ) ) {
			unset( $catalog_orderby_options['rating'] );
		}

		if ( $this->ordering_vars[ 'order' ] ) {
			$order = $this->ordering_vars[ 'order' ];
			if ( 'DESC' === $order ) {
				$label = 'Descending';
				$link_order = 'ASC';
				$arrow = 'down';
			}
		}

		$shopCatalog_orderby = apply_filters( 'woocommerce_sortby_page', array(
			'3'	=> __( '3 per page', 'woocommerce' ),
			'6' => __( '6 per page', 'woocommerce' ),
			'12' => __( '12 per page', 'woocommerce' ),
			'24'  => __( '24 per page', 'woocommerce' ),
		) );

		if ( $this->ordering_vars[ 'count' ] ) {
			$count = $this->ordering_vars[ 'count' ];
		} else {
			reset( $shopCatalog_orderby );
			$count = key( $shopCatalog_orderby );
		}

		$url_result_arr = $this->escape_from_404();
		/*echo '<pre>';
		print_r ( $url_result_arr );
		echo '</pre>';*/
?>
		<div class="catalog-ordering-wrap">
			<div class="catalog-ordering clearfix">
				<div class="orderby-order-container">
					<ul class="orderby order-dropdown">
						<li>
							<span class="current-li">
								<span class="current-li-content clearfix">
									<span class="order-angel">
										<i class="fa fa-angle-down" aria-hidden="true"></i>
									</span>
									<a aria-haspopup="true">
										<?php _e( 'Sort by', 'woocommerce' ); ?> <span><?php echo $catalog_orderby_options[ $orderby ]; ?></span>
									</a>
								</span>
							</span>
							<ul>
							<?php foreach ( $catalog_orderby_options as $id => $name ) : ?>
								<?php $class = ( $orderby === $id ) ? 'current' : ''; ?>
								<li class="<?php echo $class; ?>">
									<a href="<?php echo esc_attr( $url_result_arr[ 'paged' ] ); ?>orderby=<?php echo esc_attr( $id ); ?>&product_order=<?php echo esc_attr( $order ); ?>&product_count=<?php echo esc_attr( $count ); ?>">
										<?php _e( 'Sort by', 'woocommerce' ) ?> <span><?php echo esc_html( $name ); ?></span>
									</a>
								</li>
							<?php endforeach; ?>
							</ul>
						</li>
					</ul>
					
					<ul class="order">
						<li class="<?php echo $order; ?>">
							<a aria-label="<?php echo esc_attr( $label ); ?> order" aria-haspopup="true" href="<?php echo esc_attr( $url_result_arr[ 'paged' ] ); ?>orderby=<?php echo esc_attr( $orderby ); ?>&product_order=<?php echo $link_order; ?>&product_count=<?php echo esc_attr( $count ); ?>">
								<!-- <i class="fa fa-arrow-up" aria-hidden="true"></i> -->
								<i class="fa fa-arrow-<?php echo $arrow; ?>" aria-hidden="true"></i>
							</a>
						</li>
					</ul>
				</div>

				<ul class="sort-count order-dropdown">
					<li>
						<span class="current-li">
							<span class="current-li-content clearfix">
								<span class="order-angel">
									<i class="fa fa-angle-down" aria-hidden="true"></i>
								</span>
								<a aria-haspopup="true">
									<?php _e( 'Show', 'woocommerce' ); ?>
									<span><?php echo esc_attr( $count ); ?> <?php _e( 'Products', 'woocommence' ); ?></span>
								</a>
							</span>
						</span>
						<ul>
						<?php foreach ( $shopCatalog_orderby as $id => $name ) :
							if ( '' === $id )
								continue;
							$class = ( $count === $id ) ? 'current' : '';
						?>
							<li class="<?php echo $class; ?>">
								<a href="<?php echo esc_attr( $url_result_arr[ 'no_paged' ] ); ?>orderby=<?php echo esc_attr( $orderby ); ?>&product_order=<?php echo esc_attr( $order ); ?>&product_count=<?php echo esc_attr( $id ); ?>"> <!-- &paged=1 -->
									<?php _e( 'Show', 'woocommerce' ); ?>
									<span><?php echo esc_html( $name ) . __( 'Products', 'woocommerce' ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
						</ul>
					</li>
				</ul>

				<ul class="grid-list-view">
					<li class="grid-view-li">
						<a class="grid-view toggler active" data-toggle-type="grid" aria-label="View as grid" aria-haspopup="true" href="<?php echo esc_attr( $url_result_arr[ 'paged' ] ); ?>orderby=<?php echo esc_attr( $orderby ); ?>&product_order=<?php echo $order; ?>&product_count=<?php echo esc_attr( $count ); ?>"> <!-- <i class="icon-grid icomoon-grid"></i> -->
							<i class="fa fa-th" aria-hidden="true"></i>
						</a>
					</li>
					<li class="list-view-li">
						<a class="list-view toggler" data-toggle-type="list" aria-haspopup="true" aria-label="View as list" href="<?php echo esc_attr( $url_result_arr[ 'paged' ] ); ?>orderby=<?php echo esc_attr( $orderby ); ?>&product_order=<?php echo $order; ?>&product_count=<?php echo esc_attr( $count ); ?>">
							<!-- <i class="icon-list icomoon-list"></i> -->
							<i class="fa fa-th-list" aria-hidden="true"></i>
						</a>
					</li>
				</ul>

			</div>
			<!-- <div class="col-sm-12">
				<p class="woocommerce-result-count">Отображено 1–12 из 23 результатов</p>
				<p class="woocommerce-result-count">1–12 из 23 products</p>
			</div> -->
		</div>
	<?php

			echo '<pre>';
			echo 'Cookie:' . '<br />';
			$__orderby = ! empty( $_COOKIE[ 'orderby' ] ) ? $_COOKIE[ 'orderby' ]: '';
			$__count = ! empty( $_COOKIE[ 'product_count' ] ) ? $_COOKIE[ 'product_count' ]: '';
			$__order = ! empty( $_COOKIE[ 'product_order' ] ) ? $_COOKIE[ 'product_order' ]: '';
			echo 'orderby - ' . $__orderby . '<br />';
			echo 'count - ' . $__count . '<br />';
			echo 'order - ' . $__order . '<br /><br />';
			print_r( $orderby . ' | ' . $order . ' | ' . $count );
			echo '</pre>';

	}

	public function debug( $data, $msg = '' ) {
		if ( $msg )
			echo '<strong>' . $msg . '</strong>';
		echo '<pre>';
		print_r( $data );
		echo '</pre>';
	}
}

// Initializing product ordering plugin
function bri_product_ordering_init() {
	global $bri_product_ordering;
	$bri_product_ordering = new Bri_product_ordering();
}
add_action( 'plugins_loaded', 'bri_product_ordering_init', 99 );
