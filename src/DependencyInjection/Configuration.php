<?php

namespace Pentatrion\UploadBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
  public function getConfigTreeBuilder()
  {
    $treeBuilder = new TreeBuilder('pentatrion_upload');
    $rootNode = $treeBuilder->getRootNode();

    $rootNode
      ->children()
        ->scalarNode('file_infos_helper')
          ->defaultValue('pentatrion_upload.file_infos_helper')
        ->end()
        ->arrayNode('liip_filters')
          ->scalarPrototype()->end()
        ->end()
        ->scalarNode('default_origin')->end()
        ->arrayNode('origins')
          ->useAttributeAsKey('name')
          // ->isRequired()
          // ->requiresAtLeastOneElement()
          ->arrayPrototype()
            ->children()
              ->scalarNode('path')->end()
              ->scalarNode('liip_path')->end()
            ->end()
          ->end()
      ->end()
    ;

    return $treeBuilder;
  }
}