<?php
namespace es\eucm;

class AuthorsRepository {

	private $sqltemplate;

	public function __construct(MySQLStatementTemplate $sqltemplate) {
		$this->sqltemplate = $sqltemplate;
	}
	
	public function getAuthors(Page $page = NULL) {
		$authors = array();
		$orderClause = 'ORDER BY name ASC';
		$limitClause = '';
		$whereClause = '';
		if ($page) {
			$scrollingWindowSize = $page->getSize() + 1;
			if ($page->getDir()) {
				if ($page->isNext()) {
					$whereClause = 'WHERE name > ?';
					$orderClause = 'ORDER BY name ASC';
				} else {
					$whereClause = 'WHERE name < ?';
					$orderClause = 'ORDER BY name DESC';
				}
			}
			$limitClause = 'LIMIT '.$scrollingWindowSize;
		}
		$query = "SELECT * FROM authors $whereClause $orderClause $limitClause";
		$params=NULL;
		if ($page && $page->getDir()) {
			$lastSeen = $page->getLastSeen();
			$params = array('s', $lastSeen['name']);
		}
		$this->sqltemplate->query($query,
			function ($row) use (&$authors) {
				$author = new Author($row['name']);
				$author->id = intval($row['id']);
				$authors[] = $author;
			},
			$params
		);

		$authorsCount = count($authors);
		if ($page) {
			if ($page->isPrevious()) {
				$authors = array_reverse($authors);
			}
			if($authorsCount === $scrollingWindowSize) {
				\array_pop($authors);
				$authorsCount--;
				$page->setExistsAdditionalPage(true);
			}
			$page->setFirstInPage(array('name' => $authors[0]->name));
			$page->setLastInPage(array('name' => $authors[$authorsCount-1]->name));
		}
		return $authors;
	}
	
	public function getAuthor($authorId) {
		$query = 'SELECT * FROM authors WHERE id = ?';
		return $this->sqltemplate->queryUnique($query,
			function ($row) {
				$author = new Author($row['name']);
				$author->id = intval($row['id']);
				return $author;
			},
			array('i', intval($authorId))
		);
	}

	public function getAuthorsById(array $authorsIds) {
		$authors = array();
		$query = 'SELECT * FROM authors WHERE id IN (?)';
		return $this->sqltemplate->query($query,
			function ($row) use(&$authors) {
				$author = new Author($row['name']);
				$author->id = intval($row['id']);
				$authors[] = $author;
			},
			array('s', implode(',', $authorsIds))
		);
		return $authors;
	}

	public function addAuthor(Author $author) {
		$id = $this->sqltemplate->insertReturnLastId('INSERT INTO authors(name) VALUES (?)',
			array('s', $author->name)
		);
		$author->id = intval($id);
		return $author;
	}

	public function updateAuthor(Author $author) {
		$this->sqltemplate->update('UPDATE authors SET name = ? WHERE id = ?',
			array('si', $author->name, $author->id)
		);
		return $author;
	}

	public function deleteAuthor(Author $author) {
		return $this->sqltemplate->delete('DELETE FROM authors WHERE id = ?',
			array('i', $author->id)
		);
	}
}
