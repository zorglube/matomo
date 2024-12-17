<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\MultiSites\tests\Fixtures;

use Piwik\Date;
use Piwik\Plugins\Goals\API as GoalsAPI;
use Piwik\Tests\Framework\Fixture;

/**
 * Generates tracker testing data for our ControllerTest
 *
 * This Simple fixture adds one website and tracks one visit with couple pageviews and an ecommerce conversion
 */
class ManySitesWithVisits extends Fixture
{
    public $dateTime = '2013-01-23 01:23:45';

    public $idSiteEcommerce;
    public $idSiteGoalDefaultValue;
    public $idSiteGoalEventValue;
    public $idSiteGoalNoValue;

    public $idGoalDefaultValue;
    public $idGoalEventValue;
    public $idGoalNoValue;

    public function setUp(): void
    {
        $this->setUpWebsites();
        $this->setUpGoals();

        $this->trackVisitsForSite($this->idSiteEcommerce, 6);
        $this->trackVisitsForSite($this->idSiteGoalDefaultValue, 3);
        $this->trackVisitsForSite($this->idSiteGoalEventValue, 2);
        $this->trackVisitsForSite($this->idSiteGoalNoValue, 1);
    }

    public function tearDown(): void
    {
        // empty
    }

    private function setUpGoals(): void
    {
        $goalsApi = GoalsAPI::getInstance();

        $this->idGoalDefaultValue = $goalsApi->addGoal(
            $this->idSiteGoalDefaultValue,
            'Goal With Value',
            'manually',
            '',
            '',
            false,
            10.0
        );

        $this->idGoalEventValue = $goalsApi->addGoal(
            $this->idSiteGoalEventValue,
            'Goal Event Value',
            'event_action',
            'track value',
            'exact',
            false,
            false,
            false,
            '',
            true
        );

        $this->idGoalNoValue = $goalsApi->addGoal(
            $this->idSiteGoalNoValue,
            'Goal Without Value',
            'manually',
            '',
            ''
        );
    }

    private function setUpWebsites(): void
    {

        $this->idSiteEcommerce = self::createWebsite($this->dateTime, 1, 'Site Ecommerce');
        $this->idSiteGoalDefaultValue = self::createWebsite($this->dateTime, 0, 'Site Goal Default  Value');
        $this->idSiteGoalEventValue = self::createWebsite($this->dateTime, 0, 'Site Goal Event Value');
        $this->idSiteGoalNoValue = self::createWebsite($this->dateTime, 0, 'Site Goal No Value');

        // create 11 empty websites
        for ($i = 5; $i <= 15; $i++) {
            $idSite = self::createWebsite($this->dateTime, 0, 'Site ' . $i);

            self::assertSame($i, $idSite);
        }
    }

    private function trackVisitsForSite(int $idSite, int $visitCount): void
    {
        for ($visit = 1; $visit <= $visitCount; $visit++) {
            $visitDate = Date::factory($this->dateTime)->addHour($visit);
            $tracker = self::getTracker($idSite, $visitDate->getDatetime());
            $tracker->setUrl('http://example.com/');

            self::checkResponse($tracker->doTrackPageView('Viewing homepage'));

            $tracker->setForceVisitDateTime($visitDate->addHour(0.25)->getDatetime());

            switch ($idSite) {
                case $this->idSiteEcommerce:
                    $tracker->addEcommerceItem('SKU_ID', 'Test item!', 'Test & Category', 19.95, $visit);
                    self::checkResponse($tracker->doTrackEcommerceOrder('Order ' . $visit, 19.95 * $visit));
                    break;

                case $this->idSiteGoalDefaultValue:
                    self::checkResponse($tracker->doTrackGoal($this->idGoalDefaultValue));
                    break;

                case $this->idSiteGoalEventValue:
                    self::checkResponse($tracker->doTrackEvent('value event', 'track value', false, 25.0));
                    break;

                case $this->idSiteGoalNoValue;
                    self::checkResponse($tracker->doTrackGoal($this->idGoalNoValue));
                    break;
            }
        }
    }
}
