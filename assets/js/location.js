function setupUgandaLocationLookup(formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    const areaInput = form.querySelector('[name="pickup_area"]');
    const addressInput = form.querySelector('[name="address"]');
    const latitudeInput = form.querySelector('[name="latitude"]');
    const longitudeInput = form.querySelector('[name="longitude"]');
    const button = form.querySelector('[data-location-lookup]');
    const message = form.querySelector('[data-location-message]');
    if (!areaInput || !latitudeInput || !longitudeInput || !button || !message) return;

    const invalidateCoordinates = () => {
        latitudeInput.value = '';
        longitudeInput.value = '';
        message.textContent = 'Location changed. Find it again to update the map point. Search uses OpenStreetMap.';
        message.className = 'form-text text-warning';
    };
    areaInput.addEventListener('input', invalidateCoordinates);
    addressInput?.addEventListener('input', invalidateCoordinates);

    button.addEventListener('click', async () => {
        const searchText = [addressInput?.value.trim(), areaInput.value.trim(), 'Uganda'].filter(Boolean).join(', ');
        if (!areaInput.value.trim()) {
            message.textContent = 'Enter a pickup area or place name first.';
            message.className = 'form-text text-danger';
            areaInput.focus();
            return;
        }

        button.disabled = true;
        message.textContent = 'Finding the location...';
        message.className = 'form-text text-muted';
        const params = new URLSearchParams({
            format: 'jsonv2',
            q: searchText,
            countrycodes: 'ug',
            viewbox: '29.5,4.3,35.1,-1.6',
            bounded: '1',
            limit: '1'
        });

        try {
            const response = await fetch(`https://nominatim.openstreetmap.org/search?${params}`, {
                headers: { Accept: 'application/json' }
            });
            if (!response.ok) throw new Error('Location search failed');
            const results = await response.json();
            const result = results.find((item) => Number(item.lat) >= -1.6 && Number(item.lat) <= 4.3 && Number(item.lon) >= 29.5 && Number(item.lon) <= 35.1);
            if (!result) {
                latitudeInput.value = '';
                longitudeInput.value = '';
                message.textContent = 'No matching Uganda map point found. Your area name will still be saved for service assignment.';
                message.className = 'form-text text-warning';
                return;
            }

            latitudeInput.value = result.lat;
            longitudeInput.value = result.lon;
            message.textContent = `Approximate map point found: ${result.display_name}`;
            message.className = 'form-text text-success';
        } catch {
            message.textContent = 'Could not look up this place right now. You can still save the location name.';
            message.className = 'form-text text-warning';
        } finally {
            button.disabled = false;
        }
    });
}
