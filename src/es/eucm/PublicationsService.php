<?php

namespace es\eucm;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Validator\Constraints as Assert;

class PublicationsService {
	public function listPublications(Request $request, Application $app) {
		$page = new Page($app['request'], $app['url_generator'], 'publications', array('year','id'));
		$errors = array();

		$publications = $app['publications.repository']->getPublications($page);

		return $app['twig']->render('main.twig', array(
			'navbar' => 'listPublications-navbar.twig',
			'errors' => $errors,
			'previousURL' => $page->getPreviousURL(),
			'nextURL' => $page->getNextURL(),
			'publications' => $publications,
			'content' => 'listPublications-content.twig',
		));
	}

	public function listAllPublications(Request $request, Application $app) {
		$errors = array();

		$publications = $app['publications.repository']->getPublications();

		return $app['twig']->render('main.twig', array(
			'navbar' => 'listPublications-navbar.twig',
			'errors' => $errors,
			'previousURL' => NULL,
			'nextURL' => NULL,
			'publications' => $publications,
			'content' => 'listPublications-content.twig',
		));
	}

	public function addPublication(Request $request, Application $app) {
		// some default data for when the form is displayed the first time
		$data = array(
		);

		$categoryList = $app['publications.repository']->getCategories();
		$categories = array();
		foreach($categoryList as $category) {
			$categories[$category->id] = $category->name;
		}
		$authorList = $app['authors.repository']->getAuthors();
		$authors = array();
		foreach($authorList as $author) {
			$authors[$author->id] = $author->name;
		}

		$form = $app['form.factory']->createBuilder('form', $data)
		->setAction($app['url_generator']->generate('publications'))
		->setMethod('POST')
		->add('title', 'text', array(
			'constraints' => array(new Assert\NotBlank(), new Assert\Length(array('min' => 10, 'max' => 512))),
			'attr' => array('class' => 'form-control', 'placeholder' => 'Title'),
			'max_length' => 512,
			'label' => 'Publication Title'))
		->add('details', 'textarea', array(
			'constraints' => array(new Assert\NotBlank(), new Assert\Length(array('min' => 10, 'max' => 1024))),
			'attr' => array('class' => 'form-control', 'placeholder' => 'Publication Details (Conference name / Journal name, etc.)', 'cols' => 80, 'rows' => 13),
			'max_length' => 1024,
			'label' => 'Publication Details'))
		->add('year', 'text', array(
			'constraints' => array(new Assert\NotBlank(), new Assert\GreaterThan(array('value' => 1970), new Assert\LessThan(array('value' => 10000)))),
			'attr' => array('class' => 'form-control', 'placeholder' => 'yyyy', 'size' => 4),
			'max_length' => 4,
			'label' => 'Year of Publication'))
		->add('category', 'choice', array(
			'attr' => array('class' => 'form-control'),
			'choices' => $categories
			))
		->add('draft', 'file', array(
			'attr' => array('class' => 'form-control'),
			'label' => 'Publication\'s draft (PDF only)',
			'required' => false
			))
		->add('author_1', 'choice', array(
			'attr' => array('class' => 'form-control'),
			'choices' => $authors
			));
		for($i = 2; $i <=  10; $i++) {
			$form->add("author_$i", 'choice', array(
				'attr' => array('class' => 'form-control'),
				'choices' => $authors,
				'required' => false
			));
		}
		$form->add('Add', 'submit');
		$form = $form->getForm();

		$form->handleRequest($request);

		if ($form->isValid()) {
			$data = $form->getData();
			$publication = new Publication($data['title'], intval($data['year']), $data['details']);
			$publication->category = new Category(NULL);
			$publication->category->id = intval($data['category']);
			$formAuthors = array(intval($data['author_1']));
			for($i=2; $i <= 10; $i++) {
				if (! empty ($data["author_$i"])) {
					$formAuthors[] = intval($data["author_$i"]);
				}
			}
			$app['publications.repository']->addPublication($publication, $formAuthors);
			$draft=$data['draft'];
			if ($draft) {
				$mimeType = $draft->getMimeType();
				if (!empty($mimeType) && $mimeType === 'application/pdf') {
					$fileName = "e-UCM_draft_{$publication->id}.pdf";
					$draft->move(PUBMAN_DRAFTS_FOLDER, $fileName);
					$publication->fileURL = PUBMAN_DRAFTS_URL_PREFIX.$fileName;
					$app['publications.repository']->updateURLs($publication);
				}
			}
			return $app->redirect($app['url_generator']->generate('publications'));
		}

		// display the form
		return $app['twig']->render('main.twig', array(
			'navbar' => 'addPublication-navbar.twig',
			'content' => 'addPublication-content.twig',
			'form' => $form->createView(),
		));
	}

	public function editPublication(Request $request, Application $app, $publicationId) {
		$publication = $app['publications.repository']->getPublication($publicationId);
		if (!$publication) {
			return $app->redirect($app['url_generator']->generate('publications'));
		}

		// some default data for when the form is displayed the first time
		$data = array(
			'title' => $publication->title,
			'year' => $publication->year,
			'details' => $publication->details
		);
		$i = 1;
		foreach($publication->authors as $author) {
			$data["author_$i"] = $author->id;
			$i++;
		}

		$data['category'] = $publication->category->id;

		$categoryList = $app['publications.repository']->getCategories();
		$categories = array();
		foreach($categoryList as $category) {
			$categories[$category->id] = $category->name;
		}
		$authorList = $app['authors.repository']->getAuthors();
		$authors = array();
		foreach($authorList as $author) {
			$authors[$author->id] = $author->name;
		}

		$form = $app['form.factory']->createBuilder('form', $data)
		->setAction($app['url_generator']->generate('editPublication', array('publicationId' => $publicationId)))
		->setMethod('PUT')
		->add('title', 'text', array(
			'constraints' => array(new Assert\NotBlank(), new Assert\Length(array('min' => 10, 'max' => 512))),
			'attr' => array('class' => 'form-control', 'placeholder' => 'Title'),
			'max_length' => 512,
			'label' => 'Publication Title'))
		->add('details', 'textarea', array(
			'constraints' => array(new Assert\NotBlank(), new Assert\Length(array('min' => 10, 'max' => 1024))),
			'attr' => array('class' => 'form-control', 'placeholder' => 'Publication Details (Conference name / Journal name, etc.)', 'cols' => 80, 'rows' => 13),
			'max_length' => 1024,
			'label' => 'Publication Details'))
		->add('year', 'text', array(
			'constraints' => array(new Assert\NotBlank(), new Assert\GreaterThan(array('value' => 1970), new Assert\LessThan(array('value' => 10000)))),
			'attr' => array('class' => 'form-control', 'placeholder' => 'yyyy', 'size' => 4),
			'max_length' => 4,
			'label' => 'Year of Publication'))
		->add('category', 'choice', array(
			'attr' => array('class' => 'form-control'),
			'choices' => $categories
			));
		$form->add('draft', 'file', array(
			'attr' => array('class' => 'form-control'),
			'label' => 'Publication\'s draft (PDF only)',
			'required' => false
			));
		if ($publication->fileURL) {
			$form->add('delete_draft', 'checkbox', array(
				'attr' => array('class' => 'form-control'),
				'label' => 'Draft file exits, delete it ?',
				'required' => false
			));
		}
		$form->add('author_1', 'choice', array(
			'attr' => array('class' => 'form-control'),
			'choices' => $authors
			));
		for($i = 2; $i <=  10; $i++) {
			$form->add("author_$i", 'choice', array(
				'attr' => array('class' => 'form-control'),
				'choices' => $authors,
				'required' => false
			));
		}
		$form->add('Update', 'submit');
		$form=$form->getForm();

		$form->handleRequest($request);

		if ($form->isValid()) {
			$data = $form->getData();
			$publication->title = $data['title'];
			$publication->year =  intval($data['year']);
			$publication->details = $data['details'];
			$publication->category = new Category(NULL);
			$publication->category->id = intval($data['category']);

			$formAuthors = array(intval($data['author_1']));
			for($i=2; $i <= 10; $i++) {
				if (! empty ($data["author_$i"])) {
					$formAuthors[] = intval($data["author_$i"]);
				}
			}
			$app['publications.repository']->updatePublication($publication, $formAuthors);
			$draft=$data['draft'];
			if ($draft) {
				$mimeType = $draft->getMimeType();
				if (!empty($mimeType) && $mimeType === 'application/pdf') {
					$fileName = "e-UCM_draft_{$publication->id}.pdf";
					$draft->move(PUBMAN_DRAFTS_FOLDER, $fileName);
					$publication->fileURL = PUBMAN_DRAFTS_URL_PREFIX.$fileName;
					$app['publications.repository']->updateURLs($publication);
				}
			} else {
				if (array_key_exists('delete_draft', $data) && $data['delete_draft'] && !is_null($publication->fileURL)) {
					$fileName = "e-UCM_draft_{$publication->id}.pdf";
					if (!@unlink(PUBMAN_DRAFTS_FOLDER.$fileName)) {
						throw new \Exception('Error deleting file: '.$fileName);
					}
					$publication->fileURL = NULL;
					$app['publications.repository']->updateURLs($publication);
				}
			}
			return $app->redirect($app['url_generator']->generate('publications'));
		}

		// display the form
		return $app['twig']->render('main.twig', array(
			'navbar' => 'managePublication-navbar.twig',
			'content' => 'addPublication-content.twig',
			'form' => $form->createView(),
		));
	}

	public function deletePublication(Request $request, Application $app, $publicationId) {
		$publication = $app['publications.repository']->getPublication($publicationId);
		if (!$publication) {
			return $app->redirect($app['url_generator']->generate('publications'));
		}

		$authorList = $publication->authors;
		$authors = array();
		foreach($authorList as $author) {
			$authors[$author->id] = $author->name;
		}

		// some default data for when the form is displayed the first time
		$data = array(
			'title' => $publication->title,
			'year' => $publication->year,
			'details' => $publication->details,
			'category' => $publication->category->name,
			'authors' => implode('; ', $authors)
		);

		$form = $app['form.factory']->createBuilder('form', $data)
		->setAction($app['url_generator']->generate('delPublication', array('publicationId' => $publicationId)))
		->setMethod('DELETE')
		->add('title', 'text', array(
			'attr' => array('class' => 'form-control', 'placeholder' => 'Title'),
			'disabled' => true,
			'label' => 'Remove this publication ?'))
		->add('details', 'text', array(
			'attr' => array('class' => 'form-control', 'placeholder' => 'Publication Details (Conference name / Journal name, etc.)', 'cols' => 80, 'rows' => 13),
			'disabled' => true,
			'label' => 'Publication Details'))
		->add('year', 'text', array(
			'attr' => array('class' => 'form-control', 'placeholder' => 'yyyy', 'size' => 4),
			'disabled' => true,
			'label' => 'Year of Publication'))
		->add('category', 'text', array(
			'attr' => array('class' => 'form-control'),
			'disabled' => true,
			))
		->add('authors', 'text', array(
			'attr' => array('class' => 'form-control'),
			'disabled' => true,
			))
		->add('Delete', 'submit')
		->getForm();

		$form->handleRequest($request);

		if ($form->isValid()) {
			$data = $form->getData();
			$app['publications.repository']->deletePublication($publication);
			if(!is_null($publication->fileURL)) {
				$fileName = "e-UCM_draft_{$publication->id}.pdf";
				if (!@unlink(PUBMAN_DRAFTS_FOLDER.$fileName)) {
					throw new \Exception('Error deleting file: '.$fileName);
				}
			}
			return $app->redirect($app['url_generator']->generate('publications'));
		}

		// display the form
		return $app['twig']->render('main.twig', array(
			'navbar' => 'managePublication-navbar.twig',
			'content' => 'addPublication-content.twig',
			'form' => $form->createView(),
		));
	}
}
