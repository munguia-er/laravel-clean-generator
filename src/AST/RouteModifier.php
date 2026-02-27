<?php

namespace MunguiaEr\LaravelCleanGenerator\AST;

use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use RuntimeException;

class RouteModifier
{
    /**
     * Parse routes file and inject a new resource route mapping to the Controller safely.
     * Returns true if modifications were made.
     */
    public function addResourceRoute(string $routeFilePath, string $controllerFqcn, string $routeName, bool $isApi): bool
    {
        if (!file_exists($routeFilePath)) {
            if (!$isApi) {
                throw new RuntimeException("Route file not found: {$routeFilePath}");
            }

            // Auto-create routes/api.php for API routes (Laravel 11+ compatible)
            $dir = dirname($routeFilePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($routeFilePath, "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");
        }

        $code = file_get_contents($routeFilePath);
        $parser = (new ParserFactory)->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($code);
        } catch (\Exception $e) {
            throw new RuntimeException("Parse error in routes: {$e->getMessage()}");
        }

        $controllerParts = explode('\\', $controllerFqcn);
        $controllerName = end($controllerParts);
        $methodType = $isApi ? 'apiResource' : 'resource';

        // 1. Check if route already exists
        $checker = new class($routeName, $methodType) extends NodeVisitorAbstract {
            public bool $exists = false;
            private string $routeName;
            private string $methodType;
            public function __construct(string $routeName, string $methodType) { 
                $this->routeName = $routeName; 
                $this->methodType = $methodType;
            }
            public function enterNode(Node $node) {
                // Look for Route::apiResource('posts', ...)
                if ($node instanceof StaticCall && $node->class instanceof Name\FullyQualified === false) {
                    if ($node->class->toString() === 'Route' && (string)$node->name === $this->methodType) {
                        if (isset($node->args[0]) && $node->args[0]->value instanceof String_) {
                            if ($node->args[0]->value->value === $this->routeName) {
                                $this->exists = true;
                            }
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
            return false; // Route already exists
        }

        // 2. Add Use Statement and append route
        $visitor = new class($controllerFqcn, $controllerName, $routeName, $methodType) extends NodeVisitorAbstract {
            private string $controllerFqcn;
            private string $controllerName;
            private string $routeName;
            private string $methodType;
            public bool $addedUse = false;

            public function __construct($controllerFqcn, $controllerName, $routeName, $methodType) {
                $this->controllerFqcn = $controllerFqcn;
                $this->controllerName = $controllerName;
                $this->routeName = $routeName;
                $this->methodType = $methodType;
            }

            public function enterNode(Node $node) {
                // If it's a Use statement block, try inserting here if not done
                // Simplest approach: Add to the root level statements array later if we want it right below <?php
                return null;
            }
            
            public function afterTraverse(array $nodes) {
                // Append the route at the end of the file
                $nodes[] = new Expression(
                    new StaticCall(
                        new Name('Route'),
                        $this->methodType,
                        [
                            new Arg(new String_($this->routeName)),
                            new Arg(new ClassConstFetch(new Name($this->controllerName), 'class'))
                        ]
                    )
                );

                // Insert Use statement at the top (after Opening Tag / existing Uses)
                array_unshift($nodes, new Use_([new UseUse(new Name($this->controllerFqcn))]));
                
                return $nodes;
            }
        };

        $traverserInject = new NodeTraverser();
        $traverserInject->addVisitor($visitor);
        $ast = $traverserInject->traverse($ast);

        $printer = new Standard();
        $newCode = $printer->prettyPrintFile($ast);
        file_put_contents($routeFilePath, $newCode);

        return true;
    }
}
