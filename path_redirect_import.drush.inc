<?php

function path_redirect_import_drush_command() {
  $items = array();

  $items['path-redirect-import'] = array(
    'callback' => 'path_redirect_import_drush',
    'description' => "Import list of URL redirects",
    'examples' => array(
      'drush -l http://example.com path-redirect-import sites/all/modules/custom/import_tienda/import/consulta_fabricantes_espana.xls',
      'drush -l http://example.com primport sites/all/modules/custom/import_tienda/import/consulta_fabricantes_espana.xls',
    ),
    'aliases' => array('primport'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
  );

  return $items;
}

function path_redirect_import_drush($file) {
  if (!file_exists($file)) {
    drush_log("File $filename doesn't exist \n", 'error');
    exit;
  }
  //Read csv file
  if (($handle = fopen($file, "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
      //Check if the CSV contains at least 2 required params
      if(count($data) < 2)  continue;
      $redirect=array();
      if ($existing = path_redirect_load_by_source($data[1], isset($data[3]) ? $data[3] : '')) {
        $redirect['values']['rid'] = $existing['rid'];
      }
      $redirect['values']['source'] = $data[1];
      $aux_redirect = array();
      path_redirect_validate_redirect_field(array('#value' => $data[0]), $redirect);
      $redirect['values']['type'] = isset($data[2]) ? $data[2] : '301';
      $redirect['values']['language'] = isset($data[3]) ? $data[3] : '';
      $redirect = path_redirect_save($redirect['values']);

      if ($redirect) {
        drush_log(dt('Redirect from !source to !redirect created/updated', array('!source' => $data[1], '!redirect' => $data[0])), 'success');
      }
      else {
        drush_set_error('DRUSH_IMPORT_ERROR', dt('Failed to create redirect form from !source to !redirect created/updated', array('!source' => $redirect['source'], '!redirect' => $redirect['redirect'])));
      }

    }
    fclose($handle);
  }
}