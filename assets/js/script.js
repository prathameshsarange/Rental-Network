document.addEventListener('DOMContentLoaded', function () {
    document.body.classList.add('motion-ready');

    document.querySelectorAll('main h1').forEach(function (heading) {
        heading.classList.add('page-title');
    });

    const revealTargets = document.querySelectorAll('.hero-panel, .filter-panel, .auth-card, .card, .location-summary, .booking-summary, .payment-total');
    const showTarget = function (target) {
        target.classList.add('reveal', 'is-visible');
    };

    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver(function (entries, observer) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.08 });

        revealTargets.forEach(function (target) {
            target.classList.add('reveal');
            revealObserver.observe(target);
        });
    } else {
        revealTargets.forEach(showTarget);
    }

    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        document.querySelectorAll('[data-tilt]').forEach(function (card) {
            card.addEventListener('mousemove', function (event) {
                const bounds = card.getBoundingClientRect();
                const x = (event.clientX - bounds.left) / bounds.width - 0.5;
                const y = (event.clientY - bounds.top) / bounds.height - 0.5;
                card.style.transform = 'perspective(900px) rotateX(' + (-y * 3) + 'deg) rotateY(' + (x * 3) + 'deg) translateY(-4px)';
            });

            card.addEventListener('mouseleave', function () {
                card.style.transform = '';
            });
        });
    }

    const password = document.querySelector('[data-password]');
    const confirmPassword = document.querySelector('[data-confirm-password]');

    if (password && confirmPassword) {
        const checkPasswordMatch = function () {
            if (confirmPassword.value && password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match.');
            } else {
                confirmPassword.setCustomValidity('');
            }
        };

        password.addEventListener('input', checkPasswordMatch);
        confirmPassword.addEventListener('input', checkPasswordMatch);
    }

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-rental-mode]').forEach(function (modeSelect) {
        const form = modeSelect.closest('form');
        const dailyField = form.querySelector('[data-daily-price-field]');
        const hourlyField = form.querySelector('[data-hourly-price-field]');
        const dailyInput = form.querySelector('[name="rental_price"]');
        const hourlyInput = form.querySelector('[name="hourly_price"]');
        const updateModeFields = function () {
            const mode = modeSelect.value;
            const dailyEnabled = mode === 'Daily' || mode === 'Both';
            const hourlyEnabled = mode === 'Hourly' || mode === 'Both';
            if (dailyField) { dailyField.hidden = !dailyEnabled; }
            if (hourlyField) { hourlyField.hidden = !hourlyEnabled; }
            if (dailyInput) { dailyInput.required = dailyEnabled; }
            if (hourlyInput) { hourlyInput.required = hourlyEnabled; }
        };
        modeSelect.addEventListener('change', updateModeFields);
        updateModeFields();
    });

    document.querySelectorAll('[data-rental-calculator]').forEach(function (calculator) {
        const startInput = calculator.querySelector('[name="start_date"]');
        const endInput = calculator.querySelector('[name="end_date"]');
        const startTimeInput = calculator.querySelector('[name="start_time"]');
        const endTimeInput = calculator.querySelector('[name="end_time"]');
        const rentalUnitInput = calculator.querySelector('[name="rental_unit"]');
        const daysOutput = calculator.querySelector('[data-rental-days]');
        const rentalOutput = calculator.querySelector('[data-rental-amount]');
        const totalOutput = calculator.querySelector('[data-rental-total]');
        const availabilityFeedback = calculator.querySelector('[data-availability-feedback]');
        const submitButton = calculator.querySelector('[data-booking-submit]');
        const dailyPrice = Number(calculator.dataset.dailyPrice || 0);
        const hourlyPrice = Number(calculator.dataset.hourlyPrice || 0);
        const deposit = Number(calculator.dataset.deposit || 0);
        const hourlyTimes = calculator.querySelector('[data-hourly-times]');

        const updateRentalType = function () {
            const isHourly = rentalUnitInput && rentalUnitInput.value === 'Hourly';
            if (hourlyTimes) {
                hourlyTimes.hidden = !isHourly;
            }
            if (startTimeInput) { startTimeInput.required = isHourly; }
            if (endTimeInput) { endTimeInput.required = isHourly; }
            if (isHourly && startInput.value) {
                endInput.value = startInput.value;
                endInput.readOnly = true;
            } else {
                endInput.readOnly = false;
            }
        };

        const updateEstimate = function () {
            if (!startInput.value || !endInput.value) {
                return;
            }

            const isHourly = rentalUnitInput && rentalUnitInput.value === 'Hourly';
            let units;
            let rentalAmount;
            let availabilityParams;

            if (isHourly) {
                if (!startTimeInput.value || !endTimeInput.value || startInput.value !== endInput.value) {
                    return;
                }
                const startAt = new Date(startInput.value + 'T' + startTimeInput.value + ':00');
                const endAt = new Date(endInput.value + 'T' + endTimeInput.value + ':00');
                const hours = Math.ceil((endAt - startAt) / (60 * 60 * 1000));
                if (hours < 1 || Number.isNaN(hours)) {
                    return;
                }
                units = hours;
                rentalAmount = hours * hourlyPrice;
                daysOutput.textContent = String(hours) + ' hour(s)';
                availabilityParams = { item_id: calculator.dataset.itemId, rental_unit: 'Hourly', start_date: startInput.value, end_date: endInput.value, start_time: startTimeInput.value, end_time: endTimeInput.value };
            } else {
                const startDate = new Date(startInput.value + 'T00:00:00');
                const endDate = new Date(endInput.value + 'T00:00:00');
                const millisecondsPerDay = 24 * 60 * 60 * 1000;
                const days = Math.round((endDate - startDate) / millisecondsPerDay) + 1;

                if (days < 1 || Number.isNaN(days)) {
                    return;
                }
                units = days;
                rentalAmount = days * dailyPrice;
                daysOutput.textContent = String(days) + ' day(s)';
                availabilityParams = { item_id: calculator.dataset.itemId, rental_unit: 'Daily', start_date: startInput.value, end_date: endInput.value };
            }

            rentalOutput.textContent = 'Rs. ' + rentalAmount.toFixed(2);
            totalOutput.textContent = 'Rs. ' + (rentalAmount + deposit).toFixed(2);

            if (availabilityFeedback && calculator.dataset.availabilityUrl && calculator.dataset.itemId) {
                availabilityFeedback.textContent = 'Checking availability...';
                availabilityFeedback.className = 'availability-feedback mb-4';
                if (submitButton) {
                    submitButton.disabled = true;
                }
                const params = new URLSearchParams(availabilityParams);
                fetch(calculator.dataset.availabilityUrl + '?' + params.toString())
                    .then(function (response) { return response.json(); })
                    .then(function (result) {
                        availabilityFeedback.textContent = result.message;
                        availabilityFeedback.className = 'availability-feedback mb-4 ' + (result.available ? 'availability-ok' : 'availability-busy');
                        if (submitButton) {
                            submitButton.disabled = !result.available;
                        }
                    })
                    .catch(function () {
                        availabilityFeedback.textContent = 'Availability could not be checked. Please submit to validate your dates.';
                        availabilityFeedback.className = 'availability-feedback mb-4 availability-busy';
                        if (submitButton) {
                            submitButton.disabled = false;
                        }
                    });
            }
        };

        startInput.addEventListener('change', function () { updateRentalType(); updateEstimate(); });
        endInput.addEventListener('change', updateEstimate);
        if (startTimeInput) { startTimeInput.addEventListener('change', updateEstimate); }
        if (endTimeInput) { endTimeInput.addEventListener('change', updateEstimate); }
        if (rentalUnitInput) { rentalUnitInput.addEventListener('change', function () { updateRentalType(); updateEstimate(); }); }
        updateRentalType();
    });
});
