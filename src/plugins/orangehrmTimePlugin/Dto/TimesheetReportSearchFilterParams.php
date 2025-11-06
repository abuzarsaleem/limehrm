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

namespace OrangeHRM\Time\Dto;

use InvalidArgumentException;
use OrangeHRM\Leave\Dto\DateRangeSearchFilterParams;
use OrangeHRM\Pim\Dto\Traits\SubunitIdChainTrait;

class TimesheetReportSearchFilterParams extends DateRangeSearchFilterParams
{
    use SubunitIdChainTrait;

    public const ALLOWED_SORT_FIELDS = ['employee.lastName', 'timesheetItem.date', 'project.name', 'projectActivity.name'];

    public const INCLUDE_TIMESHEETS_APPROVED_ONLY = 'onlyApproved';
    public const INCLUDE_TIMESHEETS_ALL = 'all';

    public const INCLUDE_TIMESHEETS = [
        self::INCLUDE_TIMESHEETS_APPROVED_ONLY,
        self::INCLUDE_TIMESHEETS_ALL,
    ];

    public const TIMESHEET_APPROVED_STATE = 'APPROVED';

    /**
     * @var int[]|null
     */
    private ?array $employeeNumbers = null;

    /**
     * @var int|null
     */
    private ?int $jobTitleId = null;

    /**
     * @var int|null
     */
    private ?int $subUnitId = null;

    /**
     * @var int|null
     */
    private ?int $employmentStatusId = null;

    /**
     * @var int|null
     */
    private ?int $projectId = null;

    /**
     * @var int|null
     */
    private ?int $activityId = null;

    /**
     * @var string
     */
    private string $includeTimesheets = self::INCLUDE_TIMESHEETS_ALL;

    public function __construct()
    {
        $this->setSortField('employee.lastName');
    }

    /**
     * @return int[]|null
     */
    public function getEmployeeNumbers(): ?array
    {
        return $this->employeeNumbers;
    }

    /**
     * @param int[]|null $employeeNumbers
     */
    public function setEmployeeNumbers(?array $employeeNumbers): void
    {
        $this->employeeNumbers = $employeeNumbers;
    }

    /**
     * @return int|null
     */
    public function getJobTitleId(): ?int
    {
        return $this->jobTitleId;
    }

    /**
     * @param int|null $jobTitleId
     */
    public function setJobTitleId(?int $jobTitleId): void
    {
        $this->jobTitleId = $jobTitleId;
    }

    /**
     * @return int|null
     */
    public function getSubUnitId(): ?int
    {
        return $this->subUnitId;
    }

    /**
     * @param int|null $subUnitId
     */
    public function setSubUnitId(?int $subUnitId): void
    {
        $this->subUnitId = $subUnitId;
    }

    /**
     * @return int|null
     */
    public function getEmploymentStatusId(): ?int
    {
        return $this->employmentStatusId;
    }

    /**
     * @param int|null $employmentStatusId
     */
    public function setEmploymentStatusId(?int $employmentStatusId): void
    {
        $this->employmentStatusId = $employmentStatusId;
    }

    /**
     * @return int|null
     */
    public function getProjectId(): ?int
    {
        return $this->projectId;
    }

    /**
     * @param int|null $projectId
     */
    public function setProjectId(?int $projectId): void
    {
        $this->projectId = $projectId;
    }

    /**
     * @return int|null
     */
    public function getActivityId(): ?int
    {
        return $this->activityId;
    }

    /**
     * @param int|null $activityId
     */
    public function setActivityId(?int $activityId): void
    {
        $this->activityId = $activityId;
    }

    /**
     * @return string
     */
    public function getIncludeTimesheets(): string
    {
        return $this->includeTimesheets;
    }

    /**
     * @param string|null $includeTimesheets
     * @return void
     */
    public function setIncludeTimesheets(?string $includeTimesheets): void
    {
        if (is_null($includeTimesheets)) {
            return;
        }
        if (!in_array($includeTimesheets, self::INCLUDE_TIMESHEETS)) {
            throw new InvalidArgumentException();
        }
        $this->includeTimesheets = $includeTimesheets;
    }
}

