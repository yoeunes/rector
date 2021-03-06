<?php declare(strict_types=1);

namespace Rector\Rector\Architecture\DependencyInjection;

use Nette\Utils\Strings;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Configuration\Rector\Architecture\DependencyInjection\VariablesToPropertyFetchCollection;
use Rector\Node\PropertyFetchNodeFactory;
use Rector\NodeTypeResolver\Node\Attribute;
use Rector\NodeTypeResolver\NodeTypeResolver;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;

final class ReplaceVariableByPropertyFetchRector extends AbstractRector
{
    /**
     * @var VariablesToPropertyFetchCollection
     */
    private $variablesToPropertyFetchCollection;

    /**
     * @var PropertyFetchNodeFactory
     */
    private $propertyFetchNodeFactory;

    /**
     * @var NodeTypeResolver
     */
    private $nodeTypeResolver;

    public function __construct(
        VariablesToPropertyFetchCollection $variablesToPropertyFetchCollection,
        PropertyFetchNodeFactory $propertyFetchNodeFactory,
        NodeTypeResolver $nodeTypeResolver
    ) {
        $this->variablesToPropertyFetchCollection = $variablesToPropertyFetchCollection;
        $this->propertyFetchNodeFactory = $propertyFetchNodeFactory;
        $this->nodeTypeResolver = $nodeTypeResolver;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(
            'Turns variable in controller action to property fetch, as follow up to action injection variable to property change.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
final class SomeController
{
    /**
     * @var ProductRepository
     */
    private $productRepository;

    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function default()
    {
        $products = $productRepository->fetchAll();
    }
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
final class SomeController
{
    /**
     * @var ProductRepository
     */
    private $productRepository;

    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function default()
    {
        $products = $this->productRepository->fetchAll();
    }
}
CODE_SAMPLE
                ),
            ]
        );
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [Variable::class];
    }

    /**
     * @param Variable $variableNode
     */
    public function refactor(Node $variableNode): ?Node
    {
        $activeVariableInfo = null;
        if (! $this->isInControllerActionMethod($variableNode)) {
            return null;
        }

        foreach ($this->variablesToPropertyFetchCollection->getVariableInfos() as $variableInfo) {
            if ($variableNode->name !== $variableInfo->getName()) {
                continue;
            }

            $nodeTypes = $this->nodeTypeResolver->resolve($variableNode);
            if ($nodeTypes === $variableInfo->getTypes()) {
                $activeVariableInfo = $variableInfo;
                break;
            }
        }

        if ($activeVariableInfo === null) {
            return null;
        }

        return $this->propertyFetchNodeFactory->createLocalWithPropertyName($activeVariableInfo->getName());
    }

    private function isInControllerActionMethod(Node $node): bool
    {
        if (! Strings::endsWith((string) $node->getAttribute(Attribute::CLASS_NAME), 'Controller')) {
            return false;
        }

        /** @var ClassMethod|null $methodNode */
        $methodNode = $node->getAttribute(Attribute::METHOD_NODE);
        if ($methodNode === null) {
            return false;
        }

        // is probably in controller action
        return $methodNode->isPublic();
    }
}
