<?php
namespace MicroIceExchangeRate\Services;


/**
* Send a mail to the configured admin address
* Uses: http://gitlab.dcs/trip-microservices/retry-mail-queue
 */
class AdminMailer
{
    /**
     * @var string
     */
    private $mailerUrl;

    /**
     * @var string
     */
    private $adminEmail;
    
    
    function __construct($settings)
    {
        if (empty($settings['mailer_url'])) {
            throw new \Exception('MicroIceExchangeRate mailer_url not configured');
        }
        $this->mailerUrl = $settings['mailer_url'];

        if (empty($settings['admin_email'])) {
            throw new \Exception('MicroIceExchangeRate admin_email not configured');
        }
        $this->adminEmail = $settings['admin_email'];
    }
    
    
    function send($subject, $message)
    {
        $options = array('adapter' => 'Zend\Http\Client\Adapter\Curl');
        $client = new \Zend\Http\Client($this->mailerUrl, $options);
        $client->setMethod(\Zend\Http\Request::METHOD_POST);
        $client->setParameterPost(array(
            'To' => $this->adminEmail,
            'Subject' => $subject,
            'Body' => $message,
        ));
        $client->send();
    }
}
