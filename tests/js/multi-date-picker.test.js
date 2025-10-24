import { describe, it, expect, beforeEach, vi } from 'vitest';
import { JSDOM } from 'jsdom';

describe('Multi-Date Picker', () => {
    let dom;
    let document;
    let window;

    beforeEach(() => {
        // Create a fresh DOM for each test
        dom = new JSDOM(`
            <!DOCTYPE html>
            <html>
                <body>
                    <form>
                        <input
                            type="text"
                            id="date-picker"
                            data-min-date="2024-01-01"
                            data-max-date="2025-01-06"
                            data-existing-dates='["2025-01-02", "2025-01-03"]'
                        />
                    </form>
                </body>
            </html>
        `, { url: 'http://localhost' });

        document = dom.window.document;
        window = dom.window;
        global.document = document;
        global.window = window;
    });

    describe('Date parsing for year extraction', () => {
        it('should extract year from minDate string correctly', () => {
            const minDateStr = '2024-01-01';
            const minYear = parseInt(minDateStr.split('-')[0], 10);

            expect(minYear).toBe(2024);
        });

        it('should extract year from maxDate string correctly', () => {
            const maxDateStr = '2025-01-06';
            const maxYear = parseInt(maxDateStr.split('-')[0], 10);

            expect(maxYear).toBe(2025);
        });

        it('should handle different date formats', () => {
            const dates = [
                '2024-01-01',
                '2025-12-31',
                '2023-06-15'
            ];

            const years = dates.map(date => parseInt(date.split('-')[0], 10));

            expect(years).toEqual([2024, 2025, 2023]);
        });

        it('should avoid timezone issues by parsing string directly', () => {
            // This would fail with new Date('2024-01-01') in some timezones
            const dateStr = '2024-01-01';
            const year = parseInt(dateStr.split('-')[0], 10);

            // Using string parsing avoids timezone conversion
            expect(year).toBe(2024);

            // Compare to Date object which might convert to previous year
            const dateObj = new Date(dateStr);
            const dateObjYear = dateObj.getFullYear();

            // In some timezones, new Date('2024-01-01') becomes Dec 31, 2023
            // Our string parsing avoids this issue
            expect(year).toBe(2024);
        });
    });

    describe('Year dropdown creation', () => {
        it('should create select element with correct years', () => {
            const minYear = 2024;
            const maxYear = 2025;

            const select = document.createElement('select');

            // Simulate year dropdown creation
            for (let year = maxYear; year >= minYear; year--) {
                const option = document.createElement('option');
                option.value = year;
                option.textContent = year;
                select.appendChild(option);
            }

            expect(select.options.length).toBe(2);
            expect(select.options[0].value).toBe('2025');
            expect(select.options[1].value).toBe('2024');
        });

        it('should create years in descending order', () => {
            const minYear = 2022;
            const maxYear = 2025;

            const years = [];
            for (let year = maxYear; year >= minYear; year--) {
                years.push(year);
            }

            expect(years).toEqual([2025, 2024, 2023, 2022]);
        });

        it('should handle single year range', () => {
            const minYear = 2025;
            const maxYear = 2025;

            const years = [];
            for (let year = maxYear; year >= minYear; year--) {
                years.push(year);
            }

            expect(years).toEqual([2025]);
        });
    });

    describe('Week filtering logic', () => {
        it('should identify week with only current month days', () => {
            // Mock days with classList
            const mockDays = [
                { classList: { contains: () => false } }, // Current month
                { classList: { contains: () => false } }, // Current month
                { classList: { contains: () => false } }, // Current month
                { classList: { contains: () => false } }, // Current month
                { classList: { contains: () => false } }, // Current month
                { classList: { contains: () => false } }, // Current month
                { classList: { contains: () => false } }, // Current month
            ];

            let hasCurrentMonthDay = false;
            mockDays.forEach(day => {
                if (!day.classList.contains('prevMonthDay') && !day.classList.contains('nextMonthDay')) {
                    hasCurrentMonthDay = true;
                }
            });

            expect(hasCurrentMonthDay).toBe(true);
        });

        it('should identify week with only adjacent month days', () => {
            // Mock days all from adjacent months
            const mockDays = [
                { classList: { contains: (cls) => cls === 'prevMonthDay' } },
                { classList: { contains: (cls) => cls === 'prevMonthDay' } },
                { classList: { contains: (cls) => cls === 'prevMonthDay' } },
                { classList: { contains: (cls) => cls === 'nextMonthDay' } },
                { classList: { contains: (cls) => cls === 'nextMonthDay' } },
                { classList: { contains: (cls) => cls === 'nextMonthDay' } },
                { classList: { contains: (cls) => cls === 'nextMonthDay' } },
            ];

            let hasCurrentMonthDay = false;
            mockDays.forEach(day => {
                if (!day.classList.contains('prevMonthDay') && !day.classList.contains('nextMonthDay')) {
                    hasCurrentMonthDay = true;
                }
            });

            expect(hasCurrentMonthDay).toBe(false);
        });

        it('should identify mixed week with some current month days', () => {
            // Mock week with mix of current and adjacent months
            const mockDays = [
                { classList: { contains: (cls) => cls === 'prevMonthDay' } },
                { classList: { contains: (cls) => cls === 'prevMonthDay' } },
                { classList: { contains: () => false } }, // Current month
                { classList: { contains: () => false } }, // Current month
                { classList: { contains: () => false } }, // Current month
                { classList: { contains: () => false } }, // Current month
                { classList: { contains: () => false } }, // Current month
            ];

            let hasCurrentMonthDay = false;
            mockDays.forEach(day => {
                if (!day.classList.contains('prevMonthDay') && !day.classList.contains('nextMonthDay')) {
                    hasCurrentMonthDay = true;
                }
            });

            expect(hasCurrentMonthDay).toBe(true);
        });
    });

    describe('Days grouping into weeks', () => {
        it('should group 7 days into one week', () => {
            const days = Array(7).fill({});
            const weeks = [];

            for (let i = 0; i < days.length; i += 7) {
                weeks.push(days.slice(i, i + 7));
            }

            expect(weeks.length).toBe(1);
            expect(weeks[0].length).toBe(7);
        });

        it('should group 14 days into two weeks', () => {
            const days = Array(14).fill({});
            const weeks = [];

            for (let i = 0; i < days.length; i += 7) {
                weeks.push(days.slice(i, i + 7));
            }

            expect(weeks.length).toBe(2);
            expect(weeks[0].length).toBe(7);
            expect(weeks[1].length).toBe(7);
        });

        it('should handle 35 days (5 weeks)', () => {
            const days = Array(35).fill({});
            const weeks = [];

            for (let i = 0; i < days.length; i += 7) {
                weeks.push(days.slice(i, i + 7));
            }

            expect(weeks.length).toBe(5);
            weeks.forEach(week => {
                expect(week.length).toBe(7);
            });
        });

        it('should handle 42 days (6 weeks)', () => {
            const days = Array(42).fill({});
            const weeks = [];

            for (let i = 0; i < days.length; i += 7) {
                weeks.push(days.slice(i, i + 7));
            }

            expect(weeks.length).toBe(6);
            weeks.forEach(week => {
                expect(week.length).toBe(7);
            });
        });
    });

    describe('Existing dates parsing', () => {
        it('should parse existing dates from data attribute', () => {
            const datePickerElement = document.getElementById('date-picker');
            const existingDatesAttr = datePickerElement.getAttribute('data-existing-dates');
            const existingDates = JSON.parse(existingDatesAttr);

            expect(existingDates).toEqual(['2025-01-02', '2025-01-03']);
        });

        it('should handle empty existing dates', () => {
            const datePickerElement = document.getElementById('date-picker');
            datePickerElement.setAttribute('data-existing-dates', '[]');

            const existingDatesAttr = datePickerElement.getAttribute('data-existing-dates');
            const existingDates = JSON.parse(existingDatesAttr);

            expect(existingDates).toEqual([]);
        });

        it('should handle missing data attribute gracefully', () => {
            const datePickerElement = document.getElementById('date-picker');
            datePickerElement.removeAttribute('data-existing-dates');

            const existingDatesAttr = datePickerElement.getAttribute('data-existing-dates');
            let existingDates = [];

            try {
                if (existingDatesAttr) {
                    existingDates = JSON.parse(existingDatesAttr);
                }
            } catch (e) {
                existingDates = [];
            }

            expect(existingDates).toEqual([]);
        });

        it('should handle malformed JSON gracefully', () => {
            const datePickerElement = document.getElementById('date-picker');
            datePickerElement.setAttribute('data-existing-dates', 'not-valid-json');

            const existingDatesAttr = datePickerElement.getAttribute('data-existing-dates');
            let existingDates = [];

            try {
                if (existingDatesAttr) {
                    existingDates = JSON.parse(existingDatesAttr);
                }
            } catch (e) {
                existingDates = [];
            }

            expect(existingDates).toEqual([]);
        });
    });

    describe('Min/max date attributes', () => {
        it('should read min-date from data attribute', () => {
            const datePickerElement = document.getElementById('date-picker');
            const minDateStr = datePickerElement.getAttribute('data-min-date');

            expect(minDateStr).toBe('2024-01-01');
        });

        it('should read max-date from data attribute', () => {
            const datePickerElement = document.getElementById('date-picker');
            const maxDateStr = datePickerElement.getAttribute('data-max-date');

            expect(maxDateStr).toBe('2025-01-06');
        });

        it('should calculate year range from min/max dates', () => {
            const datePickerElement = document.getElementById('date-picker');
            const minDateStr = datePickerElement.getAttribute('data-min-date');
            const maxDateStr = datePickerElement.getAttribute('data-max-date');

            const minYear = parseInt(minDateStr.split('-')[0], 10);
            const maxYear = parseInt(maxDateStr.split('-')[0], 10);

            expect(minYear).toBe(2024);
            expect(maxYear).toBe(2025);
            expect(maxYear - minYear).toBe(1);
        });
    });

    describe('Livewire event handling', () => {
        it('should update existing dates after flash-saved event', () => {
            const datePickerElement = document.getElementById('date-picker');

            // Initial state
            let existingDates = JSON.parse(datePickerElement.getAttribute('data-existing-dates'));
            expect(existingDates).toEqual(['2025-01-02', '2025-01-03']);

            // Simulate Livewire updating the DOM after saving a flash
            datePickerElement.setAttribute('data-existing-dates', '["2025-01-02", "2025-01-03", "2025-01-04"]');

            // Verify the data attribute was updated
            existingDates = JSON.parse(datePickerElement.getAttribute('data-existing-dates'));
            expect(existingDates).toEqual(['2025-01-02', '2025-01-03', '2025-01-04']);
            expect(existingDates).toContain('2025-01-04');
        });

        it('should update existing dates after flash-deleted event', () => {
            const datePickerElement = document.getElementById('date-picker');

            // Initial state with 3 dates
            datePickerElement.setAttribute('data-existing-dates', '["2025-01-02", "2025-01-03", "2025-01-04"]');
            let existingDates = JSON.parse(datePickerElement.getAttribute('data-existing-dates'));
            expect(existingDates).toHaveLength(3);

            // Simulate Livewire updating the DOM after deleting a flash
            datePickerElement.setAttribute('data-existing-dates', '["2025-01-02", "2025-01-03"]');

            // Verify the date was removed
            existingDates = JSON.parse(datePickerElement.getAttribute('data-existing-dates'));
            expect(existingDates).toEqual(['2025-01-02', '2025-01-03']);
            expect(existingDates).not.toContain('2025-01-04');
            expect(existingDates).toHaveLength(2);
        });

        it('should handle multiple dates being saved at once', () => {
            const datePickerElement = document.getElementById('date-picker');

            // Initial state
            datePickerElement.setAttribute('data-existing-dates', '["2025-01-02"]');
            let existingDates = JSON.parse(datePickerElement.getAttribute('data-existing-dates'));
            expect(existingDates).toHaveLength(1);

            // Simulate saving multiple dates at once
            datePickerElement.setAttribute('data-existing-dates', '["2025-01-02", "2025-01-05", "2025-01-06", "2025-01-07"]');

            // Verify all dates were added
            existingDates = JSON.parse(datePickerElement.getAttribute('data-existing-dates'));
            expect(existingDates).toHaveLength(4);
            expect(existingDates).toContain('2025-01-05');
            expect(existingDates).toContain('2025-01-06');
            expect(existingDates).toContain('2025-01-07');
        });

        it('should handle all dates being deleted', () => {
            const datePickerElement = document.getElementById('date-picker');

            // Initial state with multiple dates
            datePickerElement.setAttribute('data-existing-dates', '["2025-01-02", "2025-01-03", "2025-01-04"]');
            let existingDates = JSON.parse(datePickerElement.getAttribute('data-existing-dates'));
            expect(existingDates).toHaveLength(3);

            // Simulate all dates being deleted
            datePickerElement.setAttribute('data-existing-dates', '[]');

            // Verify all dates were removed
            existingDates = JSON.parse(datePickerElement.getAttribute('data-existing-dates'));
            expect(existingDates).toEqual([]);
            expect(existingDates).toHaveLength(0);
        });
    });

    describe('Calendar reinitialization', () => {
        it('should detect when calendar needs reinitialization', () => {
            const datePickerElement = document.getElementById('date-picker');

            // Mock flatpickr instance
            const mockFlatpickr = {
                clear: vi.fn(),
                destroy: vi.fn()
            };
            datePickerElement._flatpickr = mockFlatpickr;

            // Verify flatpickr instance exists
            expect(datePickerElement._flatpickr).toBeDefined();
            expect(typeof datePickerElement._flatpickr.clear).toBe('function');
            expect(typeof datePickerElement._flatpickr.destroy).toBe('function');
        });

        it('should clear flatpickr before reinitialization on save', () => {
            const datePickerElement = document.getElementById('date-picker');

            // Mock flatpickr instance with spy functions
            const clearSpy = vi.fn();
            const destroySpy = vi.fn();
            datePickerElement._flatpickr = {
                clear: clearSpy,
                destroy: destroySpy
            };

            // Simulate the flash-saved event handler behavior
            if (datePickerElement._flatpickr) {
                datePickerElement._flatpickr.clear();
            }

            // Verify clear was called
            expect(clearSpy).toHaveBeenCalledTimes(1);
        });

        it('should destroy flatpickr instance before reinitialization', () => {
            const datePickerElement = document.getElementById('date-picker');

            // Mock flatpickr instance
            const clearSpy = vi.fn();
            const destroySpy = vi.fn();
            datePickerElement._flatpickr = {
                clear: clearSpy,
                destroy: destroySpy
            };

            // Simulate the event handler behavior
            if (datePickerElement._flatpickr) {
                datePickerElement._flatpickr.clear();
                datePickerElement._flatpickr.destroy();
            }

            // Verify both were called in order
            expect(clearSpy).toHaveBeenCalledTimes(1);
            expect(destroySpy).toHaveBeenCalledTimes(1);
        });

        it('should handle reinitialization when flatpickr does not exist', () => {
            const datePickerElement = document.getElementById('date-picker');

            // No flatpickr instance
            datePickerElement._flatpickr = undefined;

            // This should not throw an error
            expect(() => {
                if (datePickerElement._flatpickr) {
                    datePickerElement._flatpickr.clear();
                    datePickerElement._flatpickr.destroy();
                }
            }).not.toThrow();
        });

        it('should reinitialize with updated existing dates after delete', () => {
            const datePickerElement = document.getElementById('date-picker');

            // Initial state
            datePickerElement.setAttribute('data-existing-dates', '["2025-01-02", "2025-01-03"]');

            // Mock flatpickr
            const destroySpy = vi.fn();
            datePickerElement._flatpickr = {
                destroy: destroySpy
            };

            // Simulate delete event: destroy flatpickr
            if (datePickerElement._flatpickr) {
                datePickerElement._flatpickr.destroy();
            }

            // Update DOM (simulating Livewire update)
            datePickerElement.setAttribute('data-existing-dates', '["2025-01-02"]');

            // Verify destroy was called and data was updated
            expect(destroySpy).toHaveBeenCalledTimes(1);
            const updatedDates = JSON.parse(datePickerElement.getAttribute('data-existing-dates'));
            expect(updatedDates).toEqual(['2025-01-02']);
            expect(updatedDates).not.toContain('2025-01-03');
        });
    });
});
