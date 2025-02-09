<?php

namespace Drupal\firebase_config_manager\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\firebase_config_manager\Service\FirebaseService;

/**
 * Handles AJAX updates to Firestore.
 */
class FirebaseUpdateController extends ControllerBase {

  protected $firebaseService;

  public function __construct(FirebaseService $firebase_service) {
    $this->firebaseService = $firebase_service;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('firebase_config_manager.service')
    );
  }

  public function updateDocument(Request $request) {
    $collection = $request->request->get('collection');
    $doc = $request->request->get('doc');
    $field = $request->request->get('field');
    $value = $request->request->get('value');

    if ($this->firebaseService->updateFirestoreDocument($collection, $doc, $field, $value)) {
      return new JsonResponse(['status' => 'success']);
    }
    return new JsonResponse(['status' => 'error', 'message' => 'Failed to update Firestore'], 500);
  }
}