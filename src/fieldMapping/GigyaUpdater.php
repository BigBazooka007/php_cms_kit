<?php

/**
 * Created by PhpStorm.
 * User: Yaniv Aran-Shamir
 * Date: 5/26/16
 * Time: 2:05 PM
 */


namespace Gigya\CmsStarterKit\fieldMapping;

use Gigya\CmsStarterKit\GigyaApiHelper;

abstract class GigyaUpdater
{
    /**
     * @var array
     */
    private $cmsMappings;
    /**
     * @var array
     */
    private $cmsArray;
    /**
     * @var string
     */
    private $gigyaUid;
    /**
     * @var bool
     */
    private $mapped;
    /**
     * @var string
     */
    private $path;
    /**
     * @var array
     */
    private $gigyaArray;
    /**
     * @var GigyaApiHelper
     */
    private $apiHelper;

    /**
     * GigyaUpdater constructor.
	 *
	 * @param $cmsValuesArray
	 * @param $gigyaUid
	 * @param $path
	 * @param $apiHelper
     */
    public function __construct($cmsValuesArray, $gigyaUid, $path, $apiHelper)
    {
        $this->cmsArray = $cmsValuesArray;
        $this->gigyaUid = $gigyaUid;
        $this->path     = (string) $path;
        $this->mapped   = ! empty($this->path);
        $this->apiHelper = $apiHelper;
    }

    public function updateGigya()
    {
        $this->retrieveFieldMappings();
        $this->callCmsHook();
        $this->gigyaArray = $this->createGigyaArray();
        $this->callSetAccountInfo();
    }

    /**
     * @return boolean
     */
    public function isMapped()
    {
        return $this->mapped;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param string $path
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * @return mixed
     */
    public function getCmsArray()
    {
        return $this->cmsArray;
    }

    /**
     * @param mixed $cmsArray
     */
    public function setCmsArray($cmsArray)
    {
        $this->cmsArray = $cmsArray;
    }

    /**
     * @return mixed
     */
    public function getGigyaArray()
    {
        return $this->gigyaArray;
    }

    /**
     * @param mixed $gigyaArray
     */
    public function setGigyaArray($gigyaArray)
    {
        $this->gigyaArray = $gigyaArray;
    }

    /**
     * A function that calls a cms hook for example in magento 1
     * Mage::dispatchEvent("pre_sync_to_gigya", array("updater" => $this));
     */
    abstract protected function callCmsHook();

    /**
     * Puts the field mapping configuration in cache.
     * on error throws FieldMappingException
     *
     * @param Conf $mappingConf
     *
     * @throws FieldMappingException
     */
    abstract protected function setMappingCache($mappingConf);

    /**
     * Retrieves the field mapping object from cache.
     * if no mapping is found or there is an error returns false.
     *
     * @return mixed
     */
    abstract protected function getMappingFromCache();

    protected function retrieveFieldMappings()
    {

        $conf = $this->getMappingFromCache();
        if (false === $conf) {
            $mappingJson = file_get_contents($this->path);
            if (false === $mappingJson) {
                $err     = error_get_last();
                $message = "Could not retrieve field mapping configuration file. message was:" . $err['message'];
                throw new \Exception("$message");
            }
            $conf = new Conf($mappingJson);
            $this->setMappingCache($conf);
        }
        $this->cmsMappings = $conf->getCmsKeyed();
    }

    protected function createGigyaArray()
    {
        $gigyaArray = array();
        foreach ($this->cmsArray as $key => $value) {
            /** @var ConfItem $conf */
            $confs = $this->cmsMappings[$key];
            foreach ($confs as $conf) {
                $value       = $this->castVal($value, $conf);
                if(!is_null($value)) {
                  $this->assignArrayByPath($gigyaArray, $conf->getGigyaName(), $value);
                }
            }
        }

        return $gigyaArray;
    }

    protected function callSetAccountInfo()
    {
        $this->apiHelper->updateGigyaAccount($this->gigyaUid, $this->gigyaArray['profile'], $this->gigyaArray['data']);
    }

    /**
     * @param mixed $val
     * @param ConfItem $conf
     *
     * @return mixed $val;
     */

    private function castVal($val, $conf)
    {
        switch ($conf->getGigyaType()) {
            case "string":
                return (string) $val;
                break;
            case "long";
            case "int":
                return (int) $val;
                break;
			case "boolean":
            case "bool":
                if (is_string($val)) {
                    $val = strtolower($val);
                }
                return filter_var($val, FILTER_VALIDATE_BOOLEAN);
                break;
            default:
                return $val;
                break;
        }
    }

    private function assignArrayByPath(&$arr, $path, $value, $separator = '.')
    {
        $keys = explode($separator, $path);

        foreach ($keys as $key)
        {
        	if (!array_key_exists($key, $arr))
        		$arr[$key] = array();

            $arr = &$arr[$key];
        }

        $arr = $value;
    }

}