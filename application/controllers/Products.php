<?php

class Products extends CI_Controller{
	public function index(){
		//Get All Products
		$data['products'] = $this->Product_model->get_products();
		//Load View
		$data['main_content'] = 'products';
		$this->load->view('layouts/main', $data);
	}
	
	public function details($id){
		//Get Product Details
		$data['product'] = $this->Product_model->get_product_details($id);
		
		//Load View
		$data['main_content'] = 'details';
		$this->load->view('layouts/main', $data);
		
		
	}
	
	
}