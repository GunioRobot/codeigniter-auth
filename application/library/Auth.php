<?php if (!defined('BASEPATH')) exit('No direct script access allowed'); 

class Auth {

	var $CI = null;

	function Auth()
	{
		$this->CI =& get_instance();

		$this->CI->load->library('email');
		$this->CI->load->helper('url');
		$this->CI->load->config('auth');

		$this->CI->load->database();
		
		$this->table = $this->CI->config->item('auth_table');
		$this->table_fields = $this->CI->db->list_fields( $this->table );
		$this->table_protected_fields = array("id", "user_salt", "activation_code", "activated_on", "created_on", "updated_on");
		
		$this->auth_domains = $this->CI->config->item('auth_domains');
		$this->auth_login_fields = $this->CI->config->item('auth_login_fields');		
	}

	/**
	 * undocumented function
	 *
	 * @return void
	 * @author Bruno
	 **/

	function get_userdata($field)
	{
    $this->CI->load->library('session');

    $this->CI->db->cache_on();
    return $this->CI->session->userdata($field);
    $this->CI->db->cache_off();
	}

	/**
	* Register
	*
	* @access	public
	* @return object User, String (EMAIL_TAKEN)
	*
	* ToDo: Retornar objeto com erros (senha em branco, campos vazios, etc)
	*
	*/

	function register($data)
	{
		$this->CI->db->where('email', $data["email"]);
		$query = $this->CI->db->get($this->table);
	
		if( ! $query->num_rows() )
		{
			$user_salt = sha1(microtime());
			$activation_key = sha1(microtime());
			
			$usercode = $this->_unique_code();

			$email 		= $data['email'];
			$password = $data['password'];
			$password = $this->_encrypt_password($password, $user_salt);

			$query_data = array(
				'email' => $email,
				'user_salt' => $user_salt,
				'password' => $password,
				'activation_code' => $activation_key,				
				'created_on' => date("Y-m-d H:i"),
				'updated_on' => date("Y-m-d H:i")
			);
			
			// active
      $query_data["active"] = ( $this->CI->config->item('auth_require_activation') ) ? 0 : 1;
			
			// extra fields
			foreach($this->CI->config->item('auth_table_fields') as $extra_field)
			{
        $query_data[ $extra_field ] = $data[ $extra_field ];
			}
	
			$this->CI->db->insert($this->table, $query_data);
			
			$query = $this->CI->db->get_where($this->table, array('id' => $this->CI->db->insert_id()));
			$user = $query->row();
			
			// send activation key
      if( $this->CI->config->item('auth_require_activation') )
      {
        $this->send_activation_link($query_data["username"], $query_data["email"], $query_data["activation_code"]);
      }
			
			// return $user;
			return "SUCCESS";
		}
		else
		{
			return "EMAIL_TAKEN";
		}	
	}

	/**
	* Update
	*
	* @access	public
	* @return object User, NULL
	*/

	function update($user_data)
  {
    $this->CI->load->library('session');

		$this->CI->db->where(array("id" => $user_data["id"]));
		$query = $this->CI->db->get($this->table);
		
		// check if user exists
		if($query->num_rows() > 0){
			$user = $query->row();
			
      // check password
			$password_check = (isset($user_data["old_password"])) ? $this->_check_password($user_data["old_password"], $user->password, $user->user_salt ) : TRUE;
			
			if( $password_check )
			{
				// encrypt password
				if(isset($user_data["password"]))
				{
					$user_data["password"] = $this->_encrypt_password($user_data["password"], $user->user_salt);
				}
				
				// filter fields to be updated
				$table_fields = array_diff($this->table_fields, $this->table_protected_fields);
				foreach($table_fields as $table_field)
				{
					if(isset($user_data[$table_field])) $user_update_data[$table_field] = $user_data[$table_field];
				}
				
				$this->CI->db->where('id', $user_data["id"]);
				$this->CI->db->update($this->table, $user_update_data);
				
				return "SUCCESS";
				
			} else return "WRONG_PASSWORD";
		}    
  }

	/**
	* Activate
	*
	* @access	public
	* @return object User, NULL
	*/

	function activate($code)
	{
    $this->CI->load->library('session');
	
		$query = $this->CI->db->get_where($this->table, array('activation_code' => $code));
		
		if($query->num_rows() > 0)
		{
			$user = $query->row();
			
			$query_data = array(
				'activation_code' => NULL,
				'active' => 1,
				'activated_at' => date("Y-m-d H:i")
			);
	
			$this->CI->db->where('activation_code', $code);
			$this->CI->db->update($this->table, $query_data);
			
			return $user;
		}
	}

	/**
	* Authenticate
	*
	* @access	public
	* @return 	object Entry
	*/

	function authenticate($data)
	{
    $this->CI->load->library('session');

		// check authorized fields for authentication

		foreach($this->auth_login_fields as $login_field){
			$login_fields_where[] = $login_field . " = '".$data[$login_field]."'";
		}

		$where = implode(" OR ", $login_fields_where);
		$this->CI->db->where($where);
		$query = $this->CI->db->get($this->table);
		
		// check if user exists
		
		if($query->num_rows() > 0)
		{
			$user = $query->row();
		
			if( $user->active )
			{				
        // check password
				if( $this->_check_password($data["password"], $user->password, $user->user_salt ) OR ! isset($data["password"]) )
				{										
					/*
						TODO Adicionar campos customizados à sessão
					*/
					
					$userdata = array ('user_id' 	=> $user->id, 'email' 	=> $user->email, 'level' 	=> $user->level );
  								
					$this->CI->session->set_userdata($userdata);
					return "SUCCESS";
				}
				// password wrong
				else
				{
					return "WRONG_PASSWORD";
				}
			}
			// not active
			else 
			{
				return "USER_NOT_ACTIVE";
			}				
		}
		else
		{
			return "USER_NOT_FOUND";
		}
	}

	/**
	* Logout
	*
	* @access	public
	* @return boolean
	*/

	function logout()
	{
    $this->CI->load->library('session');
		$this->CI->session->sess_destroy();

		$redirect = $this->auth_domains["default"]["logout_redirect"];
		redirect($redirect);		
	}

	/**
	* Logout
	*
	* @access	public
	* @return boolean
	*/
	
	function restrict($level = NULL, $redirect = NULL)
	{
    $this->CI->load->library('session');
    
		$authorized = FALSE;
    
		if( $this->CI->session->userdata('user_id') ) {
			// check user level
			if(isset($params["level"])){
				if ($this->CI->session->userdata('level') >= $params["level"]) {
					$authorized = TRUE;
				}
			} else {
				$authorized = TRUE;
			}
			
			// check user domain
			// check user's login fields
		}
		
		if( ! $authorized )
		{
			$this->CI->session->set_flashdata('msg', 'Você precisa estar logado.');
			
			$redirect = $this->auth_domains["default"]["restrict_redirect"];
			redirect($redirect);
		}
	}

	/**
	* Logged In
	*
	* @access	public
	* @return boolean
	*/
	
	function logged_in($domain = NULL)
	{
    $this->CI->load->library('session');
    return ( ! $this->CI->session->userdata('user_id') ) ? 0 : 1;
	}
	
	/**
	* Forgot Password
	*
	* @access	public
	* @return 	object Entry
	*/	
	
	function forgot_password($login)
	{
		$this->CI->db->where("username", $login);
		$this->CI->db->or_where("email", $login);
		$query = $this->CI->db->get($this->table);
	
		if($query->num_rows() > 0)
		{
			$user = $query->row();
			$key = sha1(microtime());
			
			$this->CI->db->where("id", $user->id);
			$this->CI->db->update($this->table, array('forgot_code' => $key));
			
			$user->forgot_code = $key;			
			
			return $user;
		}
		else
		{
			return FALSE;
		}
	}
	
	/**
	* Send activation link
	*
	* @access	public
	* @return 	object Entry
	*/
	
	function send_activation_link($username, $email, $key)
	{
		$config['mailtype'] = 'html';
		$this->CI->email->initialize($config);

		$data['key'] = $key;
		$data['username'] = $username;

		$message = $this->CI->load->view('email/activate', $data, true);
		
		$this->CI->email->from('nao-responda@anhembi.br', 'Casa Anhembi Verão Guarujá');
		$this->CI->email->to($email);
		$this->CI->email->subject('Casa Anhembi Verão Guarujá: Confirmação de e-mail.');
		$this->CI->email->message( $message );
		
		$this->CI->email->send();
	}	
	
	/**
	* Send activation success
	*
	* @access	public
	* @return 	object Entry
	*/	
	
	function send_activation_success($username, $email, $key)
	{
		$user = $this->user_model->get( array('username' => $username), true );

		$data['key'] = $user->activation_code;
		$data['username'] = $user->username;

		$email_data = array(
		  'from_email' => '',
		  'from_name' => '',
		  'to_email' => $user->email,
		  'subject' => '[troqua] Sua conta foi ativada com sucesso',
		  'message' => $this->load->view('email/confirmation_success', $data, true)
		);
		
		$this->_send_email($email_data);
	}	
	
	/**
	* Send Forgot Password
	*
	* @access	public
	* @return boolean
	*/

	function send_forgot_password($login)
	{
		$message_data['key'] = $key;
		$message_data['username'] = $username;
		
		$email_data = array(
		  'from_email' => '',
		  'from_name' => '',
		  'subject' => '',
		  'message' => $this->load->view('email/forgot', $message_data, true)
		);
		
		return $this->_send_email($email_data);
	}
	
	/**
	* Send Mail
	*
	* @access	private
	* @return boolean
	*/
	
  function _send_email($data)
  {
		$config['mailtype'] = 'html';
		$this->CI->email->initialize($config);
  
		$this->CI->email->from($data["from_email"], $data["from_name"]);
		$this->CI->email->to($data["to_email"]);
		$this->CI->email->subject($data["subject"]);
		$this->CI->email->message($data["message"]);
		
		if( $this->CI->email->send() ) return 1;
  }

	/**
	* Get entry
	*
	* @access	public
	* @return 	object Entry
	*/

	function _encrypt_password($password, $user_salt)
	{
		$salt = $this->CI->config->item('encryption_key');
		$encrypted_password = sha1($salt.$user_salt.$password);
	
		return $encrypted_password;
	}

	/**
	* Get entry
	*
	* @access	public
	* @return 	object Entry
	*/

	function _check_password($pass, $enc, $user_salt )
	{
		$salt = $this->CI->config->item('encryption_key');
		$pass = sha1($salt.$user_salt.$pass);
	
		return ($pass == $enc) ? TRUE : FALSE;
	}

	/**
	* Get entry
	*
	* @access	public
	* @return 	object Entry
	*/

	function _unique_code($size = 8)
	{
    $seed = "ABCDEFGHJKLMNPQRSTUVWXYZ234567892345678923456789";
    $str = '';
    srand((double)microtime()*1000000);
    for ($i=0;$i<$size;$i++) {
        $str .= substr ($seed, rand() % 48, 1);
    }
    return $str;
	}
}
// End of library class
// Location: system/application/libraries/Auth.php
