<?php

if ( ! function_exists( 'yandexCheckoutLoadClass' ) ) {
	require_once 'lib/autoload.php';
}

use YandexCheckout\Client;

add_action( 'rcl_payments_gateway_init', 'rcl_gateway_yandexkassa_api_init', 10 );
function rcl_gateway_yandexkassa_api_init() {
	rcl_gateway_register( 'yandexkassa-api', 'Rcl_API_Yandexkassa_Payment' );
}

class Rcl_API_Yandexkassa_Payment extends Rcl_Gateway_Core {
	function __construct() {
		parent::__construct( array(
			'request'	 => 'ykapi',
			'name'		 => rcl_get_commerce_option( 'ykapi_custom_name', 'Яндекс.Касса' ),
			'submit'	 => __( 'Оплатить через Яндекс.Кассу' ),
			'icon'		 => rcl_addon_url( 'icon.jpg', __FILE__ )
		) );
	}

	function get_options() {

		return array(
			array(
				'type'			 => 'text',
				'slug'			 => 'ykapi_custom_name',
				'title'			 => __( 'Наименование платежной системы' ),
				'placeholder'	 => 'Яндекс.Касса'
			),
			array(
				'type'	 => 'text',
				'slug'	 => 'ykapi_shopid',
				'title'	 => __( 'ID магазина (SHOP ID)' )
			),
			array(
				'type'	 => 'password',
				'slug'	 => 'ykapi_key',
				'title'	 => __( 'Секретный ключ' )
			),
			array(
				'type'		 => 'select',
				'slug'		 => 'ykapi_fn',
				'title'		 => __( 'Фискализация платежа' ),
				'values'	 => array(
					__( 'Отключено' ),
					__( 'Включено' )
				),
				'childrens'	 => array(
					1 => array(
						array(
							'type'	 => 'select',
							'slug'	 => 'ykapi_tax',
							'title'	 => __( 'Система налогообложения' ),
							'values' => array(
								1	 => __( 'ОСН' ),
								2	 => __( 'УСН Доходы' ),
								3	 => __( 'УСН Доходы-Расходы' ),
								4	 => __( 'ЕНВД' ),
								5	 => __( 'ЕСН' ),
								6	 => __( 'ПСН' )
							)
						),
						array(
							'type'	 => 'select',
							'slug'	 => 'ykapi_nds',
							'title'	 => __( 'Ставка НДС' ),
							'values' => array(
								1	 => __( 'без НДС' ),
								2	 => __( 'НДС по ставке 0%' ),
								3	 => __( 'НДС по ставке 10%' ),
								4	 => __( 'НДС по ставке 18%' ),
								5	 => __( 'НДС по ставке 10/110' ),
								6	 => __( 'НДС по ставке 18/118' )
							)
						)
					)
				)
			)
		);
	}

	function create_payment( $data ) {

		$shopid	 = rcl_get_commerce_option( 'ykapi_shopid' );
		$seckey	 = rcl_get_commerce_option( 'ykapi_key' );

		$paymentArgs = array(
			'amount'		 => array(
				'value'		 => $data->pay_summ,
				'currency'	 => $data->currency,
			),
			'metadata'		 => array(
				'baggage'	 => $data->baggage_data,
				'user_id'	 => $data->user_id,
				'pay_type'	 => $data->pay_type,
				'pay_id'	 => $data->pay_id
			),
			'confirmation'	 => array(
				'type'		 => 'redirect',
				'return_url' => add_query_arg( array(
					'ykapi'		 => $shopid,
					'payment-id' => $data->pay_id
					), get_permalink( $data->page_success ) ),
			),
			'capture'		 => true,
			'description'	 => $data->description,
		);


		if ( rcl_get_commerce_option( 'ykapi_fn' ) ) {

			$items = array();

			if ( $data->pay_type == 1 ) {

				$items[] = array(
					'quantity'		 => 1,
					'amount'		 => array(
						'value'		 => $data->pay_summ,
						'currency'	 => $data->currency
					),
					'vat_code'		 => rcl_get_commerce_option( 'ykapi_nds' ),
					'description'	 => __( 'Пополнение личного счета' )
				);
			} else if ( $data->pay_type == 2 ) {

				$order = rcl_get_order( $data->pay_id );

				if ( $order ) {

					/* foreach ( $order->products as $product ) {
					  $items[] = array(
					  'quantity'	 => $product->product_amount,
					  'price'		 => array(
					  'amount' => $product->product_price
					  ),
					  'tax'		 => $rmag_options['yk_nds'],
					  'text'		 => get_the_title( $product->product_id )
					  );
					  } */

					$items[] = array(
						'quantity'		 => 1,
						'amount'		 => array(
							'value'		 => $order->order_price,
							'currency'	 => $data->currency
						),
						'vat_code'		 => rcl_get_commerce_option( 'ykapi_nds' ),
						'description'	 => __( 'Оплата заказа' ) . ' №' . $order->order_id
					);
				}
			} else {

				$items[] = array(
					'quantity'		 => 1,
					'amount'		 => array(
						'value'		 => $data->pay_summ,
						'currency'	 => $data->currency
					),
					'vat_code'		 => rcl_get_commerce_option( 'ykapi_nds' ),
					'description'	 => $data->description
				);
			}

			$paymentArgs['receipt'] = array(
				"customer"			 => array(
					"email" => get_the_author_meta( 'email', $data->user_id )
				),
				'tax_system_code'	 => rcl_get_commerce_option( 'ykapi_tax' ),
				'items'				 => $items
			);
		}

		$client = new Client();
		$client->setAuth( $shopid, $seckey );

		return $client->createPayment( $paymentArgs, uniqid( '', true ) );
	}

	function get_form( $data ) {

		$payment = $this->create_payment( $data );

		return parent::construct_form( array(
				'action'	 => $payment->confirmation->confirmation_url,
				'onclick'	 => 'location.replace("' . $payment->confirmation->confirmation_url . '");return false;',
				'fields'	 => array(
					'shopId'			 => rcl_get_commerce_option( 'ykapi_shopid' ),
					'sum'				 => $data->pay_summ,
					'orderNumber'		 => $data->pay_id,
					'customerNumber'	 => $data->user_id,
					'YK_Type_Pay'		 => $data->pay_type,
					'YK_Baggage_Data'	 => $data->baggage_data
				)
			) );
	}

	function result( $data ) {

		$shopid	 = rcl_get_commerce_option( 'ykapi_shopid' );
		$seckey	 = rcl_get_commerce_option( 'ykapi_key' );

		$POST = json_decode( file_get_contents( 'php://input' ), TRUE );

		$client = new Client();
		$client->setAuth( $shopid, $seckey );

		$payment = $client->getPaymentInfo( $POST["object"]['id'] );

		if ( $payment->status != 'succeeded' ) {
			echo "OK";
			exit;
		}

		if ( ! parent::get_payment( $payment->metadata->pay_id ) ) {
			parent::insert_payment( array(
				'pay_id'		 => $payment->metadata->pay_id,
				'pay_summ'		 => $payment->amount->value,
				'user_id'		 => $payment->metadata->user_id,
				'pay_type'		 => $payment->metadata->pay_type,
				'baggage_data'	 => $payment->metadata->baggage
			) );
			echo 'OK';
			exit;
		}
	}

	function success( $process ) {

		if ( parent::get_payment( $_REQUEST["payment-id"] ) ) {
			wp_redirect( get_permalink( $process->page_successfully ) );
			exit;
		} else {
			wp_die( 'Платеж не найден в базе данных!' );
		}
	}

}
