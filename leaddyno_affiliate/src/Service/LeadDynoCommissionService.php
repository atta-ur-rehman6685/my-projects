<?php

namespace Drupal\leaddyno_affiliate\Service;

use GuzzleHttp\ClientInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;

class LeadDynoCommissionService {

  protected $configFactory;
  protected $httpClient;
  protected $logger;

  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $http_client, LoggerInterface $logger) {
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
    $this->logger = $logger;
  }

  public function getAffiliateCommissionTotal($affiliate_id) {
    // Get the API key from the module configuration.
    $config = $this->configFactory->get('leaddyno_affiliate.settings');
    $api_key = $config->get('api_key');

    try {
      // Make the API request to retrieve the commission totals.
      $response = $this->httpClient->get('https://api.leaddyno.com/v1/affiliates/' . $affiliate_id . '/commissions_total', [
        'query' => ['key' => $api_key],
      ]);

      // Decode the response from JSON.
      $data = json_decode($response->getBody(), TRUE);
      return $data;

    } catch (\Exception $e) {
      $this->logger->error('Error retrieving commission total for affiliate @id: @message', [
        '@id' => $affiliate_id,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }
}
