<?php
/**
 * Created by PhpStorm.
 * User: maks
 * Date: 20.01.17
 * Time: 13:25
 */

namespace Synchronizer\Classes;

use Google\AdsApi\AdWords\v201609\cm\AdGroupAd;
use Google\AdsApi\AdWords\v201609\cm\AdGroupStatus;
use Google\AdsApi\AdWords\v201609\cm\AdType;
use Google\AdsApi\AdWords\v201609\cm\Campaign;
use Google\AdsApi\AdWords\v201609\cm\AdGroup;
use Google\AdsApi\AdWords\v201609\cm\ExpandedTextAd;
use Google\AdsApi\AdWords\v201609\cm\Keyword;
use Google\AdsApi\AdWords\v201609\cm\AdGroupAdStatus;

/**
 * Class DataMapper
 * @package Synchronizer\Classes
 */
class DataMapper
{
    /**
     * @param $data
     *
     * @return array
     */
    public function mapToRemove($data)
    {
        $output    = array();
        $adGroupId = null;

        foreach ($data as $key => $value) {
            if (($adGroupId === null ||
                 $adGroupId !== $value->aw_adgroup_id) &&
                $value->event_date < time()
            ) {
                $adGroupId            = $value->aw_adgroup_id;
                $output['ToRemove'][] =
                    $this->extractAdGroup($value, AdGroupStatus::REMOVED);
            }
        }

        return $output;
    }

    /**
     * @param $rows
     *
     * @return array
     */
    public function mapGroupsToCreate($rows)
    {
        $output   = [];
        $event_id = null;

        foreach ($rows as $value) {
            if ($event_id === null || $event_id !== $value->event_id) {
                $output[$value->event_id] = $this->extractAdGroupForCreate($value);
            }
        }

        return $output;
    }

    /**
     * @param $value
     *
     * @return AdGroup
     */
    private function extractAdGroupForCreate($value)
    {
        $adGroup = (new AdGroup())
            ->setCampaignId($value->aw_campaign_id)
            ->setName("Ad Group for event: " . $value->event_id)
            ->setStatus($this->getStatusAsText($value->enabled, $value->paused));

        return $adGroup;
    }

    /**
     * @param $data
     *
     * @return array|mixed
     */
    public function map($data)
    {
        $output    = array();
        $adGroupId = null;

        foreach ($data as $key => $value) {
            if ($adGroupId === null ||
                $adGroupId !== $value->aw_adgroup_id
            ) {
                $adGroupId = $value->aw_adgroup_id;
            }

            $textAd = $this->extractTextAd($value);

            $output[$adGroupId]['TextAdToCreate'][] = [
                'ad' => $textAd,
                'id' => $value->id,
            ];

            $output[$adGroupId]['TextAd'][] = $textAd;

            if (array_key_exists('LocalKeywords', $output[$adGroupId]) &&
                ! empty($output[$adGroupId]['LocalKeywords'])
            ) {
                $output[$adGroupId]['LocalKeywords'] = $this->extractAndMergeKeywords(
                    $value, $output[$adGroupId]['LocalKeywords']
                );
            } else {
                $output[$adGroupId]['LocalKeywords'] = $this->extractKeywords(
                    $value
                );
            }
        }

        foreach ($output as $adGroupId => $item) {
            $output[$adGroupId]['LocalKeywords'] =
                $this->buildKeywords($item['LocalKeywords']);
        }

        $output = $this->getAdGroupsStatusBeforeUpdate($output);

        return $output;
    }

    /**
     * @param $value
     *
     * @return AdGroupAd
     */
    private function extractTextAd($value)
    {
        $textAd = (new ExpandedTextAd())
            ->setHeadlinePart1($value->line1)
            ->setHeadlinePart2($value->line2)
            ->setDescription($value->title)
            ->setFinalUrls([$value->url])
            ->setType(AdType::EXPANDED_TEXT_AD)
            ->setId($value->aw_textad_id);

        $adGroupAd = (new AdGroupAd())
            ->setAdGroupId($value->aw_adgroup_id)
            ->setAd($textAd)
            ->setStatus(
                $this->getStatusAsText($value->enabled, $value->paused)
            );

        return $adGroupAd;
    }

    /**
     * @param $value
     *
     * @return array
     */
    private function extractKeywords($value)
    {
        $keywords = array_unique(
            explode(',', str_replace("\"", '', $value->keywords)));
        $output   = array();

        foreach ($keywords as $keyword) {
            $output[] = $keyword;
        }

        return $output;
    }

    /**
     * @param $value
     * @param $local
     *
     * @return array
     */
    private function extractAndMergeKeywords($value, $local)
    {
        $keywords = array_unique(
            explode(',', str_replace("\"", '', $value->keywords)));
        $output   = array();

        foreach ($keywords as $keyword) {
            $output[] = $keyword;
        }

        $output = array_unique(array_merge($output, $local));

        return $output;
    }

    /**
     * @param $keywords
     *
     * @return array
     */
    private function buildKeywords($keywords)
    {
        $output = [];
        foreach ($keywords as $keyword) {
            $output[] = (new Keyword())
                ->setText($keyword)
                ->setMatchType('PHRASE')
                ->setType('KEYWORD');
        }

        return $output;
    }

    /**
     * @param $enabled
     * @param $paused
     *
     * @return string
     */
    private function getStatusAsText($enabled, $paused)
    {
        $status = AdGroupAdStatus::ENABLED;

        if ($paused == 1 || $enabled == 0) {
            $status = AdGroupAdStatus::PAUSED;
        }

        return $status;
    }

    /**
     * @param $value
     * @param bool $status
     *
     * @return AdGroup
     */
    private function extractAdGroup($value, $status = false)
    {
        if ( ! $status) {
            $status = $this->getStatusAsText(
                $value->enabled,
                $value->paused
            );
        }

        $group = (new AdGroup())
            ->setId($value->aw_adgroup_id)
            ->setStatus($status);

        return $group;
    }

    /**
     * @param $data
     *
     * @return mixed
     */
    private function getAdGroupsStatusBeforeUpdate($data)
    {
        $adStatuses = array();

        foreach ($data as $adGroupId => $adGroup) {
            foreach ($adGroup['TextAd'] as $key => $textAd) {
                $adStatuses[$adGroupId][] = $textAd->getStatus();
            }

            $statuses = array_unique($adStatuses[$adGroupId]);

            if (count($statuses) === 1) {
                $data[$adGroupId]['AdGroup'] =
                    $this->extractAdGroupFromTextAds($adGroupId,
                        $statuses[0]);
            }
        }

        return $data;
    }

    /**
     * @param $adGroupId
     * @param $status
     *
     * @return AdGroup
     */
    private function extractAdGroupFromTextAds($adGroupId, $status)
    {
        $group = (new AdGroup())
            ->setId($adGroupId)
            ->setStatus($status);

        return $group;
    }

    /**
     * @param $value
     *
     * @return Campaign
     */
    private function extractCampaign($value)
    {
        return new Campaign();
    }
}
