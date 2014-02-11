<?php

namespace es\eucm;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

class AuthorsService {
	public function listAuthors(Request $request, Application $app) {
		$page = new Page($app['request'], $app['url_generator'], 'authors', array('name'));
		$errors = array();

		$authors = $app['authors.repository']->getAuthors($page);

		return $app['twig']->render('main.twig', array(
			'navbar' => 'listAuthors-navbar.twig',
			'errors' => $errors,
			'previousURL' => $page->getPreviousURL(),
			'nextURL' => $page->getNextURL(),
			'authors' => $authors,
			'content' => 'listAuthors-content.twig',
		));
	}

	public function listAllAuthors(Request $request, Application $app) {
		$errors = array();

		$authors = $app['authors.repository']->getAuthors();

		return $app['twig']->render('main.twig', array(
			'navbar' => 'listAuthors-navbar.twig',
			'errors' => $errors,
			'previousURL' => NULL,
			'nextURL' => NULL,
			'authors' => $authors,
			'content' => 'listAuthors-content.twig',
		));
	}

	public function addAuthor(Request $request, Application $app) {
		// some default data for when the form is displayed the first time
		$data = array(
		);

		$form = $app['form.factory']->createBuilder('form', $data)
		->setAction($app['url_generator']->generate('authors'))
		->setMethod('POST')
		->add('name', 'text', array(
			'constraints' => array(new Assert\NotBlank(), new Assert\Length(array('min' => 5))),
			'attr' => array('class' => 'form-control', 'placeholder' => 'Author name'),
			'label' => 'Author Name'))
		->add('Add', 'submit')
		->getForm();

		$form->handleRequest($request);

		if ($form->isValid()) {
			$data = $form->getData();
			$author = new Author($data['name']);
			$app['authors.repository']->addAuthor($author);
			return $app->redirect($app['url_generator']->generate('authors'));
		}

		// display the form
		return $app['twig']->render('main.twig', array(
			'navbar' => 'addAuthor-navbar.twig',
			'content' => 'addAuthor-content.twig',
			'form' => $form->createView(),
		));
	}

	public function editAuthor(Request $request, Application $app, $authorId) {
		// some default data for when the form is displayed the first time
		$author = $app['authors.repository']->getAuthor($authorId);
		if (!$author) {
			return $app->redirect($app['url_generator']->generate('authors'));
		}
		$data = array(
			'name' => $author->name,
		);

		$form = $app['form.factory']->createBuilder('form', $data)
		->setAction($app['url_generator']->generate('editAuthor', array('authorId' => $authorId)))
		->setMethod('PUT')
		->add('name', 'text', array(
			'constraints' => array(new Assert\NotBlank(), new Assert\Length(array('min' => 5))),
			'attr' => array('class' => 'form-control'),
			'label' => 'Author Name'))
		->add('Edit', 'submit')
		->getForm();

		$form->handleRequest($request);

		if ($form->isValid()) {
			$data = $form->getData();
			$author->name = $data['name'];
			$app['authors.repository']->updateAuthor($author);
			return $app->redirect($app['url_generator']->generate('authors'));
		}

		// display the form
		return $app['twig']->render('main.twig', array(
			'navbar' => 'manageAuthor-navbar.twig',
			'content' => 'addAuthor-content.twig',
			'form' => $form->createView(),
		));
	}

	public function deleteAuthor(Request $request, Application $app, $authorId) {
		// some default data for when the form is displayed the first time
		$author = $app['authors.repository']->getAuthor($authorId);
		if (!$author) {
			return $app->redirect($app['url_generator']->generate('authors'));
		}
		$data = array(
			'name' => $author->name,
		);

		$form = $app['form.factory']->createBuilder('form', $data)
		->setAction($app['url_generator']->generate('delAuthor', array('authorId' => $authorId)))
		->setMethod('DELETE')
		->add('name', 'text', array(
			'attr' => array('class' => 'form-control'),
			'label' => 'Delete this author?',
			'disabled' => TRUE))
		->add('Delete', 'submit')
		->getForm();

		$form->handleRequest($request);

		if ($form->isValid()) {
			$data = $form->getData();
			$author = new Author($data['name']);
			$author->id = intval($authorId);
			$app['authors.repository']->deleteAuthor($author);
			return $app->redirect($app['url_generator']->generate('authors'));
		}

		// display the form
		return $app['twig']->render('main.twig', array(
			'navbar' => 'manageAuthor-navbar.twig',
			'content' => 'addAuthor-content.twig',
			'form' => $form->createView(),
		));
	}
}
