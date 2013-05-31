<?php
namespace Drahak\Restful\Application;

use Drahak\Restful\Application\Routes\IResourceRouter;
use Drahak\Restful\Application\Routes\ResourceRoute;
use Nette\Application\Application;
use Nette\Application\BadRequestException;
use Nette\Application\IRouter;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\Http\Request;
use Nette\Object;

/**
 * MethodHandler
 * @package Drahak\Restful\Application
 * @author Drahomír Hanák
 */
class MethodHandler extends Object implements IErrorHandler
{

	/** @var IRequest */
	private $request;

	/** @var array */
	private $methods = array(
		IResourceRouter::GET => IRequest::GET,
		IResourceRouter::POST => IRequest::POST,
		IResourceRouter::PUT => IRequest::PUT,
		IResourceRouter::DELETE => IRequest::DELETE,
		IResourceRouter::HEAD => IRequest::HEAD
	);

	public function __construct(IRequest $request)
	{
		$this->request = $request;
	}

	/**
	 * On application run
	 * @param Application $application
	 *
	 * @throws \Nette\Application\BadRequestException
	 */
	public function run(Application $application)
	{
		$router = $application->getRouter();
		$response = $router->match($this->request);
		if (!$response) {
			$this->checkAvailableMethods($router);
		}
	}

	/**
	 * Recursively Checks available methods for this request
	 * @param IRouter $router
	 * @throws \Nette\Application\BadRequestException
	 */
	private function checkAvailableMethods(IRouter $router)
	{
		foreach ($router as $route) {
			if ($route instanceof IResourceRouter && !$route instanceof \Traversable) {
				$methodFlag = NULL;
				foreach ($this->methods as $flag => $requestMethod) {
					if ($route->isMethod($flag)) {
						$methodFlag = $flag;
						break;
					}
				}
				if (!$methodFlag) continue;
				$request = $this->createAcceptableRequest($methodFlag);

				$acceptableMethods = array_keys($route->getActionDictionary());
				$methodNames = array();
				foreach ($acceptableMethods as $flag) {
					$methodNames[] = $this->methods[$flag];
				}

				if (in_array($route->getMethod($request), $acceptableMethods) && $route->match($request)) {
					throw new BadRequestException('Method not supported. Available methods: ' . implode(', ', $methodNames), IResponse::S405_METHOD_NOT_ALLOWED);
				}
			}

			if ($route instanceof \Traversable) {
				$this->checkAvailableMethods($route);
			}
		}
	}

	/**
	 * Create route acceptable HTTP request
	 * @param int $methodFlag
	 * @return Request
	 */
	private function createAcceptableRequest($methodFlag)
	{
		$query = $this->removeOverrideParam($this->request->getQuery());
		$headers = $this->removeOverrideHeader($this->request->getHeaders());

		return new Request(
			$this->request->getUrl(),
			$query,
			$this->request->getPost(), NULL, NULL,
			$headers,
			$this->methods[$methodFlag]
		);
	}

	/**
	 * Remove override header
	 * @param array $headers
	 * @return array
	 */
	private function removeOverrideHeader(array $headers)
	{
		unset($headers[ResourceRoute::HEADER_OVERRIDE]);
		return $headers;
	}

	/**
	 * Remove override param from query URL parameters
	 * @param array $query
	 * @return string
	 */
	private function removeOverrideParam(array $query)
	{
		unset($query[ResourceRoute::PARAM_OVERRIDE]);
		return $query;
	}


}