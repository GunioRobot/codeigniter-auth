<?php if (!defined('BASEPATH')) exit('No direct script access allowed'); 

class Auth {

	var $CI = null;
	var	$table = "usuarios";

	function Auth()
	{
		$this->CI =& get_instance();
		
		$this->CI->load->library('session');
		$this->CI->load->helper('url');
		$this->CI->load->config('auth');

		$this->CI->load->database();
		
		$this->table = $this->CI->config->item('auth_table');
	}
	
	function register($data)
	{
		$this->CI->db->where('email', $data["email"]);
		$query = $this->CI->db->get($this->table);
	
		if( ! $query->num_rows() )
		{
			$salt = $this->CI->config->item('encryption_key');
			$user_salt = sha1(microtime());
			$key = sha1(microtime());
			
			$usercode = $this->_unique_code();

			$email 		= $data['email'];
			$password = $data['senha'];
			$password = sha1($salt.$user_salt.$password);
	
			$query_data = array(
				'salt' => $user_salt, // removido user salt
				'nome' => $data["nome"],
				'telefone' => $data["telefone"],
				'newsletter' => $data["newsletter"],

				'senha' => $password,
				'email' => $email,
				'activation_code' => $key,				
				'created_on' => date("Y-m-d H:i"),
				'updated_on' => date("Y-m-d H:i")
			);
	
			$this->CI->db->insert($this->table, $query_data);
			
			// send activation key
      $this->send_activation_link($query_data["nome"], $query_data["email"], $query_data["activation_code"]);			
			
			return "SUCCESS";
		}
		else
		{
			return "EMAIL_TAKEN";
		}	
	}
	
	function activate($code)
	{
		$this->CI->db->where('activation_code', $code);
		$query = $this->CI->db->get($this->table);
		
		if($query->num_rows() > 0)
		{
			$user = $query->row();
	
			$this->CI->db->where('activation_code', $code);
			$this->CI->db->update($this->table, array(
				'activation_code' => NULL,
				'active' => 1
				'activated_on' => date("Y-m-d H:i")
			));
			
			return $user;
		}
		else
		{
			return FALSE;
		}
	}

	function authenticate($login, $password = NULL)
	{
		$where = "username = '".$login."' OR email = '".$login."'";

		$this->CI->db->where($where);
		$query = $this->CI->db->get( $this->table );
		
		if($query->num_rows() > 0)
		{
			$user = $query->row();
		
			if($user->active)
			{
				if( $this->_check_password($password, $user->password, $user->salt ) OR $password == NULL ) // check password
				{
					$userdata = array (
											'user_id' 	=> $user->id,
											'logged_in' => TRUE
										);
										
					$this->CI->session->set_userdata( $userdata );
					return 1;
				}
				else
				{
					// password wrong
					return "WRONG_PASSWORD";
				}
			}
			else 
			{
				// not active
				return "NOT_ACTIVE";
			}				
		}
		else
		{
			return FALSE;
		}
	}


	function authenticate_email($login)
	{
		$where = "email = '".$login."'";

		$this->CI->db->where($where);
		$query = $this->CI->db->get($this->table);
		
		if($query->num_rows() > 0)
		{
			$user = $query->row();
		
			if($user->active)
			{
				$userdata = array (
										'domain' 	=> 'application',
										'user_id' 	=> $user->id,
										'logged_in' => TRUE
									);

				$this->CI->session->set_userdata( $userdata );
				return 1;
			}
			else 
			{
				// not active
				return "NOT_ACTIVE";
			}				
		}
		else
		{
			return FALSE;
		}
	}

	
	function logout()
	{
		$this->CI->session->sess_destroy();	
	}
	
	function restrict($level = NULL, $redirect = NULL)
	{
		if( ! $this->CI->session->userdata('user_id') && ! $this->CI->session->userdata('domain') == "application" )
		{
			$this->CI->session->set_flashdata('msg', 'Você precisa estar logado.');
			
			$redirect = ($redirect) ? $redirect : "/";
			redirect($redirect);
		}
	}
	
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
	* Send activation key
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
	* Get entry
	*
	* @access	public
	* @return 	object Entry
	*/	
	
	function send_activation_success($username, $email, $key)
	{
		$user = $this->user_model->get( array('username' => $username), true );
	
		$config['mailtype'] = 'html';
		$this->email->initialize($config);

		$data['key'] = $user->activation_code;
		$data['username'] = $user->username;

		$message = $this->load->view('email/confirmation_success', $data, true);
		
		$this->email->from('nao-responda@anhembi.br', 'troqua');
		$this->email->to($user->email);
		$this->email->subject('[troqua] Sua conta foi ativada com sucesso');
		$this->email->message( $message );
		
		$this->email->send();
	}	
	
	/**
	* Get entry
	*
	* @access	public
	* @return 	object Entry
	*/	

	function send_forgot($username, $email, $key)
	{

		$config['mailtype'] = 'html';
		$this->email->initialize($config);

		$data['key'] = $key;
		$data['username'] = $username;

		$message = $this->load->view('email/forgot', $data, true);
		
		$this->email->from('ola@troqua.com', 'troqua');
		$this->email->to($email);
		$this->email->subject('[troqua] Instruções para troca de senha');
		$this->email->message( $message );
		
		$this->email->send();
	}	
	
	function _check_password($pass, $enc, $user_salt )
	{
		$salt = $this->CI->config->item('encryption_key');
		$pass = sha1($salt.$user_salt.$pass);	
	
		return ($pass == $enc) ? TRUE : FALSE;
	}

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
