<?php

namespace Drupal\leaddyno_affiliate\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

class LeadDynoPurchaseService {

  protected $configFactory;
  protected $httpClient;
  protected $logger;

  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $http_client, LoggerInterface $logger) {
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
    $this->logger = $logger;
  }
  public function createPurchase($email, $affiliate_code, $purchase_amount)
  {
    $config = $this->configFactory->get('leaddyno_affiliate.settings');
    $api_key = $config->get('api_key');

    // Prepare the data in JSON format.
    $data = [
      'key' => $api_key,
      'email' => $email,
      'code' => $affiliate_code,
      'purchase_amount' => $purchase_amount,
    ];

    // if ($purchase_code) {
    //   $data['purchase_code'] = $purchase_code;
    // }
    // if ($purchase_amount) {
    //   $data['purchase_amount'] = $purchase_amount;
    // }
    // if ($affiliate_code) {
    //   $data['code'] = $affiliate_code;
    // }
    // if ($commission_amt_override) {
    //   $data['commission_amt_override'] = $commission_amt_override;
    // }
    // if ($description) {
    //   $data['description'] = $description;
    // }
    // kint($data);
    // exit;

    
    // try {
    //   $response = $this->httpClient->post('https://api.leaddyno.com/v1/purchases', [
    //     'headers' => [
    //           'Accept' => 'application/json',
    //         ],
    //     'json' => $data,
    //     'http_errors' => false,
    //   ]);

    //   $body = json_decode($response->getBody(), TRUE);
    //   return $body;

    // } catch (\Exception $e) {
    //   $this->logger->error('Error creating purchase: @message', ['@message' => $e->getMessage()]);
    //   return NULL;
    // }
  }
}
