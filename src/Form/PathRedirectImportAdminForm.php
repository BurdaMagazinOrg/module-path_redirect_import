<?php
/**
 * Created by PhpStorm.
 * User: timowelde
 * Date: 17.03.16
 * Time: 17:10
 */

namespace Drupal\path_redirect_import\Form;


use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class PathRedirectImportAdminForm extends ConfigFormBase {

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return ['path_redirect_import.settings'];
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'path_redirect_import_admin_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = array();
    $form['csv'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Import from .csv or .txt file'),
      '#description' => $this->t('To import redirects, you must create a CSV or TXT.'),
    );

    $form['csv']['delimiter'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Delimiter'),
      '#description' => $this->t('Add your delimiter.'),
      '#default_value' => ',',
      '#maxlength' => 2,
    );
    $form['csv']['no_headers'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('No headers'),
      '#description' =>
        $this->t('Check if the imported file does not start with a header row.'),
      '#default_value' => 1
    );

    $form['csv']['override'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Override sources'),
      '#description' => $this->t('Check to override stored redirects.'),
    );

    $form['csv']['csv_file'] = array(
      '#type' => 'file',
      '#multiple' => false,
      '#description' =>
        $this->t('The CSV file must include columns in the following order:
      "From URL","To URL","Redirect Status","Redirect Language". Defaults for status and language can be set in the advanced options.'),
    );

    $form['advanced'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Advanced options'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );
    $form['advanced']['status_code'] = array(
      '#type' => 'select',
      '#title' => $this->t('Redirect status'),
      '#description' =>
        $this->t('You can find more information about HTTP redirect status codes at
      <a href="@status-codes">@status-codes</a>.',
          array('@status-codes' =>
            'http://en.wikipedia.org/wiki/List_of_HTTP_status_codes#3xx_Redirection',
          )
        ),
      '#default_value' => 301,
      '#options' => redirect_status_code_options(),
    );
    /*if (module_exists('locale')) {
      $form['advanced']['language'] = array(
        '#type' => 'select',
        '#title' => $this->t('Redirect language'),
        '#description' => $this->t('A redirect set for a specific language will always be used when requesting this page in that language, and takes precedence over redirects set for <em>All languages</em>.'),
        '#default_value' => LANGUAGE_NONE,
        '#options' => array(LANGUAGE_NONE => $this->t('All languages')) + locale_language_list('name'),
      );
    }*/

//    $form['submit'] = array('#type' => 'submit', '#value' => $this->t('Import'));
//    $form['#attributes'] = array('enctype' => "multipart/form-data");

    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $validators = array('file_validate_extensions' => array('csv txt'));
    $files = file_save_upload('csv_file', $validators);
    if ($files[0]) {
      $form_state->setValue('uploaded_file', $files[0]);
    }
    else {
      $form_state->setErrorByName('csv_file', $this->t('File upload failed.'));
    }
  }


  public function submitForm(array &$form, FormStateInterface $form_state) {
    ini_set('auto_detect_line_endings', TRUE);
    /** @var \Drupal\file\Entity\File $file */
    $file = $form_state->getValue('uploaded_file');
    if (!isset($file)) {
      return;
    }

    $result = path_redirect_import_read_file(
      $file->getFileUri(), $form_state->getValues());
    if ($result['success']) {
      drupal_set_message(implode('<br />', $result['message']));
    }
    else {
      drupal_set_message(implode('<br />', $result['message']), 'error');
    }

    $file->delete();

    parent::submitForm($form, $form_state);

  }


}