<?php

namespace Drupal\firebase_config_manager\Service;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\FirebaseException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Service to interact with Firebase Remote Config & Firestore.
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
   * Get available Firestore collections.
   */
  public function getCollections() {
    try {
      return $this->firestore->collections();
    } catch (FirebaseException $e) {
      $this->logger->error('Error fetching collections: ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Get documents within a collection that contain only string or integer fields.
   */
  public function getFilteredDocuments($collection) {
    try {
      $documents = [];
      $collectionRef = $this->firestore->collection($collection);
      $querySnapshot = $collectionRef->documents();

      foreach ($querySnapshot as $document) {
        $filtered_fields = array_filter(
          $document->data(),
          fn($value) => is_string($value) || is_int($value)
        );
        if (!empty($filtered_fields)) {
          $documents[$document->id()] = $filtered_fields;
        }
      }
      return $documents;
    } catch (FirebaseException $e) {
      $this->logger->error('Error fetching documents: ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Update Firestore document field.
   */
  public function updateFirestoreDocument($collection, $document_id, $field, $new_value) {
    try {
      $docRef = $this->firestore->collection($collection)->document($document_id);
      $docRef->update([
        ['path' => $field, 'value' => $new_value]
      ]);

      $this->logger->info("Updated Firestore: {$collection}/{$document_id} - {$field} = $new_value");
      return TRUE;
    } catch (FirebaseException $e) {
      $this->logger->error('Error updating Firestore: ' . $e->getMessage());
      return FALSE;
    }
  }
}