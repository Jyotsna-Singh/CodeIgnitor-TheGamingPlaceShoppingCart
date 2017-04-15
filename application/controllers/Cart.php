<?php
class Cart extends CI_Controller{
	public $paypal_data = '';
	public $tax;
	public $shipping;
	public $total = 0;
	public $grand_total;
	
	/*
	 *	Cart Index
	 */
	 public function index(){
		//Load View
		$data['main_content'] = 'cart';
		$this->load->view('layouts/main', $data);
	 }
	 
	 /*
	  * Add To Cart
	  */
	  public function add(){
		//Item Data
		$data = array(
				'id' => $this->input->post('item_number'),
				'qty' => $this->input->post('qty'),
				'price' => $this->input->post('price'),
				'name' => $this->input->post('title')
		);
		//print_r($data);die();
		
		//Insert Into Cart
		$this->cart->insert($data);
		
		redirect('products');
	  }
	  
	  /*
	 * Update Cart
	 */
	public function update($in_cart = null){
		$data = $_POST;
		$this->cart->update($data);
		
		//Show Cart Page
		redirect('cart','refresh');

	}
	
	/*
	 *	Process Form
	 */
	 public function process(){
		if($_POST){
			foreach($this->input->post('item_name') as $key => $value){
				//Get tax & shipping from config
				$this->tax = $this->config->item('tax');
				$this->shipping = $this->config->item('shipping');
			
				$item_id = $this->input->post('item_code')[$key]; 
				$product = $this->Product_model->get_product_details($item_id);
				
				//Assign Data To Paypal
				$this->paypal_data .= '&L_PAYMENTREQUEST_0_NAME'.$key.'='.urlencode($product->title);
				$this->paypal_data .= '&L_PAYMENTREQUEST_0_NUMBER'.$key.'='.urlencode($item_id);
				$this->paypal_data .= '&L_PAYMENTREQUEST_0_AMT'.$key.'='.urlencode($product->price);
				$this->paypal_data .= '&L_PAYMENTREQUEST_0_QTY'.$key.'='. urlencode($this->input->post('item_qty')[$key]);
				
				//Price x Quanity
				$subtotal = ($product->price * $this->input->post('item_qty')[$key]);
				$this->total = $this->total + $subtotal;
				
				$paypal_product['items'][] = array(
					'itm_name'  =>$product->title,
					'itm_price' => $product->price,
					'itm_code'  => $item_id,
					'itm_qty'   => $this->input->post('item_qty')[$key]
				);
				
				//Create Order Array
				$order_data = array(
				'product_id' 		=> $item_id,
				'user_id'  			=> $this->session->userdata('user_id'),
				'transaction_id'  	=> 0,
				'qty'            	=> $this->input->post('item_qty')[$key],
				'price'      		=> $subtotal,
				'address'   		=> $this->input->post('address'),
				'address2'      	=> $this->input->post('address2'),
				'city'      		=> $this->input->post('city'),
				'state'      		=> $this->input->post('state'),
				'zipcode'      		=> $this->input->post('zipcode')
				);
				
				//Add Order Data
				$this->Product_model->add_order($order_data);
			}
			
			//Get Grand Total
			$this->grand_total = $this->total + $this->tax + $this->shipping;
			
			//Create Array Of Costs
			$paypal_product['assets'] = array(
					'tax_total'     => $this->tax,
					'shipping_cost' =>$this->shipping,
					'grand_total'   =>$this->total);
					
			//Session Array For Later
			$_SESSION["paypal_products"] = $paypal_product;
			
			//Send Paypal Params
			$padata = 	'&METHOD=SetExpressCheckout'.
					'&RETURNURL='.urlencode($this->config->item('paypal_return_url')).
					'&CANCELURL='.urlencode($this->config->item('paypal_cancel_url')).
					'&PAYMENTREQUEST_0_PAYMENTACTION='.urlencode("SALE").
					$this->paypal_data.
					'&NOSHIPPING=0'.
					'&PAYMENTREQUEST_0_ITEMAMT='.urlencode($this->total).
					'&PAYMENTREQUEST_0_TAXAMT='.urlencode($this->tax).
					'&PAYMENTREQUEST_0_SHIPPINGAMT='.urlencode($this->shipping).
					'&PAYMENTREQUEST_0_AMT='.urlencode($this->grand_total).
					'&PAYMENTREQUEST_0_CURRENCYCODE='.urlencode($this->config->item('paypal_currency_code')).
					'&LOCALECODE=GB'. //PayPal pages to match the language on your website.
					'&LOGOIMG=http://www.techguystaging.com/demofiles/logo.png'. //Custom logo
					'&CARTBORDERCOLOR=FFFFFF'.
					'&ALLOWNOTE=1';
			
		//Execute "SetExpressCheckOut" 
		$httpParsedResponseAr = $this->paypal->PPHttpPost('SetExpressCheckout', $padata, $this->config->item('paypal_api_username'), $this->config->item('paypal_api_password'), $this->config->item('paypal_api_signature'), $this->config->item('paypal_mode'));
			
		//Respond according to message we receive from Paypal
		if("SUCCESS" == strtoupper($httpParsedResponseAr["ACK"]) || "SUCCESSWITHWARNING" == strtoupper($httpParsedResponseAr["ACK"])){
			//Redirect user to PayPal store with Token received.
			$paypal_url ='https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token='.$httpParsedResponseAr["TOKEN"].'';
			header('Location: '.$paypal_url);
		} else{
			//Show error message
			print_r($httpParsedResponseAr);
			die(urldecode($httpParsedResponseAr["L_LONGMESSAGE0"]));			
		}
		}
		
		//Paypal redirects back to this page using ReturnURL, We should receive TOKEN and Payer ID
	if(!empty($this->input->get('token')) && !empty($this->input->get('PayerID'))){
				//we will be using these two variables to execute the "DoExpressCheckoutPayment"
				//Note: we haven't received any payment yet.
			
				$token = $this->input->get('token');
				$payer_id = $this->input->get('PayerID');
			
				//Get Session info
				$paypal_product = $_SESSION["paypal_products"];
				$this->paypal_data = '';
				$total_price = 0;
			
				//Loop Through Session Array
				foreach($paypal_product['items'] as $key => $item){
					$this->paypal_data .= '&L_PAYMENTREQUEST_0_QTY'.$key.'='. urlencode($item['itm_qty']);
					$this->paypal_data .= '&L_PAYMENTREQUEST_0_AMT'.$key.'='.urlencode($item['itm_price']);
					$this->paypal_data .= '&L_PAYMENTREQUEST_0_NAME'.$key.'='.urlencode($item['itm_name']);
					$this->paypal_data .= '&L_PAYMENTREQUEST_0_NUMBER'.$key.'='.urlencode($item['itm_code']);
			
					//Get Subtotal
					$subtotal = ($item['itm_price']*$item['itm_qty']);
			
					//Get Total
					$total_price = ($total_price + $subtotal);
				}
			
				$padata = 	'&TOKEN='.urlencode($token).
				'&PAYERID='.urlencode($payer_id).
				'&PAYMENTREQUEST_0_PAYMENTACTION='.urlencode("SALE").
				$this->paypal_data.
				'&PAYMENTREQUEST_0_ITEMAMT='.urlencode($total_price).
				'&PAYMENTREQUEST_0_TAXAMT='.urlencode($paypal_product['assets']['tax_total']).
				'&PAYMENTREQUEST_0_SHIPPINGAMT='.urlencode($paypal_product['assets']['shipping_cost']).
				'&PAYMENTREQUEST_0_AMT='.urlencode($paypal_product['assets']['grand_total']).
				'&PAYMENTREQUEST_0_CURRENCYCODE='.urlencode($PayPalCurrencyCode);
			
				//Execute "DoExpressCheckoutPayment"
				$httpParsedResponseAr = $this->paypal->PPHttpPost('DoExpressCheckoutPayment', $padata, $this->config->item('paypal_api_username'), $this->config->item('paypal_api_password'), $this->config->item('paypal_api_signature'), $this->config->item('paypal_mode'));
			
				//Check if everything went ok..
				if("SUCCESS" == strtoupper($httpParsedResponseAr["ACK"]) || "SUCCESSWITHWARNING" == strtoupper($httpParsedResponseAr["ACK"])){
					$data['trans_id'] = urldecode($httpParsedResponseAr["PAYMENTINFO_0_TRANSACTIONID"]);
					
					//Load View
					$data['main_content'] = 'thankyou';
					$this->load->view('layouts/main', $data);
													
					$padata = 	'&TOKEN='.urlencode($token);
					$httpParsedResponseAr = $this->paypal->PPHttpPost('GetExpressCheckoutDetails', $padata, $this->config->item('paypal_api_username'), $this->config->item('paypal_api_password'), $this->config->item('paypal_api_signature'), $this->config->item('paypal_mode'));
				} else {
					die($httpParsedResponseAr["L_LONGMESSAGE0"]);
					echo '<pre>';
					print_r($httpParsedResponseAr);
					echo '</pre>';
				}
			}
	 }
}