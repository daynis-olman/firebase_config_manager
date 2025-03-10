<?php
// File: /bitnami/drupal/modules/contrib/myfolder/src/Form/FirebaseAdminForm.php

namespace Drupal\firebase_config_manager\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\firebase_config_manager\Service\FirebaseService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\Core\Render\RendererInterface; // Import RendererInterface


/**
 * Provides an admin form for Firebase configuration.
 */
class FirebaseAdminForm extends FormBase {

  protected $configFactory;
  protected $firebaseService;
  protected $messenger;
    /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;


  /**
   * Constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory, FirebaseService $firebase_service, MessengerInterface $messenger, RendererInterface $renderer) {
    $this->configFactory = $config_factory;
    $this->firebaseService = $firebase_service;
    $this->messenger = $messenger;
    $this->renderer = $renderer;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('firebase_config_manager.service'),
      $container->get('messenger'),
      $container->get('renderer') // Inject the renderer
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'firebase_admin_form';
  }

  /**
   * Build the admin form with AJAX Firestore document management.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('firebase_config_manager.settings');

    $form['#prefix'] = '<div id="firebase-admin-form">';
    $form['#suffix'] = '</div>';

    // Attach Libraries
    $form['#attached']['library'][] = 'firebase_config_manager/firebase_admin_styles';
    $form['#attached']['library'][] = 'firebase_config_manager/firebase_editor';
    $form['#attached']['library'][] = 'firebase_config_manager/firebase_datatables';

    // Display Firebase Project ID & Name
    $project_id = $this->firebaseService->getProjectId();
    if ($project_id) {
      $form['project_id_display'] = [
        '#type' => 'markup',
        '#markup' => $this->t('Current Firebase Project: @project', ['@project' => $project_id]),
      ];
    }

    // Firebase Key Environment Variable Name
    $env_var_name = $config->get('firebase_key_env_var');
    $form['firebase_key_env_var'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Firebase JSON Key Environment Variable'),
      '#description' => $this->t('Enter the name of the environment variable that stores your Firebase JSON key. This is a more secure way to store your credentials.'),
      '#default_value' => $env_var_name,
      '#required' => TRUE,
    ];

    // Display truncated Key for verification
    if ($env_var_name) {
      $firebase_key = getenv($env_var_name);
      if ($firebase_key) {
        $truncated_key = substr($firebase_key, 0, (int) (strlen($firebase_key) * 0.2)) . '...'; // Explicitly cast to int
        $form['firebase_key_display'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Firebase Key (Truncated)'),
          '#default_value' => $truncated_key,
          '#disabled' => TRUE,
          '#description' => $this->t('A truncated version of the key to verify the environment variable is set.'),
        ];
      } else {
        $form['firebase_key_error'] = [
          '#type' => 'markup',
          '#markup' => '<p class="error-message">⚠️ The specified environment variable is not set.</p>',
        ];
      }
    }

    // Display Firestore Collections if available
    $collections = $this->firebaseService->getCollections();
    if (!empty($collections)) {
      $collection_options = array_combine($collections, $collections);
      $form['firebase_collection'] = [
        '#type' => 'select',
        '#title' => $this->t('Select Firestore Collection'),
        '#options' => $collection_options,
        '#required' => TRUE,
        '#default_value' => $config->get('selected_collection'),
        '#ajax' => [
          'callback' => '::loadFirestoreDocuments',
          'wrapper' => 'firestore-documents-wrapper',
          'event' => 'change',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Loading documents...'),
          ],
        ],
      ];
    } else {
      $form['firebase_collection_error'] = [
        '#type' => 'markup',
        '#markup' => '<p class="error-message">⚠️ No Firestore collections found. Check your Firebase credentials.</p>',
      ];
    }

    // Container for Firestore Documents Table (AJAX-loaded)
    $form['firestore_documents_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'firestore-documents-wrapper'],
    ];

    // Initially load the documents if a collection is already selected.
    $selected_collection = $config->get('selected_collection');
    if (!empty($selected_collection)) {
      $form['firestore_documents_wrapper']['firestore_documents_table'] = $this->buildFirestoreDocumentsTable($selected_collection);
    }
    else {
      $form['firestore_documents_wrapper']['#markup'] = '<p>Select a collection to load documents.</p>';
    }
    // Submit Button for Config
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Configuration'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    return $form;
  }

   /**
   * AJAX callback to remove Firebase credentials.
   */
  public function removeCredentialsCallback(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('firebase_config_manager.settings');
    $config->set('firebase_key_env_var', '')->save();
    $this->messenger->addStatus($this->t('Firebase credentials removed.'));

    //Rebuild the form to reflect the changes
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#firebase-admin-form', $this->renderer->render($form))); // Use injected renderer
    return $response;
  }

  /**
   * Helper function to build the Firestore documents table.
   */
  private function buildFirestoreDocumentsTable($collection) {
    $documents = $this->firebaseService->getFilteredDocuments($collection);
    $table = [
      '#type' => 'table',
      '#header' => [
        $this->t('Document ID'),
        $this->t('Field'),
        $this->t('Value'),
      ],
      '#attributes' => ['id' => 'firestore-documents-table'],
    ];

    if (!empty($documents)) {
      foreach ($documents as $doc_id => $fields) {
        foreach ($fields as $field_name => $field_value) {
          // Ensure the field value is a string or can be cast to a string
          $field_value = (string)$field_value;
          $table[$doc_id . '-' . $field_name] = [
            'document_id' => [
              '#markup' => $doc_id,
            ],
            'field_name' => [
              '#markup' => $field_name,
            ],
            'field_value' => [
              '#type' => 'textfield',
              '#default_value' => $field_value,
              '#attributes' => [
                'class' => ['firebase-edit'],
                'data-doc' => $doc_id,
                'data-field' => $field_name,
                'title' => $this->t('Enter alphanumeric characters and spaces only.'), // Tooltip
              ],
            ],
          ];
        }
      }
    }
    else {
      return [
        '#markup' => '<p>No editable fields found in this collection.</p>',
      ];
    }
    return $table;
  }

  /**
   * AJAX callback to load Firestore documents dynamically.
   */
  public function loadFirestoreDocuments(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $collection = $form_state->getValue('firebase_collection');

    if (empty($collection)) {
      $response->addCommand(new ReplaceCommand('#firestore-documents-wrapper', '<p class="error-message">Collection not selected.</p>'));
      return $response;
    }

    $table = $this->buildFirestoreDocumentsTable($collection);
    $response->addCommand(new ReplaceCommand('#firestore-documents-wrapper', $this->renderer->render($table))); // Use injected renderer

    return $response;
  }

  /**
   * Handle form submission for Firebase credentials.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('firebase_config_manager.settings');

    // Save the environment variable name
    if ($form_state->hasValue('firebase_key_env_var')) {
      $env_var_name = trim($form_state->getValue('firebase_key_env_var'));
      $config->set('firebase_key_env_var', $env_var_name)->save();
      $this->messenger->addStatus($this->t('Firebase environment variable name saved.'));
    }
    // Save the selected Firestore Collection
    if ($form_state->hasValue('firebase_collection')) {
      $collection = $form_state->getValue('firebase_collection');
      $config->set('selected_collection', $collection)->save();
      $this->messenger->addStatus($this->t("Firestore collection '@collection' selected.", ['@collection' => $collection]));
    }

    $form_state->setRebuild();
  }

}