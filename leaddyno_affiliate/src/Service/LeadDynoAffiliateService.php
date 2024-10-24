<?php

namespace Drupal\leaddyno_affiliate\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

class LeadDynoAffiliateService {

  protected $configFactory;
  protected $httpClient;
  protected $logger;

  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $http_client, LoggerInterface $logger) {
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
    $this->logger = $logger;
  }

  public function registerAffiliate($email, $first_name, $paypal_email = NULL) {
    // public function registerAffiliate($email, $first_name, $last_name, $affiliate_code, $affiliate_type, $paypal_email = NULL) {
    $config = $this->configFactory->get('leaddyno_affiliate.settings');
    $api_key = $config->get('api_key');

    // Prepare the data in JSON format.
    $data = [
      'key' => $api_key,
      'email' => $email,
      'first_name' => $first_name,
      // 'last_name' => $last_name,
    //   'affiliate_code' => $affiliate_code,
    //   'affiliate_type' => $affiliate_type,
    ];

    if ($paypal_email) {
      $data['paypal_email'] = $paypal_email;
    }

    try {
      $response = $this->httpClient->post('https://api.leaddyno.com/v1/affiliates', [
        'headers' => [
              'Accept' => 'application/json',
            ],
        'json' => $data,
        'http_errors' => false,
      ]);

      $body = json_decode($response->getBody(), TRUE);
     
      return $body;

    } catch (\Exception $e) {
      $this->logger->error('Error registering affiliate: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
  }
}
