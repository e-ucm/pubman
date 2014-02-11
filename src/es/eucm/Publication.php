<?php
namespace es\eucm;

class Publication {
	public $id;

	public $year;
	
	public $title;

	public $fileURL;
	
	public $authors;

	public $category;

	public $details;
	
	public function __construct($title, $year, $details) {
		$this->title = $title;
		$this->year = $year;
		$this->details = $details;
		$this->authors = array();
	}

	public function authorsList() {
		return implode('; ', \array_map(function ($author) { return $author->name; }, $this->authors));
	}

}
