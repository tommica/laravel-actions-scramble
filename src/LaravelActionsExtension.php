<?php

namespace App\Services;

use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\OperationExtensions\RequestBodyExtension;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ValidationNodesResult;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use PhpParser\Node;
use PhpParser\NodeFinder;

class LaravelActionsExtension extends RequestBodyExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo)
    {
        if ($routeInfo->methodName() !== 'asController' || $routeInfo->getMethodType()->name !== '__invoke') {
            return;
        }

        parent::handle($operation, $routeInfo);
    }

    protected function extractRouteRequestValidationRules(Route $route, $methodNode)
    {
        $rules = [];
        $nodesResults = [];

        $action = $route->getAction('uses');

        if (count($formRequestRules = $this->extractRules($action))) {
            $rules = array_merge($rules, $formRequestRules);
            $nodesResults[] = $this->actionNode($action);
        }

        return [$rules, array_filter($nodesResults)];
    }

    private function extractRules(string $action)
    {
        $reflection = new \ReflectionMethod(...explode('@', $action));
        $requestClassName = $reflection->class;
        $request = (new $requestClassName);
        return $request->rules();
    }

    private function actionNode(string $action)
    {
        $reflection = new \ReflectionMethod(...explode('@', $action));
        $requestClassName = $reflection->class;

        $method = ClassReflector::make($requestClassName)->getMethod('rules');

        $rulesMethodNode = $method->getAstNode();

        return new ValidationNodesResult(
            (new NodeFinder())->find(
                Arr::wrap($rulesMethodNode->stmts),
                fn(Node $node) => $node instanceof Node\Expr\ArrayItem
                    && $node->key instanceof Node\Scalar\String_
                    && $node->getAttribute('parsedPhpDoc')
            )
        );
    }
}
