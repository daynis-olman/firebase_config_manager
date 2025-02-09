<?php

namespace Drupal\firebase_config_manager\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\firebase_config_manager\Service\FirebaseService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class FirebaseAdminForm extends FormBase {
  protected $configFactory;
  protected $firebaseService;

  public function __construct(ConfigFactoryInterface $config_factory, FirebaseService $firebase_service) {
    $this->configFactory = $config_factory;
    $this->firebaseService = $firebase_service;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('firebase_config_manager.service')
    );
  }

  public function getFormId() {
    return 'firebase_admin_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('firebase_config_manager.settings');

    if (!$config->get('firebase_key')) {
      $form['firebase_key'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Firebase JSON Credentials'),
        '#description' => $this->t('Enter your Firebase JSON key. This will be stored securely.'),
        '#required' => TRUE,
      ];
    } else {
      $collections = $this->firebaseService->getCollections();
      $collection_options = [];
      foreach ($collections as $collection) {
        $collection_options[$collection->id()] = $collection->id();
      }

      $form['firebase_collection'] = [
        '#type' => 'select',
        '#title' => $this->t('Select Firestore Collection'),
        '#options' => $collection_options,
        '#required' => TRUE,
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save & Load Documents'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('firebase_config_manager.settings');

    if ($firebase_key = $form_state->getValue('firebase_key')) {
      $config->set('firebase_key', $firebase_key)->save();
      \Drupal::messenger()->addStatus($this->t('Firebase credentials saved securely.'));
    }

    if ($collection = $form_state->getValue('firebase_collection')) {
      $config->set('selected_collection', $collection)->save();
      \Drupal::messenger()->addStatus($this->t("Firestore collection '$collection' selected."));
    }
  }
}