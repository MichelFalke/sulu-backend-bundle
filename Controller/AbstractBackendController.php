<?php

namespace L91\Sulu\Bundle\BackendBundle\Controller;

use FOS\RestBundle\Controller\FOSRestController;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactory;
use Sulu\Component\Rest\ListBuilder\ListRepresentation;
use Sulu\Component\Rest\ListBuilder\ListRestHelper;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractBackendController
    extends FOSRestController
    implements SecuredControllerInterface,
    ManagerControllerInterface
{
    /**
     * @param Request $request
     *
     * @return Response
     */
    public function cgetAction(Request $request)
    {
        $locale = $this->getLocale($request);
        $filters = $this->getFilters($request);

        // flatted entities
        if ($request->get('flat') === 'true') {
            /** @var RestHelperInterface $restHelper */
            $restHelper = $this->get('sulu_core.doctrine_rest_helper');;

            /** @var DoctrineListBuilderFactory $factory */
            $factory = $this->get('sulu_core.doctrine_list_builder_factory');

            // get model class
            $listBuilder = $factory->create($this->getModelClass());

            // get fieldDescriptors
            $fieldDescriptors = $this->getFieldDescriptors($locale, $filters);
            $restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);

            // set filters
            foreach ($filters as $key => $value) {
                $listBuilder->where($fieldDescriptors[$key], $value);
            }

            // load entities
            $list = $listBuilder->execute();

            // get pagination
            $total = $listBuilder->count();
            $page = $listBuilder->getCurrentPage();
            $limit = $listBuilder->getLimit();
        } else {
            // load all entities by filters
            $list = $this->getManager()->findAll($locale, $filters);

            // get pagination
            $offset = $this->getOffset($filters);
            $limit = $this->getLimit($filters);
            $total = $offset + count($list);
            $page = $this->getPage($filters);

            // if to avoid db request with less items then the limit
            if (count($list) >= $limit) {
                $total = $this->getManager()->count($locale, $this->getCountFilters($filters));
            }
        }

        // create list representation
        $representation = new ListRepresentation(
            $list,
            'entities',
            $request->get('_route'),
            $request->query->all(),
            $page,
            $limit,
            $total
        );

        return $this->handleView($this->view($representation));
    }

    /**
     * @param Request $request
     * @param $id
     *
     * @return Response
     */
    public function getAction(Request $request, $id)
    {
        $locale = $this->getLocale($request);

        // get entity
        $entity = $this->getManager()->findById($id, $locale);

        return $this->handleView($this->view($entity));
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function postAction(Request $request)
    {
        $locale = $this->getLocale($request);

        // create entity
        $entity = $this->getManager()->save($this->getData($request), $locale);

        return $this->handleView($this->view($entity));
    }

    /**
     * @param Request $request
     * @param $id
     *
     * @return Response
     */
    public function putAction(Request $request, $id)
    {
        $locale = $this->getLocale($request);

        // save entity
        $entity = $this->getManager()->save($this->getData($request), $locale, $id);

        return $this->handleView($this->view($entity));
    }

    /**
     * @param Request $request
     * @param $id
     *
     * @return Response
     */
    public function deleteAction(Request $request, $id)
    {
        $locale = $this->getLocale($request);

        // delete entity
        $entity = $this->getManager()->delete($id, $locale);

        if (!$entity) {
            return new Response('', 204);
        }

        return $this->handleView($this->view($entity));
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    protected function getFieldsAction(Request $request)
    {
        $fieldDescriptors = $this->getFieldDescriptors(
            $this->getLocale($request),
            $this->getFilters($request)
        );

        return $this->handleView($this->view($fieldDescriptors));
    }

    /**
     * {@inheritdoc}
     */
    public function getLocale(Request $request)
    {
        return $request->get('locale', $request->getLocale());
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    protected function getFilters(Request $request)
    {
        $filters = $request->query->all();

        $listRestHelper = new ListRestHelper($request);

        unset($filters['page']);
        unset($filters['limit']);
        unset($filters['fields']);
        unset($filters['search']);
        unset($filters['searchFields']);
        unset($filters['locale']);
        unset($filters['flat']);

        $filters['fields'] = $listRestHelper->getFields();
        $filters['limit'] = (int)$listRestHelper->getLimit();
        $filters['offset'] = (int)$listRestHelper->getOffset();
        $filters['sortColumn'] = $listRestHelper->getSortColumn();
        $filters['sortOrder'] = $listRestHelper->getSortOrder();
        $filters['searchFields'] = $listRestHelper->getSearchFields();
        $filters['searchPattern'] = $listRestHelper->getSearchPattern();

        return $filters;
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    protected function getData(Request $request)
    {
        return $request->request->all();
    }

    /**
     * @param array $filters
     * @return array
     */
    protected function getCountFilters($filters)
    {
        unset($filters['page']);
        unset($filters['offset']);
        unset($filters['limit']);

        return $filters;
    }

    /**
     * @param array $filters
     *
     * @return int
     */
    protected function getLimit($filters)
    {
        if (!isset($filters['limit'])) {
            return 10;
        }

        return $filters['limit'];
    }

    /**
     * @param array $filters
     *
     * @return int
     */
    protected function getOffset($filters)
    {
        if (!isset($filters['offset'])) {
            return 0;
        }

        return $filters['offset'];
    }

    /**
     * @param array $filters
     *
     * @return int
     */
    protected function getPage($filters)
    {
        if (!isset($filters['page'])) {
            if (isset($filters['limit']) && isset($filters['offset'])) {
                return floor($filters['offset'] / $filters['limit']) + 1;
            }

            return 1;
        }

        return $filters['page'];
    }
}
