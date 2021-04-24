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
        ->scalarNode('file_infos_helper')->defaultValue('pentatrion_upload.file_infos_helper')->end()
        ->arrayNode('origins')
          ->arrayPrototype()
            ->children()
              ->scalarNode('path')
          ->end()          
      ->end()
    ;

    return $treeBuilder;
  }
}