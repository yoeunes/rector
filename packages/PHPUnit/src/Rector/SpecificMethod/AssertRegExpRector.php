<?php declare(strict_types=1);

namespace Rector\PHPUnit\Rector\SpecificMethod;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use Rector\Builder\IdentifierRenamer;
use Rector\NodeAnalyzer\MethodCallAnalyzer;
use Rector\Rector\AbstractPHPUnitRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;

final class AssertRegExpRector extends AbstractPHPUnitRector
{
    /**
     * @var MethodCallAnalyzer
     */
    private $methodCallAnalyzer;

    /**
     * @var IdentifierRenamer
     */
    private $identifierRenamer;

    public function __construct(MethodCallAnalyzer $methodCallAnalyzer, IdentifierRenamer $identifierRenamer)
    {
        $this->methodCallAnalyzer = $methodCallAnalyzer;
        $this->identifierRenamer = $identifierRenamer;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(
            'Turns `preg_match` comparisons to their method name alternatives in PHPUnit TestCase',
            [
                new CodeSample(
                    '$this->assertSame(1, preg_match("/^Message for ".*"\.$/", $string), $message);',
                    '$this->assertRegExp("/^Message for ".*"\.$/", $string, $message);'
                ),
                new CodeSample(
                    '$this->assertEquals(false, preg_match("/^Message for ".*"\.$/", $string), $message);',
                    '$this->assertNotRegExp("/^Message for ".*"\.$/", $string, $message);'
                ),
            ]
        );
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    /**
     * @param MethodCall $methodCallNode
     */
    public function refactor(Node $methodCallNode): ?Node
    {
        if (! $methodCallNode instanceof MethodCall) {
            return null;
        }
        if (! $this->isInTestClass($methodCallNode)) {
            return null;
        }
        if (! $this->methodCallAnalyzer->isMethods(
            $methodCallNode,
            ['assertSame', 'assertEquals', 'assertNotSame', 'assertNotEquals']
        )) {
            return null;
        }
        /** @var MethodCall $methodCallNode */
        $methodCallNode = $methodCallNode;
        /** @var FuncCall $secondArgumentValue */
        $secondArgumentValue = $methodCallNode->args[1]->value;
        if (! $this->isNamedFunction($secondArgumentValue)) {
            return null;
        }
        $methodName = (string) $secondArgumentValue->name;
        if (($methodName === 'preg_match') === false) {
            return null;
        }
        /** @var Identifier $identifierNode */
        $identifierNode = $methodCallNode->name;
        $oldMethodName = $identifierNode->toString();
        $oldCondition = null;

        $oldFirstArgument = $methodCallNode->args[0]->value;
        $oldCondition = $this->resolveOldCondition($oldFirstArgument);

        $this->renameMethod($methodCallNode, $oldMethodName, $oldCondition);
        $this->moveFunctionArgumentsUp($methodCallNode);

        return $methodCallNode;
    }

    private function moveFunctionArgumentsUp(MethodCall $methodCallNode): void
    {
        $oldArguments = $methodCallNode->args;

        /** @var FuncCall $pregMatchFunction */
        $pregMatchFunction = $oldArguments[1]->value;
        [$regex, $variable] = $pregMatchFunction->args;

        unset($oldArguments[0], $oldArguments[1]);

        $methodCallNode->args = array_merge([$regex, $variable], $oldArguments);
    }

    private function isNamedFunction(Expr $node): bool
    {
        if (! $node instanceof FuncCall) {
            return false;
        }

        $functionName = $node->name;
        return $functionName instanceof Name;
    }

    private function resolveOldCondition(Node $node): int
    {
        if ($node instanceof LNumber) {
            return $node->value;
        }

        if ($node instanceof ConstFetch) {
            /** @var Identifier $constFetchName */
            $constFetchName = $node->name;

            return $constFetchName->toLowerString() === 'true' ? 1 : 0;
        }
    }

    private function renameMethod(Node $methodCallNode, string $oldMethodName, int $oldCondition): void
    {
        if (in_array($oldMethodName, ['assertSame', 'assertEquals'], true) && $oldCondition === 1
            || in_array($oldMethodName, ['assertNotSame', 'assertNotEquals'], true) && $oldCondition === 0
        ) {
            $this->identifierRenamer->renameNode($methodCallNode, 'assertRegExp');
        }

        if (in_array($oldMethodName, ['assertSame', 'assertEquals'], true) && $oldCondition === 0
            || in_array($oldMethodName, ['assertNotSame', 'assertNotEquals'], true) && $oldCondition === 1
        ) {
            $this->identifierRenamer->renameNode($methodCallNode, 'assertNotRegExp');
        }
    }
}
