<?php
// File: /bitnami/drupal/modules/contrib/myfolder/src/Controller/FirebaseUpdateController.php

namespace Drupal\firebase_config_manager\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\firebase_config_manager\Service\FirebaseService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Psr\Log\LoggerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;


/**
 * Handles AJAX operations for Firestore.
 */
class FirebaseUpdateController extends ControllerBase {

  /**
   * The Firebase service.
   *
   * @var \Drupal\firebase_config_manager\Service\FirebaseService
   */
  protected $firebaseService;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

    /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new FirebaseUpdateController object.
   *
   * @param \Drupal\firebase_config_manager\Service\FirebaseService $firebase_service
   *   The Firebase service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user account.
   */
  public function __construct(FirebaseService $firebase_service, LoggerInterface $logger, AccountProxyInterface $current_user) {
    $this->firebaseService = $firebase_service;
    $this->logger = $logger;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('firebase_config_manager.service'),
      $container->get('logger.factory')->get('firebase_config_manager'),
      $container->get('current_user')
    );
  }

    /**
   * Check access for the firebase admin routes.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A json response
   */
private function checkAccess() {
  if (!$this->currentUser->hasPermission('manage firebase config')) {
    throw new AccessDeniedHttpException('You do not have permission to access this resource.'); // Correctly throw the exception
  }
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
    // Check access permissions
    try {
        $this->checkAccess();
    }
    catch (AccessDeniedHttpException $e) {
        return new JsonResponse(['status' => 'error', 'message' => 'Access denied.'], 403);
    }

    // Get data from the request using getContent() and json_decode
    $content = $request->getContent();
    if (empty($content)) {
        $this->logger->error('Firestore update failed: No content provided.');
        return new JsonResponse(['status' => 'error', 'message' => 'Invalid request: No data provided.'], 400);
    }

    $data = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $this->logger->error('Firestore update failed: Invalid JSON provided.');
        return new JsonResponse(['status' => 'error', 'message' => 'Invalid request: Invalid JSON data.'], 400);
    }


    $collection = $data['collection'] ?? null;
    $doc = $data['doc'] ?? null;
    $field = $data['field'] ?? null;
    $value = $data['value'] ?? null;


    if (empty($collection) || empty($doc) || empty($field) || !isset($data['value'])) {
        $this->logger->error('Firestore update failed: Missing parameters.');
        return new JsonResponse(['status' => 'error', 'message' => 'Invalid request: Missing required parameters.'], 400);
    }

    try {
        //Crucially store the value before updating.
        $originalValue = $this->firebaseService->getFilteredDocuments($collection)[$doc][$field];
        $this->firebaseService->storeOriginalValue($collection, $doc, $field, $originalValue);

        $result = $this->firebaseService->updateFirestoreDocument($collection, $doc, $field, $value);

        if ($result) {
            return new JsonResponse(['status' => 'success', 'message' => 'Firestore document updated successfully.']);
        }

        return new JsonResponse(['status' => 'error', 'message' => 'Failed to update Firestore document.'], 500);

    } catch (\Exception $e) {
        $this->logger->error('Error updating Firestore: ' . $e->getMessage());
        return new JsonResponse(['status' => 'error', 'message' => 'Error updating Firestore document: ' . $e->getMessage()], 500);
    }
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
 // Check access permissions
    try {
        $this->checkAccess();
    }
    catch (AccessDeniedHttpException $e) {
        return new JsonResponse(['status' => 'error', 'message' => 'Access denied.'], 403);
    }

    // Get data from the request using getContent() and json_decode
    $content = $request->getContent();
    if (empty($content)) {
        $this->logger->error('Firestore restore failed: No content provided.');
        return new JsonResponse(['status' => 'error', 'message' => 'Invalid request: No data provided.'], 400);
    }

    $data = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $this->logger->error('Firestore restore failed: Invalid JSON provided.');
        return new JsonResponse(['status' => 'error', 'message' => 'Invalid request: Invalid JSON data.'], 400);
    }

    $collection = $data['collection'] ?? null;
    $doc = $data['doc'] ?? null;
    $field = $data['field'] ?? null;

    if (empty($collection) || empty($doc) || empty($field)) {
        $this->logger->error('Firestore restore failed: Missing parameters.');
        return new JsonResponse(['status' => 'error', 'message' => 'Invalid request: Missing required parameters.'], 400);
    }


    try {
      $result = $this->firebaseService->restorePreviousValue($collection, $doc, $field);

      if ($result) {
        return new JsonResponse(['status' => 'success', 'message' => 'Firestore document restored successfully.']);
      }

      return new JsonResponse(['status' => 'error', 'message' => 'Failed to restore Firestore document.'], 500);

    } catch (\Exception $e) {
      $this->logger->error('Error restoring Firestore value: ' . $e->getMessage());
      return new JsonResponse(['status' => 'error', 'message' => 'Error restoring Firestore document: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Loads Firestore documents for a selected collection.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object containing the collection name.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing Firestore documents.
   */
  public function loadDocuments(Request $request) {
     // Check access permissions
    try {
      $this->checkAccess();
    }
    catch (AccessDeniedHttpException $e) {  // Catch the specific exception
      return new JsonResponse(['status' => 'error', 'message' => 'Access denied.'], 403);
    }
     // Get data from the request using getContent() and json_decode
    $content = $request->getContent();
    if (empty($content)) {
        $this->logger->error('Firestore load documents failed: No content provided.');
        return new JsonResponse(['status' => 'error', 'message' => 'Invalid request: No data provided.'], 400);
    }

    $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
        $this->logger->error('Firestore load documents failed: Invalid JSON provided.');
        return new JsonResponse(['status' => 'error', 'message' => 'Invalid request: Invalid JSON data.'], 400);
    }


    $collection = $data['collection'] ?? null;

    if (empty($collection)) {
      $this->logger->error('Firestore document load failed: Collection name is required.');
      return new JsonResponse(['status' => 'error', 'message' => 'Collection name is required.'], 400);
    }

    try {
      $documents = $this->firebaseService->getFilteredDocuments($collection);

      if (!empty($documents)) {
        return new JsonResponse([
          'status' => 'success',
          'documents' => $documents,
        ]);
      }

      return new JsonResponse([
        'status' => 'error',
        'message' => 'No documents found in the collection.',
      ], 404);

    } catch (\Exception $e) {
      $this->logger->error('Error loading Firestore documents: ' . $e->getMessage());
      return new JsonResponse([
        'status' => 'error',
        'message' => 'Error loading Firestore documents: ' . $e->getMessage(),
      ], 500);
    }
  }
}