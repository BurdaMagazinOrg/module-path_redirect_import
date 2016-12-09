<?php

namespace Drupal\path_redirect_import;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\redirect\Entity\Redirect;
use Drupal\redirect\Exception\RedirectLoopException;

/**
 * Class RedirectImport
 * @package Drupal\path_redirect_import
 */
class RedirectImport {

  /**
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * RedirectImport constructor.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   */
  public function __construct(LanguageManagerInterface $languageManager) {
    $this->languageManager = $languageManager;
  }

  /**
   * @param $langcode
   * @return bool
   */
  public function isValidLanguage($langcode) {
    return in_array($langcode, $this->validLanguages());
  }

  /**
   * @return array
   */
  public function validLanguages() {
    $languages = $this->languageManager->getLanguages();
    $languages = array_keys($languages);

    $defaultLockedLanguages = $this->languageManager->getDefaultLockedLanguages();
    $defaultLockedLanguages = array_keys($defaultLockedLanguages);

    return array_merge($languages, $defaultLockedLanguages);
  }

  /**
   * @param $line
   * @param $options
   * @param $context
   */
  public function saveRedirectCallback($line, $options, &$context) {

    list($source, $destination, $status, $language) = $line;
    $source = UrlHelper::parse($source);

    if (!$source['path'] || $source['path'] == '/') {
      return;
    }

    try {

      /**
       * @var \Drupal\redirect\RedirectRepository
       */
      $redirectRepository = \Drupal::service('redirect.repository');
      $trimmedPath = ltrim($source['path'], '/');

      /**
       * @var array \Drupal\redirect\Entity\Redirect $existingRedirect
       */
      $existingRedirect = $redirectRepository->findMatchingRedirect($trimmedPath, $source['query']);
    }
    catch (RedirectLoopException $e) {
      $context['message'] = $e->getMessage();
      return;
    }

    if ($existingRedirect) {
      if ($options['override']) {
        foreach($existingRedirect as $key => $value) {
          // TODO: Reuse existing redirects
          $value->delete();
        }
      }
      else {
        return;
      }
    }

    /**
     * @var Drupal\redirect\Entity\Redirect $redirect
     */
    $redirect = Redirect::create();
    $redirect->setSource($source['path'], $source['query']);
    if (UrlHelper::isExternal($destination)) {
      return;
//      $this->setRedirect($redirect, $destination);
    }
    else {
      /**
       * @var \Drupal\Core\Path\AliasManager $aliasManager
       */
      $aliasManager = \Drupal::service('path.alias_manager');
      $path = $aliasManager->getPathByAlias('/' . ltrim($destination, '/'));
      $redirect->setRedirect($path);

      if (\Drupal::moduleHandler()->moduleExists('language')) {
        if (!empty($language) && $this->isValidLanguage($language)){
          $redirect->setLanguage($language);
        }
      }

    }
    $redirect->setStatusCode($status);

    try {
      $redirect->save();
      $context['results'][] = array(
        'success' => TRUE,
      );
    }
    catch(Exception $e) {
      $context['results'][] = array(
        'success' => TRUE,
        'message'=>$e->getMessage()
      );
      $context['message'] = $e->getMessage();
    }
  }

  /**
   * @param $redirect
   * @param $url
   * @param array $query
   * @param array $options
   */
  protected function setRedirect($redirect, $url, array $query = array(), array $options = array()) {
    $uri = $url . ($query ? '?' . UrlHelper::buildQuery($query) : '');
    $redirect->redirect_redirect->set(0, [$uri, 'options' => $options]);
  }

}