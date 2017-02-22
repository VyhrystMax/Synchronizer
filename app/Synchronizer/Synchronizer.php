<?php
/**
 * Created by PhpStorm.
 * User: maks
 * Date: 17.01.17
 * Time: 16:46
 */

namespace Synchronizer;

use Simplon;
use Synchronizer\Classes\AdWordsAPI;
use Synchronizer\Classes\Database;
use Synchronizer\Classes\DataMapper;
use Synchronizer\Classes\DataProcessor;
use Synchronizer\Classes\Log;

class Synchronizer
{
    use Log;

    private $adWords;

    private $database;

    private $mapper;

    private $processor;

    public function __construct(AdWordsAPI $adWords, Database $database, DataMapper $mapper, DataProcessor $processor)
    {
        $this->adWords   = $adWords;
        $this->database  = $database;
        $this->mapper    = $mapper;
        $this->processor = $processor;
    }

    public function synchronize()
    {
        $this->createAdGroups();
        $this->doUpdate();
        $this->doRemove();
    }

    private function createAdGroups()
    {
        $rowsTocreate = $this->database->getAdGroupsForCreate();

        if ( ! is_array($rowsTocreate) || empty($rowsTocreate)) {
            return false;
        }
        $mapped = $this->mapper->mapGroupsToCreate($rowsTocreate);

        $result = $this->adWords->createAdGroups($mapped);

        $toDB = $this->processor->extractAdGroupsAfterCreate($result);

        if ($toDB === null) {
            return null;
        }

        $this->database->setAdGroupsToDB($toDB);
    }

    private function doUpdate()
    {
        $rowsToUpdate = $this->database->getData();

        if ( ! is_array($rowsToUpdate) || empty($rowsToUpdate)) {
            return false;
        }

        $toUpdate           = $this->mapper->map($rowsToUpdate);
        $productionKeywords =
            $this->adWords->getKeywords(array_keys($toUpdate));

        $toUpdate['ProductionKeywords'] =
            $productionKeywords && ! empty($productionKeywords) ?
                $productionKeywords : [];

        $toUpdate = $this->processor->mergeProductionKeywords($toUpdate);
        $toUpdate = $this->processor->processKeywords($toUpdate);
        $response = $this->adWords->operate($toUpdate);
        $result   = $this->processor->processResponse($response);

        if (count($result) === count($toUpdate)) {
            $this->updateDBafterSync($toUpdate, $result);
        }
    }

    private function updateDBafterSync($toUpdate, $result)
    {
        foreach ($toUpdate as $id => $item) {
            if (array_key_exists('TextAdToCreate', $item) &&
                ! empty($item['TextAdToCreate'])
            ) {
                foreach ($item['TextAdToCreate'] as $key => $ad) {
                    $dbId = $ad['id'];
                    $adId = $result[$id]['TextAdToCreate'][$key];

                    $this->database->setAdId($dbId, $adId);
                }
            }
            $this->database->update($id);
        }
    }

    private function doRemove()
    {
        $rowsToDelete = $this->database->getDataToRemove();

        if ( ! is_array($rowsToDelete) || empty($rowsToDelete)) {
            return false;
        }

        $toRemove = $this->mapper->mapToRemove($rowsToDelete);

        $removed = $this->adWords->updateAdGroups($toRemove['ToRemove']);
        $result  = $this->processor->processResponseDelete($removed);
        $this->removeFromDB($result);
    }

    private function removeFromDB($result)
    {
        foreach ($result as $id) {
            $this->database->remove($id);
        }
    }

}