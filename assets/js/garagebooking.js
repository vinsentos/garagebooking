
jQuery(document).ready(function($) {
    $('#fetch-vehicle-details').on('click', function(e) {
        e.preventDefault();

        const registrationNumber = $('#registration_number').val();
        const vehicleDetailsDiv = $('#vehicle-details');
        
        if (!registrationNumber) {
            alert('Please enter a registration number.');
            return;
        }

        // Display loading indicator
        vehicleDetailsDiv.html('<p>Loading...</p>');

        $.ajax({
            url: garageBooking.ajax_url,
            method: 'POST',
            data: {
                action: 'fetch_vehicle_details',
                registration_number: registrationNumber
            },
            success: function(response) {
                if (response.success) {
                    const { make, model, year, registration_date, mot_expiry } = response.data;
                    vehicleDetailsDiv.html(`
                        <p><strong>Make:</strong> ${make}</p>
                        <p><strong>Model:</strong> ${model}</p>
                        <p><strong>Year:</strong> ${year}</p>
                        <p><strong>Registration Date:</strong> ${registration_date}</p>
                        <p><strong>MOT Expiry:</strong> ${mot_expiry}</p>
                    `);
                } else {
                    vehicleDetailsDiv.html('<p>Error fetching vehicle details. Please try again.</p>');
                }
            },
            error: function() {
                vehicleDetailsDiv.html('<p>An error occurred. Please try again.</p>');
            }
        });
    });
});
