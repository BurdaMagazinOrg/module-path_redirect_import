<?php

namespace Drupal\path_redirect_import\Tests\Functional;

use Drupal\search\Tests\SearchTestBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Test that redirects are properly imported from CSV file.
 *
 * @group path_redirect_import
 */
class RedirectImportTest extends SearchTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = array(
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

    $this->testUser = $this->drupalCreateUser(array(
      'access content',
      'administer nodes',
      'access site reports',
      'administer languages',
      'access administration pages',
      'administer site configuration',
      'administer redirects',
    ));
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
    $csv = drupal_get_path('module', 'path_redirect_import') . '/src/Tests/files/' . 'test-redirects.csv';
    $edit = array(
      'override' => TRUE,
      'files[csv_file]' => drupal_realpath($csv),
    );

    $form_path = 'admin/config/search/redirect/import';
    $this->drupalGet($form_path);
    $this->drupalPostForm(NULL, $edit, t('Import'));

    // Assertions.
    $this->assertText('Added redirect from hello-world to node/2', format_string('Add redirect from arbitrary alias without leading slash to existing path', array()));
    $this->assertText('Added redirect from with-query?query=alt to node/1', format_string('Add redirect from arbitrary alias with query to existing path', array()));
    $this->assertText('Added redirect from forward to node/2', format_string('Add redirect from arbitrary alias with leading slash to existing path', array()));
    $this->assertText('Added redirect from test/hello to http://corporaproject.org', format_string('Add redirect to external URL', array()));

    $this->assertText('Line 13 contains invalid data; bypassed.', format_string('Bypass row with missing redirect', array()));
    $this->assertText('Line 14 contains invalid status code; bypassed.', format_string('Bypass row with invalid status code', array()));
    $this->assertText('You cannot create a redirect from the front page.', format_string('Bypass redirect from &lt;front&gt;.', array()));
    $this->assertText('You are attempting to redirect "node/2" to itself. Bypassed, as this will result in an infinite loop.', format_string('Bypass infinite loops.', array()));
    $this->assertText('The destination path "node/99997" does not exist on the site. Redirect from "blah12345" bypassed.', format_string('Bypass redirects to nonexistent internal paths.', array()));
    $this->assertText('The destination path "fellowship" does not exist on the site. Redirect from "node/2" bypassed.', format_string('Bypass redirects to nonexistent URL aliases.', array()));
    $this->assertText('Redirects from anchor fragments (i.e., with "#) are not allowed. Bypassing "redirect-with-anchor#anchor".', format_string('Bypass redirects from anchor fragments', array()));
  }

}
