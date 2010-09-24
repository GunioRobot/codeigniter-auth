<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Table
|--------------------------------------------------------------------------
|
| URL to your CodeIgniter root. Typically this will be your base URL,
| WITH a trailing slash:
|
|	http://example.com/
|
*/

$config['auth_table']	= "users";

/*
|--------------------------------------------------------------------------
| Table Fields
|--------------------------------------------------------------------------
|
| Campos extras utilizados no cadastro
|
|	cep, telefone, endereço
|
*/

$config['auth_table_fields']	= array("zip", "phone");

/*
|--------------------------------------------------------------------------
| Login Fields
|--------------------------------------------------------------------------
|
| Campos utilizados para identificar usuário
|
|	email, login, zip
|
*/

$config['auth_login_fields'] = array("email", "username");

/*
|--------------------------------------------------------------------------
| Use Password
|--------------------------------------------------------------------------
|
| Login com ou sem password
|
|	TRUE
|
*/

$config['auth_password_use'] = TRUE;

/*
|--------------------------------------------------------------------------
| Password Salt Level
|--------------------------------------------------------------------------
|
| Utilização de salt
|
|	0. No salt
|	1. App salt
|	2. App + User salt
|
*/

$config['auth_password_salt_level'] = 0;

/*
|--------------------------------------------------------------------------
| Domains
|--------------------------------------------------------------------------
|
|
*/

$config['auth_domains'] = array(
  "admin" => array(
    "login_redirect" => "",
    "logout_redirect" => ""
  ),
  "application" => array(
    "login_redirect" => "",
    "logout_redirect" => ""
  )
);

/*
|--------------------------------------------------------------------------
| E-Mails
|--------------------------------------------------------------------------
|
|
*/

$config['auth_activation_mail'] = array(
  "from" => "email@email.com",
  "subject" => "email@email.com",
  "message_view" => "email/activation"
);

$config['auth_activation_success_mail'] = array(
  "from" => "email@email.com",
  "subject" => "email@email.com",
  "message_view" => "email/activation"
);

$config['auth_forgot_mail'] = array(
  "from" => "email@email.com",
  "subject" => "email@email.com",
  "message_view" => "email/activation"
);