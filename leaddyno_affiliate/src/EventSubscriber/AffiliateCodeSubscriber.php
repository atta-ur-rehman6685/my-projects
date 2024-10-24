<?php
namespace Drupal\leaddyno_affiliate\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class AffiliateCodeSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => 'onRequest',
    ];
  }

  public function onRequest(RequestEvent $event) {
    $request = $event->getRequest();

    // Check if the affiliate code is in the query string.
    if ($request->query->has('afmc')) {
      $affiliate_code = $request->query->get('afmc');

      // Store the affiliate code in the session.
      \Drupal::service('session')->set('affiliate_code', $affiliate_code);

      // Store the affiliate code in a cookie that expires in 30 days.
      $response = new Response();
      $response->headers->setCookie(new Cookie('affiliate_code', $affiliate_code, strtotime('30 days')));
      $event->setResponse($response);

      // Optionally, you can redirect to the same URL without the affiliate code.
      $cleanUrl = preg_replace('/\?.*/', '', $request->getUri());
      $response->headers->set('Location', $cleanUrl);
      $response->setStatusCode(Response::HTTP_FOUND);
    }
  }
}
