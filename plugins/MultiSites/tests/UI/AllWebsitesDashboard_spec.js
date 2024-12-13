/*!
 * Matomo - free/libre analytics platform
 *
 * Screenshot integration tests for MultiSites.
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

describe('AllWebsitesDashboard', function () {
    this.fixture = 'Piwik\\Plugins\\MultiSites\\tests\\Fixtures\\ManySitesWithVisits';

    const parentSuite = this;

    const generalParams = 'idSite=1&period=day&date=2013-01-23';
    const dashboardUrl = '?module=MultiSites&action=index&' + generalParams;
    const widgetUrl = '?module=Widgetize&action=iframe&moduleToWidgetize=MultiSites&actionToWidgetize=standalone&' + generalParams;

    before(function() {
        testEnvironment.overrideConfig('FeatureFlags', {
            ImprovedAllWebsitesDashboard_feature: 'enabled',
        });

        // split 15 fixture sites into 2 pages
        testEnvironment.overrideConfig('General', {
            all_websites_website_per_page: 10,
        });

        testEnvironment.save();
    });

    beforeEach(async function() {
        // set in beforeEach() to have it set in each describe()
        await page.webpage.setViewport({
            width: 1440,
            height: 900,
        });
    });

    after(function () {
        delete testEnvironment.configOverride.FeatureFlags;
        delete testEnvironment.configOverride.General;

        testEnvironment.save();
    });

    async function getSitesPagination() {
        const pagination = await page.$('.sitesTablePagination .dataTablePages');
        const paginationContent = await pagination.getProperty('textContent');

        return (await paginationContent.jsonValue()).trim();
    }

    async function getSitesTableCell(rowIndex, cellIndex) {
        const cellSelector = `.sitesTableSite:nth-child(${rowIndex}) td:nth-child(${cellIndex})`;

        const cell = await page.$(cellSelector);
        const cellContent = await cell.getProperty('textContent');

        return (await cellContent.jsonValue()).trim();
    }

    describe('Rendering', function () {
        this.title = parentSuite.title; // to make sure the screenshot prefix is the same

        afterEach(function() {
            delete testEnvironment.configOverride.General.show_multisites_sparklines;
            delete testEnvironment.pluginsToUnload;

            testEnvironment.save();
        });

        it('should load the all websites dashboard correctly', async function () {
            await page.goto(dashboardUrl);
            await page.waitForNetworkIdle();

            expect(await page.screenshotSelector('#main')).to.matchImage('dashboard');
        });

        it('should render properly when widgetized', async function () {
            await page.goto(widgetUrl);
            await page.waitForNetworkIdle();

            expect(await page.screenshotSelector('#main')).to.matchImage('widgetized');
        });

        it('should not display revenue if disabled', async function () {
            testEnvironment.pluginsToUnload = ['Goals'];
            testEnvironment.save();

            await page.goto(dashboardUrl);
            await page.waitForNetworkIdle();

            expect(await page.screenshotSelector('#main')).to.matchImage('no_revenue');
        });

        it('should not display sparklines if disabled', async function () {
            testEnvironment.overrideConfig('General', 'show_multisites_sparklines', 0);
            testEnvironment.save();

            await page.goto(dashboardUrl);
            await page.waitForNetworkIdle();

            expect(await page.screenshotSelector('#main')).to.matchImage('no_sparklines');
        });

        it('should correctly display a KPI badge when added through event', async function () {
            await page.goto(dashboardUrl);
            await page.waitForNetworkIdle();

            await page.evaluate(() => {
              window.CoreHome.Matomo.on('MultiSites.DashboardKPIs.updated', function(data) {
                  data.kpis.badges.hits = '<strong>Plan:</strong> 600K hits/month';
              })
            });

            // change period to trigger reload of KPIS
            await page.click('.move-period-prev');
            await page.click('.move-period-next');
            await page.waitForNetworkIdle();

            expect(await page.screenshotSelector('#main')).to.matchImage('dashboard_kpi_badge');
        });

        it('should correctly display all badges when added through event', async function () {
            await page.goto(dashboardUrl);
            await page.waitForNetworkIdle();

            await page.evaluate(() => {
              window.CoreHome.Matomo.on('MultiSites.DashboardKPIs.updated', function(data) {
                  data.kpis.badges.hits = '<strong>Plan:</strong> 600K hits/month';
                  data.kpis.badges.pageviews = 'Weird Pageview Badge';
                  data.kpis.badges.revenue = 'Awesome Revenue Badge';
                  data.kpis.badges.visits = 'Terrific Visits Badge';
              })
            });

            // change period to trigger reload of KPIS
            await page.click('.move-period-prev');
            await page.click('.move-period-next');
            await page.waitForNetworkIdle();

            expect(await page.screenshotSelector('#main')).to.matchImage('dashboard_all_badges');
        });
    });

    describe('Dashboard Controls', function () {
        it('should link to the SitesManager', async function () {
            await page.goto(dashboardUrl);
            await page.waitForNetworkIdle();
            await page.click('.dashboardControls .btn');
            await page.waitForNetworkIdle();

            await page.waitForSelector('.modal .add-site-dialog', { visible: true });
        });

        it('should allow searching', async function () {
            await page.goto(dashboardUrl);
            await page.waitForNetworkIdle();

            expect(await getSitesTableCell(1, 1)).to.equal('Site 1');
            expect(await getSitesPagination()).to.equal('1–10 of 15');

            await page.type('.siteSearch input', 'Site 15');
            await page.click('.siteSearch .icon-search');
            await page.waitForNetworkIdle();

            expect(await getSitesTableCell(1, 1)).to.equal('Site 15');
            expect(await getSitesPagination()).to.equal('1–1 of 1');

            await page.type('.siteSearch input', 'No Results');
            await page.click('.siteSearch .icon-search');
            await page.waitForNetworkIdle();

            expect(await getSitesPagination()).to.equal('0–0 of 0');
        });
    });

    describe('Loading Error', function () {
        this.title = parentSuite.title; // to make sure the screenshot prefix is the same

        before(function () {
            testEnvironment.forceMultiSitesDashboardFailure = 1;
            testEnvironment.save();
        });

        after(function () {
            delete testEnvironment.forceMultiSitesDashboardFailure;

            testEnvironment.save();
        });

        it('should display an error message', async function () {
            await page.goto(dashboardUrl);
            await page.waitForNetworkIdle();

            expect(await page.screenshotSelector('#main')).to.matchImage('error');
        });
    });

    describe('Period Selector', function () {
        async function getPeriodSelectorTitle() {
            const periodSelector = await page.$('.periodSelector .title');
            const periodSelectorTitle = await periodSelector.getProperty('textContent');

            return (await periodSelectorTitle.jsonValue()).trim();
        }

        it('should allow changing periods', async function () {
            await page.goto(dashboardUrl);
            await page.waitForNetworkIdle();

            expect(await getPeriodSelectorTitle()).to.equal('2013-01-23');
            expect(await getSitesTableCell(1, 2)).to.equal('2');

            await page.click('.periodSelector .move-period-prev');
            await page.waitForNetworkIdle();

            expect(await getPeriodSelectorTitle()).to.equal('2013-01-22');
            expect(await getSitesTableCell(1, 2)).to.equal('0');
        });
    });

    describe('Sites Table', function () {
        this.title = parentSuite.title; // to make sure the screenshot prefix is the same

        it('should allow reversing the default sorting', async function () {
            await page.goto(dashboardUrl);
            await page.waitForNetworkIdle();

            expect(await getSitesTableCell(1, 1)).to.equal('Site 1');
            expect(await getSitesTableCell(1, 2)).to.equal('2');
            expect(await getSitesTableCell(2, 1)).to.equal('Site 2');
            expect(await getSitesTableCell(2, 2)).to.equal('1');

            // reverse default "visits" sorting
            await page.click('.sitesTableSort.sitesTableSortDesc');
            await page.waitForNetworkIdle();

            expect(await getSitesTableCell(1, 1)).to.not.equal('Site 1');
            expect(await getSitesTableCell(1, 2)).to.equal('0');
        });

        it('should allow navigation through pages', async function () {
            // sort by label for consistency
            await page.click('.sitesTable th:nth-child(1)');
            await page.waitForNetworkIdle();

            expect(await getSitesTableCell(1, 1)).to.equal('Site 1');

            await page.click('.sitesTablePagination .dataTableNext');
            await page.waitForNetworkIdle();

            expect(await getSitesTableCell(1, 1)).to.equal('Site 11');
            expect(await getSitesPagination()).to.equal('11–15 of 15');

            await page.click('.sitesTablePagination .dataTablePrevious');
            await page.waitForNetworkIdle();

            expect(await getSitesTableCell(1, 1)).to.equal('Site 1');
            expect(await getSitesPagination()).to.equal('1–10 of 15');
        });

        it('should allow sorting by other metrics', async function () {
            // sort by "hits"
            await page.click('.sitesTable th:nth-child(4)');
            await page.waitForNetworkIdle();

            expect(await getSitesTableCell(1, 1)).to.equal('Site 1');
            expect(await getSitesTableCell(1, 4)).to.equal('4');
            expect(await getSitesTableCell(2, 1)).to.equal('Site 3');
            expect(await getSitesTableCell(2, 4)).to.equal('4');

            // reverse sorting
            await page.click('.sitesTable th:nth-child(4)');
            await page.waitForNetworkIdle();

            expect(await getSitesTableCell(1, 1)).to.not.equal('Site 1');
            expect(await getSitesTableCell(1, 4)).to.equal('0');
        });

        it('should allow changing the evolution metric', async function () {
            // select "Hits" evolution
            await page.evaluate(() => {
                const hitsOption = $('.sitesTableEvolutionSelector option:contains("Hits")');
                const select = $('.sitesTableEvolutionSelector select');

                select.val(hitsOption.val()).change();
            });

            await page.waitForNetworkIdle();

            expect(await page.screenshotSelector('#main')).to.matchImage('evolution_change');
        });
    });

    describe('Responsive View', function () {
        this.title = parentSuite.title; // to make sure the screenshot prefix is the same

        it('should display correctly in tablet view', async function () {
            await page.webpage.setViewport({ width: 768, height: 1024 });
            await page.goto(dashboardUrl);
            await page.waitForNetworkIdle();

            expect(await page.screenshotSelector('#main')).to.matchImage('dashboard_tablet');
        });

        it('should display correctly in mobile view', async function () {
            await page.webpage.setViewport({ width: 352, height: 1024 });

            expect(await page.screenshotSelector('#main')).to.matchImage('dashboard_mobile');
        });
    });
});
