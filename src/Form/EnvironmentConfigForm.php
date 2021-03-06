<?php

namespace Drupal\ezconfig_manager\Form;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for exporting a single configuration file.
 */
class EnvironmentConfigForm extends FormBase {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The config storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;

  /**
   * Tracks the valid config entity type definitions.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface[]
   */
  protected $definitions = [];

  /**
   * Constructs a new ConfigSingleImportForm.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Config\StorageInterface $config_storage
   *   The config storage.
   */
  public function __construct(EntityManagerInterface $entity_manager, StorageInterface $config_storage) {
    $this->entityManager = $entity_manager;
    $this->configStorage = $config_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('config.storage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'config_single_export_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $config_name = NULL) {

    $form['config_name'] = [
      '#title' => $this->t('Configuration name'),
      '#type' => 'select',
      '#options' => $this->findConfiguration(),
      '#required' => TRUE,
      '#default_value' => $config_name,
      '#prefix' => '<div id="edit-config-type-wrapper">',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => '::updateExport',
        'wrapper' => 'edit-export-wrapper',
      ],
    ];

    $form['dir'] = [
      '#title' => $this->t('Configuration folder'),
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => $this->getDirs(),
      '#ajax' => [
        'callback' => '::updateCustomExport',
        'wrapper' => 'edit-export-wrapper',
      ],
    ];

    $form['export'] = [
      '#title' => $this->t('Here appears your configuration:'),
      '#type' => 'textarea',
      '#rows' => 24,
      '#prefix' => '<div id="edit-export-wrapper">',
      '#suffix' => '</div>',
    ];

    if ($config_name) {
      $fake_form_state = (new FormState())->setValues([
        'config_name' => $config_name,
      ]);
      $form['export'] = $this->updateExport($form, $fake_form_state);
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * Handles switching the export textarea.
   */
  public function updateExport($form, FormStateInterface $form_state) {
    $name = $form_state->getValue('config_name');
    $dir = $form_state->getValue('dir');

    $form['export']['#title'] = $this->t('Here appears your configuration:');
    $form['export']['#value'] = '';

    if ($dir != '_none' && file_exists($dir . '/' . $name)) {
      $file = $dir . '/' . $name;
      $form['export']['#title'] = $this->t('File already exists on %dir: %name', ['%name' => $name, '%dir' => $dir]);

      // Read the raw data for this config name, encode it, and display it.
      $form['export']['#value'] = file_get_contents($file);
      $form['export']['#description'] = $this->t('Filename: %name', ['%name' => $name]);
    }

    return $form['export'];
  }

  /**
   * Handles switching the export textarea.
   */
  public function updateCustomExport($form, FormStateInterface $form_state) {
    $name = $form_state->getValue('config_name');
    $dir = $form_state->getValue('dir');

    $form['export']['#title'] = $this->t('Here appears your configuration:');
    $form['export']['#value'] = '';

    if ($dir != '_none' && file_exists($dir . '/' . $name)) {
      $file = $dir . '/' . $name;
      $form['export']['#title'] = $this->t('File already exists on %dir: %name', ['%name' => $name, '%dir' => $dir]);

      // Read the raw data for this config name, encode it, and display it.
      $form['export']['#value'] = file_get_contents($file);
      $form['export']['#description'] = $this->t('Filename: %name', ['%name' => $name]);
    }

    return $form['export'];
  }

  /**
   * Handles switching the configuration type selector.
   */
  protected function findConfiguration() {
    $files = [];
    foreach ($this->getDirs() as $path => $env) {
      foreach ($this->getSubDirs($path) as $subdir) {
        $yml = scandir($subdir);

        foreach ($yml as $file) {
          if (strrpos($file, '.yml') !== FALSE) {
            $extra = str_replace($path, '', $subdir);

            if (!empty($extra)) {
              $file = $extra . '/' . $file;
            }

            if (!in_array($file, $files)) {
              $files[addslashes($file)] = $file;
            }
          }
        }
      }
    }

    return $files;
  }

  /**
   * Custom funtion to get sub dirs.
   */
  protected function getSubDirs($dir) {
    $sub = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::SELF_FIRST,
      \RecursiveIteratorIterator::CATCH_GET_CHILD
    );

    $paths = [$dir];
    foreach ($sub as $path => $dir) {
      if ($dir->isDir()) {
        $paths[] = $path;
      }
    }

    return $paths;
  }

  /**
   * Custom function to get the config dirs.
   */
  protected function getDirs($exclude = FALSE) {
    global $config_directories;

    $sync = $config_directories[CONFIG_SYNC_DIRECTORY];
    if ($exclude == TRUE) {
      $configs = [
        'active' => \Drupal::configFactory()->get('environment_config')->get('config'),
      ];

      $global = substr($sync, 0, (similar_text($sync, $configs['active']) - 1));

      foreach (scandir($global) as $dir) {
        if (strrpos($dir, '.') === FALSE && !in_array($global . $dir, $configs)
          && $global . $dir != $sync) {
          $configs[$dir] = $global . $dir;
        }
      }
    }
    else {
      $configs = [];

      $config = \Drupal::configFactory()->get('environment_config')->get('config');
      $configs[$config] = 'active';

      $global = substr($sync, 0, (similar_text($sync, $config) - 1));

      foreach (scandir($global) as $dir) {
        if (strrpos($dir, '.') === FALSE && !in_array($dir, $configs)
          && $global . $dir != $sync) {
          $configs[$global . $dir] = $dir;
        }
      }

      $exclude_dir = \Drupal::service('file_system')->realpath(file_default_scheme() . "://ezconfig_exclude");
      if (!realpath($exclude_dir)) {
        \Drupal::service('file_system')->mkdir($exclude_dir);
      }
    }

    return $configs;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $dir = $form_state->getValue('dir');
    $tmp = explode('/', $form_state->getValue('config_name'));
    $config_name = $tmp[(count($tmp) - 1)];

    if (!empty($dir) && !is_writable($dir)) {
      $form_state->setError($form['dir'], $this->t('%dir is not writable', ['%dir' => $dir]));
    }

    if (!is_writable($dir . '/' . $config_name)) {
      $form_state->setError($form['config_name'], $this->t('%config is not writable', ['%config' => $dir . '/' . $config_name]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config_name = $values['config_name'];
    $dir = $values['dir'];
    $content = $values['export'];
    file_put_contents($dir . '/' . $config_name, $content);
    drupal_set_message('Config added to ' . $dir);
  }

}
