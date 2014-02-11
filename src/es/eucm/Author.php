<?php
namespace es\eucm;

class Author {
	
	public $id;
	
	public $name;

	
	public function __construct($name) {
		$this->name = $name;
	}
}
