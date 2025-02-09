<?php

namespace Drupal\firebase_config_manager\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\firebase_config_manager\Service\FirebaseService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;

/**
 * Provides an AJAX-based admin form for managing Firestore.
 */
class FirebaseAdminForm extends FormBase {

  protected $configFactory;
  protected $firebaseService;

  /**
   * Constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory, FirebaseService $firebase_service) {
    $this->configFactory = $config_factory;
    $this->firebaseService = $firebase_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('firebase_config_manager.service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'firebase_admin_form';
  }

  /**
   * Build the admin form with AJAX-based Firestore document management.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('firebase_config_manager.settings');

    $form['#prefix'] = '<div id="firebase-admin-form">';
    $form['#suffix'] = '</div>';
    
    // Attach CSS & JavaScript Libraries
    $form['#attached']['library'][] = 'firebase_config_manager/firebase_admin_styles';
    $form['#attached']['library'][] = 'firebase_config_manager/firebase_editor';

    // Firebase Credentials Input
    if (!$config->get('firebase_key')) {
      $form['firebase_key'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Firebase JSON Key'),
        '#description' => $this->t('Paste your Firebase JSON credentials here. This will be securely stored and cannot be retrieved later.'),
        '#required' => TRUE,
      ];
    } else {
      $form['firebase_key_info'] = [
        '#markup' => '<p><strong>' . $this->t('Firebase credentials are saved securely.') . '</strong></p>',
      ];
    }

    // Firestore Collection Selection (with AJAX)
    if ($config->get('firebase_key')) {
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
        '#ajax' => [
          'callback' => '::loadFirestoreDocuments',
          'wrapper' => 'firestore-documents-wrapper',
          'event' => 'change',
        ],
      ];
    }

    // Firestore Documents Table (Populated via AJAX)
    $form['firestore_documents'] = [
      '#type' => 'markup',
      '#markup' => '<div id="firestore-documents-wrapper"></div>',
    ];

    // Submit Button (For Firebase Credentials)
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Configuration'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    return $form;
  }

  /**
   * AJAX callback to load Firestore documents dynamically.
   */
  public function loadFirestoreDocuments(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $collection = $form_state->getValue('firebase_collection');
    $documents = $this->firebaseService->getFilteredDocuments($collection);

    $document_markup = '<div id="firestore-documents-wrapper">';
    if (!empty($documents)) {
      $document_markup .= '<table class="firestore-table"><tr><th>Document ID</th><th>Field</th><th>Value</th></tr>';
      foreach ($documents as $doc_id => $fields) {
        foreach ($fields as $field_name => $field_value) {
          $document_markup .= "<tr>
            <td>{$doc_id}</td>
            <td>{$field_name}</td>
            <td><input type='text' class='firebase-edit' data-doc='{$doc_id}' data-field='{$field_name}' value='{$field_value}'></td>
          </tr>";
        }
      }
      $document_markup .= '</table>';
    } else {
      $document_markup .= '<p>No editable fields found.</p>';
    }
    $document_markup .= '</div>';

    $response->addCommand(new ReplaceCommand('#firestore-documents-wrapper', $document_markup));
    return $response;
  }

  /**
   * Handle form submission for Firebase credentials.
   */
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