<?php

namespace Drupal\commerce_fuzzy\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Provides a 'RecommendedProductsBlock' block.
 *
 * @Block(
 *  id = "recommended_products_block",
 *  admin_label = @Translation("Recommended Products"),
 * )
 */
class RecommendedProductsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
  */
  protected $requestStack;


  /**
   * The product storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $productStorage;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, RequestStack $request_stack, EntityStorageInterface $product_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->requestStack = $request_stack;
    $this->productStorage = $product_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('entity.manager')->getStorage('commerce_product')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
         'fuzzy_api_key' => $this->t('FUZZY_API_KEY'),
         'fuzzy_agent_id' => $this->t('FUZZY_AGENT_ID'),
        ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['fuzzy_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Fuzzy API Key'),
      '#description' => $this->t('Your API key from https://fuzzy.ai/'),
      '#default_value' => $this->configuration['fuzzy_api_key'],
      '#maxlength' => 64,
      '#size' => 64,
      '#weight' => '0',
    ];
    $form['fuzzy_agent_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Fuzzy Agent ID'),
      '#description' => $this->t('The Agent ID from your "product affinity" agent.'),
      '#default_value' => $this->configuration['fuzzy_agent_id'],
      '#maxlength' => 64,
      '#size' => 64,
      '#weight' => '0',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['fuzzy_api_key'] = $form_state->getValue('fuzzy_api_key');
    $this->configuration['fuzzy_agent_id'] = $form_state->getValue('fuzzy_agent_id');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Only show block on a product page.
    $product = $this->requestStack->getCurrentRequest()->get('commerce_product');
    if (!$product) {
      return [];
    }

    $data = $this->getData($product);
    $products = array_values($this->loadProducts($product));
    $sorted = array();
    for ($i = 0; $i < count($data); $i++) {
      $sorted[] = array('affinity' => $data[$i]->affinity,
                        'product' => $products[$i]);
    }
    usort($sorted, array($this, 'sortByAffinity'));

    $build = [];
    foreach ($sorted as $value) {
      $build[$value['product']->id()] = entity_view($value['product']);
    }
    return $build;
  }


  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

  protected function getData($product) {
    $inputs = $this->getInputs($product);

    $client = new \FuzzyAi\Client($this->configuration['fuzzy_api_key']);
    list($results, $evalId) = $client->evaluate($this->configuration['fuzzy_agent_id'], $inputs);
    return $results;
  }

  protected function getInputs($sourceProduct) {
    $inputs = array();

    $products = $this->loadProducts($sourceProduct);
    foreach ($products as $product_id => $product) {
      $inputs[] = array(
        'sameCategory' => (int)$this->hasSameCategory($sourceProduct, $product),
        'priceDifferencePercent' => $this->priceDifferencePercent($sourceProduct, $product),
        'percentSharedTitleWords' => $this->sharedWordsPercent($sourceProduct->title->value, $product->title->value),
        'percentSharedDescriptionWords' => $this->sharedWordsPercent($sourceProduct->body->value, $product->body->value),
      );
    }
    return $inputs;
  }

  protected function loadProducts($product) {
    $product_id = $product->id();
    $ids = $this->productStorage->getQuery()
      ->condition('product_id', $product_id, '<>')
      ->execute();
    return $this->productStorage->loadMultiple($ids);
  }

  protected function hasSameCategory($productA, $productB) {
    if (!empty($productA->field_category->entity) && !empty($productB->field_category->entity)) {
      return $productA->field_category->entity->id() == $productB->field_category->entity->id();
    }
  }

  protected function priceDifferencePercent($productA, $productB) {
    $priceA = $productA->getVariations()[0]->getPrice()->getNumber();
    $priceB = $productB->getVariations()[0]->getPrice()->getNumber();
    return (abs($priceA - $priceB) / $priceA) * 100;
  }

  protected function sharedWordsPercent($stringA, $stringB) {
    $wordTotal = str_word_count($stringA);
    $wordsA = str_word_count($stringA, 1);
    $wordsB = str_word_count($stringB, 1);
    $commonWords = array_intersect($wordsA, $wordsB);
    return (count($commonWords) / $wordTotal) * 100;
  }

  protected function sortByAffinity($a, $b) {
    if ($a['affinity'] == $b['affinity']) {
      return 0;
    }
    return ($a['affinity'] > $b['affinity']) ? -1 : 1;
  }

}
