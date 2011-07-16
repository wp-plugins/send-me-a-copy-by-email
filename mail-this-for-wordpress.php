<?php
/*
Plugin Name: Mail this for wordpress
Plugin URI: http://online-source.net/
Description: Give your visitors the option to mail the article they are reading
Author: Laurens ten Ham (MrXHellboy)
Version: 0.8 BETA
Author URI: http://online-source.net
*/

/**
 * Os_MailTheContent
 * Mail the post content
 * @param $post global $post
 */
class Os_MailTheContent{
    // Go on or halt
    private $halt               = false;
    
    // Anti bot related
    private $anti_bot_called    = false;
    private $anti_bot_result    = '';
    private $sum_string         = '';
    
    // Holds the message
    private $message            = null;
    
    // Post related
	private $the_content        = '';
	private $the_title          = '';
	private $the_permalink      = '';
    
    // From / to
	private $the_receiver       = '';
    private $the_sender         = '';
    
    // Additional mail headers
    private $the_header_string  = '';

    // Default messages
    private $mail_messages      = array(    'mail_send_error'       =>  '<span class="error">Something went wrong during the mail attempt.</span>',
                                            'mail_check_error'      =>  '<span class="error">The given email address did not pass the validator.</span>',
                                            'anti_bot_error'        =>  '<span class="error">Wrong answer on the calculation.</span>',
                                            'mail_send'             =>  '<span class="ok">Successfully send!</span>',
                                            'not_logged_on'         =>  '<span class="error">Please logon before using this feature</span>'
                                            );    
    // Default header values
	private $mail_headers        = array(	array('From: '),
									        array('MIME-Version: ', '1.0'),
									        array('Content-type: text/html; charset=', 'utf-8')
									   );

	/**
	 * Os_MailTheContent::__construct()
     * Sets the content, title, permalink, receiver and part of headers
     * Influence on CheckStatus()
     * @param bool $login logged on required
	 */
	function __construct($login = false){
	   global $user_email, $user_identity;
            if ($user_email && $user_identity){
                $this->the_sender    = array(1, $user_email, $user_identity);
            }
            elseif ((!$user_email && !$user_identity) && $login){
                $this->halt = true;
                $this->message = $this->mail_messages['not_logged_on'];                
            }


        if (array_key_exists('os_send_confirmation', $_POST)){
            global $post;
            $this->the_content      = $post->post_content;
            $this->the_title        = $post->post_title;
            $this->the_permalink    = get_permalink($post->ID);
            $this->the_receiver     = $_POST['os_send_to'];
            $this->the_sender       = (is_array($this->the_sender)) ?  
                                            $this->the_sender : array(0, $_POST['os_send_from'], get_option('blogname'));
            
        } else {
            $this->halt = true;
	   }
	}
    
    /**
     * Os_MailTheContent::FormMessages()
     * Overwrite default messages
     */
    public function FormMessages($messages = array()){
        if ($this->halt === false){
            if (!is_array($messages))
                return false;
            
            return $this->mail_messages = wp_parse_args($messages, $this->mail_messages);            
        }
    }
    
    /**
     * Os_MailTheContent::DoFunctions()
     * Perform custom function onto the content
     * The functions are called from the left to the right
     */
    public function DoFunctions($funcs = array()){
        try {
                if (!is_array($funcs)){
                    $this->halt = true;
                    throw new Os_MailException('You defined your functions as string, please change it to an array.');            
                }
        }
        catch (Os_MailException $functions){
            return $this->message = $functions->GetMailError();
        }
            
        if ($this->halt === false){
            $content = $this->the_content;
            while($func = array_shift($funcs))
                $content = call_user_func($func, $content);
            
                return $this->the_content = $content;            
        }
    }
    
	/**
	 * Os_MailTheContent::CreateHeaders()
	 * Create the additional mail header
	 */
	private function CreateHeaders(){
        $this->mail_headers[0][] = $this->the_sender[2].' <'.$this->the_sender[1].'>';
        
            while ($each = each($this->mail_headers))
                $this->the_header_string .= $each[1][0].$each[1][1]."\r\n".PHP_EOL;
                    return $this->the_header_string;
	}

	/**
	 * Os_MailTheContent::MailTheContent()
	 * Send the actuall email
	 */
	private function MailTheContent(){
		if (!(wp_mail(	$this->the_receiver,
						html_entity_decode($this->the_title),
                                    nl2br(
                                        $this->the_content
                                        ),
						$this->CreateHeaders()
					))) throw new Os_MailException( $this->mail_messages['mail_send_error'] );

			return $this->message = $this->mail_messages['mail_send'] ;
	}
    
    /**
     * Os_MailTheContent::CheckStatus()
     * Check the status and take appropriate actions
     */
    private function CheckStatus(){
        if ($this->halt === false){
            try{
                // Check mail syntax
                $this->CheckMail($this->the_receiver, $this->the_sender[1]);
                
                // Anti Bot
                if ($this->anti_bot_called === true){
                    if ($_POST['anti_bot_answer'] != $_POST['mail_anti_bot'])
                        throw new Os_MailException($this->mail_messages['anti_bot_error']);
                }
                    
                    // If no exception is thrown
                    $this->MailTheContent();
            }
            catch (Os_MailException $error){
                return $this->message = $error->GetMailError();
            }
                return;
        } else {
            return false;
        }
    }
    
    /**
     * Os_MailTheContent::CheckMail()
     * Validate email
     */
    private function CheckMail($to, $from){
        if ( (!is_email($to) || !is_email($from)) )
            throw new Os_MailException( $this->mail_messages['mail_check_error'] );
    }
    

    
    /**
     * Os_MailTheContent::AntiBot()
     * Easy sum
     */
    public function AntiBot(){
            $this->anti_bot_called = true;
                mt_srand((float)microtime()*1000000);
                    $decimals = range(0,9);
                        shuffle($decimals);
                        $sum_nums = array_rand($decimals, 2);
                    $sum_result = $sum_nums[0] + $sum_nums[1];
                $this->sum_string = $sum_nums[0].'+'.$sum_nums[1];
            return $this->anti_bot_result = $sum_result;
    }

	/**
	 * Os_MailTheContent::TheForm()
	 * The Actual form
	 */
	public function TheForm(){
        $form = '<form method="post" action="" id="mail-this">';
            $form .= 'To:<input type="text" name="os_send_to" />';
                if ($this->the_sender[0] == 0)
                    $form .= '&nbsp;From:<input type="text" name="os_send_from" />';
                else
                    $form .= '&nbsp;From:<input type="text" name="os_send_from" value="'. @$this->the_sender[1] .'" disabled="disabled" />';
                $form .= '<input type="hidden" name="os_send_content" value="confirmed" />';

        if ($this->anti_bot_called === true){
            $form .= '<span class="sum">Sum {'. $this->sum_string .'} = <input name="mail_anti_bot" type="text" size="2" /></span>';
            $form .= '<input type="hidden" value="'. $this->anti_bot_result .'" name="anti_bot_answer" />';
        }

        $form .= '&nbsp;<input type="submit" name="os_send_confirmation" value="Mail this" />';
        $form .= '</form>';
			
            echo $form;
            
       $this->CheckStatus();
            
	   if (!empty($this->message))
        echo $this->message;
            return;
	}
}
class Os_MailException extends Exception{
    private $error = '';
    
    /**
     * Os_MailException::__construct()
     */
    function __construct($msg){
        $this->error = $msg;
    }
    
    /**
     * Os_MailException::GetMailError()
     */
    function GetMailError(){
        return $this->error;                                                                                                
    }
}


?>