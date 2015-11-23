<?php
/**
 * DropCaps v1.3.4
 *
 * This plugin places a decorative dropped initial capital letter to
 * the start of the first paragraph of a text.
 *
 * Dual licensed under the MIT or GPL Version 3 licenses, see LICENSE.
 * http://benjamin-regler.de/license/
 *
 *
 * @package     DropCaps
 * @version     1.3.4
 * @link        <https://github.com/sommerregen/grav-plugin-dropcaps>
 * @author      Benjamin Regler <sommerregen@benjamin-regler.de>
 * @copyright   2015, Benjamin Regler
 * @license     <http://opensource.org/licenses/MIT>        MIT
 * @license     <http://opensource.org/licenses/GPL-3.0>    GPLv3
 */

namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Page\Page;
use RocketTheme\Toolbox\Event\Event;

/**
 * DropCaps Plugin
 *
 * This plugin places a decorative dropped initial capital letter to
 * the start of the first paragraph of a text.
 */
class DropCapsPlugin extends Plugin
{
  /**
   * @var DropCapsPlugin
   */

  /**
   * Instance of DropCaps class
   *
   * @var object
   */
  protected $dropcaps;

  /** -------------
   * Public methods
   * --------------
   */

  /**
   * Return a list of subscribed events.
   *
   * @return array    The list of events of the plugin of the form
   *                      'name' => ['method_name', priority].
   */
  public static function getSubscribedEvents()
  {
    return [
      'onPluginsInitialized' => ['onPluginsInitialized', 0],
    ];
  }

  /**
   * Initialize configuration.
   */
  public function onPluginsInitialized()
  {
    if ($this->isAdmin()) {
      $this->active = false;
      return;
    }

    if ($this->config->get('plugins.dropcaps.enabled')) {
      $weight = $this->config->get('plugins.dropcaps.weight');
      // Process contents order according to weight option
      // (default: -5): to process page content right after SmartyPants

      $this->enable([
        'onPageContentProcessed' => ['onPageContentProcessed', $weight],
        'onTwigSiteVariables' => ['onTwigSiteVariables', 0]
      ]);
    }
  }

  /**
   * Apply drop caps filter to content, when each page has not been
   * cached yet.
   *
   * @param  Event  $event The event when 'onPageContentProcessed' was
   *                       fired.
   */
  public function onPageContentProcessed(Event $event)
  {
    /** @var Page $page */
    $page = $event['page'];

    $config = $this->mergeConfig($page, $deep = true);
    if ($config->get('process', false) && $this->compileOnce($page)) {
      // Do nothing, if a route for a given page does not exist
      if (!$page->route()) {
        return;
      }

      // Get content
      $content = $page->getRawContent();

      // Insert DropCap and save modified page content
      $page->setRawContent(
        $this->init()->process($content, $config)
      );
    }
  }

  /**
   * Set needed variables to display drop caps.
   */
  public function onTwigSiteVariables()
  {
    if ($this->config->get('plugins.dropcaps.built_in_css')) {
      $this->grav['assets']->add('plugin://dropcaps/assets/css/dropcaps.css');
    }
  }

  /** -------------------------------
   * Private/protected helper methods
   * --------------------------------
   */

  /**
   * Checks if a page has already been compiled yet.
   *
   * @param  Page    $page The page to check
   * @return boolean       Returns true if page has already been
   *                       compiled yet, false otherwise
   */
  protected function compileOnce(Page $page)
  {
    static $processed = [];

    $id = md5($page->path());
    // Make sure that contents is only processed once
    if (!isset($processed[$id]) || ($processed[$id] < $page->modified())) {
      $processed[$id] = $page->modified();
      return true;
    }

    return false;
  }

  /**
   * Initialize plugin and all dependencies.
   *
   * @return \Grav\Plugin\ExternalLinks   Returns ExternalLinks instance.
   */
  protected function init()
  {
    if (!$this->dropcaps) {
      // Initialize DropCaps class
      require_once(__DIR__ . '/classes/DropCaps.php');
      $this->dropcaps = new DropCaps();
    }

    return $this->dropcaps;
  }
}
