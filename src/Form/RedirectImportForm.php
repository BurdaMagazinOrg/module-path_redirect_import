<?php

namespace Drupal\path_redirect_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\path_redirect_import\ImporterService;
use Drupal\Core\Language\Language;

/**
 * Class RedirectImportForm.
 *
 * @package Drupal\path_redirect_import\Form
 */
class RedirectImportForm extends FormBase {

  /**
   * Uploaded file entity.
   *
   * @var \Drupal\file\Entity\File
   */
  protected $file;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'redirect_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['csv'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Import from .csv or .txt file'),
    );
    $form['csv']['delimiter'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Delimiter'),
      '#description' => $this->t('Add your delimiter (e.g., comma, pipe)'),
      '#maxlength' => 2,
      '#size' => 4,
      '#default_value' => ',',
    );
    $form['csv']['no_headers'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('No headers'),
      '#description' => $this->t('If your imported file does not include a header row, make sure that you check this box.'),
    );
    $form['csv']['override'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Override existing sources'),
      '#description' => $this->t('To override stored redirects, check this box.'),
    );
    $validators = array(
      'file_validate_extensions' => array('csv'),
      'file_validate_size' => array(file_upload_max_size()),
    );
    $form['csv']['csv_file'] = array(
      '#type' => 'file',
      '#title' => $this->t('CSV File'),
      '#description' => array(
        '#theme' => 'file_upload_help',
        '#description' => $this->t('The CSV file must include the following columns in this order: "From URL","To URL","Redirect Status","Redirect Language". Defaults for status and language can be set in the advanced options, below.'),
      ),
      '#upload_validators' => $validators,
    );

    $form['advanced'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Advanced options'),
    );
    $form['advanced']['status_code'] = array(
      '#type' => 'select',
      '#title' => $this->t('Redirect status'),
      '#description' => $this->t('Set a default redirect value to use. Values set explicitly in the uploaded file will still take precedence. Find more information about HTTP redirect status codes <a href=":status_codes">here</a>.', array(':status_codes' => 'https://en.wikipedia.org/wiki/List_of_HTTP_status_codes#3xx_Redirection')),
      '#options' => redirect_status_code_options(),
      '#default_value' => '301',
      '#size' => 5,
    );
    if (\Drupal::moduleHandler()->moduleExists('language')) {
      $options = array();
      // We always need a language.
      $languages = \Drupal::languageManager()->getLanguages();
      foreach ($languages as $langcode => $language) {
        $options[$langcode] = $language->getName();
      }
      $form['advanced']['language'] = array(
        '#type' => 'language_select',
        '#title' => t('Redirect language'),
        '#description' => t('A redirect set for a specific language will always be used when requesting this page in that language, and takes precedence over redirects set for <em>All languages</em>.'),
        '#default_value' => Language::LANGCODE_NOT_SPECIFIED,
        '#options' => array(Language::LANGCODE_NOT_SPECIFIED => t('All languages')) + $options,
      );
    }
    $form['advanced']['suppress_messages'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Suppress displaying line-specific messages on screen'),
      '#description' => $this->t('Consider checking this if you are importing a very large amount of redirects. Reporting will still be logged, and general import messages will still print.'),
    );
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $this->file = file_save_upload('csv_file', $form['csv']['csv_file']['#upload_validators'], FALSE, 0);

    // Ensure we have the file uploaded.
    if (!$this->file) {
      $form_state->setErrorByName('csv_file', $this->t('You must add a valid file to the form in order to import redirects.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    ini_set('auto_detect_line_endings', TRUE);
    // Don't do anything if no valid file.
    if (!isset($this->file)) {
      drupal_set_message($this->t('No valid file was found. No redirects have been imported.'), 'warning');
      return;
    }
    $options = [
      'status_code' => $form_state->getValue('status_code'),
      'override' => $form_state->getValue('override'),
      'no_headers' => $form_state->getValue('no_headers'),
      'delimiter' => $form_state->getValue('delimiter'),
      'language' => $form_state->getValue('language') ?: Language::LANGCODE_NOT_SPECIFIED,
      'suppress_messages' => $form_state->getValue('suppress_messages'),
    ];

    ImporterService::import($this->file, $options);

    // Remove file from Drupal managed files & from filesystem.
    file_delete($this->file->id());
  }

}
