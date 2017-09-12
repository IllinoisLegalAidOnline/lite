<?php

namespace Drupal\lite\Plugin\Filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\Component\Utility\Html;

/**
 * Defines the "lite" filter.
 *
 * @Filter(
 *   id = "lite",
 *   title = @Translation("Lite changes tracking"),
 *   description = @Translation("Hide &lt;ins&gt; and &lt;del&gt; markup from Lite tracking changes in the view mode of the content."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 *   settings = {
 *     "clean" = 0,
 *     "list" = "ul ol blockquote"
 *   }
 * )
 */
class Lite extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form['clean'] = [
      '#title' => t('Clean empty markup'),
      '#description' => $this->t('If a change or a list of changes is included in a list or a container tag, the changes will be hidden but not the container tag. In some cases, like for list or Blockquote, an empty markup will be visible. Use this option to remove empty tags.'),
      '#type' => 'checkbox',
      '#default_value' => $this->settings['clean'],
      '#attributes' => [
        'data-editor-lite' => 'clean',
      ],
    ];
    $form['list'] = [
      '#type' => 'textfield',
      '#title' => t('Empty HTML tags to clean'),
      '#default_value' => $this->settings['list'],
      '#description' => $this->t('Space separated list of markup to clean, for example lists or blockquote. Be warned it will remove all empty markup even if it is not related to track changes.'),
      '#states' => [
        'visible' => [
          ':input[data-editor-lite="clean"]' => ['checked' => TRUE],
        ],
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);

    if (strpos($text, 'ice-ins') !== FALSE || strpos($text, 'ice-del') !== FALSE) {
      $result->setProcessedText($this->processChanges($text));
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    return $this->t('Track changes will be use for this content if enable.');
  }

  /**
   * Process a markup to replace <ins> and <del> markup.
   *
   * @param string $text
   *   The text string to be filtered.
   *
   * @return string
   *   Text processed.
   */
  private function processChanges($text) {
    $document = Html::load($text);
    $xpath = new \DOMXPath($document);
    $has_deleted_markup = FALSE;

    // Remove proposed insertions with the "ice-ins" class.
    foreach ($xpath->query("//ins[contains(concat(' ', normalize-space(@class), ' '), ' ice-ins ')]") as $node) {
      /** @var \DOMElement $node */
      $node->parentNode->removeChild($node);
    }

    // Keep proposed deletions with the "ice-del" class but remove markers.
    foreach ($xpath->query("//del[contains(concat(' ', normalize-space(@class), ' '), ' ice-del ')]") as $node) {
      /** @var \DOMElement $node */
      $text = $node->textContent;
      $this->replaceContent($node, $text);
      $has_deleted_markup = TRUE;
    }

    // Delete empty markup depending settings.
    if ($has_deleted_markup && $this->settings['clean']) {
      // Build query for tags to delete if empty.
      $xpath_query = NULL;
      foreach (explode(" ", $this->settings['list']) as $tag) {
        $xpath_query[] = "//" . $tag . "[not(normalize-space())]";
      }
      if ($xpath_query) {
        foreach ($xpath->query(implode(" | ", $xpath_query)) as $node) {
          /** @var \DOMElement $node */
          $node->parentNode->removeChild($node);
        }
      }
    }

    return Html::serialize($document);
  }

  /**
   * Replace the contents of a DOMNode.
   *
   * @param \DOMNode $node
   *   A DOMNode object.
   * @param string $content
   *   The text or HTML that will replace the contents of $node.
   */
  private function replaceContent(\DOMNode &$node, $content) {
    if (strlen($content)) {
      // Load the content into a new DOMDocument and retrieve the DOM nodes.
      $replacement_nodes = Html::load($content)->getElementsByTagName('body')
        ->item(0)
        ->childNodes;
    }
    else {
      $replacement_nodes = [$node->ownerDocument->createTextNode('')];
    }

    foreach ($replacement_nodes as $replacement_node) {
      // Import the replacement node from the new DOMDocument into the original
      // one, importing also the child nodes of the replacement node.
      $replacement_node = $node->ownerDocument->importNode($replacement_node, TRUE);
      $node->parentNode->insertBefore($replacement_node, $node);
    }
    $node->parentNode->removeChild($node);
  }

}
