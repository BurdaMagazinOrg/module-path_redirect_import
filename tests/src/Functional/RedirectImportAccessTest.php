<?php

namespace Drupal\path_redirect_import\Tests\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * @file
 * Test access for path_redirect_import module.
 */

/**
 * Minimal testing for drupal.org of path_redirect_import module.
 *
 * @group path_redirect_import
 */
class RedirectImportAccessTest extends BrowserTestBase {

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
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => t('Path Redirect Import access'),
      'description' => t('Test access to admin configuration UI.'),
      'group' => t('path_redirect_import'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
  }

  /**
   * The tests.
   */
  public function test() {
    $this->drupalGet('admin/config/search/redirect/import');
    $this->assertSession()->statusCodeEquals(403);
    $user = $this->drupalCreateUser(array('administer redirects'));
    $user = $this->drupalLogin($user);
    $this->drupalGet('admin/config/search/redirect/import');
    $this->assertSession()->statusCodeEquals(200);
  }

}
