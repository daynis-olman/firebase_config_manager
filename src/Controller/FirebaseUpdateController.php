<?php

namespace Drupal\firebase_config_manager\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\firebase_config_manager\Service\FirebaseService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Handles AJAX updates and restores for Firestore.
 */
class FirebaseUpdateController extends ControllerBase {

  /**
   * The Firebase service.
   *
   * @var \Drupal\firebase_config_manager\Service\FirebaseService
   */
  protected $firebaseService;

  /**
   * Constructs a new FirebaseUpdateController object.
   *
   * @param \Drupal\firebase_config_manager\Service\FirebaseService $firebase_service
   *   The Firebase service.
   */
  public function __construct(FirebaseService $firebase_service) {
    $this->firebaseService = $firebase_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('firebase_config_manager.service')
    );
  }

  /**
   * Updates a Firestore document field.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object containing the update parameters.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response indicating success or failure.
   */
  public function updateDocument(Request $request) {
    $collection = $request->request->get('collection');
    $doc = $request->request->get('doc');
    $field = $request->request->get('field');
    $value = $request->request->get('value');

    if (empty($collection) || empty($doc) || empty($field)) {
      throw new BadRequestHttpException('Invalid request: missing required parameters.');
    }

    $result = $this->firebaseService->updateFirestoreDocument($collection, $doc, $field, $value);

    if ($result) {
      return new JsonResponse(['status' => 'success', 'message' => 'Firestore document updated successfully.']);
    }

    return new JsonResponse(['status' => 'error', 'message' => 'Failed to update Firestore document.'], 500);
  }

  /**
   * Restores a Firestore document field to its previous value.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object containing the restore parameters.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response indicating success or failure.
   */
  public function restoreDocument(Request $request) {
    $collection = $request->request->get('collection');
    $doc = $request->request->get('doc');
    $field = $request->request->get('field');

    if (empty($collection) || empty($doc) || empty($field)) {
      throw new BadRequestHttpException('Invalid request: missing required parameters.');
    }

    $result = $this->firebaseService->restorePreviousValue($collection, $doc, $field);

    if ($result) {
      return new JsonResponse(['status' => 'success', 'message' => 'Firestore document restored successfully.']);
    }

    return new JsonResponse(['status' => 'error', 'message' => 'Failed to restore Firestore document.'], 500);
  }

  /**
   * Loads Firestore documents from a selected collection.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object containing the collection name.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing Firestore documents.
   */
  public function loadDocuments(Request $request) {
    $collection = $request->request->get('collection');

    if (empty($collection)) {
      throw new BadRequestHttpException('Invalid request: collection name is required.');
    }

    $documents = $this->firebaseService->getFilteredDocuments($collection);

    if (!empty($documents)) {
      return new JsonResponse(['status' => 'success', 'documents' => $documents]);
    }

    return new JsonResponse(['status' => 'error', 'message' => 'No documents found in the collection.'], 404);
  }
}