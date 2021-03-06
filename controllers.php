<?php

use Gregwar\Image\Image;
use Carbon\Carbon;

$app->match('/', function() use ($app) {
    return $app['twig']->render('home.html.twig');
})->bind('home');

$app->match('/books', function() use ($app) {
    return $app['twig']->render('books.html.twig', array(
        'books' => $app['model']->getBooks()
    ));
})->bind('books');

$app->match('/admin', function() use ($app) {
    $request = $app['request'];
    $success = false;
    if ($request->getMethod() == 'POST') {
        $post = $request->request;
        if ($post->has('login') && $post->has('password') &&
            $post->get('password') == $app['config']['admin'][$post->get('login')]) {
            $app['session']->set('admin', true);
            $success = true;
        }
    }
    return $app['twig']->render('admin.html.twig', array(
        'success' => $success
    ));
})->bind('admin');

$app->match('/logout', function() use ($app) {
    $app['session']->remove('admin');
    return $app->redirect($app['url_generator']->generate('admin'));
})->bind('logout');

$app->match('/addBook', function() use ($app) {
    if (!$app['session']->has('admin')) {
        return $app['twig']->render('shouldBeAdmin.html.twig');
    }

    $request = $app['request'];
    if ($request->getMethod() == 'POST') {
        $post = $request->request;
        if ($post->has('title') && $post->has('author') && $post->has('synopsis') &&
            $post->has('copies')) {
            $files = $request->files;
            $image = '';

            // Resizing image
            if ($files->has('image') && $files->get('image')) {
                $image = sha1(mt_rand().time());
                Image::open($files->get('image')->getPathName())
                    ->resize(240, 300)
                    ->save('uploads/'.$image.'.jpg');
                Image::open($files->get('image')->getPathName())
                    ->resize(120, 150)
                    ->save('uploads/'.$image.'_small.jpg');
            }

            // Saving the book to database
            $res = $app['model']->insertBook($post->get('title'), $post->get('author'), $post->get('synopsis'),
                $image, (int)$post->get('copies'));
        }
    }

    return $app['twig']->render('addBook.html.twig', array(
        'res' => $res
    ));
})->bind('addBook');

// Fiche d'un livre
$app->match('/book/{id}', function($id) use ($app) {
    return $app['twig']->render('book.html.twig', array(
        'book' => $app['model']->getBook($id),
        'copies' => $app['model']->getAvailableCopies($id),
        'copiesNotAvailable' => $app['model']->getNotAvailableCopies($id),
        'success' => false
    ));
})->bind('book');

// Emprunt d'un livre
$app->match('/book/{idBook}/copy/{idCopy}/loan', function($idBook, $idCopy) use ($app) {
    $request = $app['request'];
    $res = false;
    if ($request->getMethod() == 'POST') {
        $post = $request->request;
        if ($post->has('endDate') && $post->has('name')) {
            $endDate = Carbon::createFromFormat('d/m/Y', $post->get('endDate'));
            $res = $app['model']->insertLoan($idCopy, $endDate, $post->get('name'));
        }
    }

    return $app['twig']->render('loan.html.twig', array(
        'success' => $res
    ));
})->bind('addCopy');

// Retour d'un livre
$app->match('/book/{idBook}/copy/{idCopy}/return/{idLoan}', function($idBook, $idCopy, $idLoan) use ($app) {
    $app['model']->returnBook($idLoan);

    return $app->redirect($app['url_generator']->generate('book', array('id' => $idBook)));
})->bind('returnCopy');