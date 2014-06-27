<?php

namespace Becklyn\RadBundle\Model;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Becklyn\RadBundle\Helper\PaginatedList;
use Becklyn\RadBundle\Helper\Pagination;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * Base model for database accesses
 */
abstract class DoctrineModel
{


    /**
     * @var RegistryInterface
     */
    private $doctrine;


    /**
     * @param RegistryInterface $doctrine
     */
    public function __construct (RegistryInterface $doctrine)
    {
        $this->doctrine = $doctrine;
    }


    /**
     * Returns the repository
     *
     * @return \Doctrine\ORM\EntityRepository
     */
    protected function getRepository ()
    {
        return $this->doctrine->getRepository($this->getFullEntityName());
    }


    /**
     * Returns the entity manager
     *
     * @return \Doctrine\ORM\EntityManager
     */
    protected function getEntityManager ()
    {
        return $this->doctrine->getManager();
    }


    /**
     * Returns, whether it is a valid id
     *
     * @param int $id
     *
     * @return bool
     */
    protected function isId ($id)
    {
        return is_int($id) || ctype_digit($id);
    }


    /**
     * Returns paginated content
     *
     * If the $queryBuilder for entities does include a grouping function, the automatic counting will fail (since
     * sub selects are not fully supported in DQL). You can pass an additional query builder just for the counting query
     * in $countQueryBuilder then (no need to set the select("COUNT(..)"), as this will be done automatically).
     *
     * @param QueryBuilder      $queryBuilder      The query builder to retrieve the entities
     * @param Pagination        $pagination        The pagination object
     * @param QueryBuilder|null $countQueryBuilder The additional query builder, just for the count query
     * @param int|null          $forceCount        if given, forces the count to be this exact value
     *
     * @return PaginatedList
     */
    protected function getPaginatedResults (QueryBuilder $queryBuilder, Pagination $pagination, QueryBuilder $countQueryBuilder = null, $forceCount = null)
    {
        $pagination->setNumberOfItems($this->getTotalNumberOfItems($queryBuilder, $countQueryBuilder, $forceCount));
        $offset = ($pagination->getCurrentPage() - $pagination->getMinPage()) * $pagination->getItemsPerPage();

        if (0 < $pagination->getNumberOfItems())
        {
            $queryBuilder
                ->setFirstResult($offset)
                ->setMaxResults($pagination->getItemsPerPage());

            $list = iterator_to_array(new Paginator($queryBuilder->getQuery()));
        }
        else
        {
            $list = array();
        }

        return new PaginatedList(
            $list,
            $pagination
        );
    }


    /**
     * Returns the number of total items in a query builder query
     *
     * @param QueryBuilder $queryBuilder
     * @param QueryBuilder $countQueryBuilder
     * @param int|null     $forceCount if given, forces the count to be this exact value
     *
     * @return int
     */
    private function getTotalNumberOfItems (QueryBuilder $queryBuilder, QueryBuilder $countQueryBuilder = null, $forceCount = null)
    {
        if (null !== $forceCount)
        {
            return (int) $forceCount;
        }

        $queryBuilder = clone ($countQueryBuilder ?: $queryBuilder);
        $table        = current($queryBuilder->getRootAliases());

        try
        {
            return (int)$queryBuilder->select("COUNT({$table})")
                // reset parts which we don't need
                                     ->resetDQLPart('orderBy')
                                     ->getQuery()
                                     ->getSingleScalarResult();
        }
        catch (NoResultException $e)
        {
            return 0;
        }
    }


    /**
     * Returns the entity name
     *
     * @throws \Exception if the full entity name could not be guessed automatically
     * @return string the entity reference string
     */
    protected function getFullEntityName ()
    {
        $classNameParts = explode("\\", trim(get_class($this), "\\"));

        if (count($classNameParts) !== 4)
        {
            throw new \Exception("Cannot automatically generate entity name");
        }

        $bundle = $classNameParts[0] . $classNameParts[1];
        $entity = str_replace("Model", "", $classNameParts[3]);

        return "{$bundle}:{$entity}";
    }
}
