<?php declare(strict_types=1);

namespace Rector\Rector\Architecture\RepositoryAsService;

use Nette\Utils\Strings;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Class_;
use Rector\Builder\Class_\VariableInfo;
use Rector\Builder\ConstructorMethodBuilder;
use Rector\Contract\Bridge\RepositoryForDoctrineEntityProviderInterface;
use Rector\Exception\Bridge\RectorProviderException;
use Rector\Node\Attribute;
use Rector\Node\NodeFactory;
use Rector\Node\PropertyFetchNodeFactory;
use Rector\NodeAnalyzer\MethodCallAnalyzer;
use Rector\Rector\AbstractRector;

final class ServiceLocatorToDIRector extends AbstractRector
{
    /**
     * @var MethodCallAnalyzer
     */
    private $methodCallAnalyzer;

    /**
     * @var PropertyFetchNodeFactory
     */
    private $propertyFetchNodeFactory;

    /**
     * @var RepositoryForDoctrineEntityProviderInterface
     */
    private $repositoryForDoctrineEntityProvider;
    /**
     * @var ConstructorMethodBuilder
     */
    private $constructorMethodBuilder;
    /**
     * @var NodeFactory
     */
    private $nodeFactory;

    public function __construct(
        MethodCallAnalyzer $methodCallAnalyzer,
        PropertyFetchNodeFactory $propertyFetchNodeFactory,
        ConstructorMethodBuilder $constructorMethodBuilder,
        NodeFactory $nodeFactory,
        RepositoryForDoctrineEntityProviderInterface $repositoryForDoctrineEntityProvider
    ) {
        $this->methodCallAnalyzer = $methodCallAnalyzer;
        $this->propertyFetchNodeFactory = $propertyFetchNodeFactory;
        $this->repositoryForDoctrineEntityProvider = $repositoryForDoctrineEntityProvider;
        $this->constructorMethodBuilder = $constructorMethodBuilder;
        $this->nodeFactory = $nodeFactory;
    }

    public function isCandidate(Node $node): bool
    {
        if (! $this->methodCallAnalyzer->isMethod($node, 'getRepository')) {
            return false;
        }

        $className = $node->getAttribute(Attribute::CLASS_NAME);

        if($className === null){
            return false;
        }

        return ! Strings::endsWith($node->getAttribute(Attribute::CLASS_NAME), 'Repository');
    }

    public function refactor(Node $node): ?Node
    {
        return $this->propertyFetchNodeFactory->createLocalWithPropertyName(
            $this->repositoryVariableName($node)
        );

        return $node;
    }

    private function repositoryVariableName(Node $node): string
    {
        return lcfirst($this->repositoryShortName($node));
    }

    private function repositoryShortName(Node $node): string
    {
        return end(explode('\\', $this->repositoryFQN($node)));
    }

    private function repositoryFQN(Node $node): string
    {
        $repositoryArgument = $node->args[0]->value;

        if($repositoryArgument->class === null){
            $fqnOrAlias = $repositoryArgument->value;
        }

        if($repositoryArgument->class instanceof Node\Name){
            $fqnOrAlias = $repositoryArgument->class->getAttribute(Attribute::TYPES)[0];
        }

        if($repositoryArgument->class instanceof Node\Name\FullyQualified){
            $fqnOrAlias = $repositoryArgument->class->toString();
        }

        $repositoryClassName = $this->repositoryForDoctrineEntityProvider->provideRepositoryForEntity(
            $fqnOrAlias
        );

        if ($repositoryClassName === null) {
            throw new RectorProviderException(sprintf(
                'A repository was not provided for "%s" entity by your "%s" class.',
                $fqnOrAlias,
                \get_class($this->repositoryForDoctrineEntityProvider)
            ));
        }

        return $repositoryClassName;
    }
}