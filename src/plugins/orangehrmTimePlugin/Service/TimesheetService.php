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

namespace OrangeHRM\Time\Service;

use DateTime;
use LogicException;
use OrangeHRM\Core\Service\AccessFlowStateMachineService;
use OrangeHRM\Core\Traits\Service\DateTimeHelperTrait;
use OrangeHRM\Core\Traits\UserRoleManagerTrait;
use OrangeHRM\Entity\Employee;
use OrangeHRM\Entity\Timesheet;
use OrangeHRM\Entity\TimesheetItem;
use OrangeHRM\Entity\WorkflowStateMachine;
use OrangeHRM\Time\Dao\TimesheetDao;
use OrangeHRM\Time\Dto\DetailedTimesheet;
use OrangeHRM\Time\Dto\TimesheetColumn;
use OrangeHRM\Time\Dto\TimesheetRow;

class TimesheetService
{
    use DateTimeHelperTrait;
    use UserRoleManagerTrait;

    public const TIMESHEET_ACTION_MAP = [
        '0' => 'VIEW',
        '1' => 'SUBMIT',
        '2' => 'APPROVE',
        '3' => 'REJECT',
        '4' => 'RESET',
        '5' => 'MODIFY',
        '6' => 'CREATE',
    ];

    private ?TimesheetDao $timesheetDao = null;

    /**
     * @var TimesheetPeriodService|null
     */
    private ?TimesheetPeriodService $timesheetPeriodService = null;

    /**
     * @var AccessFlowStateMachineService|null
     */
    private ?AccessFlowStateMachineService $accessFlowStateMachineService = null;

    /**
     * @return AccessFlowStateMachineService
     */
    protected function getAccessFlowStateMachineService(): AccessFlowStateMachineService
    {
        if (is_null($this->accessFlowStateMachineService)) {
            $this->accessFlowStateMachineService = new AccessFlowStateMachineService();
        }
        return $this->accessFlowStateMachineService;
    }

    /**
     * @return TimesheetDao
     */
    public function getTimesheetDao(): TimesheetDao
    {
        if (is_null($this->timesheetDao)) {
            $this->timesheetDao = new TimesheetDao();
        }
        return $this->timesheetDao;
    }

    /**
     * @return TimesheetPeriodService
     */
    public function getTimesheetPeriodService(): TimesheetPeriodService
    {
        if (is_null($this->timesheetPeriodService)) {
            $this->timesheetPeriodService = new TimesheetPeriodService();
        }

        return $this->timesheetPeriodService;
    }

    /**
     * @param int $timesheetId
     * @return DetailedTimesheet
     */
    public function getDetailedTimesheet(int $timesheetId): DetailedTimesheet
    {
        $timesheet = $this->getTimesheetDao()->getTimesheetById($timesheetId);
        list($timesheetRows, $timesheetColumns) = $this->getTimesheetData($timesheet);
        return new DetailedTimesheet($timesheet, array_values($timesheetRows), array_values($timesheetColumns));
    }

    /**
     * @param Timesheet $timesheet
     * @return array[]
     */
    protected function getTimesheetData(Timesheet $timesheet): array
    {
        $timesheetDates = $this->getDateTimeHelper()->dateRange($timesheet->getStartDate(), $timesheet->getEndDate());
        $timesheetItems = $this->getTimesheetDao()->getTimesheetItemsByTimesheetId($timesheet->getId());

        $timesheetRows = [];
        $timesheetColumns = [];
        foreach ($timesheetDates as $timesheetDate) {
            $date = $this->getDateTimeHelper()->formatDateTimeToYmd($timesheetDate);
            if (!isset($timesheetColumns[$date])) {
                $timesheetColumns[$date] = new TimesheetColumn($timesheetDate);
            }
        }
        
        // Group items by project/activity, but create separate rows when items have the same date
        // to support multiple rows with same project/activity
        $itemsByRowKey = [];
        foreach ($timesheetItems as $timesheetItem) {
            $projectId = $timesheetItem->getProject()->getId();
            $projectActivityId = $timesheetItem->getProjectActivity()->getId();
            $date = $this->getDateTimeHelper()->formatDateTimeToYmd($timesheetItem->getDate());
            
            // Create a unique key that includes the item ID to allow multiple rows with same project/activity/date
            // If an item already exists for this project/activity/date, use the item ID to create a separate row
            $baseRowKey = "{$projectId}_{$projectActivityId}";
            $rowKey = $baseRowKey;
            $rowIndex = 0;
            
            // Check if we need to create a separate row (if date already assigned in existing row)
            while (isset($itemsByRowKey[$rowKey]) && isset($itemsByRowKey[$rowKey][$date])) {
                // Date already exists in this row, try next row
                $rowIndex++;
                $rowKey = "{$baseRowKey}_{$rowIndex}";
            }
            
            if (!isset($itemsByRowKey[$rowKey])) {
                $itemsByRowKey[$rowKey] = [];
            }
            $itemsByRowKey[$rowKey][$date] = $timesheetItem;
        }
        
        // Create TimesheetRow objects from grouped items
        foreach ($itemsByRowKey as $rowKey => $itemsByDate) {
            // Get project and activity from first item
            $firstItem = reset($itemsByDate);
            $projectId = $firstItem->getProject()->getId();
            $projectActivityId = $firstItem->getProjectActivity()->getId();
            
            $timesheetRow = new TimesheetRow(
                $firstItem->getProject(),
                $firstItem->getProjectActivity(),
                $timesheetDates
            );
            
            foreach ($itemsByDate as $date => $timesheetItem) {
                if (!is_null($timesheetItem->getDuration())) {
                    $timesheetRow->incrementTotal($timesheetItem->getDuration());
                    if ($timesheetColumns[$date] instanceof TimesheetColumn) {
                        $timesheetColumns[$date]->incrementTotal($timesheetItem->getDuration());
                    }
                }
                // Use reflection or direct assignment to bypass the duplicate check
                // since we've already handled grouping above
                $reflection = new \ReflectionClass($timesheetRow);
                $datesProperty = $reflection->getProperty('dates');
                $datesProperty->setAccessible(true);
                $dates = $datesProperty->getValue($timesheetRow);
                $dates[$date] = $timesheetItem;
                $datesProperty->setValue($timesheetRow, $dates);
            }
            
            $timesheetRows[$rowKey] = $timesheetRow;
        }
        
        return [$timesheetRows, $timesheetColumns];
    }

    /**
     * @param Timesheet $timesheet
     * @param DateTime $date
     * @return Timesheet
     */
    public function createTimesheetByDate(Timesheet $timesheet, DateTime $date): Timesheet
    {
        $nextState = $this->getAccessFlowStateMachineService()->getNextState(
            WorkflowStateMachine::FLOW_TIME_TIMESHEET,
            Timesheet::STATE_INITIAL,
            'SYSTEM',
            WorkflowStateMachine::TIMESHEET_ACTION_CREATE
        );
        list($startDate, $endDate) = $this->extractStartDateAndEndDateFromDate($date);
        $timesheet->setState($nextState);
        $timesheet->setStartDate(new DateTime($startDate));
        $timesheet->setEndDate(new DateTime($endDate));
        return $this->getTimesheetDao()->saveTimesheet($timesheet);
    }

    /**
     * @param DateTime $date
     * @return array  e.g array(if monday as first day in config => '2021-12-13', '2021-12-19')
     */
    public function extractStartDateAndEndDateFromDate(DateTime $date): array
    {
        $weekStartDateIndex = $this->getTimesheetPeriodService()->getTimesheetStartDate();
        return $this->getDateTimeHelper()->getWeekBoundaryForGivenDate($date, $weekStartDateIndex);
    }

    /**
     * @param int $employeeNumber
     * @param DateTime $date
     * @return bool
     */
    public function hasTimesheetForDate(int $employeeNumber, DateTime $date): bool
    {
        list($startDate) = $this->extractStartDateAndEndDateFromDate($date);
        return $this->getTimesheetDao()->hasTimesheetForStartDate($employeeNumber, new DateTime($startDate));
    }

    /**
     * @param Timesheet $timesheet
     * @param array $rows
     * @return array<string, TimesheetItem>
     */
    protected function createTimesheetItemsFromRows(Timesheet $timesheet, array $rows): array
    {
        $timesheetItems = [];
        foreach ($rows as $row) {
            if (!(isset($row['projectId']) &&
                isset($row['activityId']) &&
                isset($row['dates']))) {
                throw new LogicException('`projectId` & `activityId` & `dates` required attributes');
            }

            foreach ($row['dates'] as $date => $dateValue) {
                if (!isset($dateValue['duration'])) {
                    throw new LogicException('`duration` required attribute');
                }
                $date = new DateTime($date);
                
                // Use item ID as key if provided, otherwise use generated key
                // This allows us to match existing items correctly when multiple items
                // have the same project/activity/date combination
                $itemId = isset($dateValue['id']) && $dateValue['id'] > 0 ? (int)$dateValue['id'] : null;
                $itemKey = $itemId !== null 
                    ? 'id_' . $itemId 
                    : $this->generateTimesheetItemKey(
                        $timesheet->getId(),
                        $row['projectId'],
                        $row['activityId'],
                        $date
                    );
                
                $timesheetItem = new TimesheetItem();
                $timesheetItem->setTimesheet($timesheet);
                $timesheetItem->setEmployee($timesheet->getEmployee());
                $timesheetItem->getDecorator()->setProjectById($row['projectId']);
                $timesheetItem->getDecorator()->setProjectActivityById($row['activityId']);
                $timesheetItem->setDate($date);
                $timesheetItem->setDuration(strtotime($dateValue['duration']) - strtotime('TODAY'));
                
                // Store item ID separately for matching in DAO
                // We'll use reflection to temporarily store it, or pass it as metadata
                $timesheetItem->itemIdForMatching = $itemId;
                
                $timesheetItems[$itemKey] = $timesheetItem;
            }
        }

        return $timesheetItems;
    }

    /**
     * @param int $timesheetId
     * @param int $projectId
     * @param int $activityId
     * @param DateTime $date
     * @return string
     */
    public function generateTimesheetItemKey(int $timesheetId, int $projectId, int $activityId, DateTime $date): string
    {
        return $timesheetId . '_' .
            $projectId . '_' .
            $activityId . '_' .
            $date->format('Y_m_d');
    }

    /**
     * @param Timesheet $timesheet
     * @param array $rows
     */
    public function saveAndUpdateTimesheetItemsFromRows(Timesheet $timesheet, array $rows): void
    {
        $timesheetItems = $this->createTimesheetItemsFromRows($timesheet, $rows);
        $this->getTimesheetDao()->saveAndUpdateTimesheetItems($timesheetItems);
    }

    /**
     * @param int $loggedInEmpNumber
     * @param Timesheet $timesheet
     * @return WorkflowStateMachine[]
     */
    public function getAllowedWorkflowsForTimesheet(
        int $loggedInEmpNumber,
        Timesheet $timesheet
    ): array {
        $includeRoles = [];
        if ($loggedInEmpNumber == $timesheet->getEmployee()->getEmpNumber()
            && $this->getUserRoleManager()->essRightsToOwnWorkflow()) {
            $includeRoles = ['ESS'];
        }

        return $this->getUserRoleManager()->getAllowedActions(
            WorkflowStateMachine::FLOW_TIME_TIMESHEET,
            $timesheet->getState(),
            [],
            $includeRoles,
            [Employee::class => $timesheet->getEmployee()->getEmpNumber()]
        );
    }
}
