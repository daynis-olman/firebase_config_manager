<?php

namespace Drupal\firebase_config_manager\Service;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\FirebaseException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Service to interact with Firebase Firestore.
 */
class FirebaseService {

  protected $firestore;
  protected $logger;

  /**
   * Constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerInterface $logger) {
    $this->logger = $logger;
    $firebase_config = $config_factory->get('firebase_config_manager.settings')->get('firebase_key');

    if (!$firebase_config) {
      $this->logger->error('Firebase credentials are not configured.');
      return;
    }

    try {
      $credentials = json_decode($firebase_config, true);
      if (!$credentials) {
        throw new \Exception("Invalid Firebase credentials JSON.");
      }

      $factory = (new Factory)->withServiceAccount($credentials);
      $this->firestore = $factory->createFirestore()->database();
    } catch (\Exception $e) {
      $this->logger->error('Firebase initialization error: ' . $e->getMessage());
    }
  }

  /**
   * Store original value before update (for Undo functionality).
   */
  public function storeOriginalValue($collection, $document_id, $field, $value) {
    if (!$this->firestore) {
      $this->logger->error('Firestore connection is not established.');
      return FALSE;
    }

    try {
      $docRef = $this->firestore->collection($collection)->document($document_id);
      $docRef->update([
        ['path' => '_previous_' . $field, 'value' => $value]
      ]);
      return TRUE;
    } catch (FirebaseException $e) {
      $this->logger->error('Error storing previous Firestore value: ' . $e->getMessage());
      return FALSE;
    }
  }

  /**
   * Update Firestore document field.
   */
  public function updateFirestoreDocument($collection, $document_id, $field, $new_value) {
    if (!$this->firestore) {
      $this->logger->error('Firestore connection failed.');
      return FALSE;
    }

    try {
      $docRef = $this->firestore->collection($collection)->document($document_id);
      $docSnapshot = $docRef->snapshot();
      if ($docSnapshot->exists() && isset($docSnapshot[$field])) {
        $this->storeOriginalValue($collection, $document_id, $field, $docSnapshot[$field]);
      }

      $docRef->update([
        ['path' => $field, 'value' => $new_value]
      ]);

      return TRUE;
    } catch (FirebaseException $e) {
      $this->logger->error('Error updating Firestore: ' . $e->getMessage());
      return FALSE;
    }
  }

  /**
   * Restore the original value (Undo functionality).
   */
  public function restorePreviousValue($collection, $document_id, $field) {
    if (!$this->firestore) {
      $this->logger->error('Firestore connection failed.');
      return FALSE;
    }

    try {
      $docRef = $this->firestore->collection($collection)->document($document_id);
      $docSnapshot = $docRef->snapshot();

      if ($docSnapshot->exists() && isset($docSnapshot['_previous_' . $field])) {
        $previousValue = $docSnapshot['_previous_' . $field];

        $docRef->update([
          ['path' => $field, 'value' => $previousValue]
        ]);

        return TRUE;
      }
      
      return FALSE;
    } catch (FirebaseException $e) {
      $this->logger->error('Error restoring Firestore value: ' . $e->getMessage());
      return FALSE;
    }
  }
}