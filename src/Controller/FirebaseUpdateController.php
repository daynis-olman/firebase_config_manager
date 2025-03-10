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

class FirebaseUpdateController extends ControllerBase {

  protected $firebaseService;
  protected $logger;
  protected $currentUser;

  public function __construct(FirebaseService $firebase_service, LoggerInterface $logger, AccountProxyInterface $current_user) {
    $this->firebaseService = $firebase_service;
    $this->logger = $logger;
    $this->currentUser = $current_user;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('firebase_config_manager.service'),
      $container->get('logger.factory')->get('firebase_config_manager'),
      $container->get('current_user')
    );
  }

  private function checkAccess() {
    if (!$this->currentUser->hasPermission('manage firebase config')) {
      throw new AccessDeniedHttpException('You do not have permission to access this resource.');
    }
  }

  public function updateDocument(Request $request) {
    try {
      $this->checkAccess();
    }
    catch (AccessDeniedHttpException $e) {
      return new JsonResponse(['status' => 'error', 'message' => 'Access denied.'], 403);
    }

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

  // Remove the restoreDocument method entirely

  public function loadDocuments(Request $request) {
    try {
      $this->checkAccess();
    }
    catch (AccessDeniedHttpException $e) {
      return new JsonResponse(['status' => 'error', 'message' => 'Access denied.'], 403);
    }

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