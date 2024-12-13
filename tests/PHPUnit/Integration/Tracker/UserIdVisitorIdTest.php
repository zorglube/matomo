<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace PHPUnit\Integration\Tracker;

use Piwik\Common;
use Piwik\Config;
use Piwik\Date;
use Piwik\Db;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

class UserIdVisitorIdTest extends IntegrationTestCase
{
    public const FIRST_VISIT_TIME = '2012-01-05 00:00:00';
    public const TEST_USER_AGENT = 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36';
    public const TEST_BROWSER_LANGUAGE = 'en-gb';
    public const TEST_COUNTRY = 'nl';
    public const TEST_REGION = '06';
    public const CHANGED_USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_3) AppleWebKit/537.75.14 (KHTML, like Gecko) Version/7.0.3 Safari/7046A194A';
    public const CHANGED_BROWSER_LANGUAGE = 'ja';
    public const CHANGED_COUNTRY = 'jp';
    public const CHANGED_REGION = '22';

    private $trackerEventTsIterator;

    private $testEnv;

    private $userIdOverwritesVisitorId = 1;

    public function setUp(): void
    {
        parent::setUp();

        Fixture::createWebsite('2012-01-01 00:00:00');

        $this->testEnv = static::$fixture->getTestEnvironment();
        $this->trackerEventTsIterator = Date::factory(self::FIRST_VISIT_TIME)->getTimestamp();
    }

    private function trackPageview(\MatomoTracker $tracker, $url)
    {
        $tracker->setForceVisitDateTime($this->trackerEventTsIterator++);
        $response = $tracker->doTrackPageView($url);
        Fixture::checkResponse($response);
    }

    private function trackAction(\MatomoTracker $tracker, $action)
    {
        $tracker->setForceVisitDateTime($this->trackerEventTsIterator++);
        $response = $tracker->doTrackAction($action, 'link');
        Fixture::checkResponse($response);
    }

    private function logInUser(\MatomoTracker $tracker)
    {
        $tracker->setUserId('user-1');
    }

    private function logOutUser(\MatomoTracker $tracker)
    {
        $tracker->setUserId(false);
    }

    private function enableUserIdOverwritesVisitorId()
    {
        if ($this->userIdOverwritesVisitorId !== 1) {
            $this->userIdOverwritesVisitorId = 1;

            $this->testEnv->overrideConfig('Tracker', 'enable_userid_overwrites_visitorid', 1);
            $this->testEnv->save();
        }
    }

    private function disableUserIdOverwritesVisitorId()
    {
        if ($this->userIdOverwritesVisitorId !== 0) {
            $this->userIdOverwritesVisitorId = 0;

            $this->testEnv->overrideConfig('Tracker', 'enable_userid_overwrites_visitorid', 0);
            $this->testEnv->save();
        }
    }

    private function getTracker()
    {
        $tracker = Fixture::getTracker(1, self::FIRST_VISIT_TIME, $defaultInit = true, $useLocalTracker = true);
        $tracker->setTokenAuth(Fixture::getTokenAuth());

        // properties that cannot be changed on next action
        $tracker->setUserAgent(self::TEST_USER_AGENT);
        $tracker->setBrowserLanguage(self::TEST_BROWSER_LANGUAGE);

        // properties that can be changed on next action
        $tracker->setCountry(self::TEST_COUNTRY);
        $tracker->setRegion(self::TEST_REGION);

        return $tracker;
    }

    private function getTrackerForAlternateDevice()
    {
        $tracker = $this->getTracker();

        $tracker->setUserAgent(self::CHANGED_USER_AGENT);
        $tracker->setBrowserLanguage(self::CHANGED_BROWSER_LANGUAGE);
        $tracker->setCountry(self::CHANGED_COUNTRY);
        $tracker->setRegion(self::CHANGED_REGION);

        return $tracker;
    }

    // user does not log in during a visit, all actions are assigned to a single visitor ID
    public function testUserDoesNotLogInDuringVisit()
    {
        $this->enableUserIdOverwritesVisitorId();
        $tracker = $this->getTracker();

        $this->trackPageview($tracker, 'page-1');
        $this->assertCounts([1], 1);

        $this->trackPageview($tracker, 'page-2');
        $this->assertCounts([2], 1);

        $this->trackAction($tracker, 'action-1');
        $this->assertCounts([3], 1);

        // force a new visit for the same visitor
        $tracker->setForceNewVisit();
        $this->trackPageview($tracker, 'page-3');
        $this->assertCounts([3, 1], 1);

        $this->trackAction($tracker, 'action-2');
        $this->assertCounts([3, 2], 1);
    }

    // user logs in during a visit, custom User ID is only provided for the log in action
    // user id replaces visitor id and there is still only one distinct value, but is different
    public function testUserLogsInDuringVisit()
    {
        $this->enableUserIdOverwritesVisitorId();
        $tracker = $this->getTracker();

        $this->trackPageview($tracker, 'page-1');
        $this->assertCounts([1], 1);

        $this->trackPageview($tracker, 'page-2');
        $this->assertCounts([2], 1);

        $this->trackAction($tracker, 'action-1');
        $this->assertCounts([3], 1);

        $visitorId1 = $this->getVisitProperty('idvisitor', 1);

        // track second action with user id
        $this->logInUser($tracker);
        $this->trackAction($tracker, 'log-in');
        $this->assertCounts([4], 1);

        // expect changed visitor id
        $visitorId2 = $this->getVisitProperty('idvisitor', 1);
        $this->assertNotEquals($visitorId1, $visitorId2);

        $this->trackAction($tracker, 'action-2');
        $this->assertCounts([5], 1);

        $this->trackPageview($tracker, 'page-3');
        $this->assertCounts([6], 1);
    }

    public function testUserLogsInDuringVisitWithoutActions()
    {
        $this->enableUserIdOverwritesVisitorId();
        $tracker = $this->getTracker();

        $this->trackPageview($tracker, 'page-1');
        $this->assertCounts([1], 1);

        $this->trackPageview($tracker, 'page-2');
        $this->assertCounts([2], 1);

        $visitorId1 = $this->getVisitProperty('idvisitor', 1);

        // track second action with user id
        $this->logInUser($tracker);
        $this->trackPageview($tracker, 'page-3');
        $this->assertCounts([3], 1);

        // expect changed visitor id
        $visitorId2 = $this->getVisitProperty('idvisitor', 1);
        $this->assertNotEquals($visitorId1, $visitorId2);

        $this->trackPageview($tracker, 'page-3');
        $this->assertCounts([4], 1);
    }

    // user logs in and out during a visit, custom User ID is only provided for the log in action
    // user id replaces visitor id and there is still only one distinct value, but is different
    public function testUserLogsInAndOutDuringVisit()
    {
        $this->enableUserIdOverwritesVisitorId();
        $tracker = $this->getTracker();

        $this->trackPageview($tracker, 'page-1');
        $this->assertCounts([1], 1);

        $this->trackPageview($tracker, 'page-2');
        $this->assertCounts([2], 1);

        $this->trackAction($tracker, 'action-1');
        $this->assertCounts([3], 1);

        $visitorId1 = $this->getVisitProperty('idvisitor', 1);

        // track second action with user id
        $this->logInUser($tracker);
        $this->trackAction($tracker, 'log-in');
        $this->assertCounts([4], 1);

        // expect changed visitor id
        $visitorId2 = $this->getVisitProperty('idvisitor', 1);
        $this->assertNotEquals($visitorId1, $visitorId2);

        $this->trackAction($tracker, 'action-2');
        $this->assertCounts([5], 1);

        $this->trackPageview($tracker, 'page-3');
        $this->assertCounts([6], 1);

        // log out and de-set user id
        $this->logOutUser($tracker);
        $this->trackAction($tracker, 'log-out');
        $this->assertCounts([7], 1);

        // expect original visitor id after logging out
        $visitorId3 = $this->getVisitProperty('idvisitor', 1);
        $this->assertEquals($visitorId1, $visitorId3);
        $this->assertNotEquals($visitorId2, $visitorId3);

        $this->trackAction($tracker, 'action-3');
        $this->assertCounts([8], 1);
    }

    public function testUserLogsInAndOutDuringVisitWithoutActions()
    {
        $this->enableUserIdOverwritesVisitorId();
        $tracker = $this->getTracker();

        $this->trackPageview($tracker, 'page-1');
        $this->assertCounts([1], 1);

        $this->trackPageview($tracker, 'page-2');
        $this->assertCounts([2], 1);

        $visitorId1 = $this->getVisitProperty('idvisitor', 1);

        // track second action with user id
        $this->logInUser($tracker);
        $this->trackPageview($tracker, 'page-3');
        $this->assertCounts([3], 1);

        // expect changed visitor id
        $visitorId2 = $this->getVisitProperty('idvisitor', 1);
        $this->assertNotEquals($visitorId1, $visitorId2);

        $this->trackPageview($tracker, 'page-4');
        $this->assertCounts([4], 1);

        // log out and de-set user id
        $this->logOutUser($tracker);
        $this->trackPageview($tracker, 'page-5');
        $this->assertCounts([5], 1);

        // expect original visitor id after logging out
        $visitorId3 = $this->getVisitProperty('idvisitor', 1);
        $this->assertEquals($visitorId1, $visitorId3);
        $this->assertNotEquals($visitorId2, $visitorId3);

        $this->trackPageview($tracker, 'page-6');
        $this->assertCounts([6], 1);
    }

    public function testUserLoggedInOnMultipleDevices()
    {
        $this->enableUserIdOverwritesVisitorId();
        $trackerDevice1 = $this->getTracker();

        $this->trackPageview($trackerDevice1, 'page-1');
        $this->trackPageview($trackerDevice1, 'page-2');
        $this->trackAction($trackerDevice1, 'action-1');
        $this->logInUser($trackerDevice1);
        $this->trackAction($trackerDevice1, 'log-in');
        $this->assertCounts([4], 1);

        $trackerDevice2 = $this->getTrackerForAlternateDevice();

        $this->trackPageview($trackerDevice2, 'page-3');
        $this->trackPageview($trackerDevice2, 'page-4');
        $this->trackAction($trackerDevice2, 'action-2');
        $this->logInUser($trackerDevice2);
        $this->trackAction($trackerDevice2, 'log-in');

        // TODO: It may be unexpected that the last action of the second visit(or) is actually attributed to the first
        // visit(or). This is caused by the way how existing visits are looked up.
        // After the login a userid is provided. Therefor Matomo tracker looks for an existing visit by prioritizing the
        // userid (=visitorid). As a visit is found it will be resumed - instead of updating the actually running one

        $this->assertCounts([5, 3], 2);
        $this->assertVisitorIdsCount(2);

        // multiple devices, multiple config IDs
        $this->assertConfigIdsCount(2);
    }

    public function testUserLoggedInOnMultipleDevicesWithoutActions()
    {
        $this->enableUserIdOverwritesVisitorId();
        $trackerDevice1 = $this->getTracker();

        $this->trackPageview($trackerDevice1, 'page-1');
        $this->trackPageview($trackerDevice1, 'page-2');
        $this->logInUser($trackerDevice1);
        $this->trackPageview($trackerDevice1, 'page-3');
        $this->assertCounts([3], 1);

        $trackerDevice2 = $this->getTrackerForAlternateDevice();

        $this->trackPageview($trackerDevice2, 'page-4');
        $this->trackPageview($trackerDevice2, 'page-5');
        $this->logInUser($trackerDevice2);
        $this->trackPageview($trackerDevice2, 'page-6');

        // TODO: It may be unexpected that the last action of the second visit(or) is actually attributed to the first
        // visit(or). This is caused by the way how existing visits are looked up.
        // After the login a userid is provided. Therefor Matomo tracker looks for an existing visit by prioritizing the
        // userid (=visitorid). As a visit is found it will be resumed - instead of updating the actually running one

        $this->assertCounts([4, 2], 2);
        $this->assertVisitorIdsCount(2);

        // multiple devices, multiple config IDs
        $this->assertConfigIdsCount(2);
    }

    public function testUserLogsInAndOutMultipleTimes()
    {
        $this->enableUserIdOverwritesVisitorId();
        $tracker = $this->getTracker();

        $this->trackPageview($tracker, 'page-1');
        $this->trackPageview($tracker, 'page-2');
        $this->trackAction($tracker, 'action-1');
        $this->logInUser($tracker);
        $this->trackAction($tracker, 'log-in');
        $visitorId1 = $this->getVisitProperty('idvisitor', 1);

        $this->assertCounts([4], 1);

        $this->trackAction($tracker, 'action-2');
        $this->trackPageview($tracker, 'page-3');

        $this->assertCounts([6], 1);

        $this->logOutUser($tracker);
        $this->trackAction($tracker, 'log-out');
        $visitorId2 = $this->getVisitProperty('idvisitor', 1);

        $this->assertCounts([7], 1, 1);

        // force new visit and log in
        $tracker = $this->getTracker();
        $tracker->setForceNewVisit();

        $this->trackAction($tracker, 'action-3');
        $this->trackPageview($tracker, 'page-4');
        $this->logInUser($tracker);
        $this->trackAction($tracker, 'log-in');
        $visitorId3 = $this->getVisitProperty('idvisitor', 2);

        $this->assertCounts([7, 3], 2);

        $this->trackAction($tracker, 'action-4');
        $this->trackPageview($tracker, 'page-5');

        $this->assertCounts([7, 5], 2);

        $this->logOutUser($tracker);
        $this->trackAction($tracker, 'log-out');
        $visitorId4 = $this->getVisitProperty('idvisitor', 2);
        $this->assertCounts([7, 6], 2);

        // TODO: check when there's no action after the log out action, the idvisitor is empty

        $this->assertEquals($visitorId1, $visitorId3);
        // since we forced a new visit, the visitor id after second log out is different
        $this->assertNotEquals($visitorId2, $visitorId4);

        $this->assertUserIdsCount(1);
    }

    public function testNewVisitWithLoginAndLogoutCombination()
    {
        $this->enableUserIdOverwritesVisitorId();
        // track a first visit (without login)
        $tracker = $this->getTracker();

        $this->trackPageview($tracker, 'page-1');
        $this->trackPageview($tracker, 'page-2');

        $this->assertCounts([2], 1);

        // force a new visit of the same visitor (with login and logout)
        $tracker->setForceNewVisit();
        $this->trackPageview($tracker, 'page-1');

        $this->assertCounts([2, 1], 1);
        $visitorId1 = $this->getVisitProperty('idvisitor', 2);

        $this->logInUser($tracker);
        $this->trackPageview($tracker, 'page-3');

        $visitorId2 = $this->getVisitProperty('idvisitor', 2);
        $this->assertNotEquals($visitorId1, $visitorId2);
        // logging in causes the visitor id to change, therefore we afterwards have 2 different
        $this->assertCounts([2, 2], 2);

        $this->logOutUser($tracker);
        $this->trackPageview($tracker, 'page-5');

        // TODO: It may be unexpected that the last action of the second visit is actually attributed to the first
        // visit. This is caused by the way how existing visits are looked up.
        // After the login a userid is provided. Therefor Matomo tracker updates the visitorid of the second visit,
        // while the visitorid of the first visit remains. As the php tracker still has the original visitorid set,
        // after logout this visitorid will be used again to look for an existing visit.
        // This causes the last action to be attributed to the first visit.

        $this->assertCounts([3, 2], 2);
    }

    public function testUserLogsInAndOutMultipleTimesWithoutActions()
    {
        $this->enableUserIdOverwritesVisitorId();
        $tracker = $this->getTracker();

        $this->trackPageview($tracker, 'page-1');
        $this->trackPageview($tracker, 'page-2');
        $this->logInUser($tracker);
        $this->trackPageview($tracker, 'page-3');
        $visitorId1 = $this->getVisitProperty('idvisitor', 1);

        $this->assertCounts([3], 1);

        $this->trackPageview($tracker, 'page-4');

        $this->assertCounts([4], 1);

        $this->logOutUser($tracker);
        $this->trackPageview($tracker, 'page-5');
        $visitorId2 = $this->getVisitProperty('idvisitor', 1);

        $this->assertCounts([5], 1, 1);

        // force new visit and log in
        $tracker = $this->getTracker();
        $tracker->setForceNewVisit();

        $this->trackPageview($tracker, 'page-6');
        $this->logInUser($tracker);
        $this->trackPageview($tracker, 'page-7');
        $visitorId3 = $this->getVisitProperty('idvisitor', 2);

        $this->assertCounts([5, 2], 2);

        $this->trackPageview($tracker, 'page-5');
        $this->assertCounts([5, 3], 2);

        $this->logOutUser($tracker);
        $this->trackPageview($tracker, 'page-6');
        $visitorId4 = $this->getVisitProperty('idvisitor', 2);
        $this->assertCounts([5, 4], 2);

        $this->assertEquals($visitorId1, $visitorId3);
        // since we forced a new visit, the visitor id after second log out is different
        $this->assertNotEquals($visitorId2, $visitorId4);

        $this->assertUserIdsCount(1);
    }

    public function testNewVisitTriggeredByInactivity()
    {
        $this->enableUserIdOverwritesVisitorId();
        $tracker = $this->getTracker();

        $this->trackPageview($tracker, 'page-1');
        $this->trackPageview($tracker, 'page-2');
        $this->trackAction($tracker, 'action-1');
        $this->logInUser($tracker);
        $this->trackAction($tracker, 'log-in');
        $visitorId1 = $this->getVisitProperty('idvisitor', 1);

        // move time beyond default visit length
        $this->trackerEventTsIterator += Config::getInstance()->Tracker['visit_standard_length'] + 1;

        $this->trackPageview($tracker, 'page-3');
        $this->trackPageview($tracker, 'page-4');
        $this->trackAction($tracker, 'action-5');
        $visitorId2 = $this->getVisitProperty('idvisitor', 2);

        $this->assertCounts([4, 3], 1, 1);
        $this->assertEquals($visitorId1, $visitorId2);

        // move time beyond default visit length
        $this->trackerEventTsIterator += Config::getInstance()->Tracker['visit_standard_length'] + 1;

        $this->trackPageview($tracker, 'page-5');

        $this->assertCounts([4, 3, 1], 1, 1);
    }

    public function testNewVisitTriggeredAtMidnight()
    {
        $this->enableUserIdOverwritesVisitorId();
        $tracker = $this->getTracker();

        $this->trackPageview($tracker, 'page-1');
        $this->trackAction($tracker, 'action-1');

        // move time by a day
        $this->trackerEventTsIterator += 24 * 60 * 60;

        $this->trackPageview($tracker, 'page-3');
        $this->trackAction($tracker, 'action-3');

        $this->assertCounts([2, 2], 1);
    }

    public function testNewVisitWhenCampaignChanges()
    {
        $this->enableUserIdOverwritesVisitorId();
        $tracker = $this->getTracker();

        $tracker->setUrl('http://www.example.com/?utm_campaign=first');
        $this->trackPageview($tracker, 'page-1');

        $tracker->setUrl('http://www.example.com/?utm_campaign=second');
        $this->trackPageview($tracker, 'page-2');

        $this->assertCounts([1, 1], 1);
    }

    // user does not log in during a visit, all actions are assigned to a single visitor ID
    public function testUserDoesNotLogInDuringVisitWithoutVisitorIdOverwrite()
    {
        $this->disableUserIdOverwritesVisitorId();
        $tracker = $this->getTracker();

        $this->trackPageview($tracker, 'page-1');
        $this->assertCounts([1], 1);

        $this->trackPageview($tracker, 'page-2');
        $this->assertCounts([2], 1);

        $this->trackAction($tracker, 'action-1');
        $this->assertCounts([3], 1);

        // force a new visit for the same visitor
        $tracker->setForceNewVisit();
        $this->trackPageview($tracker, 'page-3');
        $this->assertCounts([3, 1], 1);

        $this->trackAction($tracker, 'action-2');
        $this->assertCounts([3, 2], 1);
    }

    // user logs in during a visit, custom User ID is only provided for the log in action
    // user id does not replace visitor id
    public function testUserLogsInDuringVisitWithoutVisitorIdOverwrite()
    {
        $this->disableUserIdOverwritesVisitorId();
        $tracker = $this->getTracker();

        $this->trackPageview($tracker, 'page-1');
        $this->assertCounts([1], 1);

        $this->trackPageview($tracker, 'page-2');
        $this->assertCounts([2], 1);

        $this->trackAction($tracker, 'action-1');
        $this->assertCounts([3], 1);

        $visitorId1 = $this->getVisitProperty('idvisitor', 1);

        // track second action with user id
        $this->logInUser($tracker);
        $this->trackAction($tracker, 'log-in');
        $this->assertCounts([4], 1);

        // expect same visitor id
        $visitorId2 = $this->getVisitProperty('idvisitor', 1);
        $this->assertEquals($visitorId1, $visitorId2);

        $this->trackAction($tracker, 'action-2');
        $this->assertCounts([5], 1);

        $this->trackPageview($tracker, 'page-3');
        $this->assertCounts([6], 1);
    }

    public function testUserLogsInDuringVisitWithoutActionsWithoutVisitorIdOverwrite()
    {
        $this->disableUserIdOverwritesVisitorId();
        $tracker = $this->getTracker();

        $this->trackPageview($tracker, 'page-1');
        $this->assertCounts([1], 1);

        $this->trackPageview($tracker, 'page-2');
        $this->assertCounts([2], 1);

        $visitorId1 = $this->getVisitProperty('idvisitor', 1);

        // track second action with user id
        $this->logInUser($tracker);
        $this->trackPageview($tracker, 'page-3');
        $this->assertCounts([3], 1);

        // expect same visitor id
        $visitorId2 = $this->getVisitProperty('idvisitor', 1);
        $this->assertEquals($visitorId1, $visitorId2);

        $this->trackPageview($tracker, 'page-4');
        $this->assertCounts([4], 1);
    }

    // user logs in and out during a visit, custom User ID is only provided for the log in action
    // user id replaces visitor id and there is still only one distinct value, but is different
    public function testUserLogsInAndOutDuringVisitWithoutVisitorIdOverwrite()
    {
        $this->disableUserIdOverwritesVisitorId();
        $tracker = $this->getTracker();

        $this->trackPageview($tracker, 'page-1');
        $this->assertCounts([1], 1);

        $this->trackPageview($tracker, 'page-2');
        $this->assertCounts([2], 1);

        $this->trackAction($tracker, 'action-1');
        $this->assertCounts([3], 1);

        $visitorId1 = $this->getVisitProperty('idvisitor', 1);

        // track second action with user id
        $this->logInUser($tracker);
        $this->trackAction($tracker, 'log-in');
        $this->assertCounts([4], 1);

        // expect same visitor id
        $visitorId2 = $this->getVisitProperty('idvisitor', 1);
        $this->assertEquals($visitorId1, $visitorId2);

        $this->trackAction($tracker, 'action-2');
        $this->assertCounts([5], 1);

        $this->trackPageview($tracker, 'page-3');
        $this->assertCounts([6], 1);

        // log out and de-set user id
        $this->logOutUser($tracker);
        $this->trackAction($tracker, 'log-out');
        $this->assertCounts([7], 1);

        // expect original visitor id after logging out
        $visitorId3 = $this->getVisitProperty('idvisitor', 1);
        $this->assertEquals($visitorId1, $visitorId3);
        $this->assertEquals($visitorId2, $visitorId3);

        $this->trackAction($tracker, 'action-3');
        $this->assertCounts([8], 1);
    }

    public function testUserLogsInAndOutDuringVisitWithoutActionsWithoutVisitorIdOverwrite()
    {
        $this->disableUserIdOverwritesVisitorId();
        $tracker = $this->getTracker();

        $this->trackPageview($tracker, 'page-1');
        $this->assertCounts([1], 1);

        $this->trackPageview($tracker, 'page-2');
        $this->assertCounts([2], 1);

        $visitorId1 = $this->getVisitProperty('idvisitor', 1);

        // track second action with user id
        $this->logInUser($tracker);
        $this->trackPageview($tracker, 'page-3');
        $this->assertCounts([3], 1);

        // expect same visitor id
        $visitorId2 = $this->getVisitProperty('idvisitor', 1);
        $this->assertEquals($visitorId1, $visitorId2);

        $this->trackPageview($tracker, 'page-4');
        $this->assertCounts([4], 1);

        // log out and de-set user id
        $this->logOutUser($tracker);
        $this->trackPageview($tracker, 'page-5');
        $this->assertCounts([5], 1);

        // expect original visitor id after logging out
        $visitorId3 = $this->getVisitProperty('idvisitor', 1);
        $this->assertEquals($visitorId1, $visitorId3);
        $this->assertEquals($visitorId2, $visitorId3);

        $this->trackPageview($tracker, 'page-6');
        $this->assertCounts([6], 1);
    }

    public function testUserLoggedInOnMultipleDevicesWithoutVisitorIdOverwrite()
    {
        $this->disableUserIdOverwritesVisitorId();
        $trackerDevice1 = $this->getTracker();

        $this->trackPageview($trackerDevice1, 'page-1');
        $this->trackPageview($trackerDevice1, 'page-2');
        $this->trackAction($trackerDevice1, 'action-1');
        $this->logInUser($trackerDevice1);
        $this->trackAction($trackerDevice1, 'log-in');
        $this->assertCounts([4], 1);

        $trackerDevice2 = $this->getTrackerForAlternateDevice();

        $this->trackPageview($trackerDevice2, 'page-3');
        $this->trackPageview($trackerDevice2, 'page-4');
        $this->trackAction($trackerDevice2, 'action-2');
        $this->logInUser($trackerDevice2);
        $this->trackAction($trackerDevice2, 'log-in');

        $this->assertCounts([4, 4], 2);
        $this->assertVisitorIdsCount(2);

        // multiple devices, multiple config IDs
        $this->assertConfigIdsCount(2);
    }

    public function testUserLoggedInOnMultipleDevicesWithoutActionsWithoutVisitorIdOverwrite()
    {
        $this->disableUserIdOverwritesVisitorId();
        $trackerDevice1 = $this->getTracker();

        $this->trackPageview($trackerDevice1, 'page-1');
        $this->trackPageview($trackerDevice1, 'page-2');
        $this->logInUser($trackerDevice1);
        $this->trackPageview($trackerDevice1, 'page-3');
        $this->assertCounts([3], 1);

        $trackerDevice2 = $this->getTrackerForAlternateDevice();

        $this->trackPageview($trackerDevice2, 'page-4');
        $this->trackPageview($trackerDevice2, 'page-5');
        $this->logInUser($trackerDevice2);
        $this->trackPageview($trackerDevice2, 'page-6');

        $this->assertCounts([3, 3], 2);
        $this->assertVisitorIdsCount(2);

        // multiple devices, multiple config IDs
        $this->assertConfigIdsCount(2);
    }

    public function testUserLogsInAndOutMultipleTimesWithoutVisitorIdOverwrite()
    {
        $this->disableUserIdOverwritesVisitorId();
        $tracker = $this->getTracker();

        $this->trackPageview($tracker, 'page-1');
        $this->trackPageview($tracker, 'page-2');
        $this->trackAction($tracker, 'action-1');
        $this->logInUser($tracker);
        $this->trackAction($tracker, 'log-in');
        $visitorId1 = $this->getVisitProperty('idvisitor', 1);

        $this->assertCounts([4], 1);

        $this->trackAction($tracker, 'action-2');
        $this->trackPageview($tracker, 'page-3');

        $this->assertCounts([6], 1);

        $this->logOutUser($tracker);
        $this->trackAction($tracker, 'log-out');
        $visitorId2 = $this->getVisitProperty('idvisitor', 1);

        $this->assertCounts([7], 1, 1);

        // force new visit and log in
        $tracker = $this->getTracker();
        $tracker->setForceNewVisit();

        $this->trackAction($tracker, 'action-3');
        $this->trackPageview($tracker, 'page-4');
        $this->logInUser($tracker);
        $this->trackAction($tracker, 'log-in');
        $visitorId3 = $this->getVisitProperty('idvisitor', 2);

        $this->assertCounts([7, 3], 2);

        $this->trackAction($tracker, 'action-4');
        $this->trackPageview($tracker, 'page-5');

        $this->assertCounts([7, 5], 2);

        $this->logOutUser($tracker);
        $this->trackAction($tracker, 'log-out');
        $visitorId4 = $this->getVisitProperty('idvisitor', 2);
        $this->assertCounts([7, 6], 2);

        $this->assertEquals($visitorId1, $visitorId2);
        $this->assertNotEquals($visitorId1, $visitorId3);
        $this->assertEquals($visitorId3, $visitorId4);
        $this->assertNotEquals($visitorId2, $visitorId4);

        $this->assertUserIdsCount(1);
    }

    public function testNewVisitWithLoginAndLogoutCombinationWithoutVisitorIdOverwrite()
    {
        $this->disableUserIdOverwritesVisitorId();
        // track a first visit (without login)
        $tracker = $this->getTracker();

        $this->trackPageview($tracker, 'page-1');
        $this->trackPageview($tracker, 'page-2');

        $this->assertCounts([2], 1);

        // force a new visit of the same visitor (with login and logout)
        $tracker->setForceNewVisit();
        $this->trackPageview($tracker, 'page-1');

        $this->assertCounts([2, 1], 1);
        $visitorId1 = $this->getVisitProperty('idvisitor', 2);

        $this->logInUser($tracker);
        $this->trackPageview($tracker, 'page-3');

        $visitorId2 = $this->getVisitProperty('idvisitor', 2);
        $this->assertEquals($visitorId1, $visitorId2);
        // forcing a visit so we have two visits with 2 actions each
        $this->assertCounts([2, 2], 1);

        $this->logOutUser($tracker);
        $this->trackPageview($tracker, 'page-5');

        $this->assertCounts([2, 3], 1);
    }

    public function testUserLogsInAndOutMultipleTimesWithoutActionsWithoutVisitorIdOverwrite()
    {
        $this->disableUserIdOverwritesVisitorId();
        $tracker = $this->getTracker();

        $this->trackPageview($tracker, 'page-1');
        $this->trackPageview($tracker, 'page-2');
        $this->logInUser($tracker);
        $this->trackPageview($tracker, 'page-3');
        $visitorId1 = $this->getVisitProperty('idvisitor', 1);

        $this->assertCounts([3], 1);

        $this->trackPageview($tracker, 'page-4');

        $this->assertCounts([4], 1);

        $this->logOutUser($tracker);
        $this->trackPageview($tracker, 'page-5');
        $visitorId2 = $this->getVisitProperty('idvisitor', 1);

        $this->assertCounts([5], 1, 1);

        // force new visit and log in
        $tracker = $this->getTracker();
        $tracker->setForceNewVisit();

        $this->trackPageview($tracker, 'page-6');
        $this->logInUser($tracker);
        $this->trackPageview($tracker, 'page-7');
        $visitorId3 = $this->getVisitProperty('idvisitor', 2);

        $this->assertCounts([5, 2], 2);

        $this->trackPageview($tracker, 'page-5');
        $this->assertCounts([5, 3], 2);

        $this->logOutUser($tracker);
        $this->trackPageview($tracker, 'page-6');
        $visitorId4 = $this->getVisitProperty('idvisitor', 2);
        $this->assertCounts([5, 4], 2);

        $this->assertEquals($visitorId1, $visitorId2);
        $this->assertNotEquals($visitorId1, $visitorId3);
        // since we forced a new visit, the visitor id after second log out is different
        $this->assertEquals($visitorId3, $visitorId4);

        $this->assertUserIdsCount(1);
    }

    public function testNewVisitTriggeredByInactivityWithoutVisitorIdOverwrite()
    {
        $this->disableUserIdOverwritesVisitorId();
        $tracker = $this->getTracker();

        $this->trackPageview($tracker, 'page-1');
        $this->trackPageview($tracker, 'page-2');
        $this->trackAction($tracker, 'action-1');
        $this->logInUser($tracker);
        $this->trackAction($tracker, 'log-in');
        $visitorId1 = $this->getVisitProperty('idvisitor', 1);

        // move time beyond default visit length
        $this->trackerEventTsIterator += Config::getInstance()->Tracker['visit_standard_length'] + 1;

        $this->trackPageview($tracker, 'page-3');
        $this->trackPageview($tracker, 'page-4');
        $this->trackAction($tracker, 'action-5');
        $visitorId2 = $this->getVisitProperty('idvisitor', 2);

        $this->assertCounts([4, 3], 1, 1);
        $this->assertEquals($visitorId1, $visitorId2);

        // move time beyond default visit length
        $this->trackerEventTsIterator += Config::getInstance()->Tracker['visit_standard_length'] + 1;

        $this->trackPageview($tracker, 'page-5');

        $this->assertCounts([4, 3, 1], 1, 1);
    }

    public function testNewVisitTriggeredAtMidnightWithoutVisitorIdOverwrite()
    {
        $this->disableUserIdOverwritesVisitorId();
        $tracker = $this->getTracker();

        $this->trackPageview($tracker, 'page-1');
        $this->trackAction($tracker, 'action-1');

        // move time by a day
        $this->trackerEventTsIterator += 24 * 60 * 60;

        $this->trackPageview($tracker, 'page-2');
        $this->trackAction($tracker, 'action-2');

        $this->assertCounts([2, 2], 1);
    }

    public function testNewVisitWhenCampaignChangesWithoutVisitorIdOverwrite()
    {
        $this->disableUserIdOverwritesVisitorId();
        $tracker = $this->getTracker();

        $tracker->setUrl('http://www.example.com/?utm_campaign=first');
        $this->trackPageview($tracker, 'page-1');

        $tracker->setUrl('http://www.example.com/?utm_campaign=second');
        $this->trackPageview($tracker, 'page-2');

        $this->assertCounts([1, 1], 1);
    }

    private function assertCounts(array $visitsWithActionCount, int $visitorIds, int $userIds = null, int $configIds = null)
    {
        $this->assertVisitsWithActionCount($visitsWithActionCount);
        $this->assertVisitorIdsCount($visitorIds);
        if (!is_null($userIds)) {
            $this->assertUserIdsCount($userIds);
        }
        if (!is_null($configIds)) {
            $this->assertConfigIdsCount($configIds);
        }
    }

    private function assertVisitsWithActionCount($visitsWithActionCount)
    {
        $sql = 'SELECT COUNT(DISTINCT(a.idlink_va)) as actions FROM %1$s v LEFT JOIN %2$s a ON v.idvisit = a.idvisit GROUP BY v.idvisit';
        $visitCount = Db::fetchAll(
            sprintf($sql, Common::prefixTable('log_visit'), Common::prefixTable('log_link_visit_action'))
        );
        $this->assertEquals($visitsWithActionCount, array_column($visitCount, 'actions'));
    }

    private function assertActionCount($expected)
    {
        $visitCount = Db::fetchOne("SELECT COUNT(*) FROM " . Common::prefixTable('log_link_visit_action'));
        $this->assertEquals($expected, $visitCount);
    }

    private function assertVisitorIdsCount($expected)
    {
        $visitorIdsCount = Db::fetchOne("SELECT COUNT(DISTINCT idvisitor) FROM " . Common::prefixTable('log_visit'));
        $this->assertEquals($expected, $visitorIdsCount);
    }

    private function assertUserIdsCount($expected)
    {
        $visitorIdsCount = Db::fetchOne("SELECT COUNT(DISTINCT user_id) FROM " . Common::prefixTable('log_visit'));
        $this->assertEquals($expected, $visitorIdsCount);
    }

    private function assertConfigIdsCount($expected)
    {
        $visitorIdsCount = Db::fetchOne("SELECT COUNT(DISTINCT config_id) FROM " . Common::prefixTable('log_visit'));
        $this->assertEquals($expected, $visitorIdsCount);
    }

    private function getVisitProperty($columnName, $idVisit)
    {
        return Db::fetchOne("SELECT $columnName FROM " . Common::prefixTable('log_visit') . " WHERE idvisit = ?", array($idVisit));
    }

    protected static function configureFixture($fixture)
    {
        parent::configureFixture($fixture);
        $fixture->createSuperUser = true;
    }
}
