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

namespace OrangeHRM\Time\Api\ValidationRules;

use OrangeHRM\Core\Api\V2\Validator\Rules\AbstractRule;

class TimesheetDeletedEntriesParamRule extends AbstractRule
{
    /**
     * @inheritDoc
     */
    public function validate($entries): bool
    {
        if (!is_array($entries)) {
            return false;
        }
        foreach ($entries as $entry) {
            // Validate that entry is either an integer ID or an array with 'id' key
            if (is_numeric($entry) && $entry > 0) {
                // Simple integer ID format
                continue;
            } elseif (is_array($entry)) {
                // Array format with 'id' key
                if (count(array_keys($entry)) != 1 || !isset($entry['id'])) {
                    return false;
                }
                $id = $entry['id'];
                if (!(is_numeric($id) && $id > 0)) {
                    return false;
                }
            } else {
                return false;
            }
        }
        return true;
    }
}
