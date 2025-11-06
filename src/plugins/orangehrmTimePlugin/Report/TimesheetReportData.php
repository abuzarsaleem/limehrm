<?php
/**
 * OrangeHRM is a comprehensive Human Resource Management (HRM) System that captures
 * all the essential functionalities required for any enterprise.
 * Copyright (C) 2006 OrangeHRM Inc., http://www.orangehrm.com
 *
 * OrangeHRM is free software: you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * OrangeHRM is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with OrangeHRM.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace OrangeHRM\Time\Report;

use OrangeHRM\Core\Api\CommonParams;
use OrangeHRM\Core\Api\V2\ParameterBag;
use OrangeHRM\Core\Report\ReportData;
use OrangeHRM\Core\Traits\Service\NumberHelperTrait;
use OrangeHRM\I18N\Traits\Service\I18NHelperTrait;
use OrangeHRM\Time\Dto\TimesheetReportSearchFilterParams;
use OrangeHRM\Time\Traits\Service\TimesheetServiceTrait;

class TimesheetReportData implements ReportData
{
    use TimesheetServiceTrait;
    use NumberHelperTrait;
    use I18NHelperTrait;

    /**
     * @var TimesheetReportSearchFilterParams
     */
    private TimesheetReportSearchFilterParams $filterParams;

    public function __construct(TimesheetReportSearchFilterParams $filterParams)
    {
        $this->filterParams = $filterParams;
    }

    /**
     * @inheritDoc
     */
    public function normalize(): array
    {
        $timesheetRecords = $this->getTimesheetService()
            ->getTimesheetDao()
            ->getTimesheetReportCriteriaList($this->filterParams);

        $result = [];
        foreach ($timesheetRecords as $record) {
            $termination = $record['terminationId'];
            $date = $record['date'] instanceof \DateTime 
                ? $record['date']->format('Y-m-d') 
                : $record['date'];
            
            $result[] = [
                TimesheetReport::PARAMETER_EMPLOYEE_NAME => $termination === null 
                    ? $record['fullName'] 
                    : $record['fullName'] . ' ' . $this->getI18NHelper()->transBySource('(Past Employee)'),
                TimesheetReport::PARAMETER_DATE => $date,
                TimesheetReport::PARAMETER_PROJECT_NAME => $record['projectName'] ?? '',
                TimesheetReport::PARAMETER_ACTIVITY_NAME => $record['activityName'] ?? '',
                TimesheetReport::PARAMETER_DURATION => $this->getNumberHelper()
                    ->numberFormat((float)($record['duration'] ?? 0) / 3600, 2),
                TimesheetReport::PARAMETER_COMMENT => $record['comment'] ?? '',
            ];
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getMeta(): ?ParameterBag
    {
        $total = $this->getTimesheetService()
            ->getTimesheetDao()
            ->getTotalTimesheetDuration($this->filterParams);

        return new ParameterBag(
            [
                CommonParams::PARAMETER_TOTAL => $this->getTimesheetService()
                    ->getTimesheetDao()
                    ->getTimesheetReportCriteriaListCount($this->filterParams),
                'sum' => [
                    'hours' => floor($total / 3600),
                    'minutes' => ($total / 60) % 60,
                    'label' => $this->getNumberHelper()->numberFormat($total / 3600, 2),
                ],
            ]
        );
    }
}

