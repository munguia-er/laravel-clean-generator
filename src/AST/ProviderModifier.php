<?php

namespace MunguiaEr\LaravelCleanGenerator\AST;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use RuntimeException;

class ProviderModifier
{
    /**
     * Parse provider, inject bindings and use statements safely.
     * Returns true if modifications were made.
     */
    public function addBinding(string $providerPath, string $interfaceFqcn, string $implementationFqcn): bool
    {
        if (!file_exists($providerPath)) {
            throw new RuntimeException("Provider file not found: {$providerPath}");
        }

        $code = file_get_contents($providerPath);
        $parser = (new ParserFactory)->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($code);
        } catch (\Exception $e) {
            throw new RuntimeException("Parse error in provider: {$e->getMessage()}");
        }

        $interfaceParts = explode('\\', $interfaceFqcn);
        $interfaceName = end($interfaceParts);
        
        $implParts = explode('\\', $implementationFqcn);
        $implName = end($implParts);

        // 1. Check if binding already exists
        $checker = new class($interfaceName) extends NodeVisitorAbstract {
            public bool $exists = false;
            private string $interfaceName;
            public function __construct(string $interfaceName) { $this->interfaceName = $interfaceName; }
            public function enterNode(Node $node) {
                if ($node instanceof MethodCall && $node->name instanceof Node\Identifier && $node->name->name === 'bind') {
                    if (isset($node->args[0]) && $node->args[0]->value instanceof ClassConstFetch) {
                        $calledClass = $node->args[0]->value->class->toString();
                        if ($calledClass === $this->interfaceName) {
                            $this->exists = true;
                        }
                    }
                }
                return null;
            }
        };

        $traverserCheck = new NodeTraverser();
        $traverserCheck->addVisitor($checker);
        $traverserCheck->traverse($ast);

        if ($checker->exists) {
            return false; // Already bound
        }

        // 2. Add Use Statements and Binding
        $visitor = new class($interfaceFqcn, $implementationFqcn, $interfaceName, $implName) extends NodeVisitorAbstract {
            private string $interfaceFqcn;
            private string $implementationFqcn;
            private string $interfaceName;
            private string $implName;
            public bool $addedUses = false;

            public function __construct($interfaceFqcn, $implementationFqcn, $interfaceName, $implName) {
                $this->interfaceFqcn = $interfaceFqcn;
                $this->implementationFqcn = $implementationFqcn;
                $this->interfaceName = $interfaceName;
                $this->implName = $implName;
            }

            public function leaveNode(Node $node) {
                // Add Use statements right after namespace or early in the file
                if ($node instanceof Node\Stmt\Namespace_ && !$this->addedUses) {
                    $useInterface = new Use_([new UseUse(new Name($this->interfaceFqcn))]);
                    $useImpl = new Use_([new UseUse(new Name($this->implementationFqcn))]);
                    array_unshift($node->stmts, $useInterface, $useImpl);
                    $this->addedUses = true;
                }

                if ($node instanceof ClassMethod && $node->name->name === 'register') {
                    // Create: $this->app->bind(Interface::class, Implementation::class);
                    $bindCall = new Expression(
                        new MethodCall(
                            new PropertyFetch(new Variable('this'), 'app'),
                            'bind',
                            [
                                new Arg(new ClassConstFetch(new Name($this->interfaceName), 'class')),
                                new Arg(new ClassConstFetch(new Name($this->implName), 'class'))
                            ]
                        )
                    );

                    // Prepend or Append binding? Appending is safer.
                    $node->stmts[] = $bindCall;
                }
                return null;
            }
        };

        $traverserInject = new NodeTraverser();
        $traverserInject->addVisitor($visitor);
        $ast = $traverserInject->traverse($ast);

        $printer = new Standard();
        $newCode = $printer->prettyPrintFile($ast);
        file_put_contents($providerPath, $newCode);

        return true;
    }
}
