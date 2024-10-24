<?php

namespace Drupal\leaddyno_affiliate\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\leaddyno_affiliate\Service\LeadDynoAffiliateService;
use Drupal\leaddyno_affiliate\Service\LeadDynoCommissionService;
use Drupal\user\Entity\User;
use Drupal\Core\Session\AccountProxyInterface;

class LeadDynoAffiliateController extends ControllerBase {

  protected $leadDynoAffiliateService;
  protected $leadDynoCommissionService;
  protected $currentUser;

  public function __construct(LeadDynoAffiliateService $lead_dyn_aff_service, LeadDynoCommissionService $lead_dyn_comm_service, AccountProxyInterface $current_user) {
    $this->leadDynoAffiliateService = $lead_dyn_aff_service;
    $this->leadDynoCommissionService = $lead_dyn_comm_service;
    $this->currentUser = $current_user;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('leaddyno_affiliate.service'),
      $container->get('leaddyno_affiliate.commission_service'),
      $container->get('current_user')
    );
  }

  public function registerAffiliate() {
    $user = User::load($this->currentUser->id());
    
    if ($user) {
      // $email = $user->getEmail();
      // $first_name = $user->get('name')->value;
      $email = 'ameer.workingspace11@gmail.com';
      $first_name = 'ameer';
      // $last_name = $user->get('field_last_name')->value;

      $result = $this->leadDynoAffiliateService->registerAffiliate($email, $first_name, Null);

      if ($result) {
        // kint($result);
        // exit;
        $affiliate_id = $result['id'];
        $affiliate_code = $result['affiliate_code'];

        // Set the affiliate_code field in the user entity
        $user->set('field_affiliate_code', $affiliate_code);

        // Save the user entity to store the affiliate code
        $user->save();

        $commission_result = $this->leadDynoCommissionService->getAffiliateCommissionTotal($affiliate_id);

       
        // return new JsonResponse($commission_result);
        return [
          '#theme' => 'affiliate_dasboard',
          '#referral_link' => $result['affiliate_url'],
          '#conversion_rate' => 'N/A',
          '#customers_referred' => $result['total_leads'],
          '#purchases' => $result['total_purchases'],
          '#compensation' =>  $commission_result['currency'],
          '#commissions_due' =>  $commission_result['due'],
          '#commissions_upcomming' => $commission_result['pending'],
          '#commissions_paid' => $commission_result['paid'],

          // '#attached' => [
          //   'library' => [
          //     'leaddyno_affiliate/affiliate_dasboard',
          //   ],
          // ],
        ];
      }
      else {
        return new JsonResponse(['error' => 'Affiliate registration failed.'], 500);
      }
    }
    else {
      return new JsonResponse(['error' => 'User not found.'], 404);
    }
  }
}
