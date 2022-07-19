<?php

namespace Pentatrion\UploadBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pentatrion_upload');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->fixXmlConfig('origin')
            ->children()
            ->scalarNode('uploaded_file_helper')
            ->defaultValue('pentatrion_upload.uploaded_file_helper')
            ->end()
            ->scalarNode('file_manager_helper')
            ->defaultValue('pentatrion_upload.file_manager_helper')
            ->end()
            ->arrayNode('liip_filters')
            ->scalarPrototype()->end()
            ->defaultValue(["small"])
            ->end()
            ->scalarNode('default_origin')
            ->info('take first origin name if not set')
            ->end()
            ->arrayNode('origins')
            ->useAttributeAsKey('name')
            ->requiresAtLeastOneElement()
            ->defaultValue([
                'public_uploads' => [
                    'path' => '%kernel.project_dir%/public/uploads',
                    'liip_path' => '/uploads'
                ]
            ])
            ->arrayPrototype()
            ->children()
            ->scalarNode('path')->end()
            ->scalarNode('liip_path')
            ->info('prefix to add to path to rely on one liip loader')
            ->end()
            ->end()
            ->end()
            ->end();

        return $treeBuilder;
    }
}
