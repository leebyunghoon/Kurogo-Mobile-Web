<?php

includePackage('Maps');

class MapAPIModule extends APIModule
{
    protected $id = 'map';
    protected $feeds = null;
    protected $vmin = 1;
    protected $vmax = 1;

    protected $feedGroup = null;
    protected $feedGroups = null;
    protected $numGroups = 1;
    
    // functions duped from MapWebModule

    private function getDataController($categoryPath, &$listItemPath) {
        if (!$this->feeds)
            $this->feeds = $this->loadFeedData();

        if ($categoryPath === NULL) {
            return MapDataController::factory('MapDataController', array(
                'JS_MAP_CLASS' => 'GoogleJSMap',
                'DEFAULT_ZOOM_LEVEL' => $this->getModuleVar('DEFAULT_ZOOM_LEVEL', 10)
                ));

        } else {
            $listItemPath = $categoryPath;
            if ($this->numGroups > 0) {
                if (count($categoryPath) < 2) {
                    $path = implode(MAP_CATEGORY_DELIMITER, $categoryPath);
                    throw new Exception("invalid category path $path for multiple feed groups");
                }
                $feedIndex = array_shift($listItemPath).MAP_CATEGORY_DELIMITER.array_shift($listItemPath);
            } else {
                $feedIndex = array_shift($listItemPath);
            }
            $feedData = $this->feeds[$feedIndex];
            $controller = MapDataController::factory($feedData['CONTROLLER_CLASS'], $feedData);
            $controller->setCategory($feedIndex);
            $controller->setDebugMode($this->getSiteVar('DATA_DEBUG'));
            return $controller;
        }
    }

    protected function getFeedGroups() {
        $config = $this->getConfig($this->configModule, 'feedgroups');
        return $config->getSectionVars();
    }

    protected function loadFeedData() {
        $data = array();
        $feedConfigFile = NULL;

        if ($this->feedGroup !== NULL) {
            if ($this->numGroups === 1) {
                $this->feedGroup = key($this->feedGroups);
            }
        }

        if ($this->numGroups === 0) {
            $feedConfigFile = $this->getConfig($this->configModule, 'feeds');
            $data = $feedConfigFile->getSectionVars();

        } elseif ($this->feedGroup !== NULL) {
            $configName = "feeds-{$this->feedGroup}";
            $feedConfigFile = $this->getConfig($this->configModule, $configName);
            foreach ($feedConfigFile->getSectionVars() as $id => $feedData) {
                $data[$this->feedGroup.MAP_CATEGORY_DELIMITER.$id] = $feedData;;
            }

        } else {
            foreach ($this->feedGroups as $groupID => $groupData) {
                $aConfig = $this->getConfig($this->configModule, "feeds-$groupID");
                foreach ($aConfig->getSectionVars() as $id => $feedData) {
                    $data[$groupID.MAP_CATEGORY_DELIMITER.$id] = $feedData;
                }
            }
        }

        return $data;
    }

    private function getCategoriesAsArray() {
        $category = $this->getArg('category', null);
        if ($category !== null) {
            return explode(MAP_CATEGORY_DELIMITER, $category);
        }
        return array();
    }

    // end of functions duped from mapwebmodule

    public function initializeForCommand() {

        $this->feedGroups = $this->getFeedGroups();
        $this->numGroups = count($this->feedGroups);

        switch ($this->command) {
            case 'groups':

                $response = array(
                    'total' => $this->numGroups,
                    'returned' => $this->numGroups,
                    'displayField' => 'title',
                    'results' => $this->feedGroups,
                    );

                $this->setResponse($response);
                $this->setResponseVersion(1);
            
                break;
            case 'categories':
                $this->feedGroup = $this->getArg('group', null);
                if ($this->feedGroup !== NULL && !isset($this->feedGroups[$this->feedGroup])) {
                    $this->feedGroup = NULL;
                }

                $categories = array();
                $this->feeds = $this->loadFeedData();
                foreach ($this->feeds as $id => $feedData) {
                    if (isset($feedData['HIDDEN']) && $feedData['HIDDEN']) continue;
                    $controller = MapDataController::factory($feedData['CONTROLLER_CLASS'], $feedData);
                    $controller->setCategory($id);
                    $category = array(
                        'id' => $controller->getCategory(),
                        'title' => $controller->getTitle(),
                        );
                    $category['subcategories'] = $controller->getAllCategoryNodes();
                    $categories[] = $category;
                }

                $this->setResponse($categories);
                $this->setResponseVersion(1);
            
                break;
            case 'places':
                $categoryPath = $this->getCategoriesAsArray();
                if ($categoryPath) {
                    $dataController = $this->getDataController($categoryPath, $listItemPath);
                    $listItems = $dataController->getListItems($listItemPath);
                    $places = array();
                    foreach ($listItems as $listItem) {
                        if ($listItem instanceof MapFeature) {
                            $places[] = arrayFromMapFeature($listItem);
                        }
                    }
                
                    $response = array(
                        'total' => count($places),
                        'returned' => count($places),
                        'displayField' => 'title',
                        'results' => $places,
                        );
                
                    $this->setResponse($response);
                    $this->setResponseVersion(1);
                }
                break;
            case 'search':
                $searchTerms = $this->getArg('q');
                if ($searchTerms) {
                    $this->feedGroup = $this->getArg('group', null);
                    if ($this->feedGroup !== NULL && !isset($this->feedGroups[$this->feedGroup])) {
                        $this->feedGroup = NULL;
                    }

                    $mapSearchClass = $GLOBALS['siteConfig']->getVar('MAP_SEARCH_CLASS');
                    if (!$this->feeds)
                        $this->feeds = $this->loadFeedData();
                    $mapSearch = new $mapSearchClass($this->feeds);
        
                    $searchResults = $mapSearch->searchCampusMap($searchTerms);
        
                    $places = array();
                    foreach ($searchResults as $result) {
                        $places[] = arrayFromMapFeature($result);
                    }

                    $response = array(
                        'total' => count($places),
                        'returned' => count($places),
                        'displayField' => 'title',
                        'results' => $places,
                        );
                
                    $this->setResponse($response);
                    $this->setResponseVersion(1);
                }

                break;

            // ajax calls
            case 'staticImageURL':
                $baseURL = $this->getArg('baseURL');
                $mapClass = $this->getArg('mapClass');
                $mapController = MapImageController::factory($mapClass, $baseURL);
                
                $projection = $this->getArg('projection');
                if ($projection) {
                    $mapController->setMapProjection($projection);
                }
                
                $width = $this->getArg('width');
                if ($width) {
                    $mapController->setImageWidth($width);
                }

                $height = $this->getArg('height');
                if ($height) {
                    $mapController->setImageHeight($height);
                }

                $bbox = $this->getArg('bbox', null);
                $lat = $this->getArg('lat');
                $lon = $this->getArg('lon');
                $zoom = $this->getArg('zoom');

                if ($bbox) {
                    $mapController->setBoundingBox($bbox);

                } else if ($lat && $lon && $zoom !== null) {
                    $mapController->setZoomLevel($zoom);
                    $mapController->setCenter(array('lat' => $lat, 'lon' => $lon));
                }
                
                $url = $mapController->getImageURL();
                
                $this->setResponse($url);
                $this->setResponseVersion(1);
            
                break;
            default:
                $this->invalidCommand();
                break;
        }
    }
}