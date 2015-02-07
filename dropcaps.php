<?php
/**
 * DropCaps v1.0.1
 *
 * This plugin places a decorative dropped initial capital letter to
 * the start of the first paragraph of a text.
 *
 * Licensed under MIT, see LICENSE.
 *
 * @package     DropCaps
 * @version     1.0.1
 * @link        <https://github.com/sommerregen/grav-plugin-archive-plus>
 * @author      Benjamin Regler <sommergen@benjamin-regler.de>
 * @copyright   2015, Benjamin Regler
 * @license     <http://opensource.org/licenses/MIT>            MIT
 */

namespace Grav\Plugin;

use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Common\Page\Page;
use Grav\Common\Data\Data;
use Grav\Common\Inflector;
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
      $weight = $this->config->get('plugins.dropcaps.weight');
      $this->enable([
        // Process contents order according to weight option
        // (default: -5): to process contents right after SmartyPants
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
    $config = $this->mergeConfig($page);

    // Modify page content only once
    if ( $config->get('process') AND $this->compileOnce($page) ) {
      $content = $page->getRawContent();

      // Create a DOM parser object
      $dom = new \DOMDocument('1.0', 'UTF-8');

      // Pretty print output
      $dom->preserveWhiteSpace = FALSE;
      $dom->formatOutput       = TRUE;

      // Parse the HTML using UTF-8
      // The @ before the method call suppresses any warnings that
      // loadHTML might throw because of invalid HTML in the page.
      $content = mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8');
      @$dom->loadHTML($content);

      // Do nothing, if DOM is empty or a route for a given page does not exist
      if ( is_null($dom->documentElement) OR !$page->route() ) {
        return;
      }

      // Initialize variables for titling
      $titling = $config->get('titling.enabled', FALSE);
      $breakpoints = preg_quote($config->get('titling.breakpoints'));
      $regex = '~^.+?[' . $breakpoints . ']~isux';

      // Process variables
      $found = FALSE;
      $content = '';

      // Consider child elements of <body> tag only
      $body = $dom->getElementsByTagName('body')->item(0);
      foreach ( $body->childNodes as $node ) {
        // A paragraph should have at least one node with non-empty content
        if (  !$found AND
              ($node->tagName == 'p') AND
              $node->hasChildNodes() AND
              ($node->firstChild->length > 0) ) {

          // Create span attribute
          $span = $dom->createElement('span');

          // Extract first few letters from paragraph
          $text = $dom->saveHTML($node->firstChild);
          $chunk = iconv('UTF-8', 'ASCII//TRANSLIT', $text);

          $length = 2;
          // Determine maximum text length for titling
          if ( $titling AND preg_match($regex, $chunk, $match) ) {
            $length = strlen($match[0]);
          }
          $chunk = mb_substr($chunk, 0, $length, 'UTF-8');

          // We're looking for the first paragraph tag followed by a
          // capital letter
          $pattern = '/(&#8220;|&#8216;|&lsquo;|&ldquo;|&quot;|\'|")?([A-Z])/Uui';

          if ( preg_match($pattern, $chunk, $result) ) {
            // Extract first letter and append it to the <span> element
            $firstletter = mb_strtoupper($result[2]);
            $span->appendChild($dom->createTextNode($firstletter));

            $span->setAttribute('class',
              'dropcaps dropcaps-' . mb_strtolower($firstletter));

            // Attach first non-alphabetic character to <span> attribute
            if ( $result[1] ) {
              // Hack: Use $text[0] instead of $result[1] to be aware of
              // unicode quotes which has not been regexp as such
              $firstchar = mb_substr($text, 0, 1, 'UTF-8');
              $span->setAttribute('data-quote', $firstchar);
            }

            // Extract first-line of text
            $letterlength = mb_strlen($result[0]);
            $firstline = mb_substr($text, $letterlength,
              $length - $letterlength, 'UTF-8');

            // Wrap text for titling
            if ( $titling ) {
              // Delete first line of paragraph
              $node->firstChild->data = mb_substr($text,
                $length, mb_strlen($text), 'UTF-8');

              // Wrap dropcap and first line in a titling <span> element
              $titling = $dom->createElement('span');
              $titling->setAttribute('class', 'titling');

              $titling->appendChild($span);
              $titling->appendChild($dom->createTextNode($firstline));

              // Insert titling <span> element before text of paragraph
              $node->insertBefore($titling, $node->firstChild);

              // Highlight first line of text
              if ( $config->get('titling.first_line') ) {
                $class = $node->hasAttribute('class') ? $node->getAttribute('class') : '';
                $classes = array_filter(explode(' ', $class));
                $classes[] = 'highlight';
                $node->setAttribute('class', implode(' ', $classes));
              }
            } else {
              // Delete first letter of paragraph
              $node->firstChild->data = mb_substr($text,
                $letterlength, mb_strlen($text), 'UTF-8');

              // Insert <span> element before text of paragraph
              $node->insertBefore($span, $node->firstChild);
            }

            // Don't insert more than one drop cap into document
            $found = TRUE;
          }
        }
        $content .= $node->ownerDocument->saveHTML($node);
      }

      $page->setRawContent($content);
    }
  }

  /**
   * Set needed variables to display drop caps.
   */
  public function onTwigSiteVariables() {
    if ($this->config->get('plugins.dropcaps.built_in_css')) {
      $this->grav['assets']->add('plugin://dropcaps/css/dropcaps.css');
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
