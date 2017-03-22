<?php
/**
 * Created by PhpStorm.
 * User: maks
 * Date: 21.01.17
 * Time: 19:02
 */

namespace Synchronizer\Classes;

use Google\AdsApi\AdWords\AdWordsServices;
use Google\AdsApi\AdWords\AdWordsSessionBuilder;
use Google\AdsApi\AdWords\v201609\cm\Ad;
use Google\AdsApi\AdWords\v201609\cm\AdGroup;
use Google\AdsApi\AdWords\v201609\cm\AdGroupAd;
use Google\AdsApi\AdWords\v201609\cm\AdGroupCriterion;
use Google\AdsApi\AdWords\v201609\cm\AdGroupCriterionOperation;
use Google\AdsApi\AdWords\v201609\cm\AdGroupOperation;
use Google\AdsApi\AdWords\v201609\cm\AdGroupStatus;
use Google\AdsApi\AdWords\v201609\cm\BiddableAdGroupCriterion;
use Google\AdsApi\AdWords\v201609\cm\BiddingStrategyConfiguration;
use Google\AdsApi\AdWords\v201609\cm\CpcBid;
use Google\AdsApi\AdWords\v201609\cm\Criterion;
use Google\AdsApi\AdWords\v201609\cm\Money;
use Google\AdsApi\Common\OAuth2TokenBuilder;
use Google\AdsApi\AdWords\v201609\cm\CampaignService;
use Google\AdsApi\AdWords\v201609\cm\OrderBy;
use Google\AdsApi\AdWords\v201609\cm\Paging;
use Google\AdsApi\AdWords\v201609\cm\Selector;
use Google\AdsApi\AdWords\v201609\cm\SortOrder;
use Google\AdsApi\AdWords\v201609\cm\Predicate;
use Google\AdsApi\AdWords\v201609\cm\PredicateOperator;
use Google\AdsApi\AdWords\v201609\cm\AdGroupService;
use Google\AdsApi\AdWords\v201609\cm\AdGroupAdService;
use Google\AdsApi\AdWords\v201609\cm\AdType;
use Google\AdsApi\AdWords\v201609\cm\AdGroupAdStatus;
use Google\AdsApi\AdWords\v201609\cm\AdGroupCriterionService;
use Google\AdsApi\AdWords\v201609\cm\CriterionType;
use Google\AdsApi\AdWords\v201609\cm\Operator;
use Google\AdsApi\AdWords\v201609\cm\AdGroupAdOperation;

/**
 * Class AdWordsAPI
 * @package Synchronizer\Classes
 */
class AdWordsAPI
{

    use Log;

    /**
     * @var \Google\AdsApi\AdWords\AdWordsSession|mixed
     */
    protected $session;

    /**
     * @var AdWordsServices
     */
    protected $services;

    /**
     * AdWordsAPI constructor.
     *
     * @param $pathToIni
     */
    public function __construct($pathToIni)
    {
        $this->services = new AdWordsServices();
        $this->session  = $this->getSession($pathToIni);
    }

    /**
     * @param $pathToIni
     *
     * @return \Google\AdsApi\AdWords\AdWordsSession|mixed
     */
    private function getSession($pathToIni)
    {
        try {
            $session = (new AdWordsSessionBuilder())
                ->fromFile($pathToIni)
                ->withOAuth2Credential(
                    (new OAuth2TokenBuilder())
                        ->fromFile($pathToIni)
                        ->build()
                )
                ->build();

            return $session;
        } catch (\Exception $exception) {
            $this->log('AdWords API: Unable to get session token. Message: ' .
                       $exception->getMessage(), 'error');
            exit;
        }
    }

    /**
     * @param $adGroupIds
     *
     * @return mixed
     */
    public function getKeywords($adGroupIds)
    {
        $adGroupCriterionService =
            $this->services->get($this->session, AdGroupCriterionService::class);

        $selector = (new Selector())
            ->setFields([
                'Id',
                'CriteriaType',
                'KeywordMatchType',
                'Status',
                'FinalUrls',
                'KeywordText',
            ])
            ->setOrdering([new OrderBy('AdGroupId', SortOrder::DESCENDING)])
            ->setPredicates([
                new Predicate('AdGroupId', PredicateOperator::IN, $adGroupIds),
                new Predicate('CriteriaType', PredicateOperator::IN,
                    [CriterionType::KEYWORD]),
            ])
            ->setPaging(new Paging(0, 2000));

        $page = $adGroupCriterionService->get($selector);

        return $page->getEntries();
    }

    /**
     * @param $campaginId
     *
     * @return mixed
     */
    public function getCampagin($campaginId)
    {
        $campaignService = $this->services
            ->get($this->session, CampaignService::class);

        $selector = (new Selector())
            ->setFields([
                'Id',
                'Name',
                'Status',
                'ServingStatus',
                'StartDate',
                'EndDate',
            ])
            ->setPredicates([new Predicate('CampaignId', PredicateOperator::IN, [$campaginId])])
            ->setPaging(new Paging(0, 10));

        $page = $campaignService->get($selector);

        return $page->getEntries();
    }

    /**
     * @param $adGroupId
     *
     * @return mixed
     */
    public function getAdGroup($adGroupId)
    {
        $adGroupService = $this->services
            ->get($this->session, AdGroupService::class);
        $selector       = (new Selector())
            ->setFields([
                'Id',
                'Name',
                'Status',
                'CampaignId',
                'CampaignName',
                'Settings',
                'Labels',
                'BaseCampaignId',
                'BaseAdGroupId',
                'TrackingUrlTemplate',
                'UrlCustomParameters',
            ])
            ->setOrdering([new OrderBy('Name', SortOrder::ASCENDING)])
            ->setPredicates([
                new Predicate('AdGroupId', PredicateOperator::IN, [$adGroupId]),
            ])
            ->setPaging(new Paging(0, 10));

        $page = $adGroupService->get($selector);

        return $page->getEntries();
    }


    /**
     * @param $textAdId
     *
     * @return mixed
     */
    public function getTextAd($textAdId)
    {
        $adGroupAdService =
            $this->services->get($this->session, AdGroupAdService::class);

        $selector = (new Selector())
            ->setFields([
                'Id',
                'AdType',
                'Url',
                'CreativeFinalUrls',
                'Headline',
                'Description1',
                'Description2',
                'DisplayUrl',
            ])
            ->setOrdering([new OrderBy('Headline', SortOrder::ASCENDING)])
            ->setPredicates([
                new Predicate('Id', PredicateOperator::IN, [$textAdId]),
                new Predicate('AdType', PredicateOperator::IN, [AdType::TEXT_AD]),
                new Predicate('Status', PredicateOperator::IN,
                    [
                        AdGroupAdStatus::DISABLED,
                        AdGroupAdStatus::ENABLED,
                        AdGroupAdStatus::PAUSED,
                    ]),
            ])
            ->setPaging(new Paging(0, 10));

        $page = $adGroupAdService->get($selector);

        return $page->getEntries();
    }

    /**
     * @param $array
     * @param $key
     *
     * @return bool
     */
    private function validate($array, $key)
    {
        return array_key_exists($key, $array) &&
               ! empty($array[$key]);
    }

    /**
     * @param $data
     *
     * @return array
     */
    public function operate($data)
    {
        $result = [];

        foreach ($data as $adGroupId => $relations) {
            if ($this->validate($relations, 'TextAdToCreate')) {
                $result[$adGroupId]['TextAdToCreate'] =
                    $this->createTextAd($relations['TextAdToCreate']);
            }

            if ($this->validate($relations, 'AdGroup')) {
                $result[$adGroupId]['AdGroup'] =
                    $this->updateAdGroups([$relations['AdGroup']]);
            }

            if ($this->validate($relations, 'KeywordsToUpdate')) {
                $result[$adGroupId]['KeywordsToUpdate'] =
                    $this->updateKeywords($relations['KeywordsToUpdate']);
            }

            if ($this->validate($relations, 'KeywordsToDelete')) {
                $result[$adGroupId]['KeywordsToDelete'] =
                    $this->updateKeywords($relations['KeywordsToDelete']);
            }

            if ($this->validate($relations, 'KeywordsToCreate')) {
                $result[$adGroupId]['KeywordsToCreate'] =
                    $this->addKeywords(
                        $adGroupId,
                        $relations['KeywordsToCreate']
                    );
            }
        }

        return $result;
    }

    /**
     * @param $groups
     *
     * @return array
     */
    public function createAdGroups($groups)
    {
        $result         = [];
        $adGroupService = $this->services->get($this->session, AdGroupService::class);

        foreach ($groups as $id => $group) {
            $operation = (new AdGroupOperation())
                ->setOperand($group)
                ->setOperator(Operator::ADD);

            $result[$id] = $this->mutate($adGroupService, [$operation]);
        }

        return $result;
    }

    /**
     * @param $adGroupAds
     *
     * @return null
     */
    protected function updateTextAd($adGroupAds)
    {
        $operations       = [];
        $adGroupAdService = $this->services
            ->get($this->session, AdGroupAdService::class);

        foreach ($adGroupAds as $adGroupAd) {
            if ($adGroupAd->getAd()->getId() != null) {
                $operation = (new AdGroupAdOperation())
                    ->setOperand($adGroupAd)
                    ->setOperator(Operator::ADD);

                $operations[] = $operation;
            }
        }

        return $this->mutate($adGroupAdService, $operations);
    }

    /**
     * @param $adGroupAds
     *
     * @return null
     */
    public function createTextAd($adGroupAds)
    {
        $createOperations = [];
        $deleteOperations = [];

        $adGroupAdService = $this->services
            ->get($this->session, AdGroupAdService::class);

        foreach ($adGroupAds as $adGroupAd) {
//          Remove old Ad
            if ($adGroupAd['ad']->getAd()->getId() != null) {
                $ad = (new Ad())
                    ->setId($adGroupAd['ad']->getAd()->getId());

                $adGrAd = (new AdGroupAd())
                    ->setAdGroupId($adGroupAd['ad']->getAdGroupId())
                    ->setAd($ad);

                $deleteOperation = (new AdGroupAdOperation())
                    ->setOperand($adGrAd)
                    ->setOperator(Operator::REMOVE);

                $deleteOperations[] = $deleteOperation;
            }

            $adGroupAd['ad']->setAd($adGroupAd['ad']->getAd()->setId(null));

            $createOperation = (new AdGroupAdOperation())
                ->setOperand($adGroupAd['ad'])
                ->setOperator(Operator::ADD);

            $createOperations[] = $createOperation;
        }

        if ( ! empty($deleteOperations)) {
            $this->mutate($adGroupAdService, $deleteOperations);
        }

        return $this->mutate($adGroupAdService, $createOperations);
    }

    /**
     * This method can delete and update AdGroups
     *
     * @param $adGroups
     *
     * @return array of results
     */
    public function updateAdGroups($adGroups)
    {
        $operations     = [];
        $adGroupService = $this->services
            ->get($this->session, AdGroupService::class);

        foreach ($adGroups as $adGroup) {
            $operation = (new AdGroupOperation())
                ->setOperand($adGroup)
                ->setOperator(Operator::SET);

            $operations[] = $operation;
        }

        return $this->mutate($adGroupService, $operations);
    }

    /**
     * @param $keywords
     *
     * @return null
     */
    protected function updateKeywords($keywords)
    {
        $adGroupCriterionService = $this->services
            ->get($this->session, AdGroupCriterionService::class);

        $deleteOperations = $this->deleteKeywords($keywords);
        $createOperations = $this->resetKeywordIds($keywords);

        $this->mutate($adGroupCriterionService, $deleteOperations);

        return $this->mutate($adGroupCriterionService, $createOperations);
    }

    /**
     * @param $keywords
     *
     * @return array
     */
    private function resetKeywordIds($keywords)
    {
        $operations = [];

        foreach ($keywords as $keyword) {
            $adGroupCriterion = (new BiddableAdGroupCriterion())
                ->setAdGroupId($keyword->getAdGroupId())
                ->setCriterion($keyword->getCriterion()->setId(null));

            $operation = (new AdGroupCriterionOperation())
                ->setOperand($adGroupCriterion)
                ->setOperator(Operator::ADD);

            $operations[] = $operation;
        }

        return $operations;
    }

    /**
     * @param $keywords
     *
     * @return array
     */
    private function deleteKeywords($keywords)
    {
        $operations = [];

        foreach ($keywords as $keyword) {
            $criterion = (new Criterion())
                ->setId($keyword->getCriterion()->getId());

            // Create an ad group criterion.
            $adGroupCriterion = (new AdGroupCriterion())
                ->setAdGroupId($keyword->getAdGroupId())
                ->setCriterion($criterion);

            // Create an ad group criterion operation and add it the operations list.
            $operation = (new AdGroupCriterionOperation())
                ->setOperand($adGroupCriterion)
                ->setOperator(Operator::REMOVE);

            $operations[] = $operation;
        }

        return $operations;
    }

    /**
     * @param $adGroupId
     * @param $keywords
     *
     * @return null
     */
    protected function addKeywords($adGroupId, $keywords)
    {
        $operations              = [];
        $adGroupCriterionService = $this->services
            ->get($this->session, AdGroupCriterionService::class);

        foreach ($keywords as $keyword) {
            $adGroupCriterion = (new BiddableAdGroupCriterion())
                ->setAdGroupId($adGroupId)
                ->setCriterion($keyword);

            $operation = (new AdGroupCriterionOperation())
                ->setOperand($adGroupCriterion)
                ->setOperationType(Operator::ADD)
                ->setOperator(Operator::ADD);

            $operations[] = $operation;
        }

        return $this->mutate($adGroupCriterionService, $operations);
    }

    /**
     * @param $service
     * @param $operations
     *
     * @return null
     */
    private function mutate($service, $operations)
    {
        try {
            $result = $service->mutate($operations);
            $value  = $result->getValue();

            if ($value !== null && is_array($value) && ! empty($value)) {
                return $value;
            }
        } catch (\Exception $exception) {
            $this->log($exception->getMessage(), 'error');
        }

        return null;
    }

}
