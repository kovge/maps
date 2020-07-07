<?php
/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2019
 */

namespace OCA\Maps\Controller;

use League\Flysystem\FileNotFoundException;
use OCP\Files\NotFoundException;
use OCP\App\IAppManager;
use OCP\IServerContainer;

use OCP\IURLGenerator;
use OCP\IConfig;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use PhpParser\JsonDecoder;

class UtilsController extends Controller {


    private $userId;
    private $config;
    private $userfolder;
    private $dbtype;

    public function __construct($AppName,
                                IServerContainer $serverContainer,
                                IRequest $request,
                                IConfig $config,
                                IAppManager $appManager,
                                $UserId){
        parent::__construct($AppName, $request);
        $this->userId = $UserId;
        if ($UserId !== '' and $UserId !== null and $serverContainer !== null){
            $this->userfolder = $serverContainer->getUserFolder($UserId);
        }
        // IConfig object
        $this->config = $config;
    }

    /**
     * Delete user options
     * @NoAdminRequired
     */
    public function deleteOptionsValues() {
        $keys = $this->config->getUserKeys($this->userId, 'maps');
        foreach ($keys as $key) {
            $this->config->deleteUserValue($this->userId, 'maps', $key);
        }
        return new DataResponse(['done'=>1]);
    }

    /**
     * Save options values to the DB for current user
     * @NoAdminRequired
     */
    public function saveOptionValue($options, $myMapId=null) {
        if( is_null($myMapId) || $myMapId==="") {
            foreach ($options as $key => $value) {
                $this->config->setUserValue($this->userId, 'maps', $key, $value);
            }
        } else {
            $folders = $this->userfolder->getById($myMapId);
            $folder = array_shift($folders);
            try {
                $file=$folder->get(".maps");
            } catch (NotFoundException $e) {
                $file=$folder->newFile(".maps", $content = "{}");
            }
            $ov = json_decode($file->getContent(),true, 512);
            foreach ($options as $key => $value) {
                $ov[$key] = $value;
            }
            $file->putContent(json_encode($ov, JSON_PRETTY_PRINT));

        }
        return new DataResponse(['done'=>1]);
    }

    /**
     * get options values from the config for current user
     * @NoAdminRequired
     */
    public function getOptionsValues($myMapId=null) {
        $ov = array();

        if( is_null($myMapId) || $myMapId==="") {
            // get all user values
            $keys = $this->config->getUserKeys($this->userId, 'maps');
            foreach ($keys as $key) {
                $value = $this->config->getUserValue($this->userId, 'maps', $key);
                $ov[$key] = $value;
            }
        } else {
            $folders = $this->userfolder->getById($myMapId);
            $folder = array_shift($folders);
            try {
                $file=$folder->get(".maps");
            } catch (NotFoundException $e) {
                $file=$folder->newFile(".maps", $content = "{}");
            }
            $ov = json_decode($file->getContent(),true, 512);
        }

        // get routing-specific admin settings values
        $settingsKeys = [
            'osrmCarURL',
            'osrmBikeURL',
            'osrmFootURL',
            'osrmDEMO',
            'graphhopperAPIKEY',
            'mapboxAPIKEY',
            'graphhopperURL'
        ];
        foreach ($settingsKeys as $k) {
            $v = $this->config->getAppValue('maps', $k);
            $ov[$k] = $v;
        }
        return new DataResponse(['values'=>$ov]);
    }

    /**
     * set routing settings
     */
    public function setRoutingSettings($values) {
        $acceptedKeys = [
            'osrmCarURL',
            'osrmBikeURL',
            'osrmFootURL',
            'osrmDEMO',
            'graphhopperAPIKEY',
            'mapboxAPIKEY',
            'graphhopperURL'
        ];
        foreach ($values as $k=>$v) {
            if (in_array($k, $acceptedKeys)) {
                $this->config->setAppValue('maps', $k, $v);
            }
        }
        $response = new DataResponse('DONE');
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

}
