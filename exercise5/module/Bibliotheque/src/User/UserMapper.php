<?php

namespace Bibliotheque\User;

use DomainException;
use Rhumsaa\Uuid\Uuid;
use Zend\Crypt\Password\Bcrypt;
use Zend\Db\TableGateway\TableGatewayInterface;
use Zend\Paginator\Adapter\DbTableGateway as DbTableGatewayPaginator;

class UserMapper implements UserMapperInterface
{
    protected $collectionClass;
    protected $entityClass;
    protected $table;

    public function __construct(
        TableGatewayInterface $table,
        $entityClass = 'Bibliotheque\User\UserEntity',
        $collectionClass = 'Bibliotheque\User\UserCollection'
    ) {
        $this->table = $table;
        $this->entityClass = $entityClass;
        $this->collectionClass = $collectionClass;
    }

    public function create($username, $password, $fullname)
    {
        if ($this->byUsername($username)) {
            throw new DomainException(
                sprintf('Username "%s" already exists', $username),
                409
            );
        }

        $crypt= new Bcrypt();

        $user = array(
            'user_id'  => (string) Uuid::uuid4(),
            'username' => $username,
            'password' => $crypt->create($password),
            'name'     => $fullname,
        );

        $this->table->insert($user);

        return new $this->entityClass($user);
    }

    public function fetch($id)
    {
        $results = $this->table->select(array('user_id' => $id));
        if (! $results->count()) {
            throw new DomainException(sprintf(
                'Could not find user with ID "%s"',
                $id
            ), 404);
        }

        return $results->current();
    }

    public function fetchAll()
    {
        $paginator = new DbTableGatewayPaginator($this->table);
        return new $this->collectionClass($paginator);
    }

    public function exists($id)
    {
        $results = $this->table->select(array('user_id' => $id));
        if (! $results->count()) {
            return false;
        }

        return true;
    }

    public function byUsername($username)
    {
        $results = $this->table->select(array('username' => $username));
        if (! $results->count()) {
            return false;
        }

        return true;
    }
}
