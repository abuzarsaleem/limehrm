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

namespace OrangeHRM\Time\Dao;

use DateTime;
use LogicException;
use OrangeHRM\Core\Dao\BaseDao;
use OrangeHRM\Entity\Timesheet;
use OrangeHRM\Entity\TimesheetActionLog;
use OrangeHRM\Entity\TimesheetItem;
use OrangeHRM\ORM\ListSorter;
use OrangeHRM\ORM\Paginator;
use OrangeHRM\ORM\QueryBuilderWrapper;
use OrangeHRM\Time\Dto\DefaultTimesheetSearchFilterParams;
use OrangeHRM\Time\Dto\EmployeeReportsSearchFilterParams;
use OrangeHRM\Time\Dto\EmployeeTimesheetListSearchFilterParams;
use OrangeHRM\Time\Dto\TimesheetActionLogSearchFilterParams;
use OrangeHRM\Time\Dto\TimesheetSearchFilterParams;
use OrangeHRM\Time\Traits\Service\TimesheetServiceTrait;

class TimesheetDao extends BaseDao
{
    use TimesheetServiceTrait;

    /**
     * @param int $timesheetId
     * @return Timesheet|null
     */
    public function getTimesheetById(int $timesheetId): ?Timesheet
    {
        return $this->getRepository(Timesheet::class)->find($timesheetId);
    }

    /**
     * @param Timesheet $timesheet
     * @return Timesheet
     */
    public function saveTimesheet(Timesheet $timesheet): Timesheet
    {
        $this->persist($timesheet);
        return $timesheet;
    }

    /**
     * @param int $timesheetId
     * @param int $timesheetItemId
     * @return TimesheetItem|null
     */
    public function getTimesheetItemByTimesheetIdAndTimesheetItemId(
        int $timesheetId,
        int $timesheetItemId
    ): ?TimesheetItem {
        $timesheetItem = $this->getRepository(TimesheetItem::class)
            ->findOneBy(['id' => $timesheetItemId, 'timesheet' => $timesheetId]);
        return ($timesheetItem instanceof TimesheetItem) ? $timesheetItem : null;
    }

    /**
     * @param int $timesheetId
     * @return TimesheetItem[]
     */
    public function getTimesheetItemsByTimesheetId(int $timesheetId): array
    {
        $q = $this->createQueryBuilder(TimesheetItem::class, 'timesheetItem')
            ->andWhere('IDENTITY(timesheetItem.timesheet) = :timesheetId')
            ->setParameter('timesheetId', $timesheetId);

        return $q->getQuery()->execute();
    }

    /**
     * @param TimesheetItem $timesheetItem
     * @return TimesheetItem
     */
    public function saveTimesheetItem(TimesheetItem $timesheetItem): TimesheetItem
    {
        $this->persist($timesheetItem);
        return $timesheetItem;
    }

    /**
     * @param TimesheetActionLog $timesheetActionLog
     * @return TimesheetActionLog
     */
    public function saveTimesheetActionLog(TimesheetActionLog $timesheetActionLog): TimesheetActionLog
    {
        $this->persist($timesheetActionLog);
        return $timesheetActionLog;
    }

    /**
     * @param DateTime $date
     * @param int|null $employeeNumber
     * @return bool
     */
    public function hasTimesheetForStartDate(int $employeeNumber, DateTime $date): bool
    {
        $q = $this->createQueryBuilder(Timesheet::class, 'timesheet');
        $q->andWhere('timesheet.startDate = :date');
        $q->andWhere('timesheet.employee = :employeeNumber');
        $q->setParameter('date', $date);
        $q->setParameter('employeeNumber', $employeeNumber);

        return $this->getPaginator($q)->count() > 0;
    }

    /**
     * @param int $timesheetId
     * @param TimesheetActionLogSearchFilterParams $timesheetActionLogParamHolder
     * @return TimesheetActionLog[]
     */
    public function getTimesheetActionLogs(
        int $timesheetId,
        TimesheetActionLogSearchFilterParams $timesheetActionLogParamHolder
    ): array {
        $qb = $this->getTimesheetActionLogsPaginator($timesheetId, $timesheetActionLogParamHolder);
        return $qb->getQuery()->execute();
    }

    /**
     * @param int $timesheetId
     * @param TimesheetActionLogSearchFilterParams $timesheetActionLogParamHolder
     * @return Paginator
     */
    protected function getTimesheetActionLogsPaginator(
        int $timesheetId,
        TimesheetActionLogSearchFilterParams $timesheetActionLogParamHolder
    ): Paginator {
        $qb = $this->createQueryBuilder(TimesheetActionLog::class, 'timesheetActionLog');
        $qb->leftJoin('timesheetActionLog.timesheet', 'timesheet');

        $this->setSortingAndPaginationParams($qb, $timesheetActionLogParamHolder);

        $qb->andWhere('timesheet.id = :timesheetId')
            ->setParameter('timesheetId', $timesheetId);

        return $this->getPaginator($qb);
    }

    /**
     * @param $timesheetId
     * @param TimesheetActionLogSearchFilterParams $timesheetActionLogParamHolder
     * @return int
     */
    public function getTimesheetActionLogsCount(
        $timesheetId,
        TimesheetActionLogSearchFilterParams $timesheetActionLogParamHolder
    ): int {
        return $this->getTimesheetActionLogsPaginator($timesheetId, $timesheetActionLogParamHolder)->count();
    }

    /**
     * @param TimesheetSearchFilterParams $timesheetParamHolder
     * @return array
     */
    public function getTimesheetByStartAndEndDate(
        TimesheetSearchFilterParams $timesheetParamHolder
    ): array {
        $qb = $this->getTimesheetPaginator(
            $timesheetParamHolder,
        );
        return $qb->getQuery()->execute();
    }

    /**
     * @param TimesheetSearchFilterParams $timesheetParamHolder
     * @return Paginator
     */
    private function getTimesheetPaginator(
        TimesheetSearchFilterParams $timesheetParamHolder
    ): Paginator {
        $qb = $this->createQueryBuilder(Timesheet::class, 'timesheet');

        $this->setSortingAndPaginationParams($qb, $timesheetParamHolder);
        if (!is_null($timesheetParamHolder->getToDate() && !is_null($timesheetParamHolder->getFromDate()))) {
            $qb->andWhere(
                $qb->expr()->between(
                    'timesheet.startDate',
                    ':startDate',
                    ':endDate'
                )
            )
                ->setParameter('startDate', $timesheetParamHolder->getFromDate())
                ->setParameter('endDate', $timesheetParamHolder->getToDate());
        }

        $qb->andWhere('timesheet.employee = :empNumber')
            ->setParameter('empNumber', $timesheetParamHolder->getEmpNumber());

        return $this->getPaginator($qb);
    }

    /**
     * @param TimesheetSearchFilterParams $timesheetParamHolder
     * @return int
     */
    public function getTimesheetCount(TimesheetSearchFilterParams $timesheetParamHolder): int
    {
        return $this->getTimesheetPaginator($timesheetParamHolder)->count();
    }

    /**
     * @param int $timesheetId
     * @param array $entryIds e.g. array([1], [2], [3]) or array(['id' => 1], ['id' => 2]) or array(1, 2, 3)
     * @return int
     */
    public function deleteTimesheetRows(int $timesheetId, array $entryIds): int
    {
        if (empty($entryIds)) {
            return 0;
        }
        
        // Extract entry IDs from various formats (integer, array with 'id' key)
        $ids = [];
        foreach ($entryIds as $entry) {
            if (is_numeric($entry) && $entry > 0) {
                $ids[] = (int)$entry;
            } elseif (is_array($entry) && isset($entry['id']) && is_numeric($entry['id']) && $entry['id'] > 0) {
                $ids[] = (int)$entry['id'];
            }
        }
        
        if (empty($ids)) {
            return 0;
        }
        
        $q = $this->createQueryBuilder(TimesheetItem::class, 'ti');
        return $q->delete()
            ->where($q->expr()->eq('ti.timesheet', ':timesheetId'))
            ->andWhere($q->expr()->in('ti.id', ':entryIds'))
            ->setParameter('timesheetId', $timesheetId)
            ->setParameter('entryIds', $ids)
            ->getQuery()
            ->execute();
    }

    /**
     * @param array<string, TimesheetItem> $timesheetItems
     */
    public function saveAndUpdateTimesheetItems(array $timesheetItems): void
    {
        if (empty($timesheetItems)) {
            return;
        }

        // First, collect all item IDs that need to be looked up
        $itemIdsToQuery = [];
        $itemsByDate = [];
        $uniqueItemKeys = [];
        
        foreach (array_values($timesheetItems) as $timesheetItem) {
            /** @var TimesheetItem $timesheetItem */
            $timesheetId = $timesheetItem->getTimesheet()->getId();
            $projectId = $timesheetItem->getProject()->getId();
            $activityId = $timesheetItem->getProjectActivity()->getId();
            
            if ($timesheetItem->getProjectActivity()->getProject()->getId() !== $projectId) {
                throw new LogicException(
                    "The project activity (id: $activityId) not belongs to provided project (id: $projectId)"
                );
            }
            
            // Check if item has an ID for matching
            $itemId = property_exists($timesheetItem, 'itemIdForMatching') 
                ? $timesheetItem->itemIdForMatching 
                : null;
            
            if ($itemId !== null && $itemId > 0) {
                $itemIdsToQuery[] = $itemId;
            }

            // Generate unique key for this item (includes date)
            $itemKey = $this->getTimesheetService()->generateTimesheetItemKey(
                $timesheetId,
                $projectId,
                $activityId,
                $timesheetItem->getDate()
            );

            // Only add query condition if we haven't processed this exact item key yet
            if (!isset($uniqueItemKeys[$itemKey])) {
                $uniqueItemKeys[$itemKey] = true;
                $itemsByDate[] = [
                    'timesheetId' => $timesheetId,
                    'projectId' => $projectId,
                    'activityId' => $activityId,
                    'date' => $timesheetItem->getDate(),
                ];
            }
        }
        
        // Build query to find all existing items
        $q = $this->createQueryBuilder(TimesheetItem::class, 'ti');
        $paramIndex = 0;
        
        // First, query by IDs if any are provided
        if (!empty($itemIdsToQuery)) {
            $q->orWhere($q->expr()->in('ti.id', ':itemIds'));
            $q->setParameter('itemIds', $itemIdsToQuery);
        }
        
        // Then, add date-based queries
        foreach ($itemsByDate as $itemData) {
            $timesheetIdParamKey = 'timesheetId_' . $paramIndex;
            $projectIdParamKey = 'projectId_' . $paramIndex;
            $activityIdParamKey = 'activityId_' . $paramIndex;
            $dateParamKey = 'date_' . $paramIndex;

            // Query includes date to properly match items when multiple rows have same project/activity
            $q->orWhere(
                $q->expr()->andX(
                    $q->expr()->eq('ti.timesheet', ':' . $timesheetIdParamKey),
                    $q->expr()->eq('ti.project', ':' . $projectIdParamKey),
                    $q->expr()->eq('ti.projectActivity', ':' . $activityIdParamKey),
                    $q->expr()->eq('ti.date', ':' . $dateParamKey)
                )
            );
            $q->setParameter($timesheetIdParamKey, $itemData['timesheetId'])
                ->setParameter($projectIdParamKey, $itemData['projectId'])
                ->setParameter($activityIdParamKey, $itemData['activityId'])
                ->setParameter($dateParamKey, $itemData['date']);
            
            $paramIndex++;
        }

        /** @var array<string, TimesheetItem> $updatableTimesheetItems */
        $updatableTimesheetItems = [];
        if ($paramIndex > 0 || !empty($itemIdsToQuery)) {
            foreach ($q->getQuery()->execute() as $updatableTimesheetItem) {
                $itemId = $updatableTimesheetItem->getId();
                // Store by ID for direct lookup
                $updatableTimesheetItems['id_' . $itemId] = $updatableTimesheetItem;
                // Also store by generated key for fallback matching
                $itemKey = $this->getTimesheetService()->generateTimesheetItemKey(
                    $updatableTimesheetItem->getTimesheet()->getId(),
                    $updatableTimesheetItem->getProject()->getId(),
                    $updatableTimesheetItem->getProjectActivity()->getId(),
                    $updatableTimesheetItem->getDate()
                );
                if (!isset($updatableTimesheetItems[$itemKey])) {
                    $updatableTimesheetItems[$itemKey] = $updatableTimesheetItem;
                }
            }
        }

        foreach ($timesheetItems as $key => $timesheetItem) {
            // Check if item has an ID stored for matching (from frontend)
            $itemId = property_exists($timesheetItem, 'itemIdForMatching') 
                ? $timesheetItem->itemIdForMatching 
                : null;
            $existingItem = null;
            
            if ($itemId !== null && $itemId > 0) {
                // Try to find by ID first (most reliable for matching existing items)
                $existingItem = $updatableTimesheetItems['id_' . $itemId] ?? null;
            }
            
            // Fallback to key-based matching if ID-based matching failed
            if (!$existingItem && isset($updatableTimesheetItems[$key])) {
                $existingItem = $updatableTimesheetItems[$key];
            }
            
            if ($existingItem) {
                // Update existing item - preserve comment if new item doesn't have one
                $existingItem->setDuration($timesheetItem->getDuration());
                // Only update comment if the new item has a comment (to preserve existing comments)
                if ($timesheetItem->getComment() !== null) {
                    $existingItem->setComment($timesheetItem->getComment());
                }
                $this->getEntityManager()->persist($existingItem);
                continue;
            }
            // Create new item
            $this->getEntityManager()->persist($timesheetItem);
        }
        $this->getEntityManager()->flush();
    }

    /**
     * @param int $timesheetId
     * @param int $activityId
     * @param int $projectId
     * @return bool
     */
    public function isDuplicateTimesheetItem(
        int $timesheetId,
        int $activityId,
        int $projectId
    ): bool {
        $qb = $this->createQueryBuilder(TimesheetItem::class, 'timesheetItem');
        $qb->andWhere('timesheetItem.timesheet = :timesheetId');
        $qb->setParameter('timesheetId', $timesheetId);
        $qb->andWhere('timesheetItem.project = :projectId');
        $qb->setParameter('projectId', $projectId);
        $qb->andwhere('timesheetItem.projectActivity = :activityId');
        $qb->setParameter('activityId', $activityId);

        return $this->getPaginator($qb)->count() > 0;
    }

    /**
     * @param EmployeeTimesheetListSearchFilterParams $employeeTimesheetActionSearchFilterParams
     * @return Timesheet[]
     */
    public function getEmployeeTimesheetList(
        EmployeeTimesheetListSearchFilterParams $employeeTimesheetActionSearchFilterParams
    ): array {
        $paginator = $this->getEmployeeTimesheetPaginator($employeeTimesheetActionSearchFilterParams);
        return $paginator->getQuery()->execute();
    }

    /**
     * @param EmployeeTimesheetListSearchFilterParams $employeeTimesheetActionSearchFilterParams
     * @return Paginator
     */
    public function getEmployeeTimesheetPaginator(
        EmployeeTimesheetListSearchFilterParams $employeeTimesheetActionSearchFilterParams
    ): Paginator {
        $q = $this->createQueryBuilder(Timesheet::class, 'timesheet');
        $q->leftJoin('timesheet.employee', 'employee');

        if (!is_null($employeeTimesheetActionSearchFilterParams->getEmployeeNumbers())) {
            $q->andWhere($q->expr()->in('timesheet.employee', ':empNumbers'))
                ->setParameter('empNumbers', $employeeTimesheetActionSearchFilterParams->getEmployeeNumbers());
        }

        if (!empty($employeeTimesheetActionSearchFilterParams->getActionableStatesList())) {
            $q->andWhere($q->expr()->in('timesheet.state', ':states'))
                ->setParameter('states', $employeeTimesheetActionSearchFilterParams->getActionableStatesList());
        }

        $this->setSortingAndPaginationParams($q, $employeeTimesheetActionSearchFilterParams);
        $q->addOrderBy('employee.lastName');
        return $this->getPaginator($q);
    }

    /**
     * @param EmployeeTimesheetListSearchFilterParams $employeeTimesheetActionSearchFilterParams
     * @return int
     */
    public function getEmployeeTimesheetListCount(
        EmployeeTimesheetListSearchFilterParams $employeeTimesheetActionSearchFilterParams
    ): int {
        $paginator = $this->getEmployeeTimesheetPaginator($employeeTimesheetActionSearchFilterParams);
        return $paginator->count();
    }

    /**
     * @param DefaultTimesheetSearchFilterParams $defaultTimesheetSearchFilterParams
     * @return Timesheet|null
     */
    public function getDefaultTimesheet(
        DefaultTimesheetSearchFilterParams $defaultTimesheetSearchFilterParams
    ): ?Timesheet {
        $qb = $this->createQueryBuilder(Timesheet::class, 'timesheet');
        $qb->andWhere('timesheet.employee = :empNumber');
        $qb->setParameter('empNumber', $defaultTimesheetSearchFilterParams->getEmpNumber());
        if (!is_null($defaultTimesheetSearchFilterParams->getFromDate()) && !is_null(
            $defaultTimesheetSearchFilterParams->getToDate()
        )) {
            $qb->andWhere('timesheet.startDate = :fromDate');
            $qb->setParameter('fromDate', $defaultTimesheetSearchFilterParams->getFromDate());
            $qb->andWhere('timesheet.endDate = :toDate');
            $qb->setParameter('toDate', $defaultTimesheetSearchFilterParams->getToDate());
        } else {
            $qb->orderBy('timesheet.startDate', ListSorter::DESCENDING);
            $qb->setMaxResults(1);
        }
        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @param  EmployeeReportsSearchFilterParams  $filterParams
     * @return array
     */
    public function getTimesheetItemsForEmployeeReport(
        EmployeeReportsSearchFilterParams $filterParams
    ): array {
        return $this->getTimesheetItemsPaginatorForEmployeeReport($filterParams)->getQuery()->execute();
    }

    /**
     * @param  EmployeeReportsSearchFilterParams  $filterParams
     * @return int
     */
    public function getTimesheetItemsCountForEmployeeReport(EmployeeReportsSearchFilterParams $filterParams): int
    {
        return $this->getTimesheetItemsPaginatorForEmployeeReport($filterParams)->count();
    }

    /**
     * @param  EmployeeReportsSearchFilterParams  $filterParams
     * @return Paginator
     */
    private function getTimesheetItemsPaginatorForEmployeeReport(
        EmployeeReportsSearchFilterParams $filterParams
    ): Paginator {
        $qb = $this->getTimesheetItemsForEmployeeReportQueryBuilderWrapper($filterParams)->getQueryBuilder();
        $qb->select(
            'project.name AS projectName',
            'projectActivity.name AS activityName',
            'customer.name AS customerName',
            'COALESCE(SUM(timesheetItem.duration),0) AS totalDurationByGroup'
        );

        $qb->addGroupBy('projectName');
        $qb->addGroupBy('activityName');
        $qb->addGroupBy('customerName');

        $qb->addOrderBy('projectName', ListSorter::ASCENDING);
        $qb->addOrderBy('activityName', ListSorter::ASCENDING);
        $qb->addOrderBy('customerName', ListSorter::ASCENDING);

        return $this->getPaginator($qb);
    }

    /**
     * @param  EmployeeReportsSearchFilterParams  $filterParams
     * @return QueryBuilderWrapper
     */
    private function getTimesheetItemsForEmployeeReportQueryBuilderWrapper(
        EmployeeReportsSearchFilterParams $filterParams
    ): QueryBuilderWrapper {
        $q = $this->createQueryBuilder(TimesheetItem::class, 'timesheetItem');
        $q->leftJoin('timesheetItem.timesheet', 'timesheet');
        $q->leftJoin('timesheetItem.projectActivity', 'projectActivity');
        $q->leftJoin('timesheetItem.project', 'project');
        $q->leftJoin('project.customer', 'customer');
        $q->andWhere('timesheetItem.employee = :empNumber');
        $q->setParameter('empNumber', $filterParams->getEmpNumber());
        $this->setSortingAndPaginationParams($q, $filterParams);

        if (!is_null($filterParams->getProjectId())) {
            $q->andWhere('timesheetItem.project = :projectId');
            $q->setParameter('projectId', $filterParams->getProjectId());
        }

        if (!is_null($filterParams->getActivityId())) {
            $q->andWhere('timesheetItem.projectActivity = :activityId');
            $q->setParameter('activityId', $filterParams->getActivityId());
        }

        //Timesheet items after fromDate (including fromDate) and Timesheet items before toDate (including toDate)
        if (!is_null($filterParams->getFromDate()) && !is_null($filterParams->getToDate())) {
            $q->andWhere($q->expr()->between('timesheetItem.date', ':fromDate', ':toDate'));
            $q->setParameter('fromDate', $filterParams->getFromDate());
            $q->setParameter('toDate', $filterParams->getToDate());
        }
        //Timesheet items after fromDate (including fromDate)
        elseif (!is_null($filterParams->getFromDate())) {
            $q->andWhere($q->expr()->gte('timesheetItem.date', ':fromDate'));
            $q->setParameter('fromDate', $filterParams->getFromDate());
        }

        //Timesheet items before toDate (including toDate)
        elseif (!is_null($filterParams->getToDate())) {
            $q->andWhere($q->expr()->lte('timesheetItem.date', ':toDate'));
            $q->setParameter('toDate', $filterParams->getToDate());
        }

        if ($filterParams->getIncludeTimesheets(
        ) === EmployeeReportsSearchFilterParams::INCLUDE_TIMESHEETS_APPROVED_ONLY) {
            $q->andWhere('timesheet.state = :state');
            $q->setParameter('state', EmployeeReportsSearchFilterParams::TIMESHEET_APPROVED_STATE);
        }
        //else: neither fromDate nor toDate is available

        return $this->getQueryBuilderWrapper($q);
    }

    /**
     * @param  EmployeeReportsSearchFilterParams  $filterParams
     * @return int
     */
    public function getTotalDurationForEmployeeReport(EmployeeReportsSearchFilterParams $filterParams): int
    {
        $qb = $this->getTimesheetItemsForEmployeeReportQueryBuilderWrapper($filterParams)->getQueryBuilder();
        //COALESCE usage => if timesheetItem.duration == null, it will be converted to 0
        $qb->select('COALESCE(SUM(timesheetItem.duration),0) AS totalDuration');
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param int $timesheetId
     * @param int $projectId
     * @param int $activityId
     * @param DateTime $date
     * @return TimesheetItem | null
     */
    public function getTimesheetItemByProjectIdAndTimesheetIdAndActivityIdAndDate(
        int $timesheetId,
        int $projectId,
        int $activityId,
        DateTime $date
    ): ?TimesheetItem {
        return $this->getRepository(TimesheetItem::class)->findOneBy([
            'timesheet' => $timesheetId,
            'project' => $projectId,
            'projectActivity' => $activityId,
            'date' => $date
        ]);
    }
}
