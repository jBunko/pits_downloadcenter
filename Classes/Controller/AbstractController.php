<?php

namespace PITS\PitsDownloadcenter\Controller;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2015 HOJA <hoja.ma@pitsolutions.com>, PIT Solutions Pvt Ltd
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use PITS\PitsDownloadcenter\Domain\Repository\CategoryRepository;
use PITS\PitsDownloadcenter\Domain\Repository\DownloadRepository;
use PITS\PitsDownloadcenter\Domain\Repository\FiletypeRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

/**
 * AbstractController
 */
abstract class AbstractController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    /**
     * downloadRepository
     *
     * @var DownloadRepository
     */
    protected $downloadRepository = NULL;

    /**
     * fileTypeRepository
     *
     * @var FiletypeRepository
     */
    protected $fileTypeRepository = NULL;

    /**
     * @var array
     */
    protected $extConf = array();

    /**
     * @var string
     */
    protected $isLogin = NULL;

    /**
     * contains the ts settings for the current action
     *
     * @var array
     */
    protected $actionSettings = array();

    /**
     * contains the specific ts settings for the current controller
     *
     * @var array
     */
    protected $controllerSettings = array();

    /**
     * @var PersistenceManager
     */
    protected $persistenceManager = NULL;

    /**
     * @var int
     */
    protected $currentPageUid;

    /**
     * @var string
     */
    protected $encryptionKey;

    /**
     * @var string
     */
    protected $encryptionMethod;

    /**
     * @var string
     */
    protected $initializationVector;

    /**
     * @var string
     * extensionName
     */
    protected $extensionName;

    /**
     * categoryRepository
     *
     * @var CategoryRepository
     */
    protected $categoryRepository = NULL;

    /**
     * storageRepository
     *
     * @var StorageRepository
     */
    protected $storageRepository = NULL;


    /**
     * @var ResourceFactory
     */
    protected $resourceFactory = null;


    /**
     * datetime
     *
     * @var \DateTime
     */
    protected $dateTime = null;

    //JB: get rid of object manager via dependency injection. also inject resourceFactory
    public function __construct(
        DownloadRepository $downloadRepository,
        FiletypeRepository $fileTypeRepository,
        CategoryRepository $categoryRepository,
        PersistenceManager $persistenceManager,
        StorageRepository  $storageRepository,
        ResourceFactory    $resourceFactory
    )
    {
        $this->downloadRepository = $downloadRepository;
        $this->fileTypeRepository = $fileTypeRepository;
        $this->categoryRepository = $categoryRepository;
        $this->persistenceManager = $persistenceManager;
        $this->storageRepository = $storageRepository;
        $this->resourceFactory = $resourceFactory;
    }


    //JB: polyfill function for '$this->request->getBaseUri()', which is deprecated
    protected function getBaseUrl()
    {
        return $GLOBALS['TYPO3_REQUEST']->getAttribute('normalizedParams')->getSiteUrl();
    }

    /**
     * Initializes the controller before invoking an action method.
     *
     * Override this method to solve tasks which all actions have in
     * common.
     *
     * @return void
     */
    protected function initializeAction()
    {
        // Initialize Parent Context
        parent::initializeAction();

        // Basic Configuration Variables
        $this->extensionName = $this->request->getControllerExtensionName();
        $this->dateTime = new \DateTime('now', new \DateTimeZone('Europe/Berlin'));

        //JB: remove deprecated TYPO3-version comparison
        $this->extConf = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][GeneralUtility::camelCaseToLowerCaseUnderscored($this->extensionName)];


        // Encryption Variables
        $this->initializationVector = $this->strToHex("12345678");
        $this->encryptionKey = isset($this->extConf['secure_encryption_key']) ? $this->extConf['secure_encryption_key'] : NULL;
        $this->encryptionMethod = isset($this->extConf['secure_encryption_method']) ? $this->extConf['secure_encryption_method'] : NULL;
        $this->controllerSettings = $this->settings['controllers'][$this->request->getControllerName()];
        $this->actionSettings = $this->controllerSettings['actions'][$this->request->getControllerActionName()];
        $this->currentPageUid = $GLOBALS['TSFE']->id;
        $this->configurationManager = $this->objectManager->get('TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface');


    }

    /**
     * Initializes the view before invoking an action method.
     *
     * Override this method to solve assign variables common for all actions
     * or prepare the view in another way before the action is called.
     *
     * @return void
     *
     * JB: Removing parent call which is empty and not supported in v12.
     * TODO v12: this function will still be called in v12, but this parameter which only stucks there because of
     * TODO v12: the interface definition, should be removed!
     */
    protected function initializeView(\TYPO3\CMS\Extbase\Mvc\View\ViewInterface $view)
    {
        $this->view->assignMultiple(array(
            'controllerSettings' => $this->controllerSettings,
            'actionSettings' => $this->actionSettings,
            'extConf' => $this->extConf,
            'currentPageUid' => $this->currentPageUid
        ));


    }

    /**
     * strToHex
     *
     * @param $string
     * @return string
     */
    public function strToHex($string)
    {
        $hex = '';
        for ($i = 0; $i < strlen($string); $i++) {
            $ord = ord($string[$i]);
            $hexCode = dechex($ord);
            $hex .= substr('0' . $hexCode, -2);
        }
        return strToUpper($hex);
    }

    /**
     * generate subcategories and return category tree
     * this is a recursive function
     *
     * @param $parentID integer
     * @return array
     **/
    public function doGetSubCategories($parentID)
    {
        $categoryTree = array();
        $subCategories = $this->categoryRepository->getSubCategories($parentID);
        $i = 0;
        foreach ($subCategories as $key => $value) {

            //JB: remove deprecated TYPO3-version comparison

            if ($value['l10n_parent'] != 0) {
                $categoryTree[$key]['localized_uid'] = $value['uid'];
                $catID = $value['l10n_parent'];
            } else {
                $catID = $value['uid'];
            }
            $catName = $value['categoryname'];


            $categoryTree[$key]['id'] = $catID;
            $categoryTree[$key]['title'] = $catName;

            $has_sub = NULL;
            $has_sub = $this->categoryRepository->getSubCategoriesCount($catID);
            if ($has_sub) {
                $categoryTree[$key]['input'] = $this->doGetSubCategories($catID);
            }
            $i++;
        }
        return $categoryTree;
    }

    /**
     * function for structured file result
     *
     * @param $fileObject array
     * @param $showPreview boolean
     * @param $allowDirectLinkDownlod boolean
     * @param $basePath string
     * @return array
     **/
    public function generateFiles($fileObject, $showPreview, $allowDirectLinkDownlod, $basePath)
    {
        $response = array();
        $pImgWidth = (!empty($this->settings['previewThumbnailWidth']) && !empty($this->settings['previewThumbnailWidth'])) ? $this->settings['previewThumbnailWidth'] : "150m";
        $pImgHeight = (!empty($this->settings['previewThumbnailHeight']) && !empty($this->settings['previewThumbnailHeight'])) ? $this->settings['previewThumbnailHeight'] : "150m";
        $i = 0;
        $pageUid = $GLOBALS['TSFE']->id;
        foreach ($fileObject as $key => $value) {
            if ($value instanceof \TYPO3\CMS\Core\Resource\File) {
                $key = $i++;
                $fileProperty = $value->getProperties();
                $response[$key]['id'] = (int)$fileProperty['uid'];
                $response[$key]['title'] = (!empty($fileProperty['title'])) ? $fileProperty['title'] : $value->getNameWithoutExtension();
                $response[$key]['size'] = $this->formatBytes($fileProperty['size']);
                $response[$key]['fileType'] = strtoupper($fileProperty['extension']);
                $response[$key]['extension'] = $fileProperty['extension'];
                $response[$key]['dataType'] = ($fileProperty['tx_pitsdownloadcenter_domain_model_download_filetype'] != 0 && $fileProperty['tx_pitsdownloadcenter_domain_model_download_filetype'] != NULL) ? explode(',', $fileProperty['tx_pitsdownloadcenter_domain_model_download_filetype']) : array();
                $response[$key]['categories'] = ($fileProperty['tx_pitsdownloadcenter_domain_model_download_category'] != 0 && $fileProperty['tx_pitsdownloadcenter_domain_model_download_category'] != NULL) ? explode(',', $fileProperty['tx_pitsdownloadcenter_domain_model_download_category']) : array();

                // for preview image
                if ($showPreview) {
                    $processed = $this->processImage($value, $pImgWidth, $pImgHeight);

                    $response[$key]['imageUrl'] =
                        // JB: Point on processed file through server root for file check since "file_exists"
                        // JB: won't work accordingly if you're hosting your TYPO3-Instance on a subfolder of your domain
                        // JB: eg - "www.dont-want-more-ssl-certificates.com/devroot/typo3"
                        ($processed == '' || !file_exists($_SERVER['DOCUMENT_ROOT'] . $processed)) ?
                            $this->getBaseUrl() . 'typo3conf/ext/pits_downloadcenter/Resources/Public/Icons/noimage.jpg' :
                            // JB: On the other hand, the processed file string begins with the subfolder, so the domain needs to be prepended
                            // JB: Not sure if this will break in future updates
                            $GLOBALS['TYPO3_REQUEST']->getAttribute('normalizedParams')->getRequestHost() . $processed;
                }

                // check force download or direct download
                if (!$allowDirectLinkDownlod) {
                    // Changed File Uid to encrypted format
                    $file_uid_secure = base64_encode(
                        openssl_encrypt($fileProperty['uid'],
                            $this->encryptionMethod,
                            $this->encryptionKey,
                            TRUE,
                            $this->initializationVector
                        )
                    );
                    $downloadArguments = [
                        'tx_pitsdownloadcenter_pitsdownloadcenter' => array(
                            'controller' => 'Download',
                            'action' => 'forceDownload',
                            'fileid' => $file_uid_secure
                        )
                    ];
                    $response[$key]['downloadUrl'] = $this->uriBuilder->reset()
                        ->setTargetPageUid($pageUid)
                        ->setCreateAbsoluteUri(TRUE)
                        ->setArguments($downloadArguments)
                        ->build();
                    $response[$key]['url'] = $response[$key]['downloadUrl'];
                    // $this->redirectToUri($response[$key]['url'], 0, 404);
                } else {
                    $baseUrl = $this->getBaseUrl();
                    $response[$key]['url'] = $baseUrl . $value->getPublicUrl();
                    $response[$key]['downloadUrl'] = $baseUrl . $value->getPublicUrl();
                }
            }
        }
        return $response;
    }

    /**
     * processed Images
     * changed the deprecated method to 8LTS function call
     *
     * @param $fileObj \TYPO3\CMS\Core\Resource\File
     * @param $size_w string
     * @param $size_h string
     * @return string
     **/
    public function processImage($fileObj, $size_w, $size_h)
    {
        $cObj = $this->configurationManager->getContentObject();
        $response = $cObj->cObjGetSingle('IMG_RESOURCE', [
                'file.' => [
                    'treatAsReference' => 1,
                    'width' => $size_w,
                    'height' => $size_h
                ],
                'file' => $fileObj->getUid()
            ]
        );



        return $response;
    }

    /**
     * Function Returns FileTypes
     *
     * @param $fileTypesObject \TYPO3\CMS\Extbase\Persistence\Generic\QueryResult
     * @return array
     **/
    public function getFileTypes($fileTypesObject)
    {
        $response = array();
        foreach ($fileTypesObject as $key => $value) {
            $response[$key]['id'] = $value->getUid();
            $response[$key]['title'] = $value->getFiletype();
        }
        return $response;
    }

    /**
     * Function Returns the Page Translations
     *
     * @return array
     **/
    public function getPageTranslations()
    {
        $translatedValue = array();
        $translatedValue['keywordsearch'] = $this->localise("tx_pitsdownloadcenter_domain_model_download.keywordsearch");
        $translatedValue['searchkey'] = $this->localise("tx_pitsdownloadcenter_domain_model_download.searchkey");
        $translatedValue['filterbyarea'] = $this->localise("tx_pitsdownloadcenter_domain_model_download.filterbyarea");
        $translatedValue['categoryplaceholder'] = $this->localise("tx_pitsdownloadcenter_domain_model_download.categoryplaceholder");
        $translatedValue['searchbytype'] = $this->localise("tx_pitsdownloadcenter_domain_model_download.searchbytype");
        $translatedValue['resultsfound'] = $this->localise("tx_pitsdownloadcenter_domain_model_download.resultsfound");
        $translatedValue['tabletitle'] = $this->localise("tx_pitsdownloadcenter_domain_model_download.tabletitle");
        $translatedValue['tablesize'] = $this->localise("tx_pitsdownloadcenter_domain_model_download.tablesize");
        $translatedValue['tabletype'] = $this->localise("tx_pitsdownloadcenter_domain_model_download.tabletype");
        $translatedValue['tabledownload'] = $this->localise("tx_pitsdownloadcenter_domain_model_download.tabledownload");
        return $translatedValue;
    }

    /**
     * Localisation Function
     *
     * @param $id string
     * @return string
     */
    public function localise($id)
    {
        return \TYPO3\CMS\Extbase\Utility\LocalizationUtility::translate($id, 'PitsDownloadcenter');
    }

    /**
     * Size Conversion Function
     *
     * @param $bytes integer
     * @param $precision integer
     * @return integer
     */
    public function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
