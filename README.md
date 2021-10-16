# WooCommerce Custom Checkout Fields
A php class for creating custom checkout fields for WooCommerce websites.
Screenshots: https://www.solagirl.net/woocommerce-custom-checkout-fields-2021.html
## Usage
Define your your fields then pass theme as parameters to the class constructor.
* $billing_fields
* $shipping_fields
* $special_fields

$special_fields are fields below order note. They are not created via WooCommerce custom fields filter, and therefore not managed by WooCommerce. WooCommerce will not automatically save them to post_meta and user_meta.

A field definition looks like below:
```php
$field = array(
	// type support 
	// text,datetime,datetime-local,date,month,month,time,number,email,url,tel,checkbox,textarea,hidden,select,radio,state,country
	'type'                  => 'text', 
	'label'                 => '',
	'description'           => '',
	'placeholder'           => '',
	'maxlength'             => false,
	'required'              => false,
	'autocomplete'          => false,
	'id'                    => $key,
	'class'                 => array(),
	'label_class'           => array(),
	'input_class'           => array(),
	'return'                => false, // true to return the html string
	'options'               => array(),
	'custom_attributes'     => array(),
	'validate'              => array(), // add a validate-classname to the field, could be used to do custom validation
	'default'               => '',
	'autofocus'             => '',
	'priority'              => '',
	'save_usermeta'         => false, // Do not save this value to usermeta
	'show_in_email'         => true,
	'show_in_order_details' => true,
	'show_in_admin'         => true,
	'display_position'      => '1'  // 1 - Display inside order total after shipping method | - Display after order total
);
```

For detailed parameters please checkout the definition of woocommerce_form_field().

## An Example
Code should be placed inside theme's functions.php.

```php
add_action( 'init', function(){

  $billing_fields = array(
    'billing_field_company_type' => array(
      'type'          => 'select',
      'label'         => '公司类型',
      'placeholder'   => '',
      'required'      => false,
      'class'         => array('form-row-wide'),
      'label_class'   => array(),
      'clear'         => true,
      'priority'      => 20,
      'options'       => array('1'=>'有限责任公司','2' => '个人独资企业', '3' => '外商独资公司'),
      'default'       => '1',
      'display_position' => '1'
    ),
  );

  $shipping_fields = array();
  
  $special_fields = array(
    'order_dropshipping' => array(
      'type'          => 'radio',
      'label'         => '是否为Dropshipping订单',
      'placeholder'   => '',
      'required'      => false,
      'class'         => array('form-row-wide'),
      'label_class'   => array(),
      'clear'         => true,
      'priority'      => 1,
      'options'       => array('yes'=>'是','no' => '否'),
      'default'       => 'no',
      'display_position' => '2'
    ),
    
  );

  new Sola_Custom_WC_Checkout_Fields( $billing_fields, $shipping_fields, $special_fields );
});
```

