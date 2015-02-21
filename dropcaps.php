<?php
/**
 * DropCaps v1.1.0
 *
 * This plugin places a decorative dropped initial capital letter to
 * the start of the first paragraph of a text.
 *
 * Licensed under MIT, see LICENSE.
 *
 * @package     DropCaps
 * @version     1.1.0
 * @link        <https://github.com/sommerregen/grav-plugin-dropcaps>
 * @author      Benjamin Regler <sommerregen@benjamin-regler.de>
 * @copyright   2015, Benjamin Regler
 * @license     <http://opensource.org/licenses/MIT>            MIT
 */

namespace Grav\Plugin;

use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Common\Page\Page;
use RocketTheme\Toolbox\Event\Event;

/**
 * DropCaps Plugin
 *
 * This plugin places a decorative dropped initial capital letter to
 * the start of the first paragraph of a text.
 */
class DropCapsPlugin extends Plugin {
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
  public static function getSubscribedEvents() {
    return [
      'onPluginsInitialized' => ['onPluginsInitialized', 0],
    ];
  }

  /**
   * Initialize configuration.
   */
  public function onPluginsInitialized() {
    if ($this->isAdmin()) {
      $this->active = false;
      return;
    }

    if ( $this->config->get('plugins.dropcaps.enabled') ) {
      // Initialize DropCaps class
      require_once(__DIR__ . '/classes/DropCaps.php');
      $this->dropcaps = new DropCaps();

      $weight = $this->config->get('plugins.dropcaps.weight');
      // Process contents order according to weight option
      // (default: -5): to process page content right after SmartyPants

      $this->enable([
        'onPageContentProcessed' => ['onPageContentProcessed', $weight],
        'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
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
  public function onPageContentProcessed(Event $event) {
    /** @var Page $page */
    $page = $event['page'];

    $config = $this->mergeConfig($page, $deep = TRUE);
    if ( $config->get('process', FALSE) AND $this->compileOnce($page) ) {
      // Do nothing, if a route for a given page does not exist
      if ( !$page->route() ) {
        return;
      }

      // Get content
      $content = $page->getRawContent();

      // Insert DropCap and save modified page content
      $page->setRawContent(
        $this->dropcaps->process($content, $config)
      );
    }
  }

  /**
   * Set needed variables to display drop caps.
   */
  public function onTwigSiteVariables() {
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
   * @return boolean       Returns TRUE if page has already been
   *                       compiled yet, FALSE otherwise
   */
  protected function compileOnce(Page $page) {
    static $processed = array();

    $id = md5($page->path());
    // Make sure that contents is only processed once
    if ( !isset($processed[$id]) OR ($processed[$id] < $page->modified()) ) {
      $processed[$id] = $page->modified();
      return TRUE;
    }

    return FALSE;
  }
}
