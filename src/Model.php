<?php

class Model
{
    protected $pdo;

    public function __construct(array $config)
    {
        try {
            if ($config['engine'] == 'mysql') {
                $this->pdo = new \PDO(
                    'mysql:dbname='.$config['database'].';host='.$config['host'],
                    $config['user'],
                    $config['password']
                );
                $this->pdo->exec('SET CHARSET UTF8');
            } else {
                $this->pdo = new \PDO(
                    'sqlite:'.$config['file']
                );
            }
        } catch (\PDOException $error) {
            throw new ModelException('Unable to connect to database');
        }
    }

    /**
     * Tries to execute a statement, throw an explicit exception on failure
     */
    protected function execute(\PDOStatement $query, array $variables = array())
    {
        if (!$query->execute($variables)) {
            $errors = $query->errorInfo();
            throw new ModelException($errors[2]);
        }

        return $query;
    }

    /**
     * Récupère un résultat exactement
     */
    protected function fetchOne(\PDOStatement $query)
    {
        if ($query->rowCount() != 1) {
            return false;
        } else {
            return $query->fetch();
        }
    }

    /**
     * Inserting a book in the database
     */
    public function insertBook($title, $author, $synopsis, $image, $copies)
    {
        $query = $this->pdo->prepare('INSERT INTO livres (titre, auteur, synopsis, image)
            VALUES (?, ?, ?, ?)');
        $this->execute($query, array($title, $author, $synopsis, $image));

        $book_id = $this->pdo->lastInsertId();
        $query = $this->pdo->prepare('INSERT INTO exemplaires (book_id) VALUES (?)');
        for ($i = 0; $i<$copies; $i++){
            $this->execute($query, array($book_id));
        }

        return $query;
    }

    /**
     * Getting all the books
     */
    public function getBooks()
    {
        $query = $this->pdo->prepare('SELECT livres.* FROM livres');

        $this->execute($query);

        return $query->fetchAll();
    }

    /**
     * Getting a book
     */
    public function getBook($id)
    {
        $query = $this->pdo->prepare('SELECT livres.* FROM livres WHERE livres.id = ?');
        $this->execute($query, array($id));

        return $this->fetchOne($query);
    }

    /**
     * Getting all the copies
     */
    public function getCopies()
    {
        $query = $this->pdo->prepare('SELECT exemplaires.* FROM exemplaires');

        $this->execute($query);

        return $query->fetchAll();
    }

    /**
     * Getting all the copies for a book
     */
    public function getBookCopies($id_book)
    {
        $query = $this->pdo->prepare('SELECT exemplaires.* FROM exemplaires WHERE exemplaires.book_id = ?');

        $this->execute($query, array($id_book));

        return $query->fetchAll();
    }

    /**
     * Getting available copies for a book
     */
    public function getAvailableCopies($idBook)
    {
        $query = $this->pdo->prepare('
          SELECT ex.* FROM exemplaires ex inner join emprunts em on ex.id = em.exemplaire
          WHERE ex.book_id = ?
          AND em.fini = 1
          UNION
          SELECT ex.* FROM exemplaires ex
          WHERE ex.book_id = ?
          AND ex.id NOT IN(SELECT em.exemplaire FROM emprunts em)
        ');
        $this->execute($query, array($idBook, $idBook));

        return $query->fetchAll();
    }

    /**
     * Insert a new loan
     */
    public function insertLoan($idCopy, $endDate, $name){
        $query = $this->pdo->prepare('INSERT INTO emprunts (personne, exemplaire, debut, fin, fini)
            VALUES (?, ?, ?, ?, ?)');

        $this->execute($query, array($name, $idCopy, date("Y-m-d"), $endDate, false));

        return $query;
    }

    /**
     * Getting not available copies for a book
     */
    public function getNotAvailableCopies($idBook)
    {
        $query = $this->pdo->prepare('
          SELECT ex.id, ex.book_id, em.id as emprunt_id, em.personne  FROM exemplaires ex inner join emprunts em on ex.id = em.exemplaire
          WHERE ex.book_id = ?
          AND em.fini = 0
        ');
        $this->execute($query, array($idBook));

        return $query->fetchAll();
    }

    /**
     * Set the return of a book
     */
    public function returnBook($id){
        $query = $this->pdo->prepare('
            UPDATE emprunts
            SET fini = 1
            WHERE id = ?
        ');

        $this->execute($query, array($id));

        return $query;
    }

}
