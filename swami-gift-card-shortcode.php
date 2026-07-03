<?php
/**
 * Plugin Name: Swami Gift Card Shortcode
 * Description: Adds a premium Swami Ritual Thai gift-card shortcode with WooCommerce checkout and recipient email delivery.
 * Version: 1.1.5
 * Author: Dev
 * Text Domain: swami-gift-card
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Swami_Gift_Card_Shortcode {
	const PRODUCT_OPTION = 'swami_gift_card_product_id';
	const NONCE_ACTION   = 'swami_gift_card_purchase';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );

		add_shortcode( 'swami_gift_card', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'template_redirect', array( $this, 'handle_purchase' ) );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_gift_card_price' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'send_gift_card_email' ) );
		add_action( 'woocommerce_payment_complete', array( $this, 'send_gift_card_email' ) );
		add_action( 'woocommerce_order_status_on-hold', array( $this, 'send_gift_card_email' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'send_gift_card_email' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'send_gift_card_email' ) );
		add_action( 'swami_gift_card_send_scheduled_email', array( $this, 'send_scheduled_gift_card_email' ) );
	}

	public function activate() {
		if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Product_Simple' ) ) {
			return;
		}

		$product_id = absint( get_option( self::PRODUCT_OPTION ) );
		if ( $product_id && get_post( $product_id ) ) {
			return;
		}

		$product = new WC_Product_Simple();
		$product->set_name( 'Tarjeta Regalo Swami Ritual Thai' );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_sold_individually( true );
		$product->set_regular_price( 45 );
		$product->set_price( 45 );
		$product->set_description( 'Gift card generated from the Swami Ritual Thai gift-card shortcode.' );
		$product_id = $product->save();

		if ( $product_id ) {
			update_option( self::PRODUCT_OPTION, $product_id );
		}
	}

	public function register_assets() {
		wp_register_style(
			'swami-gift-card',
			plugins_url( 'assets/swami-gift-card.css', __FILE__ ),
			array(),
			'1.1.5'
		);

		wp_register_script(
			'swami-gift-card',
			plugins_url( 'assets/swami-gift-card.js', __FILE__ ),
			array(),
			'1.1.5',
			true
		);
	}

	public function render_shortcode( $atts = array() ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return '<div class="swami-gift-card-error">WooCommerce needs to be active for the gift card checkout.</div>';
		}

		$atts = shortcode_atts(
			array(
				'hero_image' => '',
				'card_image' => '',
			),
			$atts,
			'swami_gift_card'
		);

		wp_enqueue_style( 'swami-gift-card' );
		wp_enqueue_script( 'swami-gift-card' );

		$services = $this->get_services();
		$selected = $services[0];
		$hero     = esc_url( $atts['hero_image'] );
		$card     = esc_url( $atts['card_image'] );

		ob_start();
		?>
		<section class="swami-gift-card" style="<?php echo $hero ? '--swami-hero-image:url(' . esc_url( $hero ) . ');' : ''; ?> <?php echo $card ? '--swami-card-image:url(' . esc_url( $card ) . ');' : ''; ?>">
			<div class="swami-gift-card__hero">
				<div class="swami-gift-card__inner">
					<h1>Tarjeta Regalo</h1>
					<div class="swami-gift-card__divider" aria-hidden="true"><span></span><i>✦</i><span></span></div>
					<p>Regala bienestar, equilibrio y un momento solo para ellos.</p>
				</div>
			</div>

			<div class="swami-gift-card__inner swami-gift-card__layout">
				<form class="swami-gift-card__form" method="post">
					<?php wp_nonce_field( self::NONCE_ACTION, 'swami_gift_card_nonce' ); ?>
					<input type="hidden" name="swami_gift_card_action" value="purchase">
					<input type="hidden" name="swami_service_name" value="<?php echo esc_attr( $selected['name'] ); ?>" data-swami-service-name>
					<input type="hidden" name="swami_service_minutes" value="<?php echo esc_attr( $selected['minutes'] ); ?>" data-swami-service-minutes>
					<input type="hidden" name="swami_service_price" value="<?php echo esc_attr( $selected['price'] ); ?>" data-swami-service-price>

					<div class="swami-gift-card__section-title"><span>✧</span><strong>1. Elige tu masaje</strong></div>
					<div class="swami-gift-card__services">
						<?php foreach ( $services as $index => $service ) : ?>
							<label class="swami-gift-card__service <?php echo 0 === $index ? 'is-selected' : ''; ?>">
								<input type="radio" name="swami_service_key" value="<?php echo esc_attr( $service['key'] ); ?>" <?php checked( 0, $index ); ?> data-name="<?php echo esc_attr( $service['name'] ); ?>" data-minutes="<?php echo esc_attr( $service['minutes'] ); ?>" data-price="<?php echo esc_attr( $service['price'] ); ?>" data-icon="<?php echo esc_attr( $service['icon'] ); ?>">
								<?php if ( ! empty( $service['badge'] ) ) : ?>
									<span class="swami-gift-card__badge"><?php echo esc_html( $service['badge'] ); ?></span>
								<?php endif; ?>
								<span class="swami-gift-card__check">✓</span>
								<span class="swami-gift-card__service-icon"><?php echo $this->service_icon( $service['key'] ); ?></span>
								<strong><?php echo esc_html( $service['name'] ); ?></strong>
								<small><?php echo esc_html( $service['minutes'] ); ?> min</small>
								<b>€<?php echo esc_html( $service['price'] ); ?></b>
							</label>
						<?php endforeach; ?>
					</div>
					<div class="swami-gift-card__whatsapp-note">
						<span><i class="swami-gift-card__whatsapp-icon" aria-hidden="true"><?php echo $this->whatsapp_icon(); ?></i> Para regalar sesiones dobles de 105 minutos, contáctanos por WhatsApp.</span>
						<a href="https://wa.me/34615219202" target="_blank" rel="noopener"><i class="swami-gift-card__whatsapp-icon" aria-hidden="true"><?php echo $this->whatsapp_icon(); ?></i> Contactar por WhatsApp</a>
					</div>

					<div class="swami-gift-card__section-title"><span>♙</span><strong>2. Datos del envío</strong></div>
					<div class="swami-gift-card__fields">
						<label>Para <input type="text" name="swami_recipient_name" placeholder="Nombre del destinatario" required data-preview-recipient></label>
						<label>Email del destinatario <input type="email" name="swami_recipient_email" placeholder="email@ejemplo.com" required></label>
						<label>De <input type="text" name="swami_sender_name" placeholder="Tu nombre" required data-preview-sender></label>
						<label>Fecha de entrega (opcional) <input type="date" name="swami_delivery_date"></label>
					</div>

					<div class="swami-gift-card__section-title"><span>✉</span><strong>3. Mensaje personalizado</strong></div>
					<div class="swami-gift-card__message-wrap">
						<textarea name="swami_message" maxlength="250" placeholder="Escribe tu mensaje aquí..." data-preview-message></textarea>
						<small><span data-message-count>0</span> / 250</small>
					</div>

					<div class="swami-gift-card__quick-messages" aria-label="Mensajes rápidos">
						<button type="button" data-message-template="Disfruta de un momento de calma, bienestar y renovación.">Bienestar</button>
						<button type="button" data-message-template="Un regalo para que te cuides y desconectes como mereces.">Cuidado</button>
						<button type="button" data-message-template="Con mucho cariño, te regalo una experiencia para recordar.">Cariño</button>
						<button type="button" data-message-template="Feliz día. Que este ritual te llene de energía y equilibrio.">Celebración</button>
					</div>

					<button class="swami-gift-card__submit" type="submit">✈ Comprar y enviar tarjeta regalo</button>
					<p class="swami-gift-card__secure">▣ Pago seguro y protegido</p>
				</form>

				<aside class="swami-gift-card__preview" aria-label="Vista previa de tu tarjeta regalo">
					<div class="swami-gift-card__preview-title">◉ Vista previa de tu tarjeta regalo</div>
					<div class="swami-gift-card__card">
						<div class="swami-gift-card__card-left">
							<h2>TARJETA<br>REGALO</h2>
							<p>Para:</p>
							<strong data-card-recipient>______________________</strong>
							<p>De:</p>
							<strong data-card-sender>______________________</strong>
							<em data-card-message></em>
						</div>
						<div class="swami-gift-card__card-right">
							<h3 data-card-service><?php echo esc_html( $selected['name'] ); ?></h3>
							<div class="swami-gift-card__line"></div>
							<div class="swami-gift-card__brand">
								<b>SWAMI</b>
								<span>Ritual Thai</span>
							</div>
							<p>C/ Castellví, 8 50004</p>
							<small>Reservas:<br>www.swamiritual.com • 615219202</small>
						</div>
					</div>
					<div class="swami-gift-card__features">
						<span>✉ <b>Envío por email</b><small>rápido y automático</small></span>
						<span>▣ <b>Diseño premium</b><small>exclusivo</small></span>
						<span>▢ <b>Pago 100%</b><small>seguro</small></span>
						<span>◷ <b>Válida por</b><small>6 meses</small></span>
					</div>
					<p class="swami-gift-card__note">♢ Sin fees ocultos. Cambios y reenvíos gratuitos.</p>
				</aside>
			</div>
		</section>
		<?php

		return ob_get_clean();
	}

	public function handle_purchase() {
		if ( empty( $_POST['swami_gift_card_action'] ) || 'purchase' !== $_POST['swami_gift_card_action'] ) {
			return;
		}

		if ( empty( $_POST['swami_gift_card_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['swami_gift_card_nonce'] ) ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed.', 'swami-gift-card' ) );
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_die( esc_html__( 'WooCommerce cart is unavailable.', 'swami-gift-card' ) );
		}

		$product_id = $this->ensure_product();
		if ( ! $product_id ) {
			wp_die( esc_html__( 'Gift card product could not be created.', 'swami-gift-card' ) );
		}

		$service = $this->find_service( sanitize_key( wp_unslash( $_POST['swami_service_key'] ?? '' ) ) );
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $product_id, 1, 0, array(), $this->posted_gift_data( $service ) );
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		if ( ! empty( $cart_item_data['swami_gift_card'] ) ) {
			return $cart_item_data;
		}

		return $cart_item_data;
	}

	public function display_cart_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['swami_gift_card'] ) ) {
			return $item_data;
		}

		foreach ( $this->meta_labels() as $key => $label ) {
			if ( ! empty( $cart_item['swami_gift_card'][ $key ] ) ) {
				$item_data[] = array(
					'name'  => $label,
					'value' => esc_html( $cart_item['swami_gift_card'][ $key ] ),
				);
			}
		}

		return $item_data;
	}

	public function apply_gift_card_price( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['swami_gift_card']['raw_price'] ) || empty( $cart_item['data'] ) ) {
				continue;
			}

			$cart_item['data']->set_price( (float) $cart_item['swami_gift_card']['raw_price'] );
		}
	}

	public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values['swami_gift_card'] ) ) {
			return;
		}

		foreach ( $this->meta_labels() as $key => $label ) {
			if ( ! empty( $values['swami_gift_card'][ $key ] ) ) {
				$item->add_meta_data( $label, $values['swami_gift_card'][ $key ], true );
			}
		}
	}

	public function send_scheduled_gift_card_email( $order_id ) {
		$this->send_gift_card_email( $order_id, true );
	}

	public function send_gift_card_email( $order_id, $force_delivery_date = false ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_swami_gift_card_email_sent' ) ) {
			return;
		}

		$sent_count = 0;
		foreach ( $order->get_items() as $item ) {
			$email = $item->get_meta( 'Email del destinatario' );
			if ( ! $email || ! is_email( $email ) ) {
				continue;
			}

			$recipient = $item->get_meta( 'Para' );
			$sender    = $item->get_meta( 'De' );
			$service   = $item->get_meta( 'Masaje' );
			$duration  = $item->get_meta( 'Duracion' );
			if ( ! $duration ) {
				$duration = $item->get_meta( 'Duración' );
			}
			$price     = $item->get_meta( 'Precio' );
			$message   = $item->get_meta( 'Mensaje' );
			$delivery  = $item->get_meta( 'Fecha de entrega' );

			$delivery_timestamp = $delivery ? strtotime( $delivery . ' 09:00:00' ) : 0;
			if ( ! $force_delivery_date && $delivery_timestamp > current_time( 'timestamp' ) ) {
				if ( ! wp_next_scheduled( 'swami_gift_card_send_scheduled_email', array( $order_id ) ) ) {
					wp_schedule_single_event( $delivery_timestamp, 'swami_gift_card_send_scheduled_email', array( $order_id ) );
				}
				continue;
			}

			$subject = 'Tu Tarjeta Regalo Swami Ritual Thai';
			$body    = $this->email_body( $recipient, $sender, $service, $duration, $price, $delivery, $message, $order->get_order_number() );
			$sent    = wp_mail( $email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );

			if ( $sent ) {
				$order->add_order_note( 'Swami gift card email sent to ' . $email . '.' );
				$sent_count++;
			} else {
				$order->add_order_note( 'Swami gift card email failed for ' . $email . '. Check WP Mail SMTP authentication. Gift card data is saved on the order item.' );
			}
		}

		if ( $sent_count > 0 ) {
			$order->update_meta_data( '_swami_gift_card_email_sent', current_time( 'mysql' ) );
			$order->save();
		}
	}

	private function ensure_product() {
		$product_id = absint( get_option( self::PRODUCT_OPTION ) );
		if ( $product_id && get_post( $product_id ) ) {
			return $product_id;
		}

		$this->activate();
		return absint( get_option( self::PRODUCT_OPTION ) );
	}

	private function posted_gift_data( $service ) {
		return array(
			'swami_gift_card' => array(
				'service'         => $service['name'],
				'minutes'         => $service['minutes'] . ' min',
				'price'           => '€' . $service['price'],
				'raw_price'       => $service['price'],
				'recipient_name'  => sanitize_text_field( wp_unslash( $_POST['swami_recipient_name'] ?? '' ) ),
				'recipient_email' => sanitize_email( wp_unslash( $_POST['swami_recipient_email'] ?? '' ) ),
				'sender_name'     => sanitize_text_field( wp_unslash( $_POST['swami_sender_name'] ?? '' ) ),
				'delivery_date'   => sanitize_text_field( wp_unslash( $_POST['swami_delivery_date'] ?? '' ) ),
				'message'         => sanitize_textarea_field( wp_unslash( $_POST['swami_message'] ?? '' ) ),
			),
			'unique_key'       => wp_generate_uuid4(),
		);
	}

	private function meta_labels() {
		return array(
			'service'         => 'Masaje',
			'minutes'         => 'Duracion',
			'price'           => 'Precio',
			'recipient_name'  => 'Para',
			'recipient_email' => 'Email del destinatario',
			'sender_name'     => 'De',
			'delivery_date'   => 'Fecha de entrega',
			'message'         => 'Mensaje',
		);
	}

	private function get_services() {
		return array(
			array( 'key' => 'thai', 'name' => 'Masaje Tailandés', 'minutes' => 55, 'price' => 45, 'icon' => '♙' ),
			array( 'key' => 'kobido', 'name' => 'Masaje facial kobido', 'minutes' => 45, 'price' => 40, 'icon' => '⚗' ),
			array( 'key' => 'personalizado_60', 'name' => 'Masaje completo personalizado', 'minutes' => 60, 'price' => 45, 'icon' => '●' ),
			array( 'key' => 'aromatherapy_full', 'name' => 'Masaje completo con aromaterapia', 'minutes' => 90, 'price' => 50, 'icon' => '♡', 'badge' => 'NUEVO' ),
		);
	}

	private function find_service( $key ) {
		foreach ( $this->get_services() as $service ) {
			if ( $service['key'] === $key ) {
				return $service;
			}
		}

		$services = $this->get_services();
		return $services[0];
	}

	private function whatsapp_icon() {
		return '<svg viewBox="0 0 32 32" focusable="false" aria-hidden="true"><path d="M16 3.2A12.7 12.7 0 0 0 5.1 22.4L3.7 28.8l6.5-1.7A12.7 12.7 0 1 0 16 3.2Zm0 2.4a10.3 10.3 0 0 1 8.8 15.6 10.3 10.3 0 0 1-13.9 3.5l-.5-.3-3.5.9.8-3.4-.3-.5A10.3 10.3 0 0 1 16 5.6Zm-4.1 5.2c-.3 0-.8.1-1.2.6-.4.5-1.5 1.5-1.5 3.6s1.6 4.2 1.8 4.5c.2.3 3.1 4.8 7.6 6.5 3.8 1.5 4.5.8 5.3.7.8-.1 2.7-1.1 3-2.2.4-1.1.4-2 .3-2.2-.1-.2-.4-.3-.8-.5l-2.9-1.4c-.4-.2-.7-.2-1 .2-.3.4-1.1 1.4-1.4 1.7-.3.3-.5.3-.9.1-.4-.2-1.8-.7-3.4-2.1-1.3-1.1-2.1-2.5-2.4-2.9-.3-.4 0-.7.2-.9.2-.2.4-.5.6-.7.2-.2.3-.4.4-.7.1-.3 0-.5 0-.7l-1.3-3.1c-.3-.8-.7-.7-1-.7h-1.4Z"/></svg>';
	}

	private function service_icon( $key ) {
		$icons = array(
			'thai' => '<svg viewBox="0 0 64 64" focusable="false" aria-hidden="true"><path d="M22 45h29a7 7 0 0 1 7 7v2H14v-2a7 7 0 0 1 7-7Z"/><path d="M15 45c3-6 8-9 15-9h12"/><path d="M40 22c0 4-3 7-7 7s-7-3-7-7 3-7 7-7 7 3 7 7Z"/><path d="M12 34c4-5 9-7 16-7"/><path d="M52 34c-4-5-9-7-16-7"/><path d="M24 39c2 2 5 3 8 3s6-1 8-3"/></svg>',
			'kobido' => '<svg viewBox="0 0 64 64" focusable="false" aria-hidden="true"><path d="M22 21c3-5 17-5 20 0 3 6 2 19-1 25-2 5-6 8-9 8s-7-3-9-8c-3-6-4-19-1-25Z"/><path d="M25 33h.1M39 33h.1"/><path d="M28 43c3 2 5 2 8 0"/><path d="M17 34c5-1 8-4 10-9"/><path d="M47 34c-5-1-8-4-10-9"/><path d="M14 43c7-1 12-5 15-11"/><path d="M50 43c-7-1-12-5-15-11"/></svg>',
			'personalizado_60' => '<svg viewBox="0 0 64 64" focusable="false" aria-hidden="true"><path d="M11 43h31c7 0 12 5 12 11H11V43Z"/><path d="M18 37c4-5 13-6 20-2"/><path d="M41 28c4-4 11-4 15 1"/><path d="M46 24c4 4 5 9 3 14"/><path d="M18 49h29"/><path d="M16 31c3-3 7-5 12-5"/></svg>',
			'aromatherapy_full' => '<svg viewBox="0 0 64 64" focusable="false" aria-hidden="true"><path d="M17 36h30c0 10-6 17-15 17s-15-7-15-17Z"/><path d="M12 36h40"/><path d="M23 27c-3-4 3-7 0-11"/><path d="M32 27c-3-4 3-7 0-11"/><path d="M41 27c-3-4 3-7 0-11"/><path d="M47 19c6-2 10 1 12 7-6 1-10-2-12-7Z"/><path d="M17 19c-6-2-10 1-12 7 6 1 10-2 12-7Z"/><path d="M25 44h14"/></svg>',
		);

		return $icons[ $key ] ?? $icons['thai'];
	}

	private function email_body( $recipient, $sender, $service, $duration, $price, $delivery, $message, $order_number ) {
		$recipient = esc_html( $recipient ?: 'Hola' );
		$sender    = esc_html( $sender ?: 'Swami Ritual Thai' );
		$service   = esc_html( $service ?: 'Tarjeta Regalo' );
		$duration  = esc_html( $duration );
		$price     = esc_html( $price );
		$delivery  = esc_html( $delivery );
		$message   = nl2br( esc_html( $message ) );

		return '
			<div style="font-family:Arial,sans-serif;background:#f7f2ea;padding:32px;color:#4f4d4a">
				<div style="max-width:620px;margin:auto;background:#fff;border:1px solid #e6d8c0;padding:34px">
					<h1 style="font-family:Georgia,serif;font-size:42px;margin:0;color:#54514f">TARJETA REGALO</h1>
					<h2 style="font-family:Georgia,serif;color:#bf8718;margin:20px 0 8px">SWAMI Ritual Thai</h2>
					<p>Para: <strong>' . $recipient . '</strong></p>
					<p>De: <strong>' . $sender . '</strong></p>
					<p>Masaje: <strong>' . $service . '</strong></p>
					' . ( $duration ? '<p>Duracion: <strong>' . $duration . '</strong></p>' : '' ) . '
					' . ( $price ? '<p>Precio: <strong>' . $price . '</strong></p>' : '' ) . '
					' . ( $delivery ? '<p>Fecha de entrega: <strong>' . $delivery . '</strong></p>' : '' ) . '
					' . ( $message ? '<p style="border-top:1px solid #eadfcf;padding-top:18px;line-height:1.55">' . $message . '</p>' : '' ) . '
					<p style="margin-top:26px;font-size:13px;color:#777">Pedido #' . esc_html( $order_number ) . ' · Válida por 6 meses.</p>
				</div>
			</div>';
	}
}

Swami_Gift_Card_Shortcode::instance();
