<?php
namespace es\eucm;

class PublicationsRepository {

	const ORDER_ID = 0;

	const ORDER_YEAR = 1;

	private $sqltemplate;

	private $transactiontemplate;

	public function __construct(MySQLStatementTemplate $sqltemplate, MySQLTransactionTemplate $transactiontemplate) {
		$this->sqltemplate = $sqltemplate;
		$this->transactiontemplate = $transactiontemplate;
	}
	
	public function getPublications($page = NULL) {
		$publications = array();
		$orderClause = 'ORDER BY year DESC, category ASC, id DESC';
		$limitClause = '';
		$whereClause = '';
		if ($page) {
			$scrollingWindowSize = $page->getSize() + 1;
			if ($page->getDir()) {
				if ($page->isNext()) {
					$whereClause = 'WHERE year <= ? AND (id < ? OR year < ?)';
					$orderClause = 'ORDER BY year DESC, id DESC';
				} else {
					$whereClause = 'WHERE year > ? OR (id > ? AND year >= ?)';
					$orderClause = 'ORDER BY year ASC, id ASC';
				}
			}
			$limitClause = 'LIMIT '.($scrollingWindowSize);
		}
		$query = "SELECT * FROM publications $whereClause $orderClause $limitClause";

		$getPublicationAuthors = array($this, 'getPublicationAuthors');
		$getPublicationCategory = array($this, 'getPublicationCategory');

		$params=NULL;
		if ($page && $page->getDir()) {
			$lastSeen = $page->getLastSeen();
			$params = array('iii', intval($lastSeen['year']), intval($lastSeen['id']), intval($lastSeen['year']));
		}
		$this->sqltemplate->query($query,
			function ($row) use (&$publications, $getPublicationAuthors, $getPublicationCategory) {
				$publication = new Publication($row['title'], $row['year'], $row['details']);
				$publication->id = $row['id'];
				$publication->fileURL = $row['fileURL'];
				$publication->authors = call_user_func($getPublicationAuthors, $row['id']);
				$publication->category = call_user_func($getPublicationCategory, $row['category']);
				$publications[] = $publication;
			},
			$params
		);

		$publicationsCount = count($publications);
		if ($page) {
			if ($page->isPrevious()) {
				$publications = array_reverse($publications);
			}
			if($publicationsCount === $scrollingWindowSize) {
				\array_pop($publications);
				$publicationsCount--;
				$page->setExistsAdditionalPage(true);
			}
			$page->setFirstInPage(array('id' => $publications[0]->id, 'year' => $publications[0]->year));
			$page->setLastInPage(array('id' => $publications[$publicationsCount-1]->id, 'year' => $publications[$publicationsCount-1]->year));
		}
		return $publications;
	}

	public function getPublicationAuthors($publicationId) {
		$authors = array();
		$this->sqltemplate->query('SELECT P.order, A.id, A.name FROM publication_author P, authors A WHERE ( (A.id = P.idAuthor) AND (P.idPublication = ?)) ORDER BY P.order',
			function ($row) use (&$authors) {
				$author = new Author($row['name']);
				$author->id = intval($row['id']);
				$authors[] = $author;
			},
			array('i', intval($publicationId))
		);

		return $authors;
	}

	public function getCategories() {
		$categories = array();
		$this->sqltemplate->query('SELECT id, name FROM categories',
			function ($row) use (&$categories) {
				$category = new Category($row['name']);
				$category-> id = intval($row['id']);
				$categories[] = $category;
			}
		);

		return $categories;
	}

	public function getPublicationCategory($categoryId) {
		return $this->sqltemplate->queryUnique('SELECT * FROM categories WHERE id = ?',
			function ($row) {
				$category = new Category($row['name']);
				$category-> id = intval($row['id']);
				return $category;
			},
			array('i', intval($categoryId))
		);
	}

	public function getPublication($publicationId) {

		$getPublicationAuthors = array($this, 'getPublicationAuthors');
		$getPublicationCategory = array($this, 'getPublicationCategory');

		$query = 'SELECT * FROM publications WHERE id = ?';
		return $this->sqltemplate->queryUnique($query,
			function ($row) use ($getPublicationAuthors, $getPublicationCategory) {
				$publication = new Publication($row['title'], intval($row['year']), $row['details']);
				$publication->id = intval($row['id']);
				$publication->fileURL = $row['fileURL'];
				$publication->authors = call_user_func($getPublicationAuthors, $row['id']);
				$publication->category = call_user_func($getPublicationCategory, $row['category']);

				return $publication;
			},
			array('i', intval($publicationId))
		);
	}

	public function addPublication(Publication $publication, $authors) {
		return $this->transactiontemplate->execute(function ($conn) use ($publication, $authors) {
			$sqltemplate = new MySQLStatementTemplate($conn);
			$id = $sqltemplate->insertReturnLastId('INSERT INTO publications(title, year, details, fileURL, category) VALUES (?, ?, ?, ?, ?)',
				array('sissi', $publication->title, $publication->year, $publication->details, $publication->fileURL, $publication->category->id)
			);
			$id = intval($id);
			$publication->id = $id;
			$i=1;
			foreach ($authors as $authorId) {
				$sqltemplate->insert('INSERT INTO publication_author(`order`, idAuthor, idPublication) VALUES (?, ?, ?)',
					array('iii', $i, $authorId, $id)
				);
				$i++;
			}
			return $publication;
		});
	}

	public function updatePublication(Publication $publication, $authors) {
		return $this->transactiontemplate->execute(function ($conn) use ($publication, $authors) {
			$sqltemplate = new MySQLStatementTemplate($conn);

			$id = $sqltemplate->update('UPDATE publications SET title = ?, year = ?, details = ?, fileURL = ?, category = ? WHERE id = ?',
				array('sissii', $publication->title, $publication->year, $publication->details, $publication->fileURL, $publication->category->id, $publication->id)
			);

			$id = $publication->id;
			$sqltemplate->delete('DELETE FROM publication_author WHERE idPublication = ?',
				array('i', $id)
			);

			$i=1;
			foreach ($authors as $authorId) {
				$sqltemplate->insert('INSERT INTO publication_author(`order`, idAuthor, idPublication) VALUES (?, ?, ?)',
					array('iii', $i, $authorId, $id)
				);
				$i++;
			}
			return $publication;
		});
        }

	public function updateURLs(Publication $publication) {
		return $this->sqltemplate->update('UPDATE publications SET fileURL = ? WHERE id = ?',
			array('si', $publication->fileURL, $publication->id)
		);
	}

	public function deletePublication(Publication $publication) {
		return $this->transactiontemplate->execute(function ($conn) use($publication) {
			$sqltemplate = new MySQLStatementTemplate($conn);
			$count = $sqltemplate->delete('DELETE FROM publication_author WHERE idPublication = ?',
				array('i', $publication->id)
			);
			$count += $sqltemplate->delete('DELETE FROM publications WHERE id = ?',
				array('i', $publication->id)
			);
			return $count;
		});
	}

	public function getAuthorPublications($authorId) {
		$query = "SELECT * FROM publications P, publication_author A WHERE P.id = A.idPublication AND A.idAuthor = ? ORDER BY year DESC, category ASC";

		$getPublicationAuthors = array($this, 'getPublicationAuthors');
		$getPublicationCategory = array($this, 'getPublicationCategory');

		$params=NULL;
		$publications = array();
		$this->sqltemplate->query($query,
			function ($row) use (&$publications, $getPublicationAuthors, $getPublicationCategory) {
				$publication = new Publication($row['title'], $row['year'], $row['details']);
				$publication->id = $row['id'];
				$publication->fileURL = $row['fileURL'];
				$publication->authors = call_user_func($getPublicationAuthors, $row['id']);
				$publication->category = call_user_func($getPublicationCategory, $row['category']);
				$publications[] = $publication;
			},
			array('i', intval($authorId))
		);
		return $publications;
	}

}
