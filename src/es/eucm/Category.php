<?php
namespace es\eucm;

class Category {
	
	public $id;
	
	public $name;
	
	public function __construct($name) {
		$this->name = $name;
	}
}
