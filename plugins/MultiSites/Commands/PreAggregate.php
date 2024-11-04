<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\MultiSites\Commands;

use Piwik\Archive;
use Piwik\ArchiveProcessor\Parameters;
use Piwik\DataAccess\ArchiveWriter;
use Piwik\Period\Range;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\MultiSites\API;
use Piwik\Plugins\SitesManager\API as APISitesManager;
use Piwik\Segment;
use Piwik\Site;

class PreAggregate extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('multi-sites:pre-aggregate');
    }

    protected function doExecute(): int
    {
        $input = $this->getInput();
        $output = $this->getOutput();

        $range = new Range('day', '2024-01-01,2024-03-31');
        $multiSitesApi = API::getInstance();

        foreach ($range->getSubperiods() as $period) {
            $segment = new Segment('', []);
            $site = new Site(0);

            $archiveParams = new Parameters($site, $period, $segment);
            $archiveParams->setRequestedPlugin('MultiSites');
            $archiveWriter = new ArchiveWriter($archiveParams);

            // build the archive type used to query archive data
            $archive = Archive::build('all', $period->getLabel(), $period->toString(), $segment);

            // determine what data will be displayed
            $fieldsToGet = [];
            $columnNameRewrites = [];
            $apiECommerceMetrics = [];

            $apiMetrics = API::getApiMetrics(true);

            foreach ($apiMetrics as $metricName => $metricSettings) {
                if (!empty($showColumns) && !in_array($metricName, $showColumns)) {
                    unset($apiMetrics[$metricName]);
                    continue;
                }

                $fieldsToGet[] = $metricSettings[API::METRIC_RECORD_NAME_KEY];
                $columnNameRewrites[$metricSettings[API::METRIC_RECORD_NAME_KEY]] = $metricName;

                if ($metricSettings[API::METRIC_IS_ECOMMERCE_KEY]) {
                    $apiECommerceMetrics[$metricName] = $metricSettings;
                }
            }

            $dataTable = $archive->getDataTableFromNumericAndMergeChildren($fieldsToGet);
            $multiSitesApi->populateLabel($dataTable);

            $archiveWriter->initNewArchive();
            $archiveWriter->insertBlobRecord('MultiSites_AllWebsitesDashboard', $dataTable->getSerialized());
            $archiveWriter->finalizeArchive();
        }

        return self::SUCCESS;
    }
}
