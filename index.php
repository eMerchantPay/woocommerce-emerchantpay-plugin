<?php

/*
Plugin Name: eCommerce EMP WooCommerce Plugin
Description: Extends WooCommerce with eCommerce EMP WooCommerce Plugin.
Version: 1.0.0
 */
mb_internal_encoding("UTF-8"); 
 
 
add_action('plugins_loaded', 'woocommerce_emp_ecom_init', 0);

function woocommerce_emp_ecom_init() {

    if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

    /**
     * Localisation
     */
    load_plugin_textdomain('wc-emp-ecom', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');

    if($_GET['msg']!=''){
        add_action('the_content', 'showMessageEmp');
    }

    function showMessageEmp($content){
            return '<div class="box '.htmlentities($_GET['type']).'-box">'.htmlentities(urldecode($_GET['msg'])).'</div>'.$content;
    }
    /**
     * Gateway class
     */
    class WC_Gateway_emp_ecom extends WC_Payment_Gateway {
    protected $msg = array();
        public function __construct(){
            
            $this -> id = 'ecom';
            $this -> method_title = __('Ecom', 'emp');
            //$this -> icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/logo.gif';
            $this -> has_fields = false;
            $this -> init_form_fields();
            $this -> init_settings();
            $this -> title = $this -> settings['title'];
            $this -> description = $this -> settings['description'];
			$this -> gateway_url = $this -> settings['gateway_url'];
			$this -> client_id = $this -> settings['client_id'];
            $this -> secret_key = $this -> settings['secret_key'];
			$this -> form_id = $this -> settings['form_id'];
			$this -> test_transaction = $this -> settings['test_transaction'];
			$this -> item_1_digital = $this -> settings['item_1_digital'];
			$this -> credit_card_trans_type = $this -> settings['credit_card_trans_type'];
            $this -> redirect_page_id = $this -> settings['redirect_page_id'];
            $this -> msg['message'] = "";
            $this -> msg['class'] = "";
            add_action('init', array(&$this, 'check_ecom_response'));
          
			//update for woocommerce >2.0
            add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_ecom_response' ) );

            add_action('valid-ecom-request', array(&$this, 'successful_request'));
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
             } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            }
            add_action('woocommerce_receipt_ecom', array(&$this, 'receipt_page'));
            add_action('woocommerce_thankyou_ecom',array(&$this, 'thankyou_page'));
        }

        function init_form_fields(){

            $this -> form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'emp'),
                    'type' => 'checkbox',
                    'label' => __('Enable eCommerce EMP WooCommerce Plugin.', 'emp'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('Title:', 'Emp'),
                    'type'=> 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'Emp'),
                    'default' => __('Ecom', 'emp')),
                'description' => array(
                    'title' => __('Description:', 'emp'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'emp'),
                    'default' => __('Pay securely by Credit or Debit card or internet banking through eCommerce ECOM WooCommerce Plugin.', 'emp')),
               
			   'gateway_url' => array(
                    'title' => __('Gateway URL', 'emp'),
                    'type' => 'text',
                    'description' => __('Given to Merchant')),

			   'client_id' => array(
                    'title' => __('Client ID', 'emp'),
                    'type' => 'text',
                    'description' => __('Given to Merchant')),
                'secret_key' => array(
                    'title' => __('Secret Key', 'emp'),
                    'type' => 'text',
                    'description' =>  __('Given to Merchant', 'emp'),
                ),
				  'form_id' => array(
                    'title' => __('Form ID', 'emp'),
                    'type' => 'text',
                    'description' =>  __('Given to Merchant', 'emp'),
                ),
				 
				  'test_transaction' => array(
                    'title' => __('Tes Mode', 'emp'),
                    'type' => 'text',
                    'description' =>  __('1 - Test mode 0 - Live mode'),
                ),
				
				  'item_1_digital' => array(
                    'title' => __('Digital/Phisial products', 'emp'),
                    'type' => 'text',
                    'description' =>  __('1 - Digital 0 - Phisical'),
                ),
				
				  'credit_card_trans_type' => array(
                    'title' => __('Auth/Sale', 'emp'),
                    'type' => 'text',
                    'description' =>  __('sale/auth'),
					
                ),
				  'redirect_page_id' => array(
                    'title' => __('Return Page'),
                    'type' => 'select',
                    'options' => $this -> get_pages('Select Page'),
                    'description' => "URL of success page",
					'default' => __('Order Received'),
				
                )
			      
            );


        }
        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         **/
		 
		 
        public function admin_options(){
            echo '<h3>'.__('eCommerce EMPS WooCommerce Plugin', 'emp').'</h3>';
            echo '<p>'.__('eCommerce EMP WooCommerce Plugin').'</p>';
            echo '<table class="form-table">';
            $this -> generate_settings_html();
            echo '</table>';

        }

     
	   function receipt_page($order){
          
            echo $this -> generate_ecom_form($order);		
	
        }
		
	
        /**
         * Process the payment and return the result
         **/
		 
		 
        function process_payment($order_id){
            $order = new WC_Order($order_id);
            return array('result' => 'success', 
						'redirect' => add_query_arg
						('order',
							$order->id, add_query_arg('key', 
							$order->order_key, 
							get_permalink(get_option('woocommerce_pay_page_id'))))
							
            );
        }
		

        /**
         * Check for valid server callback
         **/
		 
		 
         function check_ecom_response(){
            global $woocommerce;
            if(isset($_REQUEST['order_reference']) && isset($_REQUEST['notification_type'])){
			
	
                $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
                $order_id_time = $_REQUEST['order_reference'];
                $order_id = explode('_', $_REQUEST['order_reference']);
                $order_id = (int)$order_id[0];
                $this -> msg['class'] = 'error';
               
                if($order_id != ''){
                    
                        $order = new WC_Order($order_id);
                  
                        $AuthDesc = $_REQUEST['notification_type'];
                     
                        if($order -> status !=='completed'){
                          

                                if($AuthDesc=="order"){
								
                                        $order -> payment_complete();
                                        $woocommerce -> cart -> empty_cart();
                                }
								
								if($AuthDesc=="orderdeclined"){
								
								    $order -> update_status('Failed');
                                    $this -> msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
                                    $this -> msg['class'] = 'error';

                                }
                               		
                        }

                }
                $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
                //For wooCoomerce 2.0
                
				$redirect_url = add_query_arg( array('msg'=> urlencode($this -> msg['message']), 'type'=>$this -> msg['class']), $redirect_url );

                wp_redirect( $redirect_url );
				
                exit;
            }
        }
       /*
        //Removed For WooCommerce 2.0
       function showMessageEemp($content){
            return '<div class="box '.$this -> msg['class'].'-box">'.$this -> msg['message'].'</div>'.$content;
        }*/
        /**
         * Generate Checkout form
         **/
		 
		 
        public function generate_ecom_form($order_id){
            global $woocommerce;
            $order = new WC_Order($order_id);
            $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
          //For wooCoomerce 2.0
            $redirect_url = add_query_arg( 'wc-api', get_class( $this ), $redirect_url );
           
		   $order_id = $order_id.'_'.date("ymds");
           
			$order_currency=get_option('woocommerce_currency');
			$item_currency="item_1_unit_price_".$order_currency;

			include_once('ParamSigner.class');
			$ps=new Paramsigner;

			//prepare trans request string
			//required fields  
			$ps->setSecret($this -> secret_key);

			$ps->setParam('client_id',$this -> client_id);
			$ps->setParam('form_id',$this -> form_id);
			$ps->setParam('transtype',$transtype);
			$ps->setParam('test_transaction',$this -> test_transaction);
			$ps->setParam('item_1_name',$order_id);
			$ps->setParam("$item_currency",$order -> order_total);
			$ps->setParam('order_currency',$order_currency);
			$ps->setParam('item_1_digital',$this -> item_1_digital);
			$ps->setParam('customer_email',$order -> billing_email);
			$ps->setParam('customer_country',$order -> billing_country);
			$ps->setParam('order_reference',$order_id);
			$ps->setParam('credit_card_trans_type',$this -> credit_card_trans_type);
			$ps->setParam('approval_url',"$redirect_url");
			//$ps->setParam('decline_url',"$redirect_url");


			//Customer paramters

			$ps->setParam('customer_first_name',$order -> billing_first_name);
			$ps->setParam('customer_last_name',$order -> billing_last_name);
			$ps->setParam('customer_company',$order -> billing_company);
			$ps->setParam('customer_address',$order -> billing_address_1);
			$ps->setParam('customer_address2',$order -> billing_address_2);
			$ps->setParam('customer_city',$order -> billing_city);
			$ps->setParam('customer_state',$order -> billing_state);
			$ps->setParam('customer_postcode',$order -> billing_postcode);
			$ps->setParam('customer_phone',$order -> billing_phone);


			//Shipping parameters

			$ps->setParam('shipping_first_name',$order -> shipping_first_name);
			$ps->setParam('shipping_last_name',$order -> shipping_last_name);
			$ps->setParam('shipping_company',$order -> shipping_company);
			$ps->setParam('shipping_address',$order -> shipping_address_1);
			$ps->setParam('shipping_address2',$order -> shipping_address_2);
			$ps->setParam('shipping_city',$order -> shipping_city);
			$ps->setParam('shipping_state',$order -> shipping_state);
			$ps->setParam('shipping_postcode',$order -> shipping_postcode);
			$ps->setParam('shipping_phone',$order -> billing_phone);
			$ps->setParam('shipping_country',$order -> shipping_country);

			//generate Query String

			$requestString=$ps->getQueryString();
			$fullrequest=$this -> gateway_url."/payment/form/post"."?".$requestString;
		
            return '
			<form>
                
			<iframe src="'.$fullrequest.'" width="600" height="600"  scrolling="no" frameborder="0" border="0" allowtransparency="true" ></iframe>
		
            </form>';
					
        }

	   function get_pages($title = false, $indent = true) {
            
			echo '<script type="text/javascript">
					if (top.location.href != self.location.href)
					top.location.href = self.location.href;
				  </script>';
		
			$wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title) $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while($has_parent) {
                        $prefix .=  ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }

    }

    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_emp_ecom_gateway($methods) {
        $methods[] = 'WC_Gateway_emp_ecom';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_emp_ecom_gateway' );
    }
?>
