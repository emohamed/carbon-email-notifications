<?php
namespace CarbonForms;

/**
 * Form email notification
 */
class EmailNotification {
	/**
	 * Settings
	 */
	private $base_settings = [
		'recepients'  => [],
		'from'        => '',
		'from_name'   => '',
		'subject'     => '',

		'template'    => __DIR__ . '/../email-templates/form.php',

		'smtp_config' => [
			'enable'     => false,
			'host'       => '',
			'port'       => '',
			'username'   => '',
			'password'   => '',
			'encryption' => '',

		],
	];

	/**
	 * Path to the template file
	 */
	private $template;

	/**
	 * @var PhpMailer
	 */
	public $mailer;

	/**
	 * The last sending error will be stored here. 
	 * @var string
	 */
	public $error;

	function __construct($settings=[]) {
		$settings = array_merge($this->base_settings, $settings);

		$mailer = new \PHPMailer();
		$mailer->isHTML(true);

		$mailer->From     = $settings['from'];
		$mailer->FromName = $settings['from_name'];

		$recepients = $this->normalize_recepients($settings['recepients']);

		foreach ($recepients as $email => $name) {
			$mailer->AddAddress($email, $name);
		}

		$mailer->Subject = $settings['subject'];

		if (isset($settings['smtp_config']) && $settings['smtp_config']['enable']) {
			$smtp_config = $settings['smtp_config'];

			$mailer->isSMTP();
			$mailer->SMTPAuth   = true;

			$mailer->Host       = $smtp_config['host'];
			$mailer->Port       = $smtp_config['port'];
			$mailer->Username   = $smtp_config['username'];
			$mailer->Password   = $smtp_config['password'];
			$mailer->SMTPSecure = $smtp_config['encryption'];
		}

		$this->mailer = $mailer;
		$this->set_template($settings['template']);
	}

	public function set_template($file) {
		if (!file_exists($file)) {
			throw new Exception("Couldn't find template file $file");
		}

		$this->template = $file;
	}

	public function get_template() {
		return $this->template;
	}

	/**
	 * Render the HTML with the $context variables.
	 * @param $template string path to the template file
	 * @param $context $context array with the variables used in the template
	 */
	function render_message($context) {
		extract($context);

		ob_start();
		include($this->template);
		$html = ob_get_contents();
		ob_end_clean();

		return $html;
	}

	/**
	 * Build hash containing email addresses as keys and names as
	 * values from the following formats:
	 * 
	 *  * "johndoe@gmail.com"
	 *  * ["johndoe@gmail.com", "doejane@gmail.com"]
	 *  * ["johndoe@gmail.com" => "John Doe", "doejane@gmail.com"]
	 */
	protected function normalize_recepients($recepients) {
		$result = array();

		// Scalar strings are supported
		$recepients = (array)$recepients;

		foreach ($recepients as $key => $value) {
			// Allow recepients to be passed as
			// ["user@gmail.com"] or ["user@gmail.com" => "User Name"]
			if (is_numeric($key)) {
				$recepient_mail = $value;
				$recepient_name = '';
			} else {
				$recepient_mail = $key;
				$recepient_name = $value;
			}

			$result[$recepient_mail] = $recepient_name;
		}

		return $result;
	}

	/**
	 * Add an attachment to the message
	 */
	function attach($file_path, $display_name) {
		$this->mailer->AddAttachment($file_path, $display_name);
	}

	/**
	 * Sends an HTML email message. 
	 */
	function send( $fields ) {
		$this->mailer->Body = $this->render_message([
			'fields' => $fields,
		]);

		$result = $this->mailer->send();

		if (!$result) {
			throw new MailDeliveryException($this->mailer->ErrorInfo);
		}

		return $result;
	}
}
