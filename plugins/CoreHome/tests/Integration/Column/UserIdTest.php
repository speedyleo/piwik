<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CoreHome\tests\Integration\Column;

use Piwik\Access;
use Piwik\Cache;
use Piwik\DataAccess\ArchiveTableCreator;
use Piwik\Db;
use Piwik\Plugins\CoreHome\Columns\UserId;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\Mock\FakeAccess;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\DataTable;

/**
 * @group CoreHome
 * @group UserIdTest
 * @group Plugins
 * @group Column
 */
class UserIdTest extends IntegrationTestCase
{
    /**
     * @var UserId
     */
    private $userId;

    protected $date = '2014-04-04';

    public function setUp()
    {
        parent::setUp();
        $this->userId = new UserId();

        $this->setSuperUser();

        Fixture::createSuperUser();
        Fixture::createWebsite('2014-01-01 00:00:00');
        Fixture::createWebsite('2014-01-01 00:00:00');
    }

    public function tearDown()
    {
        // clean up your test here if needed
        $tables = ArchiveTableCreator::getTablesArchivesInstalled();
        if (!empty($tables)) {
            Db::dropTables($tables);
        }
        parent::tearDown();
    }

    public function test_isUsedInAtLeastOneSite_shouldReturnFalseByDefault_WhenNothingIsTracked()
    {
        $this->assertNotUsedInAtLeastOneSite($idSites = array(1), 'day', $this->date);
    }

    public function test_isUsedInAtLeastOneSite_shouldCache()
    {
        $key   = '1.month.' . $this->date;
        $cache = Cache::getTransientCache();
        $this->assertFalse($cache->contains($key));

        $this->userId->isUsedInAtLeastOneSite($idSites = array(1), 'day', $this->date);

        $this->assertTrue($cache->contains($key));
        $this->assertFalse($cache->fetch($key));
    }

    public function test_isUsedInAtLeastOneSite_shouldDetectUserIdWasUsedInAllSites_WhenOneSiteGiven()
    {
        $this->trackPageviewsWithUsers();

        $this->assertUsedInAtLeastOneSite($idSites = array(1), 'day', $this->date);
    }

    public function test_isUsedInAtLeastOneSite_shouldDetectUserIdWasUsedInAtLeastOneSite_WhenMultipleSitesGiven()
    {
        $this->trackPageviewsWithUsers();

        $this->assertUsedInAtLeastOneSite($idSites = array(1,2), 'day', $this->date);
    }

    public function test_isUsedInAtLeastOneSite_shouldDetectUserIdWasNotUsedInAtLeastOneSite_WhenMultipleSitesGiven()
    {
        $this->trackPageviewsWithoutUsers();

        $this->assertNotUsedInAtLeastOneSite($idSites = array(1,2), 'day', $this->date);
    }

    public function test_isUsedInAtLeastOneSite_shouldDetectUserIdWasNotUsed_WhenOneSiteGiven()
    {
        $this->trackPageviewsWithUsers();

        $this->assertNotUsedInAtLeastOneSite($idSites = array(2), 'day', $this->date);
    }

    public function test_isUsedInAtLeastOneSite_shouldDefaultToMonthPeriodAndDetectUserIdIsUsedAlthoughNotTodayButYesterday()
    {
        $this->trackPageviewsWithUsers();

        $this->assertUsedInAtLeastOneSite($idSites = array(1), 'day', '2014-04-03');
    }

    public function test_isUsedInAtLeastOneSite_shouldDefaultToMonthPeriodAndDetectUserIdIsUsedAlthoughNotTodayButTomorrow()
    {
        $this->trackPageviewsWithUsers();

        $this->assertUsedInAtLeastOneSite($idSites = array(1), 'day', '2014-04-05');
    }

    public function test_isUsedInAtLeastOneSite_shouldDetectItWasNotUsedInMarchAlthoughItWasUsedInApril()
    {
        $this->trackPageviewsWithUsers();

        $this->assertNotUsedInAtLeastOneSite($idSites = array(1), 'day', '2014-03-04');
    }

    public function test_isUsedInAtLeastOneSite_shouldDetectItCorrectWithRangeDates()
    {
        $this->trackPageviewsWithUsers();

        $this->assertUsedInAtLeastOneSite($idSites = array(1), 'range', '2014-04-01,2014-05-05');

        // not used in that range date
        $this->assertNotUsedInAtLeastOneSite($idSites = array(1), 'range', '2014-04-01,2014-04-03');
    }

    private function assertUsedInAtLeastOneSite($idSites, $period, $date)
    {
        $result = $this->userId->isUsedInAtLeastOneSite($idSites, $period, $date);

        $this->assertTrue($result);
    }

    private function assertNotUsedInAtLeastOneSite($idSites, $period, $date)
    {
        $result = $this->userId->isUsedInAtLeastOneSite($idSites, $period, $date);

        $this->assertFalse($result);
    }

    private function trackPageviewsWithUsers()
    {
        $this->trackPageviewsWithDifferentUsers(array('user1', false, 'user3'));
    }

    private function trackPageviewsWithoutUsers()
    {
        $this->trackPageviewsWithDifferentUsers(array(false, false, false));
    }

    private function trackPageviewsWithDifferentUsers($userIds)
    {
        $tracker = $this->getTracker();

        foreach ($userIds as $index => $userId) {
            $tracker->setForceNewVisit();
            $this->trackPageview($tracker, $userId, '/index/' . $index . '.html');
        }
    }

    private function trackPageview(\PiwikTracker $tracker, $userId, $url = null)
    {
        if (null !== $url) {
            $tracker->setUrl('http://www.example.org' . $url);
        }

        $tracker->setUserId($userId);

        $title = $url ? : 'test';

        $tracker->doTrackPageView($title);
    }

    private function getTracker()
    {
        $tracker = Fixture::getTracker(1, $this->date . ' 00:01:01', true, true);
        $tracker->setTokenAuth(Fixture::getTokenAuth());
        return $tracker;
    }

    private function setSuperUser()
    {
        $pseudoMockAccess = new FakeAccess();
        $pseudoMockAccess::setSuperUserAccess(true);
        Access::setSingletonInstance($pseudoMockAccess);
    }

}