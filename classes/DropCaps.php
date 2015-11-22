<?php
/**
 * DropCaps
 *
 * This file is part of Grav DropCaps plugin.
 *
 * Dual licensed under the MIT or GPL Version 3 licenses, see LICENSE.
 * http://benjamin-regler.de/license/
 */

namespace Grav\Plugin;

use Grav\Common\GravTrait;

/**
 * DropCaps
 *
 * Helper class to place a decorative dropped initial capital letter to
 * the start of the first paragraph of a text.
 */
class DropCaps
{
  /**
   * @var DropCaps
   */
  use GravTrait;

  /** -------------
   * Public methods
   * --------------
   */

  /**
   * Process contents i.e. insert DropCap at the beginning of the text.
   *
   * @param  string $content The content to be processed
   * @param  array  $options Array of options of how to filter dropcaps
   *
   * @return string          The processed content
   */
  public function process($content, $options)
  {
    // Initialize variables for titling
    $titling = $options->get('titling.enabled');
    $id = md5($content);

    $breakpoints = preg_quote($options->get('titling.breakpoints'), '~');
    $regex = "~.+?(?<=)[$breakpoints](?=\s\w|\s*$)~is";

    // Load PHP built-in DOMDocument class
    if (($dom = $this->loadDOMDocument($content)) === null) {
      return $content;
    }

    // Create a DOM XPath object
    $xpath = new \DOMXPath($dom);

    // Get first paragraph of body element
    $paragraph = $xpath->evaluate('body/p[1]')->item(0);
    // A paragraph should have at least one node with non-empty content
    if (!$paragraph || !$paragraph->hasChildNodes()) {
      return $content;
    }

    $textContent = '';
    $convmap = array(0x80, 0xffff, 0, 0xffff);
    foreach ($paragraph->childNodes as $node) {
      if ($node instanceof \DOMText) {
        // Make sure that content is UTF-8 and entities properly encoded
        $text = htmlspecialchars($node->textContent);
        $text = mb_encode_numericentity($text, $convmap, 'UTF-8');

        $textContent .= $text;
        // Check to match a breakpoint
        if (preg_match($regex, $textContent, $match)) {
          $textContent = $match[0];
          break;
        }
      } else {
        // Add placeholder to text
        $textContent .= "\x1A$id\x1A";
      }

      // No breakpoint found...
      if ($paragraph->lastChild === $node) {
        return $content;
      }
    }

    // Replace placeholder with regex for matching a XML/HTML tag
    $re = str_replace("\x1A$id\x1A", '\s*<\w+[^>]*>.*?',
      preg_quote($textContent, '~'));
    $re = '~(<p[^>]*>)\s*(' . $re . ')~is';

    // Do content replacement
    $content = preg_replace_callback($re, function($match) use ($paragraph, $options) {
      $content = $this->insertDropCap($match[2]);
      list($tag, $content) = $this->insertTitling($paragraph, $content, $options->get('titling'));
      return $tag . $content;
    }, $content, 1);

    // Write content back to page
    return $content;
  }

  /** -------------------------------
   * Private/protected helper methods
   * --------------------------------
   */

  /**
   * Insert DropCap into content.
   *
   * @param  string $content The content to insert the DropCap
   *
   * @return string          Return the content with inserted DropCap
   */
  protected function insertDropCap($content)
  {
    // Extract first few letters from paragraph and normalize them
    $chunk = mb_substr($content, 0, min(8, mb_strlen($content)), 'UTF-8');
    $chunk = iconv('UTF-8', 'ASCII//TRANSLIT', $chunk);

    // We're looking for the first paragraph tag followed by a
    // capital letter
    $pattern = '/^(&#8220;|&#8216;|&lsquo;|&ldquo;|&quot;|\'|")?([A-Z])/Uui';
    if (!preg_match($pattern, $chunk, $result)) {
      return $content;
    }

    // Extract first letter and append it to the <span> element
    $firstletter = mb_strtoupper($result[2]);
    $dropcaps = '<span class="dropcaps dropcaps-' . mb_strtolower($firstletter) . '"';

    // Attach first non-alphabetic character to <span> attribute
    if ($result[1]) {
      // Hack: Use $content instead of $result[1] to be aware of
      // unicode quotes which has not been regex as such
      $firstchar = mb_substr(mb_convert_encoding($content, 'UTF-8', 'HTML-ENTITIES'), 0, 1, 'UTF-8');
      $dropcaps .= ' data-quote="' . $firstchar . '"';
    }

    $dropcaps .= ">$firstletter</span>";
    // Delete first letter of paragraph
    $dropcaps .= mb_substr($content, strlen($result[0]),
      mb_strlen($content), 'UTF-8');

    return $dropcaps;
  }

  /**
   * Insert titling text into content.
   *
   * @param  DOMNode $paragraph A DOMNode object which originally holds
   *                            the content in $content
   * @param  string  $content   The content to be titled.
   * @param  array   $options   Some options of the format:
   *                              enabled:    true | false
   *                              first_line: true | false
   *
   * @return array              Return the start tag of the paragraph
   *                            and the titled content
   */
  protected function insertTitling($paragraph, $content, $options = [])
  {
    // Wrap text for titling
    if ($options['enabled']) {
      // Wrap first sentence of content in span element
      $content = '<span class="titling">' . $content . '</span>';
    }

    // Highlight first line of text
    if ($options['first_line']) {
      $class = $paragraph->hasAttribute('class') ? $paragraph->getAttribute('class') : '';
      $classes = array_filter(explode(' ', $class));
      $classes[] = 'highlight';
      $paragraph->setAttribute('class', implode(' ', $classes));
    }

    // Convert paragraph attributes to an XML/HTML tag attribute string
    $attributes = [];
    foreach ($paragraph->attributes as $attribute) {
      $name = $attribute->name;
      $value = htmlspecialchars($attribute->value, ENT_QUOTES, 'UTF-8');
      $attributes[] =  $name . '="' . $value . '"';
    }
    $attributes = count($attributes) ? ' ' . implode(' ', $attributes) : '';
    $tag = '<p' . rtrim($attributes) . '>';

    // Return tag and content separately
    return array($tag, $content);
  }

  /**
   * Load contents into PHP built-in DOMDocument object
   *
   * Two Really good resources to handle DOMDocument with HTML(5)
   * correctly.
   *
   * @see http://stackoverflow.com/questions/3577641/how-do-you-parse-and-process-html-xml-in-php
   * @see http://stackoverflow.com/questions/7997936/how-do-you-format-dom-structures-in-php
   *
   * @param  string      $content The content to be loaded into the
   *                              DOMDocument object
   *
   * @return DOMDocument          DOMDocument object of content
   */
  protected function loadDOMDocument($content)
  {
    // Clear previous errors
    if (libxml_use_internal_errors(true) === true) {
      libxml_clear_errors();
    }

    // Parse content using PHP built-in DOMDocument class
    $document = new \DOMDocument('1.0', 'UTF-8');

    // Encode contents as UTF-8, strip whitespaces & normalize newlines
    $content = mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8');
    // $content = preg_replace(array('~\R~u', '~>[[:space:]]++<~m'),
    //   array("\n", '><'), $content);

    // Parse the HTML using UTF-8
    // The @ before the method call suppresses any warnings that
    // loadHTML might throw because of invalid HTML in the page.
    @$document->loadHTML($content);

    // Do nothing, if DOM is empty
    if (is_null($document->documentElement)) {
      return null;
    }

    return $document;
  }
}
