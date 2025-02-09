<?php

namespace Drupal\firebase_config_manager\Service;

use Kreait\Firebase\Factory;
use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Service to interact with Firebase Remote Config & Firestore.
 */
class FirebaseService {
  protected $remoteConfig;
  protected $firestore;
  protected $logger;

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
      $this->remoteConfig = $factory->createRemoteConfig();
      $this->firestore = $factory->createFirestore()->database();
    } catch (\Exception $e) {
      $this->logger->error('Firebase initialization error: ' . $e->getMessage());
    }
  }

  public function getCollections() {
    try {
      return $this->firestore->collections();
    } catch (\Exception $e) {
      $this->logger->error('Error fetching collections: ' . $e->getMessage());
      return [];
    }
  }
}