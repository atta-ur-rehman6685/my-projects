<?php
namespace Drupal\mygotodoc_dependent_user\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\node\Entity\Node;

class AccountVerificationController extends ControllerBase {

  /**
   * Verifies the account based on the token and node ID.
   */
  public function verifyAccount($nid, Request $request) {
    $token = $request->query->get('token');
    $node = Node::load($nid);


    if ($node && $node->bundle() === 'child_accounts') {
      // Assuming you saved the token in a field, e.g., 'field_verification_token'.
      $saved_token = $node->get('field_verification_token')->value;

      if($saved_token === 'true'){
        // Return a success message.
        \Drupal::messenger()->addStatus($this->t('Account already verified. The account has been activated.'));

        // Optionally, redirect to a confirmation page or the node view.
        // return;
        return new RedirectResponse('/verification-confirmed');
      }
      
      // Compare the tokens.
      if ($saved_token && $saved_token === $token) {
        // If token is valid, publish the node.
        $node->setPublished(TRUE);

        $node->set('field_verification_token', 'true');

        $node->save();

        // Return a success message.
        \Drupal::messenger()->addStatus($this->t('Account verified successfully. The account has been activated.'));

        // Optionally, redirect to a confirmation page or the node view.
        // return;
        return new RedirectResponse('/verification-confirmed');
      }
      else {
        // Token mismatch or invalid.
        \Drupal::messenger()->addError($this->t('Invalid verification token.'));
        return new RedirectResponse('/verification-failed');
      }
    }
    else {
      // Node not found or not of the correct type.
      \Drupal::messenger()->addError($this->t('Invalid account.'));
      return new RedirectResponse('/verification-failed');
    }
  }
}
