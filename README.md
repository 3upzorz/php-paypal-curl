#php-paypal-curl

A Laravel library for interacting with the paypal api through cURL. Currently only has support for Express Checkout using the Classic NVP API

##Installation

1. place the PayPalCurl.php file in app/libraries/[Insert Namespace here]/
2. add an alias in the app/confing/app.php file ``'PayPalCurl'	  => '[Insert Namespace here]\PayPalCurl'``
3. configure the static variable at the top of PayPalCurl.php to use your correct paypal credentials

##Usage

You should now be able to access the functions through ``PayPalCurl::functionName()``

