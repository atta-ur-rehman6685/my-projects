<?php

namespace Drupal\mygotodoc_independent_user\Access;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\node\NodeInterface;

class IndependentUserAccessCheck {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs an IndependentUserAccessCheck object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user service.
   */
  public function __construct(AccountInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * Custom access check for creating independent users.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check access for.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user object.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(NodeInterface $node, AccountInterface $account) {
    // Check if the user has the 'create_patient_role_users' permission and the patient role.
    if ($account->hasPermission('create_patient_role_users')) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

}
