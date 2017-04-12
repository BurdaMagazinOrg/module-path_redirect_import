<?php

namespace Drupal\path_redirect_import\Tests\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Test that redirects are properly imported from CSV file.
 *
 * @group path_redirect_import
 */
class RedirectImportTest extends BrowserTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = array(
    'node',
    'file',
    'redirect',
    'path_redirect_import',
    'language',
  );

  /**
   * A user with permission to administer nodes.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $testUser;

  /**
   * An CSV file path for uploading.
   *
   * @var \Drupal\file\FileInterface
   */
  protected $csv;

  /**
   * An array of content for testing purposes.
   *
   * @var string[]
   */
  protected $test_data = array(
    'First Page' => 'Page 1',
    'Second Page' => 'Page 2',
    'Third Page' => 'Page 3',
  );

  /**
   * An array of nodes created for testing purposes.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected $nodes;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->testUser = $this->drupalCreateUser([
      'access content',
      'administer nodes',
      'access site reports',
      'administer languages',
      'access administration pages',
      'administer site configuration',
      'administer redirects',
    ]);
    $this->drupalLogin($this->testUser);

    // Add a new language.
    ConfigurableLanguage::createFromLangcode('fr')->save();

    // Make the body field translatable. The title is already translatable by
    // definition.
    $field_storage = FieldStorageConfig::loadByName('node', 'body');
    $field_storage->setTranslatable(TRUE);
    $field_storage->save();

    // Create EN language nodes.
    foreach ($this->test_data as $title => $body) {
      $info = array(
        'title' => $title . ' (EN)',
        'body' => array(array('value' => $body)),
        'type' => 'page',
        'langcode' => 'en',
      );
      $this->nodes[$title] = $this->drupalCreateNode($info);
    }

    // Create non-EN nodes.
    foreach ($this->test_data as $title => $body) {
      $info = array(
        'title' => $title . ' (FR)',
        'body' => array(array('value' => $body)),
        'type' => 'page',
        'langcode' => 'fr',
      );
      $this->nodes[$title] = $this->drupalCreateNode($info);
    }

    // Create language-unspecified nodes.
    foreach ($this->test_data as $title => $body) {
      $info = array(
        'title' => $title . ' (UND)',
        'body' => array(array('value' => $body)),
        'type' => 'page',
        'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      );
      $this->nodes[$title] = $this->drupalCreateNode($info);
    }

  }

  /**
   * Test that various rows in a CSV are imported/ignored as expected.
   */
  public function testRedirectImport() {

    // Copy other test files from simpletest.
    $csv = drupal_get_path('module', 'path_redirect_import') . '/tests/' . 'test-redirects.csv';
    $edit = array(
      'override' => TRUE,
      'files[csv_file]' => \Drupal::service('file_system')->realpath($csv),
    );

    $this->drupalGet('admin/config/search/redirect/import');
    $this->submitForm($edit, t('Import'));

    // Assertions.
    $this->assertSession()->pageTextContains('Added redirect from hello-world to node/2');
    $this->assertSession()->pageTextContains('Added redirect from with-query?query=alt to node/1');
    $this->assertSession()->pageTextContains('Added redirect from forward to node/2');
    $this->assertSession()->pageTextContains('Added redirect from test/hello to http://corporaproject.org');
    $this->assertSession()->pageTextContains('Line 13 contains invalid data; bypassed.');
    $this->assertSession()->pageTextContains('Line 14 contains invalid status code; bypassed.');
    $this->assertSession()->pageTextContains('You are attempting to redirect "node/2" to itself. Bypassed, as this will result in an infinite loop.');
    $this->assertSession()->pageTextContains('The destination path "node/99997" does not exist on the site. Redirect from "blah12345" bypassed.');
    $this->assertSession()->pageTextContains('The destination path "fellowship" does not exist on the site. Redirect from "node/2" bypassed.');
    $this->assertSession()->pageTextContains('Redirects from anchor fragments (i.e., with "#) are not allowed. Bypassing "redirect-with-anchor#anchor".');
  }

}
