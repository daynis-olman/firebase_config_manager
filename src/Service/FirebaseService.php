<?php
// File: /bitnami/drupal/modules/contrib/myfolder/src/Service/FirebaseService.php

namespace Drupal\firebase_config_manager\Service;

// Include Composer's autoloader *after* the namespace declaration
require_once '/bitnami/drupal/vendor/autoload.php'; // Full path is crucial

use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\FirebaseException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;
use Drupal\Component\Serialization\Json;
use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Core\Exception\GoogleException;
use Drupal\Core\File\FileSystemInterface;

class FirebaseService {

  protected $firestore;
  protected $logger;
  protected $projectId;
  protected $configFactory;

  public function __construct(ConfigFactoryInterface $config_factory, LoggerInterface $logger, FileSystemInterface $file_system) {
    $this->logger = $logger;
    $this->configFactory = $config_factory;

    $env_var_name = $this->configFactory->get('firebase_config_manager.settings')->get('firebase_key_env_var');

    if (empty($env_var_name)) {
      $this->logger->error('Firebase environment variable name is not configured.');
      return;
    }

    $firebase_config = getenv($env_var_name);
    if (empty($firebase_config)) {
      $this->logger->error('Firebase environment variable is not set or is empty.');
      return;
    }

    try {
      $credentials = Json::decode($firebase_config);
      if (!is_array($credentials)) {
        throw new \InvalidArgumentException("Invalid Firebase credentials JSON.");
      }

      $factory = (new Factory())->withServiceAccount($credentials);

      try {
          $this->firestore = $factory->createFirestore();
          $this->projectId = $credentials['project_id'] ?? NULL;
      } catch (GoogleException $e) {
          $this->logger->error('Firestore database creation error: ' . $e->getMessage());
          $this->firestore = null;
          $this->projectId = null;
          return;
      } catch (\Exception $e) {
          $this->logger->error('Firestore client creation error: ' . $e->getMessage());
          $this->firestore = null;
          $this->projectId = null;
          return;
      }

    } catch (\InvalidArgumentException $e) {
      $this->logger->error('Firebase credentials decoding error: ' . $e->getMessage());
      $this->firestore = null;
      $this->projectId = null;
    } catch (\Exception $e) {
        $this->logger->error('Firebase initialization error: ' . $e->getMessage());
        $this->firestore = null;
        $this->projectId = null;
    }
  }

  public function getCollections() {
    if (!$this->firestore) {
      $this->logger->error('Firestore connection is not established.');
      return NULL;
    }

    try {
      $collections = $this->firestore->database()->collections();
      $collectionNames = [];

      foreach ($collections as $collection) {
        $collectionNames[] = $collection->id();
      }

      return $collectionNames;
    } catch (FirebaseException $e) {
      $this->logger->error('Error retrieving Firestore collections: ' . $e->getMessage());
      return [];
    }  catch (\Exception $e) {
        $this->logger->error('Unexpected error retrieving collections: ' . $e->getMessage());
        return [];
    }
  }

  public function getProjectId() {
    return $this->projectId;
  }

  public function getFilteredDocuments($collection) {
    if (!$this->firestore) {
      $this->logger->error('Firestore connection is not established.');
      return NULL;
    }

    try {
      $collectionRef = $this->firestore->database()->collection($collection);
      $documents = $collectionRef->documents();
      $filteredData = [];

      foreach ($documents as $document) {
        if ($document->exists()) {
          $data = $document->data();
          $filteredFields = [];

          foreach ($data as $field => $value) {
            if (is_string($value) || is_int($value)) {
              $filteredFields[$field] = $value;
            }
          }

          if (!empty($filteredFields)) {
            $filteredData[$document->id()] = $filteredFields;
          }
        }
      }

      return $filteredData;
    } catch (FirebaseException $e) {
      $this->logger->error('Error retrieving Firestore documents: ' . $e->getMessage());
      return [];
    } catch (\Exception $e) {
        $this->logger->error('Unexpected error retrieving documents: ' . $e->getMessage());
        return [];
    }
  }

  // Remove storeOriginalValue method

  public function updateFirestoreDocument($collection, $document_id, $field, $new_value) {
    if (!$this->firestore) {
      $this->logger->error('Firestore connection failed.');
      return FALSE;
    }

    try {
      $docRef = $this->firestore->database()->collection($collection)->document($document_id);
      $docSnapshot = $docRef->snapshot();

      if (!$docSnapshot->exists()) {
        $this->logger->warning("Document {$document_id} does not exist in collection {$collection}.");
        return FALSE;
      }

      $data = $docSnapshot->data();
      if (!array_key_exists($field, $data)) {
        $this->logger->warning("Field {$field} does not exist in document {$document_id}.");
        return FALSE;
      }

      if (is_int($data[$field])) {
        $new_value = (int) $new_value;
      }
      elseif (is_string($data[$field])) {
        $new_value = (string) $new_value;
      }

      $docRef->update([
        ['path' => $field, 'value' => $new_value]
      ]);

      return TRUE;
    } catch (FirebaseException $e) {
      $this->logger->error('Error updating Firestore: ' . $e->getMessage());
      return FALSE;
    } catch (\Exception $e) {
        $this->logger->error('Unexpected error updating document: ' . $e->getMessage());
        return FALSE;
    }
  }

  // Remove restorePreviousValue method
}