<?php

namespace es\eucm;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGenerator;

class Page {

	const DIR_PARAM_NAME = 'dir';

	const DIR_PARAM_VALUE_NEXT = 'next';

	const DIR_PARAM_VALUE_PREV = 'prev';

	private $urlGenerator;

	private $routeName;

	private $size;

	private $lastSeen;

	private $firstInPage;

	private $lastInPage;

	private $dir;

	private $existsAdditionalPage;

	public function __construct(Request $request, UrlGenerator $urlGenerator, $routeName, array $paramNames=array()) {
		$this->urlGenerator = $urlGenerator;
		$this->routeName = $routeName;
		$this->size = 20;
		$this->lastSeen = array();
		foreach ($paramNames as $param) {
			$this->lastSeen[$param] = rawurldecode($request->query->get($param));
		}
		$this->dir = $request->query->getAlpha(self::DIR_PARAM_NAME);
		$this->firstInPage = array();
		$this->lastInPage = array();
		$this->existsAdditionalPage = false;
	}

	public function getPreviousURL() {
		$previousURL = NULL;
		// !\is_null($this->dir) && ($this->isNext() || ($this->isPrevious() && $this->existsAdditionalPage))
		if (!empty($this->dir) && ($this->isNext() || $this->existsAdditionalPage)) {
			$params = array();
			foreach($this->firstInPage as $paramName => $paramValue) {
				$params[rawurlencode($paramName)] = rawurlencode(strval($paramValue));
			}
			$params[rawurlencode(self::DIR_PARAM_NAME)] = rawurlencode(self::DIR_PARAM_VALUE_PREV);
			$previousURL=$this->urlGenerator->generate($this->routeName, $params);
		}
		return $previousURL;
	}

	public function getNextURL() {
		$nextURL = NULL;

		if ($this->isPrevious() || ((empty($this->dir) || $this->isNext())&&$this->existsAdditionalPage) ) {
			$params = array();
			foreach($this->lastInPage as $paramName => $paramValue) {
				$params[rawurlencode($paramName)] = rawurlencode(strval($paramValue));
			}
			$params[rawurlencode(self::DIR_PARAM_NAME)] = rawurlencode(self::DIR_PARAM_VALUE_NEXT);
			$nextURL=$this->urlGenerator->generate($this->routeName, $params);
		}
		return $nextURL;
	}

	public function getSize() {
		return $this->size;
	}

	public function getLastSeen() {
		return $this->lastSeen;
	}

	public function getPage() {
		return $this->page;
	}

	public function getDir() {
		return $this->dir;
	}

	public function isNext() {
		return self::DIR_PARAM_VALUE_NEXT === $this->dir;
	}

	public function isPrevious() {
		return self::DIR_PARAM_VALUE_PREV === $this->dir;
	}

	public function getFirstInPage() {
		return $this->firstInPage;
	}

	public function setFirstInPage($firstInPage) {
		$this->firstInPage = $firstInPage;
	}

	public function getLastInPage() {
		return $this->lastInPage;
	}

	public function setLastInPage($lastInPage) {
		$this->lastInPage = $lastInPage;
	}

	public function getExistsAdditionalPage() {
		return $this->existsAdditionalPage;
	}

	public function setExistsAdditionalPage($existsAdditionalPage) {
		$this->existsAdditionalPage = $existsAdditionalPage;
	}
}
