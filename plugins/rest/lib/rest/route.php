<?php

class rex_yform_rest_route
{
    public $config = [];

    public static $requestMethods = ['get', 'post', 'delete'];

    public function __construct($config)
    {
        $this->config = $config;
        $this->config['table'] = $config['type']::table();
        $this->type = $config['type'];
        $this->table = $this->config['table'];
        $this->query = $this->config['query'];
        $this->instance = $this->table->createDataset();
        $this->path = ('/' == substr($this->config['path'], -1)) ? substr($this->config['path'], 0, -1) : $this->config['path'];
    }

    // kreatif: method to verify the json is valid
    public static function getRequestData()
    {
        $jsonData = [];
        $content  = trim(file_get_contents('php://input'));

        if ($content != '') {
            $jsonData = @json_decode($content, true);

            if (json_last_error() != JSON_ERROR_NONE) {
                \rex_yform_rest::sendError(400, 'json-is-not-valid');
            }
        }
        return $jsonData;
    }

    // kreatif: $paths added
    public function hasAuth($paths)
    {
        if (isset($this->config['auth'])) {
            if (is_callable($this->config['auth'])) {
                return call_user_func($this->config['auth'], $this, $paths);
            }
            return $this->config['auth'];
        }
        return true;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function handleRequest($paths, $get, $returnResponse = false)
    {
        // kreatif: extension point added
        $get = \rex_extension::registerPoint(new \rex_extension_point('YFORM_REST_PARSE_GET', $get, [
            'route' => $this,
            'paths' => $paths,
        ]));

        if (!isset($this->config['table'])) {
            \rex_yform_rest::sendError(400, 'table-not-available');
        }

        $requestMethod = $this->getRequestMethod();
        if (in_array($requestMethod, self::$requestMethods) && !isset($this->config[$requestMethod])) {
            \rex_yform_rest::sendError(400, 'request-method-not-available');
        }

        /** @var \rex_yform_manager_table $table */
        $table = $this->config['table'];

        /** @var rex_yform_manager_query $query */
        // kreatif: extension point added
        $query = \rex_extension::registerPoint(new \rex_extension_point('YFORM_REST_GET_QUERY', $this->config['query'], [
            'route' => $this,
        ]));

        switch ($requestMethod) {
            case 'get':

                $instance = $table->createDataset();
                $fields = $this->getFields('get', $instance);

                // kreatif: EP added
                \rex_extension::registerPoint(new \rex_extension_point('YFORM_REST_PRE_INSTANCE_GET', $table, [
                    'route' => $this,
                    'paths' => $paths,
                ]));

                /** @var rex_yform_manager_dataset $instance */
                $instance = null;
                /** @var rex_yform_manager_collection $instance */
                $instances = null;
                $attribute = null;
                $baseInstances = false;

                if (0 == count($paths)) {
                    $baseInstances = true;

                    // Base Instances with filter and order
                    $query = $this->getFilterQuery($query, $fields, $get);
                    $itemsAll = $query->count();

                    $per_page = (isset($get['per_page'])) ? (int) $get['per_page'] : (int) $table->getListAmount();
                    $per_page = ($per_page < 0) ? $per_page = $table->getListAmount() : $per_page;

                    $currentPage = (isset($get['page'])) ? (int) $get['page'] : 1;
                    $currentPage = ($currentPage < 0) ? 1 : $currentPage;

                    $query->limit(($currentPage - 1) * $per_page, $per_page);

                    $order = [];
                    if (isset($get['order']) && is_array($get['order'])) {
                        foreach ($get['order'] as $orderName => $orderValue) {
                            if (array_key_exists($orderName, $fields)) {
                                $orderValue = ('desc' != $orderValue) ? 'asc' : 'desc';
                                $order[$orderName] = $orderValue;
                                $query->orderBy($orderName, $orderValue);
                            }
                        }
                        if (0 == count($order)) {
                            $order[$table->getSortFieldName()] = $table->getSortOrderName();
                        }
                        $query->orderBy($table->getSortFieldName(), $table->getSortOrderName());
                    }

                    $instances = $query->find();
                }

                /*
                 * Beispiele:
                /77
                /77/name
                /77/autos
                /77/quatsch
                /77/autos/32
                /77/autos/32/name
                /77/autos/32/prio
                /77/autos/32/years
                /77/autos/32/years/40
                /77/autos/32/years/40/name
                */

                foreach ($paths as $path) {
                    if ($instances) {
                        $id = $path;
                        foreach ($instances as $i_instance) {
                            if ($i_instance->getId() == $id) {
                                $instance = $i_instance;
                            }
                        }

                        if (!$instance) {
                            \rex_yform_rest::sendError(400, 'dataset-not-found', ['paths' => $paths, 'table' => $instances->getTable()->getTableName()]);
                        }
                        $attribute = null;
                        $instances = null;
                    } elseif (!$instance) {
                        $id = $path;
                        if (!$instance) {
                            $id_column = 'id';
                            if ('' != $query->getTableAlias()) {
                                $id_column = $query->getTableAlias().'.id';
                            }

                            $query
                                ->where($id_column, $id);
                            $instance = $query->findOne();

                            if (!$instance) {
                                // kreatif: EP added
                                $instance = \rex_extension::registerPoint(new \rex_extension_point('YFORM_REST_DATASET_NOT_FOUND', $instance, [
                                    'route' => $this,
                                    'paths' => $paths,
                                ]));
                            }
                            if (!$instance) {
                                \rex_yform_rest::sendError(400, 'dataset-not-found', ['paths' => $paths, 'table' => $query->getTable()->getTableName()]);
                            }
                            // kreatif: instance detail view marking
                            $instance->isDetail = true;

                            $fields = $this->getFields('get', $instance);
                        }
                        $attribute = null;
                    } else {
                        $attribute = $path;

                        // kreatif: EP added
                        $searchAttribute = \rex_extension::registerPoint(new \rex_extension_point('YFORM_REST_HANDLE_PATH', true, [
                            'path' => $path,
                            'instance' => $instance
                        ]));

                        // kreatif: check if attribute verifyin is needed or just proceed
                        if ($searchAttribute) {
                            if (!array_key_exists($attribute, $fields)) {
                                \rex_yform_rest::sendError(400, 'attribute-not-found', ['paths' => $paths, 'table' => $table->getTableName()]);
                            }

                            if ('be_manager_relation' == $fields[$attribute]->getTypeName()) {
                                $instances = $instance->getRelatedCollection($attribute);
                                if (count($instances) > 0) {
                                    $instance = $instances->current();
                                }
                                $fields = self::getFields('get', $instance);
                                $instance = null;
                            }
                        }
                    }
                }

                if ($instances) {
                    $data = [];
                    foreach ($instances as $instance) {
                        $data[] = $this->getInstanceData(
                            $instance,
                            array_merge($paths, [$instance->getId()])
                        );
                    }

                    if ($baseInstances) {
                        $links = [];
                        $meta = [];

                        $linkParams = \rex_extension::registerPoint(new \rex_extension_point('YFORM_REST_LINKPARAMS', [
                            'page' => $currentPage,
                            'per_page' => $per_page,
                            'order' => $order,
                        ], [
                            'route' => $this
                        ]));

                        if (isset($get['filter']) && is_array($get['filter'])) {
                            $linkParams['filter'] = $get['filter'];
                            $meta['filter'] = $get['filter'];
                        }

                        if ($order) {
                            $meta['order'] = $order;
                        }

                        $meta['totalItems'] = (int) $itemsAll;
                        $meta['currentItems'] = count($instances);
                        $meta['itemsPerPage'] = $per_page;
                        $meta['currentPage'] = $currentPage;
                        $meta['totalPages'] = ceil((int) $itemsAll / $per_page); // kreatif: info added

                        $links['self'] = \rex_yform_rest::getLinkByPath($this, $linkParams);
                        $links['first'] = \rex_yform_rest::getLinkByPath($this, array_merge(
                            $linkParams,
                            ['page' => 1]
                        ));
                        if (($currentPage - 1) > 0) {
                            $links['prev'] = \rex_yform_rest::getLinkByPath($this, array_merge(
                                $linkParams,
                                ['page' => ($currentPage - 1)]
                            ));
                        }
                        if (($currentPage * $per_page) < $itemsAll) {
                            $links['next'] = \rex_yform_rest::getLinkByPath($this, array_merge(
                                $linkParams,
                                ['page' => ($currentPage + 1)]
                            ));
                        }

                        $data = [
                            'links' => $links,
                            'meta' => $meta,
                            'data' => $data,
                        ];
                    }
                } elseif ($instance) {
                    if ($attribute) {
                        $data = $instance->getValue($attribute, true);
                    } else {
                        $data = $this->getInstanceData(
                            $instance,
                            array_merge($paths)
                        );
                    }
                }

                $data = \rex_extension::registerPoint(new \rex_extension_point('YFORM_REST_BEFORE_SEND', $data, [
                    'query' => $query,
                ]));

                if ($returnResponse) {
                    return $data;
                } else {
                    \rex_yform_rest::sendContent(200, $data);
                }

                break;

            // ----- /END GET

            case 'post':

                $instance = $table->createDataset();

                $errors = [];
                $fields = $this->getFields('post', $instance);

                // kreatif: use self::getRequestData() to verify json validity
                $in = \rex_extension::registerPoint(new \rex_extension_point('YFORM_REST_POST_DATA',self::getRequestData(), [
                    'instance' => $instance,
                    'route' => $this,
                    'paths' => $paths
                ]));

                $data = (array) @$in['data']['attributes'];
                $type = (string) @$in['data']['type'];

                if (self::getTypeFromInstance($instance) != $type) {
                    \rex_yform_rest::sendError(400, 'post-data-type-different');
                }

                if (0 == count($data)) {
                    \rex_yform_rest::sendError(400, 'post-data-attributes-empty');
                } else {
                    $dataset = null;
                    if (isset($in['id'])) {
                        $dataset = $table->getDataset($in['id']);
                        $OKStatus = 200; // update
                    }

                    if (!$dataset) {
                        if (isset($in['id'])) {
                            $dataset = $table->getRawDataset($in['id']);
                        }
                        if (!$dataset) {
                            $dataset = $table->createDataset();
                        }
                        $OKStatus = 201; // created
                    }

                    foreach ($data as $inKey => $inValue) {
                        if (array_key_exists($inKey, $fields) && 'be_manager_relation' != $fields[$inKey]->getTypeName()) {
                            $dataset->setValue($inKey, $inValue);
                        }
                    }

                    $relations = (array) @$in['data']['relationships'];

                    foreach ($relations as $inKey => $inValue) {
                        if (array_key_exists($inKey, $fields) && 'be_manager_relation' == $fields[$inKey]->getTypeName()) {
                            $relation_data = @$inValue['data'];
                            if (!is_array($relation_data)) {
                                $relation_data = [$relation_data];
                            }

                            $value = [];
                            foreach ($relation_data as $relation_date) {
                                $relation_date_type = $relation_date['type'] ?? rex_yform_manager_dataset::getModelClass($fields[$inKey]->getElement('table'));
                                // TODO: übergebenen Type mit Klasse der Relation prüfen

                                // kreatif: recursive relationships handling added
                                if ($relation_date_type && isset($relation_date['attributes'])) {
                                    $_inst = $relation_date_type::create();
                                    $_route = \rex_yform_rest::getRouteByInstance($_inst);

                                    $relation_date['type'] = $relation_date_type;

                                    \rex_extension::register('YFORM_REST_POST_DATA', static function (\rex_extension_point $ep) {
                                        $_data = $ep->getParam('relationData');
                                        if($_data && get_class($ep->getParam('instance')) == $_data['type']) {
                                            $ep->setSubject(['data' => $_data]);
                                        }
                                    }, \rex_extension::EARLY, ['relationData' => $relation_date]);

                                    $relation_date = $_route->handleRequest([], $_GET, true);
                                }
                                //kreatif: end

                                $relation_date_id = (int) @$relation_date['id'];
                                if ($relation_date_id > 0) {
                                    $value[] = $relation_date_id;
                                }
                            }
                            // TODO: entsprechend des relationstypes reagieren
                            $dataset->setValue($inKey, implode(',', $value));
                        }
                    }

                    // TODO:
                    // komplettes Dataset zurückgeben, nach https://jsonapi.org/

                    if ($dataset->save()) {
                        // kreatif: EP added
                        \rex_extension::registerPoint(new \rex_extension_point('YFORM_REST_SAVED', $dataset, [
                            'status' => $OKStatus,
                        ]));

                        if ($returnResponse) {
                            return ['id' => $dataset->getId()];
                        } else {
                            \rex_yform_rest::sendContent($OKStatus, ['id' => $dataset->getId()]);
                        }
                    } else {
                        foreach ($dataset->getMessages() as $message_key => $message) {
                            $errors[] = \rex_i18n::translate($message);
                        }
                        \rex_yform_rest::sendError(400, 'errors-set', $errors);
                    }
                }

                break;

            case 'delete':

                $instance = $table->createDataset();

                $fields = $this->getFields('delete', $instance);

                $queryClone = clone $query;
                $query = $this->getFilterQuery($query, $fields, $get);

                if ($queryClone === $query && isset($get['filter'])) {
                    \rex_yform_rest::sendError(404, 'no-available-filter-set');
                } elseif ($queryClone != $query) {
                    // filter set -> true
                } elseif (0 == count($paths)) {
                    \rex_yform_rest::sendError(404, 'no-id-set');
                } else {
                    $id = $paths[0];
                    $query->where('id', $id);
                }

                $data = $query->find();

                $content = [];
                $content['all'] = count($data);
                $content['deleted'] = 0;
                $content['failed'] = 0;

                foreach ($data as $i_data) {
                    $date = [];
                    $date['id'] = $i_data->getId();
                    if ($i_data->delete()) {
                        ++$content['deleted'];
                    } else {
                        ++$content['failed'];
                    }
                    $content['dataset'][] = $date;
                }

                if ($returnResponse) {
                    return $content;
                } else {
                    \rex_yform_rest::sendContent(200, $content);
                }

                break;

            default:
                $availableMethods = [];
                foreach (self::$requestMethods as $method) {
                    if (isset($this->config[$method])) {
                        $availableMethods[] = strtoupper($method);
                    }
                }
                \rex_yform_rest::sendError(404, 'no-request-method-found', ['please only use: ' . implode(',', $availableMethods)]);
        }
    }

    public function getFields($type = 'get', $instance = null)
    {
        // kreatif: EP added
        \rex_extension::registerPoint(new \rex_extension_point('YFORM_REST_GET_FIELDS', $this, [
            'type' => $type,
            'instance' => $instance
        ]));

        $class = $this->getTypeFromInstance($instance);

        $returnFields = ['id' => new \rex_yform_manager_field([
            'name' => 'id',
            'type_id' => 'value',
            'type_name' => 'integer',
        ])];

        if (!isset($this->config[$type]['fields'][$class])) {
            return $returnFields;
        }

        /** @var \rex_yform_manager_table $table */
        $table = $class::table();

        if (!is_object($table)) {
            throw  new rex_api_exception('Problem with Config: A Table/Class does not exists ');
        }

        $availableFields = $table->getValueFields();

        foreach ($availableFields as $key => $availableField) {
            if ('none' != $availableField->getDatabaseFieldType()) {
                // ALLE Felder erlaubt wenn kein Feld gesetzt ? count($this->config[$type]['fields'][$class]) == 0 ||
                if (isset($this->config[$type]['fields'][$class]) && in_array($key, @$this->config[$type]['fields'][$class], true)) {
                    $returnFields[$key] = $availableField;
                }
            }
        }

        return $returnFields;
    }

    public function getFilterQuery($query, $fields, $get)
    {
        /** @var \rex_yform_manager_query $query */
        $tableAlias = $query->getTableAlias();

        if (isset($get['filter']) && is_array($get['filter'])) {
            foreach ($get['filter'] as $filterKey => $filterValue) {
                foreach ($fields as $fieldName => $field) {
                    /* @var \rex_yform_manager_field $field */

                    if ($fieldName == $filterKey) {
                        if (method_exists('rex_yform_value_' . $field->getTypeName(), 'getSearchFilter')) {
                            try {
                                $rawQuery = $field->object->getSearchFilter([
                                    'value' => $filterValue,
                                    'field' => $field,
                                ]);

                                if ('' != $tableAlias) {
                                    // TODO: fieser hack bisher, da bekannt wie die SearchFilter funktionieren.
                                    $rawQuery = str_replace('`'.$field.'`', '`'.$tableAlias.'`.`'.$field.'`', $rawQuery);
                                }
                            } catch (Error $e) {
                                \rex_yform_rest::sendError(400, 'field-class-not-found', ['field' => $fieldName]);
                                exit;
                            }
                            $query->whereRaw('(' . $rawQuery . ')');
                        } else {
                            $query->where($filterKey, $filterValue);
                        }
                    }
                }
            }
        }
        return \rex_extension::registerPoint(new \rex_extension_point('YFORM_REST_GET_FILTER_QUERY', $query, [
            'route' => $this,
            'fields' => $fields,
            'get' => $get,
        ]));
    }

    public function getInstanceData($instance, $paths, $onlyId = false)
    {
        $links = [];
        $links['self'] = \rex_yform_rest::getLinkByPath($this, [], $paths);

        if ($onlyId) {
            return
                [
                    'id' => $instance->getId(),
                    'type' => $this->getTypeFromInstance($instance),
                    'links' => $links,
                ];
        }
        return
                \rex_extension::registerPoint(new \rex_extension_point('YFORM_REST_GET_INSTANCE_DATA', [
                    // kreatif: (int) casting added
                    'id' => (int)$instance->getId(),
                    'type' => $this->getTypeFromInstance($instance),
                    'attributes' => $this->getInstanceAttributes($instance),
                    'relationships' => $this->getInstanceRelationships($instance),
                    'links' => $links,
                ], [
                    'instance' => $instance,
                    'route' => $this,
                ]));
    }

    public function getInstanceAttributes(\rex_yform_manager_dataset $instance)
    {
        $data = [];

        $fields = $this->getFields('get', $instance);

        foreach ($fields as $fieldName => $field) {
            if ('be_manager_relation' != $field->getTypeName()) {
                // kreatif: fieldname mapping added
                $fieldName = $instance->getRestFieldname($fieldName);
                // kreatif: add data type check
                $value = $instance->getValue($field->getName());

                if ($field->getElement('type_name') == 'number') {
                    $value = (float)$value;
                } else if ($field->getElement('db_type') == 'int' || $field->getElement('type_name') == 'integer') {
                    $value = (int)$value;
                }
                $data[$fieldName] = $value;
            }
        }
        return $data;
    }

    public function getInstanceRelationships(\rex_yform_manager_dataset $instance)
    {
        $paths[] = $instance->getId();

        $fields = $this->getFields('get', $instance);

        $return = [];

        /** @var rex_yform_manager_field $field */

        foreach ($fields as $field) {
            if ('be_manager_relation' == $field->getTypeName()) {
                $relationInstances = $instance->getRelatedCollection($field->getName());

                $data = [];
                foreach ($relationInstances as $relationInstance) {
                    $onlyId = false;
                    if ($this->table->getTableName() == $relationInstance->getTableName()) {
                        $onlyId = true;
                    }
                    $data[] = $this->getInstanceData(
                        $relationInstance,
                        array_merge($paths, [$field->getName(), $relationInstance->getId()]),
                        $onlyId
                    );
                }
                $return[$field->getName()] = [
                    'data' => $data,
                ];

                $links = [];
                $links['self'] = \rex_yform_rest::getLinkByPath($this, [], array_merge($paths, [$field->getName()]));

                if (isset($relationInstance)) {
                    $route = \rex_yform_rest::getRouteByInstance($relationInstance);

                    if ($route) {
                        $links['absolute'] = \rex_yform_rest::getLinkByPath($route, []);
                    }
                }

                $return[$field->getName()]['links'] = $links;
            }
        }

        return $return;
    }

    public function getInstanceValue($instance, $key, $attributCall = false)
    {
        return $instance->getValue($key, $attributCall);
    }

    public function getRequestMethod()
    {
        if (isset($_SERVER['X-HTTP-Method-Override'])) {
            return strtolower($_SERVER['X-HTTP-Method-Override']);
        }
        return strtolower($_SERVER['REQUEST_METHOD']);
    }

    public function getTypeFromInstance($instance = null)
    {
        $type = get_class($instance);

        if ('rex_yform_manager_dataset' == $type || 'rex_yform_rest_route' == $instance || !$instance) {
            $type = 'not-defined';
        }
        return $type;
    }
}
