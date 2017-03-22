<?php
/**
 * Created by PhpStorm.
 * User: maks
 * Date: 20.01.17
 * Time: 13:26
 */

namespace Synchronizer\Classes;

use Google\AdsApi\AdWords\v201609\cm\AdGroup;
use Google\AdsApi\AdWords\v201609\cm\AdGroupOperation;
use Google\AdsApi\AdWords\v201609\cm\BiddableAdGroupCriterion;
use Google\AdsApi\AdWords\v201609\cm\Keyword;

/**
 * Class DataProcessor
 * @package Synchronizer\Classes
 */
class DataProcessor
{
    use Log;

    /**
     * @param $data
     *
     * @return mixed
     */
    public function processKeywords($data)
    {
        foreach ($data as $adGroupId => $adGroupData) {
            if (array_key_exists('ProductionKeywords', $adGroupData) &&
                ! empty($adGroupData['ProductionKeywords'])
            ) {
                $prod_kw = $adGroupData['ProductionKeywords'];
            } else {
                $prod_kw = [];
            }

            list(
                $data[$adGroupId]['KeywordsToUpdate'],
                $data[$adGroupId]['KeywordsToCreate'],
                $data[$adGroupId]['KeywordsToDelete']) =
                $this->extractKeywords(
                    $adGroupData['LocalKeywords'],
                    $prod_kw
                );

            unset($data[$adGroupId]['LocalKeywords']);
            unset($data[$adGroupId]['ProductionKeywords']);
        }

        return $data;
    }

    /**
     * @param $data
     *
     * @return mixed
     */
    public function mergeProductionKeywords($data)
    {
        foreach ($data['ProductionKeywords'] as $criterion) {
            $data[$criterion->getAdGroupId()]['ProductionKeywords'][] = $criterion;
        }

        unset($data['ProductionKeywords']);

        return $data;
    }

    /**
     * @param $toCreate
     * @param $production
     *
     * @return array
     */
    private function extractKeywords($toCreate, $production)
    {
        $toUpdate = [];
        $toDelete = [];

        foreach ($production as $key => $criterion) {
            if (array_key_exists($key, $toCreate)) {
                $toUpdate[] = $criterion->setCriterion(
                    $criterion
                        ->getCriterion()
                        ->setText($toCreate[$key]->getText())
                );

                unset($toCreate[$key]);
            } else {
                $toDelete[] = $criterion;
            }
        }

        return array($toUpdate, $toCreate, $toDelete);
    }


    /**
     * @param $response
     *
     * @return array
     */
    public function processResponse($response)
    {
        $output = [];

        foreach ($response as $adGroupId => $relations) {

            if ($this->validateElement($relations, 'AdGroup')) {
                $output[$adGroupId]['AdGroup'] = $adGroupId;
                $this->log("AdGroup with ID: $adGroupId was updated", 'info');
            }

            if ($this->validateElement($relations, 'TextAdToCreate')) {
                foreach ($relations['TextAdToCreate'] as $ad) {
                    $output[$adGroupId]['TextAdToCreate'][] =
                        $ad->getAd()->getId();
                }

                $this->log("TextAd related to AdGroup $adGroupId was created. TextAds: "
                           . implode(', ', $output[$adGroupId]['TextAdToCreate']), 'info');
            }

            if ($this->validateElement($relations, 'KeywordsToUpdate', true)) {
                $output[$adGroupId]['KeywordsToUpdate'] =
                    $this->extractKeywordIds($relations['KeywordsToUpdate']);

                $this->log("Keywords " . implode(', ', $output[$adGroupId]['KeywordsToUpdate']) .
                           " Related to AdGroup: $adGroupId was updated", 'info');
            }

            if ($this->validateElement($relations, 'KeywordsToCreate', true)) {
                $output[$adGroupId]['KeywordsToCreate'] =
                    $this->extractKeywordIds($relations['KeywordsToCreate']);

                $this->log("Keywords " . implode(', ', $output[$adGroupId]['KeywordsToCreate']) .
                           " Related to AdGroup: $adGroupId was created", 'info');
            }

            if ($this->validateElement($relations, 'KeywordsToDelete', true)) {
                $output[$adGroupId]['KeywordsToDelete'] =
                    $this->extractKeywordIds($relations['KeywordsToDelete']);

                $this->log("Keywords " . implode(', ', $output[$adGroupId]['KeywordsToDelete']) .
                           " Related to AdGroup: $adGroupId was deleted", 'info');
            }

        }

        return $output;
    }

    /**
     * @param $response
     *
     * @return array|null
     */
    public function processResponseDelete($response)
    {
        $ids = [];

        foreach ($response as $adGroup) {
            if ($adGroup instanceof AdGroup) {
                $ids[] = $adGroup->getId();
            } else {
                $this->log(str_replace("\n", ' ', 'Something went wrong during ad groups deleting!
                Reason: Incorrect API response.'), 'error');

                return null;
            }
        }

        $this->log('AdGroups ' . implode(', ', $ids) .
                   ' was successfully removed from AdWords account', 'info');

        return $ids;
    }

    /**
     * @param $element
     * @param $key
     * @param bool $notEmpty
     *
     * @return bool
     */
    private function validateElement($element, $key, $notEmpty = false)
    {
        $result = array_key_exists($key, $element) &&
                  $element[$key] !== null;

        if ( ! $notEmpty) {
            return $result;
        } else {
            return $result && ! empty($element[$key]);
        }
    }

    /**
     * @param $keywords
     *
     * @return array
     */
    public function extractKeywordIds($keywords)
    {
        $ids = [];

        foreach ($keywords as $keyword) {
            if ($keyword instanceof BiddableAdGroupCriterion) {
                $ids[] = $keyword->getCriterion()->getId();
            } else {
                $ids[] = $keyword->getId();
            }
        }

        return $ids;
    }

    /**
     * @param $groups
     *
     * @return array|null
     */
    public function extractAdGroupsAfterCreate($groups)
    {
        $result = [];

        if (empty($groups)) {
            $this->log('Something went wrong during Ad Groups creation', 'error');

            return null;
        }

        foreach ($groups as $id => $adGroup) {
            $group['adw_camp_id']  = $adGroup[0]->getCampaignId();
            $group['adw_group_id'] = $adGroup[0]->getId();
            $group['name']         = $adGroup[0]->getName();

            $result[$id] = $group;
        }

        return $result;
    }
}