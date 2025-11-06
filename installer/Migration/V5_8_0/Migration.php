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

namespace OrangeHRM\Installer\Migration\V5_8_0;

use OrangeHRM\Installer\Util\V1\AbstractMigration;
use OrangeHRM\Installer\Util\V1\LangStringHelper;

class Migration extends AbstractMigration
{
    protected ?LangStringHelper $langStringHelper = null;

    /**
     * @return LangStringHelper
     */
    protected function getLangStringHelper(): LangStringHelper
    {
        if (!$this->langStringHelper instanceof LangStringHelper) {
            $this->langStringHelper = new LangStringHelper($this->getConnection());
        }
        return $this->langStringHelper;
    }

    /**
     * @inheritDoc
     */
    public function up(): void
    {
        // Insert screen and permissions from YAML file
        $this->getDataGroupHelper()->insertScreenPermissions(__DIR__ . '/permission/screen.yaml');
        
        // Insert language strings
        $this->getLangStringHelper()->insertOrUpdateLangStrings(__DIR__, 'time');

        // Get the Time module ID
        $timeModuleId = $this->getDataGroupHelper()->getModuleIdByName('time');

        // Get the screen ID we just created
        $screenId = $this->getDataGroupHelper()->getScreenIdByModuleAndUrl(
            $timeModuleId,
            'displayTimesheetReportCriteria'
        );

        // Get the Reports menu ID (parent menu for all reports under Time module)
        $timeMenuId = $this->createQueryBuilder()
            ->select('menu_item.id')
            ->from('ohrm_menu_item', 'menu_item')
            ->where('menu_item.menu_title = :menuTitle')
            ->setParameter('menuTitle', 'Time')
            ->andWhere('menu_item.level = :level')
            ->setParameter('level', 1)
            ->executeQuery()
            ->fetchOne();

        $reportsMenuId = $this->createQueryBuilder()
            ->select('menu_item.id')
            ->from('ohrm_menu_item', 'menu_item')
            ->where('menu_item.menu_title = :menuTitle')
            ->setParameter('menuTitle', 'Reports')
            ->andWhere('menu_item.parent_id = :parentId')
            ->setParameter('parentId', $timeMenuId)
            ->executeQuery()
            ->fetchOne();

        // Check if menu item already exists
        $existingMenuItem = $this->createQueryBuilder()
            ->select('menu_item.id')
            ->from('ohrm_menu_item', 'menu_item')
            ->where('menu_item.menu_title = :menuTitle')
            ->setParameter('menuTitle', 'Timesheet Report')
            ->andWhere('menu_item.parent_id = :parentId')
            ->setParameter('parentId', $reportsMenuId)
            ->executeQuery()
            ->fetchOne();

        // Insert menu item only if it doesn't exist
        if ($existingMenuItem === false) {
            $this->createQueryBuilder()
                ->insert('ohrm_menu_item')
                ->values(
                    [
                        'menu_title' => ':menuTitle',
                        'screen_id' => ':screenId',
                        'parent_id' => ':parentId',
                        'level' => ':level',
                        'order_hint' => ':orderHint',
                        'status' => ':status'
                    ]
                )
                ->setParameter('menuTitle', 'Timesheet Report')
                ->setParameter('screenId', $screenId)
                ->setParameter('parentId', $reportsMenuId)
                ->setParameter('level', 3)
                ->setParameter('orderHint', 400)
                ->setParameter('status', 1)
                ->executeQuery();
        }
    }

    /**
     * @inheritDoc
     */
    public function getVersion(): string
    {
        return '5.8.0';
    }
}

