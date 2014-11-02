<?php

/**
 * @file
 * Contains \Drupal\wb_breadcrumbs\WbPathBasedBreadcrumbBuilder.
 */

namespace Drupal\wb_breadcrumbs;

use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Class to define the menu_link breadcrumb builder.
 */
class WbPathBasedBreadcrumbBuilder implements BreadcrumbBuilderInterface {
  use StringTranslationTrait;

  /**
   * The menu tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuTree;

  /**
   * The configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */

  protected $config_factory;

  /**
   * Constructs a WbPathBasedBreadcrumbBuilder object.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_tree
   *   The menu tree service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A configuration object.
   */


  public function __construct(MenuLinkTreeInterface $menu_tree, ConfigFactoryInterface $config_factory) {
    $this->menuTree = $menu_tree;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('menu.link_tree'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $path = $route_match->getRouteObject();
    $path = $path->getPath();
    $pos = strpos($path, '/admin');
    if ($pos === false) {
      return TRUE;
    } else {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $menu_tree = $this->menuTree;
    $menu_name = $this->config_factory->get('wb_breadcrumbs.settings')->get('breadcrumb_menu');
    $parameters = $menu_tree->getCurrentRouteMenuTreeParameters($menu_name);
    $tree = $menu_tree->load($menu_name, $parameters);
    $manipulators = array(
      array('callable' => 'menu.default_tree_manipulators:checkAccess'),
      array('callable' => 'menu.default_tree_manipulators:generateIndexAndSort'),
    );
    $tree = $menu_tree->transform($tree, $manipulators);
    $link = $this->linksFromTree($tree);
    return $link;
  }

  public function linksFromTree($tree) {
    $out = [];
    foreach ($tree as $element) {
      if ($element->inActiveTrail) {
        $text = $element->link->getTitle();
        $urlObject = $element->link->getUrlObject();
        $out[] = $this->createFromRoute($text, $urlObject);
        if ($element->subtree) {
          $out = array_merge($out, $this->linksFromTree($element->subtree));
        }
      }
    }
    return $out;
  }

  public function createFromRoute($text, $urlObject) {
    return new Link ($text, $urlObject);
  }
}
