<!--
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
-->

<template>
  <base-widget
    icon="stopwatch"
    :loading="isLoading"
    :title="`${$t('attendance.punch_in')}/Out`"
  >
    <oxd-form :loading="isPunching" @submit-valid="handlePunch">
      <div class="orangehrm-punch-in-out-card">
        <div class="orangehrm-attendance-card-profile">
          <div class="orangehrm-attendance-card-profile-record">
            <oxd-text tag="p" class="orangehrm-attendance-card-state">
              {{ currentStatus }}
            </oxd-text>
            <oxd-text tag="p" class="orangehrm-attendance-card-details">
              {{ lastPunchTime }}
            </oxd-text>
          </div>
        </div>

        <template v-if="attendanceRecord.previousRecord && isPunchedIn">
          <oxd-divider />
          <oxd-form-row>
            <oxd-grid :cols="1" class="orangehrm-full-width-grid">
              <oxd-grid-item>
                <oxd-input-group :label="$t('attendance.punched_in_time')">
                  <oxd-text
                    type="subtitle-2"
                    class="orangehrm-punch-in-out-small-text"
                  >
                    {{ previousAttendanceRecordDate }} -
                    {{ previousAttendanceRecordTime }}
                    <oxd-text
                      tag="span"
                      class="orangehrm-attendance-punchedIn-timezone"
                    >
                      {{ `(GMT ${previousRecordTimezone})` }}
                    </oxd-text>
                  </oxd-text>
                </oxd-input-group>
              </oxd-grid-item>
              <oxd-grid-item v-if="attendanceRecord.previousRecord.note">
                <oxd-input-group :label="$t('attendance.punched_in_note')">
                  <oxd-text
                    type="subtitle-2"
                    class="orangehrm-punch-in-out-small-text"
                  >
                    {{ attendanceRecord.previousRecord.note }}
                  </oxd-text>
                </oxd-input-group>
              </oxd-grid-item>
            </oxd-grid>
          </oxd-form-row>
        </template>

        <oxd-divider />
        <oxd-form-row>
          <oxd-grid :cols="2" class="orangehrm-full-width-grid">
            <oxd-grid-item>
              <date-input
                :key="attendanceRecord.time"
                v-model="attendanceRecord.date"
                :label="$t('general.date')"
                :rules="rules.date"
                :disabled="!isEditable"
                required
              />
            </oxd-grid-item>
            <oxd-grid-item>
              <oxd-input-field
                v-model="attendanceRecord.time"
                :label="$t('general.time')"
                :disabled="!isEditable"
                :rules="rules.time"
                type="time"
                :placeholder="$t('attendance.hh_mm')"
                required
              />
            </oxd-grid-item>
          </oxd-grid>
        </oxd-form-row>

        <oxd-form-row>
          <oxd-grid :cols="1" class="orangehrm-full-width-grid">
            <oxd-grid-item>
              <oxd-input-field
                v-model="attendanceRecord.note"
                :rules="rules.note"
                :label="$t('general.note')"
                :placeholder="$t('general.type_here')"
                type="textarea"
                :rows="2"
              />
            </oxd-grid-item>
          </oxd-grid>
        </oxd-form-row>

        <oxd-divider />
        <oxd-form-actions>
          <required-text />
          <oxd-button
            class="orangehrm-left-space"
            :display-type="punchButtonType"
            :label="punchButtonLabel"
            type="submit"
          />
        </oxd-form-actions>
      </div>
    </oxd-form>
  </base-widget>
</template>

<script>
import {
  parseDate,
  parseTime,
  formatDate,
  formatTime,
  guessTimezone,
  getStandardTimezone,
  setClockInterval,
  isToday,
} from '@/core/util/helper/datefns';
import {
  required,
  validDateFormat,
  shouldNotExceedCharLength,
} from '@/core/util/validation/rules';
import {promiseDebounce} from '@ohrm/oxd';
import useLocale from '@/core/util/composable/useLocale';
import {APIService} from '@/core/util/services/api.service';
import BaseWidget from '@/orangehrmDashboardPlugin/components/BaseWidget.vue';
import useDateFormat from '@/core/util/composable/useDateFormat';

export default {
  name: 'PunchInOutWidget',

  components: {
    'base-widget': BaseWidget,
  },

  setup() {
    const {locale} = useLocale();
    const http = new APIService(
      window.appGlobal.baseUrl,
      '/api/v2/attendance/records',
    );
    const latestHttp = new APIService(
      window.appGlobal.baseUrl,
      '/api/v2/attendance/records/latest',
    );
    const currentDateTimeHttp = new APIService(
      window.appGlobal.baseUrl,
      '/api/v2/attendance/current-datetime',
    );
    const configHttp = new APIService(
      window.appGlobal.baseUrl,
      '/api/v2/attendance/configs',
    );
    const {jsDateFormat, userDateFormat, timeFormat, jsTimeFormat} =
      useDateFormat();

    return {
      http,
      latestHttp,
      currentDateTimeHttp,
      configHttp,
      locale,
      timeFormat,
      jsTimeFormat,
      jsDateFormat,
      userDateFormat,
    };
  },

  data() {
    return {
      isLoading: false,
      isPunching: false,
      latestRecord: null,
      attendanceRecord: {
        date: null,
        time: null,
        note: null,
        previousRecord: null,
      },
      isEditable: false,
      previousRecordTimezone: null,
      timezoneOffset: null,
      calendarObserver: null,
      calendarCheckInterval: null,
      resizeHandler: null,
    };
  },

  computed: {
    isPunchedIn() {
      return (
        this.latestRecord?.state?.id === 'PUNCHED IN' ||
        this.latestRecord?.state?.name === 'PUNCHED IN'
      );
    },
    currentStatus() {
      if (!this.latestRecord) {
        return this.$t('attendance.not_punched_in');
      }
      if (this.isPunchedIn) {
        return this.$t('attendance.punched_in');
      }
      return this.$t('attendance.punched_out');
    },
    lastPunchTime() {
      if (!this.latestRecord) return null;

      const record = this.isPunchedIn
        ? this.latestRecord.punchIn
        : this.latestRecord.punchOut;

      if (!record?.userDate || !record?.userTime) return null;

      try {
        const parsedDate = parseDate(
          `${record.userDate} ${record.userTime}`,
          'yyyy-MM-dd HH:mm',
        );
        const formattedTime = formatDate(parsedDate, 'hh:mm a', {
          locale: this.locale,
        });
        const timezoneOffset = record.offset || this.timezoneOffset;

        if (isToday(parsedDate)) {
          return this.$t('dashboard.state_today_at_time_timezone_offset', {
            lastState: this.currentStatus,
            time: formattedTime,
            timezoneOffset: timezoneOffset,
          });
        } else {
          const formattedDate = formatDate(parsedDate, 'MMM do', {
            locale: this.locale,
          });
          return this.$t('dashboard.state_date_at_time_timezone_offset', {
            lastState: this.currentStatus,
            date: formattedDate,
            time: formattedTime,
            timezoneOffset: timezoneOffset,
          });
        }
      } catch (e) {
        return null;
      }
    },
    punchButtonLabel() {
      return this.isPunchedIn
        ? this.$t('attendance.out')
        : this.$t('attendance.in');
    },
    punchButtonType() {
      return this.isPunchedIn ? 'outline-danger' : 'main';
    },
    previousAttendanceRecordDate() {
      if (!this.attendanceRecord?.previousRecord) return null;
      return formatDate(
        parseDate(this.attendanceRecord.previousRecord.userDate),
        this.jsDateFormat,
        {locale: this.locale},
      );
    },
    previousAttendanceRecordTime() {
      if (!this.attendanceRecord?.previousRecord) return null;
      return formatTime(
        parseTime(
          this.attendanceRecord.previousRecord.userTime,
          this.timeFormat,
        ),
        this.jsTimeFormat,
      );
    },
    rules() {
      return {
        date: [
          required,
          validDateFormat(this.userDateFormat),
          promiseDebounce(this.validateDate, 500),
        ],
        time: [required, promiseDebounce(this.validateDate, 500)],
        note: [shouldNotExceedCharLength(250)],
      };
    },
  },

  beforeMount() {
    this.initializeWidget();
  },

  mounted() {
    this.setupCalendarPopupHandler();
  },

  beforeUnmount() {
    if (this.calendarObserver) {
      this.calendarObserver.disconnect();
    }
    if (this.calendarCheckInterval) {
      clearInterval(this.calendarCheckInterval);
    }
    if (this.resizeHandler) {
      window.removeEventListener('resize', this.resizeHandler);
    }
  },

  methods: {
    setupCalendarPopupHandler() {
      // Use MutationObserver to watch for calendar popup
      this.calendarObserver = new MutationObserver(() => {
        this.handleCalendarPopup();
      });

      // Start observing the widget
      const widgetElement = this.$el;
      if (widgetElement) {
        this.calendarObserver.observe(widgetElement, {
          childList: true,
          subtree: true,
          attributes: true,
          attributeFilter: ['class', 'style'],
        });
      }

      // Also check periodically for calendar popup
      this.calendarCheckInterval = setInterval(() => {
        this.handleCalendarPopup();
      }, 100);

      // Initial check
      this.$nextTick(() => {
        this.handleCalendarPopup();
      });

      // Handle window resize to reposition calendar on mobile/desktop switch
      this.resizeHandler = () => {
        this.handleCalendarPopup();
      };
      window.addEventListener('resize', this.resizeHandler);
    },

    handleCalendarPopup() {
      // Find calendar popup elements - check both widget and document body
      const widgetElement = this.$el;
      if (!widgetElement) return;

      // Look for calendar popup with various selectors
      const calendarSelectors = [
        '.vc-popover-content-wrapper',
        '.vc-container',
        '.vc-popover',
        '[class*="vc-popover"]',
        '[class*="vc-container"]',
        '[class*="vc-date"]',
        '[class*="vc-calendar"]',
        '.oxd-date-input-calendar',
        '[class*="oxd-date-input-calendar"]',
      ];

      // Search in widget and document body
      const searchElements = [widgetElement, document.body];

      calendarSelectors.forEach((selector) => {
        searchElements.forEach((searchElement) => {
          const popups = searchElement.querySelectorAll(selector);
          popups.forEach((popup) => {
            if (popup instanceof HTMLElement) {
              // Check if this popup is related to our widget's date input
              const widgetDateInput = widgetElement.querySelector(
                'input[type="date"], .date-input input, .oxd-input-field[type="date"] input',
              );

              if (!widgetDateInput) return;

              // Find the input field's parent container (oxd-input-field or oxd-grid-item)
              let inputParent = widgetDateInput.closest(
                '.oxd-input-field, .oxd-grid-item, .oxd-input-group',
              );
              if (!inputParent) {
                inputParent = widgetDateInput.parentElement;
              }

              // Ensure parent has relative positioning
              if (inputParent instanceof HTMLElement) {
                const computedStyle = window.getComputedStyle(inputParent);
                if (computedStyle.position === 'static') {
                  inputParent.style.position = 'relative';
                }
                inputParent.style.overflow = 'visible';
                inputParent.style.zIndex = '1';
              }

              // Use absolute positioning relative to parent
              popup.style.position = 'absolute';
              popup.style.zIndex = '10000';
              popup.style.overflow = 'visible';
              popup.style.maxWidth = 'none';
              popup.style.maxHeight = 'none';
              popup.style.clip = 'auto';
              popup.style.clipPath = 'none';

              // Calculate position relative to parent container
              const inputRect = widgetDateInput.getBoundingClientRect();
              const parentRect = inputParent
                ? inputParent.getBoundingClientRect()
                : widgetElement.getBoundingClientRect();
              const popupRect = popup.getBoundingClientRect();

              // Calculate relative positions
              const relativeLeft = inputRect.left - parentRect.left;
              const relativeTop = inputRect.bottom - parentRect.top;

              // Ensure calendar fits within viewport
              const viewportWidth = window.innerWidth;
              const viewportHeight = window.innerHeight;
              const popupWidth = popupRect.width || 300;
              const popupHeight = popupRect.height || 300;

              // Detect mobile viewport
              const isMobile = viewportWidth < 768;
              const mobilePadding = 10;

              let leftPos = relativeLeft;
              let topPos = relativeTop + 5;

              // Check if calendar would overflow viewport
              const absoluteLeft = parentRect.left + leftPos;
              const absoluteTop = parentRect.top + topPos;

              // Adjust horizontal position if it would overflow
              if (absoluteLeft + popupWidth > viewportWidth - mobilePadding) {
                leftPos =
                  viewportWidth - mobilePadding - popupWidth - parentRect.left;
              }
              if (absoluteLeft < mobilePadding) {
                leftPos = mobilePadding - parentRect.left;
              }

              // Adjust vertical position if it would overflow
              if (absoluteTop + popupHeight > viewportHeight - mobilePadding) {
                // Position above input instead
                topPos = inputRect.top - parentRect.top - popupHeight - 5;
              }
              if (absoluteTop < mobilePadding) {
                topPos = mobilePadding - parentRect.top;
              }

              // Apply calculated position
              popup.style.left = `${leftPos}px`;
              popup.style.top = `${topPos}px`;
              popup.style.right = 'auto';
              popup.style.bottom = 'auto';

              // On mobile, ensure calendar doesn't exceed viewport width
              if (isMobile) {
                const maxWidth = viewportWidth - mobilePadding * 2;
                popup.style.maxWidth = `${maxWidth}px`;
              } else {
                popup.style.maxWidth = 'none';
              }

              // Ensure popup is in the correct parent
              if (inputParent && popup.parentElement !== inputParent) {
                inputParent.appendChild(popup);
              }
            }
          });
        });
      });
    },
    async initializeWidget() {
      await this.fetchConfiguration();
      await this.setCurrentDateTime();
      if (!this.isEditable) {
        setClockInterval(this.setCurrentDateTime, 60000);
      }
      await this.fetchLatestRecord();
    },
    async fetchConfiguration() {
      try {
        const response = await this.configHttp.request({
          method: 'GET',
          url: '/api/v2/attendance/configs',
        });
        this.isEditable = response.data.data.canUserChangeCurrentTime;
      } catch (error) {
        this.isEditable = false;
      }
    },
    async fetchLatestRecord() {
      this.isLoading = true;
      try {
        const response = await this.latestHttp.request({
          method: 'GET',
          url: '/api/v2/attendance/records/latest',
        });
        this.latestRecord = response.data.data;
        if (this.isPunchedIn) {
          this.attendanceRecord.previousRecord = this.latestRecord.punchIn;
          this.previousRecordTimezone = getStandardTimezone(
            this.attendanceRecord.previousRecord?.offset,
          );
          this.timezoneOffset = this.attendanceRecord.previousRecord?.offset;
        } else if (this.latestRecord.punchOut) {
          this.timezoneOffset = this.latestRecord.punchOut?.offset;
        }
      } catch (error) {
        // If no record found, that's okay - user hasn't punched in yet
        if (error.response?.status !== 404) {
          // Error fetching latest attendance record
        }
        this.latestRecord = null;
        this.attendanceRecord.previousRecord = null;
      } finally {
        this.isLoading = false;
      }
    },
    setCurrentDateTime() {
      return new Promise((resolve) => {
        this.currentDateTimeHttp
          .request({method: 'GET', url: '/api/v2/attendance/current-datetime'})
          .then((res) => {
            const {utcDate, utcTime} = res.data.data;
            const currentDate = parseDate(
              `${utcDate} ${utcTime} +00:00`,
              'yyyy-MM-dd HH:mm xxx',
            );
            this.attendanceRecord.date = formatDate(currentDate, 'yyyy-MM-dd');
            this.attendanceRecord.time = formatDate(currentDate, 'HH:mm');
            resolve();
          })
          .catch(() => {
            // Fallback to local time
            const now = new Date();
            this.attendanceRecord.date = formatDate(now, 'yyyy-MM-dd');
            this.attendanceRecord.time = formatDate(now, 'HH:mm');
            resolve();
          });
      });
    },
    validateDate() {
      if (!this.attendanceRecord.date || !this.attendanceRecord.time) {
        return true;
      }
      if (parseDate(this.attendanceRecord.date) === null) {
        return true;
      }
      const tzOffset = (new Date().getTimezoneOffset() / 60) * -1;
      return new Promise((resolve) => {
        this.http
          .request({
            method: 'GET',
            url: `/api/v2/attendance/${
              this.isPunchedIn ? 'punch-out' : 'punch-in'
            }/overlaps`,
            params: {
              date: this.attendanceRecord.date,
              time: this.attendanceRecord.time,
              timezoneOffset: tzOffset,
            },
            validateStatus: (status) => {
              return (status >= 200 && status < 300) || status == 400;
            },
          })
          .then((res) => {
            const {data, error} = res.data;
            if (error) {
              return resolve(error.message);
            }
            return data.valid === true
              ? resolve(true)
              : resolve(this.$t('attendance.overlapping_records_found'));
          });
      });
    },
    async handlePunch() {
      this.isPunching = true;
      try {
        const timezone = guessTimezone();

        if (this.isPunchedIn) {
          // Punch Out - use PUT (API automatically finds the last punch-in record)
          await this.http.request({
            method: 'PUT',
            data: {
              date: this.attendanceRecord.date,
              time: this.attendanceRecord.time,
              note: this.attendanceRecord.note,
              timezoneOffset: timezone.offset,
              timezoneName: timezone.name,
            },
          });
        } else {
          // Punch In - use POST
          await this.http.request({
            method: 'POST',
            data: {
              date: this.attendanceRecord.date,
              time: this.attendanceRecord.time,
              note: this.attendanceRecord.note,
              timezoneOffset: timezone.offset,
              timezoneName: timezone.name,
            },
          });
        }

        await this.$toast.saveSuccess();
        // Refresh the widget data
        await this.setCurrentDateTime();
        await this.fetchLatestRecord();
        // Reset note
        this.attendanceRecord.note = null;
      } catch (error) {
        await this.$toast.showError({
          title: this.$t('general.error'),
          message:
            error?.response?.data?.error?.message ||
            this.$t('general.error_occurred'),
        });
      } finally {
        this.isPunching = false;
      }
    },
  },
};
</script>

<style src="./punch-in-out-widget.scss" lang="scss" scoped></style>
