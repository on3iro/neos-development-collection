<?php

namespace Neos\ContentGraph\DoctrineDbalAdapter\Domain\Repository;

/*
 * This file is part of the Neos.ContentGraph.DoctrineDbalAdapter package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */
use Neos\ContentRepository\Domain as ContentRepository;
use Neos\ContentRepository\Domain\Projection\Content as ContentProjection;
use Neos\ContentRepository\Utility;
use Neos\Flow\Annotations as Flow;


/**
 * Implementation detail of ContentGraph and ContentSubgraph
 *
 * @Flow\Scope("singleton")
 */
final class NodeFactory
{

    /**
     * @Flow\Inject
     * @var ContentRepository\Service\NodeTypeManager
     */
    protected $nodeTypeManager;


    /**
     * @Flow\Inject
     * @var ContentRepository\Repository\NodeDataRepository
     * @todo get rid of this
     */
    protected $nodeDataRepository;

    /**
     * @param array $nodeDataArrayFromProjection
     * @param ContentRepository\Service\Context $context
     * @return ContentRepository\Model\NodeInterface
     */
    public function mapRawDataToNode(array $nodeDataArrayFromProjection, ContentRepository\Service\Context $context = null): ContentRepository\Model\NodeInterface
    {
        $nodeType = $this->nodeTypeManager->getNodeType($nodeDataArrayFromProjection['nodetypename']);
        $className = $nodeType->getNodeInterfaceImplementationClassName();

        // $serializedSubgraphIdentifier is empty for the root node
        if (!empty($nodeDataArrayFromProjection['dimensionspacepointhash'])) {
            // NON-ROOT case
            $contentStreamIdentifier = new ContentRepository\ValueObject\ContentStreamIdentifier($nodeDataArrayFromProjection['contentstreamidentifier']);
            $dimensionSpacePoint = new ContentRepository\ValueObject\DimensionSpacePoint(json_decode($nodeDataArrayFromProjection['dimensionspacepoint'], true));

            $legacyDimensionValues = $dimensionSpacePoint->toLegacyDimensionArray();
            $query = $this->nodeDataRepository->createQuery();
            $nodeData = $query->matching(
                $query->logicalAnd([
                    $query->equals('workspace', (string) $contentStreamIdentifier),
                    $query->equals('identifier', $nodeDataArrayFromProjection['nodeaggregateidentifier']),
                    $query->equals('dimensionsHash', Utility::sortDimensionValueArrayAndReturnDimensionsHash($legacyDimensionValues))
                ])
            )->execute()->getFirst();

            $node = new $className($nodeData, $context);
            $node->nodeType = $nodeType;
            $node->aggregateIdentifier = new ContentRepository\ValueObject\NodeAggregateIdentifier($nodeDataArrayFromProjection['nodeaggregateidentifier']);
            $node->identifier = new ContentRepository\ValueObject\NodeIdentifier($nodeDataArrayFromProjection['nodeidentifier']);
            $node->properties = new ContentProjection\PropertyCollection(json_decode($nodeDataArrayFromProjection['properties'], true));
            if (!array_key_exists('name', $nodeDataArrayFromProjection)) {
                throw new \Exception('The "name" property was not found in the $nodeDataArrayFromProjection; you need to include the "name" field in the SQL result.');
            }
            $node->name = $nodeDataArrayFromProjection['name'];
            $node->nodeTypeName = new ContentRepository\ValueObject\NodeTypeName($nodeDataArrayFromProjection['nodetypename']);
            #@todo fetch workspace from finder using the content stream identifier
            #$node->workspace = $this->workspaceRepository->findByIdentifier($this->contentStreamIdentifier);
            $node->dimensionSpacePoint = $dimensionSpacePoint;
            $node->contentStreamIdentifier = $contentStreamIdentifier;

            return $node;
        } else {
            // root node
            $subgraphIdentifier = null;
            $nodeData = ($context ? $context->getWorkspace()->getRootNodeData() : null);

            $node = new $className($nodeData, $context);
            $node->nodeType = $nodeType;
            $node->nodeTypeName = new ContentRepository\ValueObject\NodeTypeName($nodeDataArrayFromProjection['nodetypename']);
            $node->identifier = new ContentRepository\ValueObject\NodeIdentifier($nodeDataArrayFromProjection['nodeidentifier']);
            #@todo fetch workspace from finder using the content stream identifier
            #$node->workspace = $this->workspaceRepository->findByIdentifier($this->contentStreamIdentifier);

            $node->dimensionSpacePoint = null;

            return $node;
        }
    }
}
