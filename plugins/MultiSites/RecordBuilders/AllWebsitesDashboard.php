<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\MultiSites\RecordBuilders;

use Piwik\Archive;
use Piwik\ArchiveProcessor;
use Piwik\ArchiveProcessor\Record;
use Piwik\ArchiveProcessor\RecordBuilder;
use Piwik\Plugins\MultiSites\API;

class AllWebsitesDashboard extends RecordBuilder
{
    public const RECORD_NAME = 'MultiSites_AllWebsitesDashboard';

    public function getRecordMetadata(ArchiveProcessor $archiveProcessor): array
    {
        return [
            Record::make(Record::TYPE_BLOB, self::RECORD_NAME),
        ];
    }

    protected function aggregate(ArchiveProcessor $archiveProcessor): array
    {
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

        $params = $archiveProcessor->getParams();
        $period = $params->getPeriod();
        $archive = Archive::build($params->getIdSites(), $period->getLabel(), $period->toString(), $params->getSegment());
        $archive->forceFetchingWithoutLaunchingArchiving();
        $dataTable = $archive->getDataTableFromNumericAndMergeChildren($fieldsToGet);

        API::getInstance()->populateLabel($dataTable);

        return [self::RECORD_NAME => $dataTable];
    }
}
